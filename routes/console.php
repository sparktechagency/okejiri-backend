<?php

use App\Console\Commands\BlockUserClear;
use App\Console\Commands\ServiceSuspendClear;
use Illuminate\Support\Facades\Schedule;

Schedule::command(ServiceSuspendClear::class)->daily();
Schedule::command(BlockUserClear::class)->daily();
