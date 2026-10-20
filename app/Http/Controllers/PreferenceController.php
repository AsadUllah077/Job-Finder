<?php

namespace App\Http\Controllers;

use App\Http\Requests\PreferencesRequest;
use App\Models\SearchPreference;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PreferenceController extends Controller
{
    public function edit(Request $r)
    {
        return view('preferences', ['p' => $r->user()->preference ?? new SearchPreference(['preferred_keywords' => config('jobfinder.default_keywords'), 'preferred_locations' => config('jobfinder.default_locations'), 'salary_currency' => 'USD', 'alert_frequency' => 'daily']), 'history' => DB::table('search_histories')->where('user_id', $r->user()->id)->latest()->limit(10)->get()]);
    }

    public function update(PreferencesRequest $r)
    {
        $data = $r->safe()->except(['keywords', 'locations']);
        $split = fn ($s) => array_slice(array_values(array_unique(array_filter(array_map('trim', explode(',', $s ?? ''))))), 0, 30);
        $data['preferred_keywords'] = $split($r->keywords);
        $data['preferred_locations'] = $split($r->locations);
        foreach (['worldwide', 'remote_only', 'email_notifications_enabled'] as $key) {
            $data[$key] = $r->boolean($key);
        }
        $data['preferred_job_types'] = $r->input('preferred_job_types', []);
        $p = $r->user()->preference;
        if ($data['email_notifications_enabled'] && ! $p?->email_notifications_enabled) {
            $data['alerts_started_at'] = now();
        }
        $r->user()->preference()->updateOrCreate([], $data);

        return back()->with('status', 'Preferences updated. Alerts use these filters for newly discovered jobs.');
    }

    public function unsubscribe(Request $r, User $user)
    {
        return view('unsubscribe', ['user' => $user]);
    }

    public function confirmUnsubscribe(Request $r, User $user)
    {
        $user->preference()?->update(['email_notifications_enabled' => false]);

        return view('unsubscribe', ['user' => $user, 'done' => true]);
    }

    public function clearHistory(Request $r)
    {
        DB::table('search_histories')->where('user_id', $r->user()->id)->delete();

        return back()->with('status','Search history cleared.');
    }
}
