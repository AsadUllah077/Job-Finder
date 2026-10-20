<?php

namespace App\Jobs;

use App\Models\JobListing;
use App\Models\User;
use App\Notifications\JobDigest;
use App\Services\RelevanceScorer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SendJobAlerts implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public string $frequency = 'daily') {}

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(RelevanceScorer $scorer): void
    {
        if (! config('jobfinder.mail_enabled') || in_array(config('mail.default'), ['log', 'array'])) {
            return;
        }
        User::whereHas('preference', fn ($q) => $q->where('email_notifications_enabled', true)->where('alert_frequency', $this->frequency))->each(function ($user) use ($scorer) {
            $lock = Cache::lock('alerts:user:'.$user->id, 360);
            if (! $lock->get()) {
                return;
            }
            try {
                $p = $user->preference;
                $jobs = JobListing::active()->where('is_demo', false)->where('first_seen_at', '>=', $p->alerts_started_at ?? $p->created_at)
                    ->whereNotIn('id', DB::table('alert_deliveries')->where('user_id', $user->id)->whereNotNull('sent_at')->select('job_id'))
                    ->orderBy('id')->cursor()->filter(fn ($j) => $scorer->matchesAlert($j, $p))->take(50)->collect()->values();
                if ($jobs->isEmpty()) {
                    return;
                }
                $user->notify(new JobDigest($jobs->map(fn ($j) => ['title' => $j->title, 'company_name' => $j->company_name, 'location' => $j->location, 'url' => route('jobs.show', $j)])->all()));
                foreach ($jobs as $j) {
                    DB::table('alert_deliveries')->updateOrInsert(['user_id' => $user->id, 'job_id' => $j->id], ['sent_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                }
            } finally {
                $lock->release();
            }
        });
    }
}
