<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\SettingRequest;
use App\Models\Setting;
use App\Traits\ApiResponse;
use Exception;

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
}
