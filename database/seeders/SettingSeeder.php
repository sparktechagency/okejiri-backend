<?php
namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Setting::create([
            'referral_bonus_amount'        => 10.50,
            'minimum_withdrawal_threshold' => 1000,
            'three_day_boosting_price'     => 800,
            'seven_day_boosting_price'     => 1600,
            'fifteen_day_boosting_price'   => 3200,
            'thirty_day_boosting_price'    => 5600,
            'profit'    => 10, // in percent
        ]);
    }
}
