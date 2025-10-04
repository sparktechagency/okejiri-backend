<?php
namespace App\Console\Commands;

use App\Models\Package;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ServiceSuspendClear extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:service-suspend-clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove expired service suspensions and update their status.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expiredSuspensionServices = Package::where('is_suspend', true)
            ->whereNot('suspend_reason', 'Suspend permanently')
            ->whereDate('suspend_until', '<=', Carbon::today())
            ->get();

        foreach ($expiredSuspensionServices as $service) {
            $service->is_suspend     = false;
            $service->suspend_reason = null;
            $service->suspend_until  = null;
            $service->save();

            $this->info("Unsuspend service: {$service->title} (ID: {$service->id})");
        }

        $this->info('Expired suspensions cleared successfully.');
    }
}
