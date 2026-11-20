<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Monthly digest (1st of the month, 09:00 Africa/Lagos). The s6 scheduler service runs schedule:work.
\Illuminate\Support\Facades\Schedule::command('redstar:digest')->monthlyOn(1, '09:00')->timezone('Africa/Lagos')->withoutOverlapping();

// AI: pick up editions the PDF worker marked ready (cheap DB check; Replicate work runs on the queue).
\Illuminate\Support\Facades\Schedule::command('redstar:ai-sweep')->everyMinute()->withoutOverlapping();

// Bulk archive push: publish auto-publish editions once the worker has processed them.
\Illuminate\Support\Facades\Schedule::command('redstar:auto-publish')->everyMinute()->withoutOverlapping();
