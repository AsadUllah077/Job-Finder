<?php

use App\Jobs\SendJobAlerts;
use App\Jobs\SyncSource;
use App\Models\JobListing;
use App\Models\JobSource;
use App\Models\Setting;
use App\Models\User;
use App\Services\Sources\SourceRegistry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('jobs:sync {--source=}', function () {
    $sources = JobSource::where('enabled', true)->when($this->option('source'), fn ($q, $s) => $q->where('slug', $s))->get();
    foreach ($sources as $s) {
        if (! app(SourceRegistry::class)->for($s)->configured($s)) {
            $this->warn($s->name.': setup required');

            continue;
        }
        SyncSource::dispatch($s->id);
        $this->info($s->name.': queued (source cooldown applies)');
    }
})->purpose('Queue enabled, configured job sources');
Artisan::command('jobs:alerts {--frequency=daily}', function () {
    SendJobAlerts::dispatch($this->option('frequency'));
    $this->info('Alert processing queued; requires configured mail and opt-in users.');
});
Artisan::command('users:admin {email}', function () {
    $user = User::where('email', $this->argument('email'))->first();
    if (! $user) {
        $this->error('Register this account first.');

        return 1;
    }
    $user->forceFill(['is_admin' => true])->save();
    $this->info('Administrator access granted.');
});
Schedule::call(function () {
    $registry = app(SourceRegistry::class);
    foreach (JobSource::where('enabled', true)->get() as $s) {
        $interval = max((int) Setting::read('sync_minutes', 60) * 60, $registry->for($s)->minimumInterval());
        if ($registry->for($s)->configured($s) && (! $s->last_checked_at || $s->last_checked_at->addSeconds($interval)->isPast())) {
            SyncSource::dispatch($s->id);
        }
    }
})->everyMinute()->name('jobs:scheduled-sync')->withoutOverlapping();
Schedule::job(new SendJobAlerts('immediate'))->everyFifteenMinutes()->withoutOverlapping();
Schedule::job(new SendJobAlerts('daily'))->dailyAt('08:00')->timezone('Asia/Karachi')->withoutOverlapping();
Schedule::call(fn () => JobListing::where('is_active', true)->whereNotNull('expires_at')->where('expires_at', '<=', now())->update(['is_active' => false]))->hourly()->name('jobs:expire')->withoutOverlapping();
