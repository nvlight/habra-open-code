<?php

use App\Jobs\DiscoverHabrUrlsJob;
use App\Jobs\DispatchHabrFetchJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('cert:check')->dailyAt('09:00');

Schedule::job(new DiscoverHabrUrlsJob)->dailyAt('03:00');
Schedule::command('habr:retry-failed')->dailyAt('03:20');
Schedule::job(new DispatchHabrFetchJob)->dailyAt('03:30');
