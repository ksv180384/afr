<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

\Illuminate\Support\Facades\Schedule::command('daily-practice:prune-previews')->hourly()->withoutOverlapping();

\Illuminate\Support\Facades\Schedule::command('daily-practice:metrics --days=7')->dailyAt('03:20')->timezone('UTC')->withoutOverlapping();
