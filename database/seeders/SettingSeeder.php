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
            'minimum_withdrawal_threshold' => 15,
            'three_day_boosting_price'     => 100,
            'seven_day_boosting_price'     => 200,
            'fifteen_day_boosting_price'   => 400,
            'thirty_day_boosting_price'    => 700,
        ]);
    }
}
