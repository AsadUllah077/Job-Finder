<?php

namespace App\Http\Controllers;

use App\Jobs\SyncSource;
use App\Models\JobListing;
use App\Models\JobSource;
use App\Models\Setting;
use App\Models\SyncRun;
use App\Models\User;
use App\Services\Sources\SourceRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function index(Request $r)
    {
        return view('admin.index', [
            'sources' => JobSource::withCount('jobs')->get(), 'runs' => SyncRun::with('source')->latest()->paginate(15),
            'users' => User::orderBy('id')->paginate(15, ['*'], 'users_page'),
            'failures' => DB::table('failed_jobs')->latest('failed_at')->limit(10)->get(['id', 'queue', 'failed_at']),
            'active' => JobListing::active()->count(), 'expired' => JobListing::where('is_active', false)->orWhere('expires_at', '<=', now())->count(),
            'settings' => ['keywords' => Setting::read('keywords', ['Laravel', 'PHP']), 'locations' => Setting::read('locations', config('jobfinder.default_locations')), 'sync_minutes' => Setting::read('sync_minutes', 60), 'alert_frequency' => Setting::read('alert_frequency', 'daily')],
        ]);
    }

    public function updateSource(Request $r, JobSource $source)
    {
        $data = $r->validate(['enabled' => 'nullable|boolean', 'board' => 'nullable|regex:/^[a-zA-Z0-9_-]{1,100}$/', 'company' => 'nullable|string|max:150', 'region' => ['nullable', Rule::in(['global', 'eu'])], 'max_pages' => 'required|integer|min:1|max:10', 'country' => ['nullable', Rule::in(['gb', 'us', 'at', 'au', 'be', 'br', 'ca', 'ch', 'de', 'es', 'fr', 'in', 'it', 'mx', 'nl', 'nz', 'pl', 'sg', 'za'])], 'currency' => 'nullable|regex:/^[A-Z]{3}$/']);
        $source->update(['enabled' => $r->boolean('enabled'), 'configuration' => array_filter(collect($data)->except('enabled')->all(), fn ($v) => $v !== null && $v !== '')]);

        return back()->with('status', 'Source settings saved. Secrets remain in environment configuration.');
    }

    public function sync(JobSource $source, SourceRegistry $registry)
    {
        if (! $source->enabled || ! $registry->for($source)->configured($source)) {
            return back()->with('status', 'Enable and configure this source before syncing.');
        }
        SyncSource::dispatch($source->id);

        return back()->with('status', 'Synchronization requested. The queue worker will process it, respecting the source cooldown.');
    }

    public function settings(Request $r)
    {
        $data = $r->validate(['keywords' => 'required|string|max:1000', 'locations' => 'required|string|max:1000', 'sync_minutes' => 'required|integer|min:60|max:10080', 'alert_frequency' => ['required', Rule::in(['daily', 'immediate'])]]);
        foreach ($data as $key => $value) {
            if (in_array($key, ['keywords', 'locations'])) {
                $value = array_values(array_filter(array_map('trim', explode(',', $value))));
            }
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return back()->with('status', 'Collection and alert defaults updated. Existing user preferences are preserved.');
    }

    public function user(Request $r, User $user)
    {
        $r->validate(['is_admin' => 'required|boolean']);
        abort_if($user->id === $r->user()->id && ! $r->boolean('is_admin'), 422, 'You cannot remove your own administrator access.');
        $user->forceFill(['is_admin' => $r->boolean('is_admin')])->save();

        return back()->with('status','User role updated.');
    }
}
