<?php
namespace App\Http\Controllers;

use App\Http\Requests\Dispute\DisputeStoreRequest;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\User;
use App\Notifications\NewDisputeNotification;
use App\Services\FileUploadService;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            ->paginate($per_page);

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
}
