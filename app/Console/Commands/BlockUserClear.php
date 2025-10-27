<?php
namespace App\Console\Commands;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class BlockUserClear extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:block-user-clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Unblock users whose blocking period has expired';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = User::where('is_blocked', true)
            ->whereNotNull('block_expires_at')
            ->where('block_expires_at', '<=', Carbon::now())
            ->update([
                'is_blocked'       => false,
                'block_expires_at' => null,
            ]);

        if ($count > 0) {
            $this->info("Successfully unblocked {$count} users.");
        } else {
            $this->info('No users needed to be unblocked.');
        }

        return 0;
    }
}
