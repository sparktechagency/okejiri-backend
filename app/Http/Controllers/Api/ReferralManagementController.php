<?php
namespace App\Http\Controllers\Api;

use App\Models\ReferUser;
use Exception;
use App\Models\Setting;
use App\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Setting\SettingRequest;
use Illuminate\Http\Request;

class ReferralManagementController extends Controller
{
    use ApiResponse;
    public function updateSettings(SettingRequest $request)
    {
        try {
            $setting                               = Setting::findOrFail(1);
            $setting->referral_bonus_amount        = $request->referral_bonus_amount;
            $setting->minimum_withdrawal_threshold = $request->minimum_withdrawal_threshold;
            $setting->save();
            return $this->responseSuccess($setting, 'Setting updated successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'Failed to update setting.', 500);
        }
    }

    public function myReferrals(Request $request){
        try {
                 $per_page        = $request->input('per_page',10);
            $user = Auth::user();
            $referrals = ReferUser::with('referred_user:id,name,email,avatar')->where('referrer',$user->id)->where('status','approved')->latest('id')->paginate($per_page);
            return $this->responseSuccess($referrals, 'Referrals fetched successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'Failed to fetch referrals.', 500);
        }
    }
}
