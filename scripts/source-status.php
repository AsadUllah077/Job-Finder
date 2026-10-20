<?php

use App\Models\JobSource;
use Illuminate\Contracts\Console\Kernel;

// Local diagnostic command; prints only non-secret source status, not exception payloads.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
foreach (JobSource::withCount('jobs')->get() as $source) {
    echo json_encode(['source' => $source->name, 'status' => $source->last_sync_status, 'checked' => $source->last_checked_at?->toIso8601String(), 'listings' => $source->jobs_count, 'error' => $source->last_error]).PHP_EOL;
}
