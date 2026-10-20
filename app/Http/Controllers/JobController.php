<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchJobsRequest;
use App\Models\JobListing;
use App\Models\JobSource;
use App\Models\SyncRun;
use App\Services\JobSearch;
use App\Services\RelevanceScorer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class JobController extends Controller
{
    public function dashboard(Request $r, RelevanceScorer $scorer)
    {
        $stats = Cache::remember('dashboard:counts', 60, fn () => ['active' => JobListing::active()->count(), 'today' => JobListing::active()->whereDate('first_seen_at', today())->count(), 'week' => JobListing::active()->where('first_seen_at', '>=', now()->startOfWeek())->count()]);
        $stats['saved'] = $r->user()->savedJobs()->count();
        $stats['applications'] = $r->user()->savedJobs()->whereIn('application_status', ['Applied', 'Interview', 'Offer'])->count();
        $stats['matches'] = JobListing::active()->cursor()->filter(fn ($j) => $scorer->score($j, $r->user()->preference) >= 70)->count();

        return view('dashboard', ['stats' => $stats, 'recent' => JobListing::active()->with('source')->latest('first_seen_at')->limit(5)->get(), 'runs' => SyncRun::with('source')->latest()->limit(5)->get()]);
    }

    public function index(SearchJobsRequest $r, JobSearch $search)
    {
        $filters = array_filter($r->validated(), fn ($v) => $v !== null && $v !== '');
        if ($r->user() && ! empty($filters) && ! $r->has('page')) {
            $last = DB::table('search_histories')->where('user_id', $r->user()->id)->latest('id')->first();
            if (! $last || json_decode($last->filters, true) !== $filters) {
                DB::table('search_histories')->insert(['user_id' => $r->user()->id, 'filters' => json_encode($filters), 'created_at' => now(), 'updated_at' => now()]);
            }
            $keep = DB::table('search_histories')->where('user_id', $r->user()->id)->latest('id')->limit(20)->pluck('id');
            DB::table('search_histories')->where('user_id', $r->user()->id)->whereNotIn('id', $keep)->delete();
        }

        return view('jobs.index', ['jobs' => $search->results($filters, $r->user()), 'sources' => JobSource::orderBy('name')->get(), 'filters' => $filters]);
    }

    public function show(Request $r, JobListing $job)
    {
        return view('jobs.show', ['job' => $job->load('source'), 'saved' => $r->user()?->savedJobs()->where('job_id', $job->id)->first()]);
    }
}
