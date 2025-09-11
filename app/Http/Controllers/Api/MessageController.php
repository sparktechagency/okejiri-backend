<?php
namespace App\Http\Controllers\Api;

use Exception;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Message;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\FileUploadService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\MessageResource;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Http\Requests\Message\SendMessageRequest;

class MessageController extends Controller
{
    use ApiResponse;
    protected $fileuploadService;
    private $filePath = 'uploads/messages';
    public function __construct(FileUploadService $fileuploadService)
    {
        $this->fileuploadService = $fileuploadService->setPath($this->filePath);
    }

    public function sendMessage(SendMessageRequest $request)
    {
        try {
            $message = new Message();
            $message->sender_id = Auth::user()->id;
            $message->receiver_id = $request->receiver_id;
            $message->message = $request->message;
            if ($request->hasFile('attachments')) {
                $attachments = [];
                foreach ($request->file('attachments') as $file) {
                    $extension = $file->getClientOriginalExtension();
                    if (in_array($extension, ['jpg', 'jpeg', 'png'])) {
                        $savedPath = $this->fileuploadService->saveOptimizedImage($file, 40, 1320, null, false);
                    } else {
                        $savedPath = $this->fileuploadService->saveFile($file);
                    }

                    $attachments[] = $savedPath;
                }

                $message->attachments = json_encode($attachments);
            }
            $message->save();
            return $this->responseSuccess(new MessageResource($message), 'Message sent successfully.', 201);
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'The request could not be processed due to an error.', 500);
        }
    }

    public function editMessage(Request $request, $id)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $message = Message::findOrFail($id);

        if ($message->sender_id !== Auth::id()) {
            return $this->responseError(null, 'You are not authorized to edit this message.', 403);
        }
        $message->message = $request->message;
        $message->edited_at = now();
        $message->save();

        return $this->responseSuccess(new MessageResource($message), 'Message updated successfully.');
    }

    public function getMessage(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|numeric|exists:users,id',
        ]);

        $userId = Auth::id();
        $receiverId = $request->receiver_id;
        $perPage = $request->per_page ?? 20;
        $search = $request->search;
        $order_by = 'asc';

        $messages = Message::where(function ($query) use ($userId, $receiverId) {
            $query->where(function ($q) use ($userId, $receiverId) {
                $q->where('sender_id', $userId)
                    ->where('receiver_id', $receiverId)
                    ->where('deleted_by_sender', false);
            })->orWhere(function ($q) use ($userId, $receiverId) {
                $q->where('sender_id', $receiverId)
                    ->where('receiver_id', $userId)
                    ->where('deleted_by_receiver', false);
            });
        })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('message', 'like', "%{$search}%");
                });
            })
            ->orderBy('created_at', $order_by)
            ->paginate($perPage);

        return $this->responseSuccess(MessageResource::collection($messages)->response()->getData(), 'Messages retrieved successfully.');
    }

    public function markAsRead(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|numeric|exists:users,id',
        ]);

        $userId = Auth::id();
        $receiverId = $request->receiver_id;

        Message::where('sender_id', $receiverId)
            ->where('receiver_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return $this->responseSuccess(null, 'Messages marked as read successfully.');
    }

    public function unsendForMe($id)
    {
        $userId = Auth::id();
        $message = Message::find($id);
        if (!$message) {
            return $this->responseError(null, 'Message not found.', 404);
        }

        if ($message->sender_id == $userId) {
            $message->deleted_by_sender = true;
        } elseif ($message->receiver_id == $userId) {
            $message->deleted_by_receiver = true;
        } else {
            return $this->responseError(null, 'You do not have permission to delete this message.', 403);
        }

        $message->save();
        return $this->responseSuccess(null, 'Message unsend for you.');
    }

    public function unsendForEveryone($id)
    {
        $userId = Auth::id();

        $message = Message::find($id);

        if (!$message) {
            return $this->responseError(null, 'Message not found.', 404);
        }

        if ($message->sender_id !== $userId) {
            return $this->responseError(null, 'You do not have permission to unsend this message.', 403);
        }

        if ($message->attachments) {
            $this->fileuploadService->deleteMultipleFiles($message->attachments);
        }

        $message->delete();

        return $this->responseSuccess(null, 'Message has been unsent for everyone.');
    }

    public function searchNewUser(Request $request)
    {
        $perPage = $request->per_page ?? 20;
        $search = $request->search;
        $role = $request->role;

        $users = User::when($search, function ($query) use ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%");
            });
        })
            ->when($role, function ($query) use ($role) {
                $query->where('role', $role);
            })
            ->select('id', 'name', 'email', 'avatar', 'role')
            ->orderByDesc('id')
            ->paginate($perPage);

        return $this->responseSuccess($users, 'Users retrieved successfully.');
    }

    public function chatList(Request $request)
    {
        $userId = auth()->id();
        $search = $request->input('search');
        $role = $request->input('role');
        $perPage = $request->input('per_page') ?? 10;

        $chatUserIds = Message::where('sender_id', $userId)
            ->pluck('receiver_id')
            ->merge(
                Message::where('receiver_id', $userId)
                    ->pluck('sender_id')
            )
            ->unique()
            ->values();

        if ($chatUserIds->isEmpty()) {
            return $this->responseSuccess(
                new LengthAwarePaginator([], 0, $perPage),
                'Chat list data retrieved successfully.'
            );
        }

        $query = User::whereIn('users.id', $chatUserIds);

        if ($search) {
            $query->where('users.name', 'LIKE', "%$search%");
        }

        if ($role) {
            $query->where('users.role', $role);
        }

        $query->leftJoin('messages', function ($join) use ($userId) {
            $join->on(function ($join) use ($userId) {
                $join->where(function ($q) use ($userId) {
                    $q->whereColumn('messages.sender_id', '=', 'users.id')
                        ->where('messages.receiver_id', '=', $userId);
                })->orWhere(function ($q) use ($userId) {
                    $q->whereColumn('messages.receiver_id', '=', 'users.id')
                        ->where('messages.sender_id', '=', $userId);
                });
            });
        });

        $query->select('users.*', DB::raw('MAX(messages.created_at) as last_message_at'))
            ->groupBy('users.id')
            ->orderByDesc('last_message_at');

        $users = $query->paginate($perPage);

        $otherUserIds = $users->pluck('id')->toArray();

        // Fetch last messages for all users
        $lastMessages = Message::where(function ($q) use ($userId, $otherUserIds) {
            $q->where('sender_id', $userId)->whereIn('receiver_id', $otherUserIds);
        })->orWhere(function ($q) use ($userId, $otherUserIds) {
            $q->whereIn('sender_id', $otherUserIds)->where('receiver_id', $userId);
        })->orderBy('created_at', 'desc')->get()
            ->groupBy(function ($message) use ($userId) {
                return $message->sender_id === $userId ? $message->receiver_id : $message->sender_id;
            });

        // Fetch unread counts for all users
        $unreadCounts = Message::where('receiver_id', $userId)
            ->whereIn('sender_id', $otherUserIds)
            ->where('is_read', false)
            ->select('sender_id', DB::raw('COUNT(*) as unread_count'))
            ->groupBy('sender_id')
            ->pluck('unread_count', 'sender_id');

        $chatList = $users->getCollection()->map(function ($user) use ($userId, $lastMessages, $unreadCounts) {
            $lastMessage = $lastMessages[$user->id]->first() ?? null;
            $unreadCount = $unreadCounts->get($user->id, 0);

            $preview = $this->getMessagePreview($lastMessage, $userId, $user);
            $lastMessageTime = $this->formatLastMessageTime($lastMessage);
            $unreadStatusText = $this->getUnreadStatusText($unreadCount);

            return [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'role' => $user->role,
                'last_message' => $preview,
                'last_message_time' => $lastMessageTime,
                'unread_count' => $unreadCount,
                'is_read' => $unreadCount === 0,
                'unread_status_text' => $unreadStatusText,
            ];
        });

        $users->setCollection($chatList);

        return $this->responseSuccess($users, 'Chat list data retrieved successfully.');
    }

    // Generate preview text for last message or attachment
    private function getMessagePreview($message, $userId, $otherUser)
    {
        if (!$message)
            return null;

        if (!empty($message->message)) {
            if ($message->forwarded_from) {
                $forwardedUser = User::find($message->forwarded_from);
                $name = $message->sender_id === $userId
                    ? 'You'
                    : ($forwardedUser?->name ? explode(' ', $forwardedUser->name)[0] : 'Someone');
                return "$name forwarded a message";
            }
            if ($message->sender_id === $userId) {
                return 'You: ' . $message->message;
            }
            return $message->message;
        }

        $attachments = $message->attachments;

        if (is_string($attachments)) {
            $attachments = json_decode($attachments, true);
        }

        if (is_array($attachments) && count($attachments) > 0) {
            $count = count($attachments);
            $prefix = $message->sender_id === $userId
                ? 'You sent'
                : explode(' ', $otherUser->name)[0] . ' sent';

            return $prefix . ' ' . ($count > 1 ? "$count attachments" : "an attachment");
        }

        return null;
    }

    // Format the last message time based on date rules
    private function formatLastMessageTime($message)
    {
        if (!$message)
            return null;
        $created = Carbon::parse($message->created_at);
        $now = now();

        if ($created->isToday()) {
            return $created->format('h:i A');
        } elseif ($created->isCurrentWeek()) {
            return $created->format('D');
        } elseif ($created->isCurrentYear()) {
            return $created->format('M j');
        } else {
            return $created->format('Y');
        }
    }

    // Generate unread count text
    private function getUnreadStatusText($count)
    {
        if ($count === 0) {
            return null;
        }
        if ($count > 9) {
            return '9+ new messages';
        }
        return $count === 1 ? "1 new message" : "$count new messages";
    }

}
