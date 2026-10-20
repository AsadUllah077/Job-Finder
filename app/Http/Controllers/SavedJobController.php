<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveApplicationRequest;
use App\Models\JobListing;
use App\Models\SavedJob;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SavedJobController extends Controller
{
    public function index(Request $r)
    {
        $r->validate(['status' => ['nullable', Rule::in(SavedJob::STATUSES)]]);
        $q = $r->user()->savedJobs()->with('job.source');
        if ($r->filled('status')) {
            $q->where('application_status', $r->status);
        }

        return view('saved.index', ['savedJobs' => $q->latest('saved_at')->paginate(12)->withQueryString()]);
    }

    public function store(Request $r, JobListing $job)
    {
        $r->user()->savedJobs()->firstOrCreate(['job_id' => $job->id], ['saved_at' => now(), 'application_status' => 'Saved']);

        return back()->with('status', 'Job saved to your private tracker.');
    }

    public function update(SaveApplicationRequest $r, SavedJob $savedJob)
    {
        Gate::authorize('update', $savedJob);
        $data = $r->validated();
        if (! empty($data['interview_at'])) {
            $data['interview_at'] = Carbon::parse($data['interview_at'], config('jobfinder.display_timezone'))->utc();
        }
        $savedJob->update($data);

        return back()->with('status', 'Application updated.');
    }

    public function destroy(SavedJob $savedJob)
    {
        Gate::authorize('delete', $savedJob);
        $savedJob->delete();

        return back()->with('status', 'Job removed from your tracker.');
    }

    public function export(Request $r)
    {
        return response()->streamDownload(function () use ($r) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Title', 'Company', 'Location', 'Source', 'Status', 'Saved', 'Applied', 'Interview', 'Notes', 'URL']);
            foreach ($r->user()->savedJobs()->with('job.source')->cursor() as $s) {
                $row = [$s->job->title, $s->job->company_name, $s->job->location, $s->job->source->name, $s->application_status, $s->saved_at->toDateString(), $s->applied_at?->toDateString(), $s->interview_at?->toDateTimeString(), $s->notes, $s->job->application_url];
                fputcsv($stream, array_map(fn ($v) => preg_match('/^[\s]*[=+@\-]/u', (string) $v) ? "'".$v : $v, $row));
            }
            fclose($stream);
        }, 'jobfinder-saved-jobs.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
