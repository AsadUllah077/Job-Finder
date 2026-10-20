<?php

use App\Models\JobListing;
use App\Services\JobNormalizer;
use App\Services\JobSearch;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

/** Read-only smoke check against the configured database; no external requests. */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$driver = DB::getDriverName();
echo 'Database driver: '.$driver.PHP_EOL;
echo 'Database version: '.DB::selectOne('SELECT VERSION() AS version')->version.PHP_EOL;
$count = JobListing::active()->where('is_demo', false)->count();
echo 'Active real listings: '.$count.PHP_EOL;
$matches = app(JobSearch::class)->query(['q' => 'PHP'], null)->count();
echo 'PHP search matches: '.$matches.PHP_EOL;
if ($driver !== 'mysql') {
    throw new RuntimeException('This verification requires MySQL/MariaDB.');
}
if ($count === 0) {
    throw new RuntimeException('No real listings available. Synchronize a source first.');
}
foreach (JobListing::where('is_demo', false)->cursor() as $job) {
    if (! app(JobNormalizer::class)->safeUrl($job->application_url)) {
        throw new RuntimeException('Unsafe application URL.');
    }
}
echo 'Stored source URLs validated. No demo listings counted.'.PHP_EOL;
