<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReferUser;
use App\Models\User;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReferralManagementController extends Controller
{
    use ApiResponse;

    public function myReferrals(Request $request)
    {
        try {
            $per_page  = $request->input('per_page', 10);
            $user      = Auth::user();
            $referrals = ReferUser::with('referred_user:id,name,email,avatar')->where('referrer', $user->id)->where('status', 'approved')->latest('id')->paginate($per_page);
            return $this->responseSuccess($referrals, 'Referrals fetched successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'Failed to fetch referrals.', 500);
        }
    }

    public function referralManagement(Request $request)
    {
        $per_page      = $request->input('per_page', 10);
        $search        = $request->input('search');
        $referral_data = ReferUser::with(['referrer:id,name,avatar,kyc_status', 'referred_user:id,name,avatar,email,kyc_status'])->when($search, function ($query) use ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('referrer', function ($referrerQuery) use ($search) {
                    $referrerQuery->where('name', 'like', "%{$search}%");
                })
                    ->orWhereHas('referred_user', function ($referredQuery) use ($search) {
                        $referredQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        })->latest('id')->where('status', 'approved')->paginate($per_page);
        $data = [
            'total_referral_signup' => ReferUser::where('status', 'approved')->count(),
            'referral_data'         => $referral_data,
        ];
        return $this->responseSuccess($data, 'Referral information retrieved successfully');
    }
    public function referralManagementDetail(Request $request, $refer_id)
    {
        $referral_data = ReferUser::findOrFail($refer_id);
        $referrer_user = User::where('id', $referral_data->referrer)->select('id', 'name', 'email', 'phone', 'address', 'avatar', 'kyc_status')->first();
        $data          = [
            'referral_user_data'           => $referrer_user,
            'total_refers'                 => ReferUser::where('referrer', $referral_data->referrer)->where('status', 'approved')->count(),
            'total_earning_from_referrals' => ReferUser::where('referrer', $referral_data->referrer)->where('status', 'approved')->sum('referral_rewards'),
            'referral_list'                => ReferUser::with('referred_user:id,name,email,avatar,kyc_status')->where('referrer', $referral_data->referrer)->where('status', 'approved')->latest('id')->get(),
        ];
        return $this->responseSuccess($data, 'Referral details retrieved successfully');
    }
}
