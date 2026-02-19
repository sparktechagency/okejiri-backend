<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\KYCRejectMail;
use App\Mail\UserDeleteMail;
use App\Models\Company;
use App\Models\User;
use App\Notifications\KYCApprovedCongratulationNotification;
use App\Notifications\KYCNotification;
use App\Notifications\KYCRejectNotification;
use App\Services\FileUploadService;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class UserController extends Controller
{
    use ApiResponse;
    protected $fileuploadService;
    private $defaultFile       = ['default_image.png'];
    private $company_logo_path = 'uploads/companies';
    private $user_avatar       = 'uploads/users/avatar';
    private $id_card_front     = 'uploads/users/kyc/id_card_front';
    private $id_card_back      = 'uploads/users/kyc/id_card_back';
    private $selfie            = 'uploads/users/kyc/selfie';
    public function __construct(FileUploadService $fileuploadService)
    {
        $this->fileuploadService = $fileuploadService->setDefaultFiles($this->defaultFile);
    }

    public function index(Request $request)
    {
        $request->validate([
            'role' => 'required|in:USER,PROVIDER',
        ]);
        $search   = $request->input('search');
        $role     = $request->input('role', "USER");
        $per_page = $request->input('per_page', 10);
        $filter   = $request->input('filter');

        $users = User::with('provider_services.service:id,name,image')
            ->latest('id')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', '%' . $search . '%')
                        ->orWhere('email', 'LIKE', '%' . $search . '%')
                        ->orWhere('phone', 'LIKE', '%' . $search . '%')
                        ->orWhere('address', 'LIKE', '%' . $search . '%');
                });
            })
            ->when($role, function ($query) use ($role) {
                $query->where('role', $role);
            })
            ->when($filter, function ($query) use ($filter) {
                $query->where('kyc_status', $filter);
            })
            ->select('id', 'name', 'email', 'phone', 'address', 'avatar', 'role', 'provider_type', 'kyc_status', 'created_at')->paginate($per_page);

        $meta_data = [
            'kyc_requests' => User::where('role', $role)->where('kyc_status', 'In Review')->count(),
        ];
        return $this->responseSuccess($users, "$role retrieved successfully", 200, 'success', $meta_data);
    }

    public function sentNotifications(Request $request)
    {
        $request->validate([
            'role' => 'required|in:USER,PROVIDER',
        ]);
        $role = $request->input('role');
        try {
            $unverified_users = User::where('role', $role)->where('kyc_status', 'Unverified')->get();

            $notificationData = [
                'title' => 'Complete your KYC',
                'body'  => 'Complete your KYC to access all the features.',
                'data'  => [
                    'type' => 'complete_kyc',
                ],
            ];

            Notification::send(
                $unverified_users,
                new KYCNotification(
                    $notificationData['title'],
                    $notificationData['body'],
                    $notificationData['data']
                )
            );

            return $this->responseSuccess([], 'Notifications sent successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }
    public function sentSingleNotification(Request $request, $user_id)
    {
        try {

            $unverified_users = User::with('devices')->findOrFail($user_id);
            $unverified_users->notify(new KYCNotification(
                'Complete your KYC',
                'Complete your KYC to access all the features.',
                [
                    'type' => 'complete_kyc',
                ]
            ));
            return $this->responseSuccess([], 'Notifications sent successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }

    public function getKycRequests(Request $request)
    {
        $request->validate([
            'role' => 'required|in:USER,PROVIDER',
        ]);
        $role              = $request->input('role');
        $per_page          = $request->input('per_page', 10);
        $search            = $request->input('search');
        $kyc_request_users = User::where('role', $role)->when($search, function ($query) use ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', '%' . $search . '%')
                    ->orWhere('email', 'LIKE', '%' . $search . '%')
                    ->orWhere('phone', 'LIKE', '%' . $search . '%')
                    ->orWhere('address', 'LIKE', '%' . $search . '%');
            });
        })->where('kyc_status', 'In Review')->select('id', 'name', 'email', 'phone', 'address', 'avatar', 'role', 'provider_type', 'kyc_status', 'id_card_front', 'id_card_back', 'selfie', 'created_at')->paginate($per_page);

        return $this->responseSuccess($kyc_request_users, "KYC requested " . Str::lower($role) . " retrieved successfully");
    }
    public function getKycRequestDetails(Request $request, $user_id)
    {
        try {
            $kyc_request_user = User::where('id', $user_id)->where('kyc_status', 'In Review')->select('id', 'name', 'email', 'phone', 'address', 'avatar', 'role', 'provider_type', 'kyc_status', 'id_card_front', 'id_card_back', 'selfie', 'created_at')->first();
            if (! $kyc_request_user) {
                return $this->responseError(null, "This user not apply for the kyc.", 404);
            }
            return $this->responseSuccess($kyc_request_user, "KYC requested user details retrieved successfully");
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }

    public function acceptKyc($user_id)
    {
        try {
            $user             = User::findOrFail($user_id);
            $user->kyc_status = 'Verified';
            $user->save();

            $query = $user->notifications()
                ->where('type', KYCNotification::class);
            if ($query->count() > 0) {
                $query->delete();
            }
            if ($user->role === 'PROVIDER') {
                $title     = "Congratulations! Your KYC has been approved.";
                $sub_title = "You can now add your services and start receiving bookings on the Okejiri.";
            } elseif ($user->role === 'USER') {
                $title     = "Congratulations! Your KYC has been approved.";
                $sub_title = "You can now book services on the Okejiri.";
            }

            $user->notify(new KYCApprovedCongratulationNotification(
                $title,
                $sub_title,
                [
                    'type' => 'kyc_approved',
                ]
            ));

            return $this->responseSuccess($user, "KYC request accepted.");
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }
    public function rejectKyc(Request $request, $user_id)
    {
        $request->validate([
            'reason' => 'required|string',
        ]);
        try {
            $reason           = $request->input('reason');
            $user             = User::findOrFail($user_id);
            $user->kyc_status = 'Rejected';
            $user->save();
            Mail::to($user->email)->send(new KYCRejectMail($user->name, $reason));
            if ($user->role === 'PROVIDER') {
                $title     = "Your KYC has been rejected.";
                $sub_title = "Unfortunately, your KYC verification was not approved. Please review the requirements and resubmit your documents.";
            } elseif ($user->role === 'USER') {
                $title     = "Your KYC has been rejected.";
                $sub_title = "Your KYC verification failed. Please check your information and try again.";
            }
            $user->notify(new KYCRejectNotification(
                $title,
                $sub_title,
                [
                    'type' => 'kyc_reject',
                ]
            ));

            return $this->responseSuccess($user, "KYC request rejected.");
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }

    public function deleteUsers(Request $request, $user_id)
    {
        $request->validate([
            'reason' => 'required|string',
        ]);
        try {
            $reason = $request->input('reason');
            $user   = User::with('company')->findOrFail($user_id);
            Mail::to($user->email)->send(new UserDeleteMail($user->name, $reason));
            if ($user->company) {
                $company = $user->company;

                if (! empty($company->company_logo)) {
                    $this->fileuploadService
                        ->setPath($this->company_logo_path)
                        ->deleteFile($company->company_logo);
                }

                $company->delete();
            }

            if (! empty($user->avatar)) {
                $this->fileuploadService
                    ->setPath($this->user_avatar)
                    ->setDefaultFiles('default_avatar.png')
                    ->deleteFile($user->avatar);
            }

            if (! empty($user->id_card_front)) {
                $this->fileuploadService
                    ->setPath($this->id_card_front)
                    ->deleteFile($user->id_card_front);
            }

            if (! empty($user->id_card_back)) {
                $this->fileuploadService
                    ->setPath($this->id_card_back)
                    ->deleteFile($user->id_card_back);
            }

            if (! empty($user->selfie)) {
                $this->fileuploadService
                    ->setPath($this->selfie)
                    ->deleteFile($user->selfie);
            }

            $user->delete();

            return $this->responseSuccess($user, "User deleted successfully.");
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }

    public function userDetails($user_id)
    {
        try {
            $user = User::findOrFail($user_id);
            return $this->responseSuccess($user, 'User details retrieved successfully');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage());
        }
    }

}
