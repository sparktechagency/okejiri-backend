<?php

use App\Console\Commands\ServiceSuspendClear;
use Illuminate\Support\Facades\Schedule;

Schedule::command(ServiceSuspendClear::class)->daily();
