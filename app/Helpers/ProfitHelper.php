<?php
namespace App\Helpers;

use App\Models\Setting;

class ProfitHelper
{
    public static function getPercentageAmount($amount)
    {
        $setting = Setting::find(1);

        if (! $setting) {
            $profit = 0;
        }
        $profit = $setting->profit;
        return ($amount * $profit) / 100;
    }
}
