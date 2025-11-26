<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\TestApiKeysJob;
use App\Jobs\CheckApiKeyCooldownJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('apikey:test', function () {
    TestApiKeysJob::dispatch();
    $this->info('TestApiKeysJob dispatched');
})->purpose('Test Gemini API keys and update status');

Schedule::job(new TestApiKeysJob())->everyTenMinutes();
Schedule::job(new CheckApiKeyCooldownJob())->everyFiveMinutes();
