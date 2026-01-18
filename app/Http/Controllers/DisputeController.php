<?php
namespace App\Http\Controllers;

use App\Http\Requests\Dispute\DisputeMailRequest;
use App\Http\Requests\Dispute\DisputeStoreRequest;
use App\Mail\DisputeMail;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\User;
use App\Notifications\NewDisputeNotification;
use App\Services\FileUploadService;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class DisputeController extends Controller
{
    protected $fileuploadService;
    private $filePath = 'uploads/disputes';

    public function __construct(FileUploadService $fileuploadService)
    {
        $this->fileuploadService = $fileuploadService->setPath($this->filePath);
    }
    use ApiResponse;
    public function addDispute(DisputeStoreRequest $request)
    {
        $booking = Booking::findOrFail($request->booking_id);

        $existingDispute = Dispute::where('booking_id', $request->booking_id)->first();

        if ($existingDispute) {
            return $this->responseError(null, 'A dispute has already been raised for this booking.', 409);
        }

        $to_user_id = (Auth::id() == $booking->user_id)
            ? $booking->provider_id
            : $booking->user_id;

        $dispute                 = new Dispute();
        $dispute->booking_id     = $request->booking_id;
        $dispute->from_user_id   = Auth::id();
        $dispute->to_user_id     = $to_user_id;
        $dispute->raised_by_role = Auth::user()->role;
        $dispute->reason         = $request->reason;
        $dispute->details        = $request->details;

        if ($request->hasFile('attachments')) {
            $attachments = $this->fileuploadService->saveMultipleFiles(
                $request->file('attachments'),
                true, 40, 1320, null, true
            );
            $dispute->attachments = json_encode($attachments);
        }

        $dispute->save();

        $notify_user = User::where('id', $to_user_id)->first();
        $notify_user->notify(new NewDisputeNotification($dispute->id));
        return $this->responseSuccess($dispute, 'Dispute created successfully', 201);
    }

    public function myDispute(Request $request)
    {
        $per_page = $request->input('per_page', 10);
        $disputes = Dispute::where('from_user_id', Auth::id())->latest('id')
        // ->paginate($per_page);
            ->get();

        return $this->responseSuccess($disputes, 'Disputes retrieved successfully');
    }
    public function DisputeDetails($dispute_id)
    {
        try {
            $dispute = Dispute::with('appeal')->findOrFail($dispute_id);
            if (Auth::id() == $dispute->from_user_id) {
                $oppositeUser = User::with('company')->select('id', 'name', 'email', 'avatar', 'role', 'kyc_status')
                    ->find($dispute->to_user_id);
            } else {
                $oppositeUser = User::with('company')->select('id', 'name', 'email', 'avatar', 'role', 'kyc_status')
                    ->find($dispute->from_user_id);
            }
            $dispute->opposite_party = $oppositeUser;

            return $this->responseSuccess($dispute, 'Dispute details retrieved successfully');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }
    public function DisputeDelete($dispute_id)
    {
        try {
            $dispute = Dispute::with('appeal')->findOrFail($dispute_id);
            if ($dispute->attachments) {
                $attachments = is_array($dispute->attachments) ? $dispute->attachments : json_decode($dispute->attachments, true);
                foreach ($attachments as $filePath) {
                    $this->fileuploadService->deleteFile($filePath);
                }
            }
            if ($dispute->appeal && $dispute->appeal->attachments) {
                $appealAttachments = is_array($dispute->appeal->attachments)
                    ? $dispute->appeal->attachments
                    : json_decode($dispute->appeal->attachments, true);

                foreach ($appealAttachments as $filePath) {
                    $this->fileuploadService->deleteFile($filePath);
                }
            }
            $dispute->delete();
            return $this->responseSuccess($dispute, 'Dispute deleted successfully');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }

    public function getAdminDispute(Request $request)
    {
        $request->validate([
            'filter' => 'nullable|in:Pending,Under review,Resolved',
            'role'   => 'required|in:USER,PROVIDER',
        ]);
        $per_page = $request->input('per_page', 10);
        $filter   = $request->input('filter');
        $search   = $request->input('search');
        $role     = $request->input('role', 'USER');

        $disputes = Dispute::with([
            'to_user:id,name,avatar,kyc_status',
            'from_user:id,name,avatar,kyc_status',
        ])
            ->where('raised_by_role', $role)
            ->when($filter, function ($q) use ($filter) {
                $q->where('status', $filter);
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where('reason', 'like', "%{$search}%")
                        ->orWhereHas('from_user', function ($u) use ($search) {
                            $u->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('to_user', function ($u) use ($search) {
                            $u->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('id')
            ->paginate($per_page);
        return $this->responseSuccess($disputes, 'Disputes retrieved successfully');
    }

    public function getAdminDisputeDetails($dispute_id)
    {
        try {
            $dispute_details = Dispute::with([
                'to_user:id,name,avatar,kyc_status,email,phone,address',
                'from_user:id,name,avatar,kyc_status,email,phone,address',
                'appeal',
            ])->findOrFail($dispute_id);
            return $this->responseSuccess($dispute_details, 'Dispute details retrieved successfully');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }

    public function disputeAction(Request $request, $dispute_id)
    {
        $request->validate([
            'action' => 'required|in:refund_user,pay_provider,block_user,block_provider',
            'days'   => 'required_if:action,block_user,block_provider|numeric',
        ]);
        try {
            $dispute = Dispute::with('booking.transaction')->findOrFail($dispute_id);
            if ($request->action == 'refund_user') {
                if ($dispute->booking->payment_type == 'from_balance') {
                    $user                  = $dispute->booking->user;
                    $user->wallet_balance += $dispute->booking->price;
                    $user->save();
                    $dispute->booking->status  = 'Cancelled';
                    $dispute->booking->save();
                    $dispute->status  = 'Resolved';
                    $dispute->save();
                } elseif ($dispute->booking->payment_type == 'make_payment') {
                    $transactionId = $dispute->booking->payment_intent_id;
                    $response      = Http::withToken(env('FLUTTERWAVE_SECRET_KEY'))
                        ->post("https://api.flutterwave.com/v3/transactions/{$transactionId}/refund");

                    $result = $response->json();
                    if ($result['status'] === 'success') {
                        $dispute->booking->status = 'Cancelled';
                        $dispute->booking->save();
                        $dispute->status = 'Resolved';
                        $dispute->save();
                    }
                }
                if ($dispute->booking->transaction) {
                    $dispute->booking->transaction->update([
                        'receiver_id'      => null,
                        'transaction_type' => 'refund',
                        'profit'           => null,
                    ]);
                }
            } elseif ($request->action == 'pay_provider') {
                if ($dispute->booking->status === 'Completed') {
                    return $this->responseSuccess(null, 'Booking already completed and payment already transferred.', 200);
                }
                if (! $dispute->booking->transaction) {
                    return $this->responseError(null, 'No transaction found for this booking.', 404);
                }
                $provider                  = $dispute->booking->provider;
                $provider->wallet_balance += $dispute->booking->transaction->amount - $dispute->booking->transaction->profit;
                $provider->save();
                $dispute->booking->status  = 'Completed';
                $dispute->booking->save();
                $dispute->status  = 'Resolved';
                $dispute->save();
            } elseif ($request->action == 'block_user') {
                $user                   = $dispute->booking->user;
                $user->is_blocked       = 1;
                $user->block_expires_at = now()->addDays((int) $request->days);
                $user->save();
                $dispute->status = 'Resolved';
                $dispute->save();
            } elseif ($request->action == 'block_provider') {
                $provider                   = $dispute->booking->provider;
                $provider->is_blocked       = 1;
                $provider->block_expires_at = now()->addDays((int) $request->days);
                $provider->save();
                $dispute->status = 'Resolved';
                $dispute->save();
            }
            return $this->responseSuccess(null, 'Action performed successfully');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }

    public function disputeMail(DisputeMailRequest $request)
    {
        try {
            $user         = User::findOrFail($request->user_id);
            $mail_message = $request->message;
            Mail::to($user->email)->send(new DisputeMail($request->subject, $mail_message));
            return $this->responseSuccess(null, 'Mail sent successfully');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }

}
