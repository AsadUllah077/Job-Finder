<?php

namespace App\Jobs;

use App\Models\JobListing;
use App\Models\JobSource;
use App\Models\Setting;
use App\Models\SyncRun;
use App\Services\JobNormalizer;
use App\Services\Sources\SourceException;
use App\Services\Sources\SourceRegistry;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncSource implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 900;

    public int $uniqueFor = 172800;

    public function __construct(public int $sourceId) {}

    public function uniqueId(): string
    {
        return 'source:'.$this->sourceId;
    }

    public function backoff(): array
    {
        return [300, 900, 1800];
    }

    public function handle(SourceRegistry $registry, JobNormalizer $normalizer): void
    {
        $lock = Cache::lock('sync:'.$this->sourceId, 930);
        if (! $lock->get()) {
            return;
        }
        try {
            $source = JobSource::findOrFail($this->sourceId);
            if (! $source->enabled) {
                return;
            }
            $adapter = $registry->for($source);
            if ($source->last_checked_at && $source->last_checked_at->addSeconds($adapter->minimumInterval())->isFuture()) {
                return;
            }
            if (Cache::has('cooldown:'.$source->id)) {
                return;
            }
            $source->update(['last_checked_at' => now(), 'last_sync_status' => 'running', 'last_error' => null]);
            $run = SyncRun::create(['source_id' => $source->id, 'started_at' => now(), 'status' => 'running']);
            $fetched = $created = $skipped = 0;
            try {
                if (! $adapter->configured($source)) {
                    throw new SourceException('Source setup is incomplete. Configure credentials or board settings.');
                }
                $keywords = Setting::read('keywords', ['Laravel', 'PHP']);
                foreach ($adapter->fetch($source) as $raw) {
                    $fetched++;
                    $data = $normalizer->normalize($raw);
                    if (! $data || ! collect($keywords)->contains(fn ($k) => Str::contains(Str::lower($data['title'].' '.$data['description']), Str::lower($k)))) {
                        $skipped++;

                        continue;
                    }
                    $job = JobListing::firstOrNew(['source_id' => $source->id, 'external_id' => $data['external_id']]);
                    $new = ! $job->exists;
                    $job->fill($data);
                    $job->first_seen_at ??= now();
                    $job->last_seen_at = now();
                    $job->is_active = ! $job->expires_at || $job->expires_at->isFuture();
                    $job->save();
                    if ($new) {
                        $created++;
                    }
                }
                $run->update(['finished_at' => now(), 'status' => 'success', 'fetched' => $fetched, 'created' => $created, 'skipped' => $skipped]);
                $source->update(['last_synced_at' => now(), 'last_sync_status' => 'success', 'last_error' => null]);
            } catch (\Throwable $e) {
                $error = $e instanceof SourceException ? $e->getMessage() : 'Synchronization failed. Check server configuration and retry.';
                $run->update(['finished_at' => now(), 'status' => 'failed', 'error' => $error, 'fetched' => $fetched, 'created' => $created, 'skipped' => $skipped]);
                $source->update(['last_sync_status' => 'failed', 'last_error' => $error]);
                Log::warning('Job source sync failed', ['source_id' => $source->id, 'reason' => $error]);
                $delay = max($adapter->minimumInterval(), $e instanceof SourceException ? $e->retryAfter : 0);
                Cache::put('cooldown:'.$source->id, true, $delay);
                // Retrying at the source interval keeps transient failures from exhausting quotas.
                if ($this->job && $this->attempts() < $this->tries) {
                    $this->release($delay + 1);
                } elseif ($this->job) {
                    $this->fail(new SourceException($error));
                }
            }
        } finally {
            $lock->release();
        }
    }

    public function failed(?\Throwable $e): void
    {
        SyncRun::where('source_id', $this->sourceId)->where('status', 'running')->update(['status' => 'failed', 'finished_at' => now(), 'error' => 'Worker stopped or exceeded its time limit.']);
        JobSource::where('id', $this->sourceId)->where('last_sync_status', 'running')->update(['last_sync_status' => 'failed', 'last_error' => 'Worker stopped or exceeded its time limit.']);
    }
}
