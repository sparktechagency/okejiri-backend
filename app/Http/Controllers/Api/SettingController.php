<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\SettingRequest;
use App\Models\Setting;
use App\Traits\ApiResponse;
use Exception;

class SettingController extends Controller
{
    use ApiResponse;
    public function getSettings()
    {
        try {
            $setting = Setting::findOrFail(1);
            return $this->responseSuccess($setting, 'Setting retrieved successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'Failed to retrieved setting.');
        }
    }
    public function updateSettings(SettingRequest $request)
    {
        try {
            $setting                               = Setting::findOrFail(1);
            $setting->referral_bonus_amount        = $request->referral_bonus_amount ?? $setting->referral_bonus_amount;
            $setting->minimum_withdrawal_threshold = $request->minimum_withdrawal_threshold ?? $setting->minimum_withdrawal_threshold;
            $setting->three_day_boosting_price     = $request->three_day_boosting_price ?? $setting->three_day_boosting_price;
            $setting->seven_day_boosting_price     = $request->seven_day_boosting_price ?? $setting->seven_day_boosting_price;
            $setting->fifteen_day_boosting_price   = $request->fifteen_day_boosting_price ?? $setting->fifteen_day_boosting_price;
            $setting->thirty_day_boosting_price    = $request->thirty_day_boosting_price ?? $setting->thirty_day_boosting_price;
            $setting->save();
            return $this->responseSuccess($setting, 'Setting updated successfully.');
        } catch (Exception $e) {
            return $this->responseError($e->getMessage(), 'Failed to update setting.', 500);
        }
    }
}
