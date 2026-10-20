<?php

namespace App\Services;

use App\Models\JobListing;
use App\Models\SearchPreference;
use Illuminate\Support\Str;

class RelevanceScorer
{
    public function score(JobListing $job, ?SearchPreference $p = null): int
    {
        $w = array_merge(['keywords' => 45, 'location' => 25, 'salary' => 10, 'remote' => 20], $p?->weights ?? []);
        $text = Str::lower($job->title.' '.$job->description);
        $keywords = $p?->preferred_keywords ?: config('jobfinder.default_keywords');
        $matched = count(array_filter($keywords, fn ($k) => Str::contains($text, Str::lower($k))));
        $keywordScore = min(1, $matched / max(1, min(count($keywords), 3)));
        $locations = $p?->preferred_locations ?: config('jobfinder.default_locations');
        $locationScore = $p?->worldwide || ! $locations ? 1 : (int) $this->matchesLocations($job, $locations);
        $remoteScore = $p?->remote_only ? (int) ($job->workplace_type === 'remote') : ($job->workplace_type === 'remote' ? 1 : 0.5);
        // Compare a user's annual floor only with explicit annual salary in the same currency.
        $salaryScore = ! $p?->minimum_salary ? 0.5 : (($job->salary_currency === $p->salary_currency && in_array($job->salary_period, ['year', 'yearly', 'annual'])) ? (int) (($job->salary_max ?? $job->salary_min ?? 0) >= $p->minimum_salary) : 0);
        $score = ($keywordScore * $w['keywords'] + $locationScore * $w['location'] + $salaryScore * $w['salary'] + $remoteScore * $w['remote']) / max(1, array_sum($w)) * 100;
        if ($p?->experience_level && $p->experience_level !== $job->experience_level) {
            $score -= $job->experience_level === 'internship' ? 30 : 12;
        }
        if ($p?->preferred_job_types && ! in_array($job->job_type, $p->preferred_job_types)) {
            $score -= 10;
        }
        if (! preg_match('/\b(php|laravel)\b/i', $text)) {
            $score -= 15;
        }

        return (int) max(0, min(100, round($score)));
    }

    public function matchesAlert(JobListing $j, SearchPreference $p): bool
    {
        if ($j->is_demo || ! $j->is_active) {
            return false;
        }
        if ($p->remote_only && $j->workplace_type !== 'remote') {
            return false;
        }
        if ($p->preferred_keywords && ! collect($p->preferred_keywords)->contains(fn ($k) => Str::contains(Str::lower($j->title.' '.$j->description), Str::lower($k)))) {
            return false;
        }
        if (! $p->worldwide && $p->preferred_locations && ! $this->matchesLocations($j, $p->preferred_locations)) {
            return false;
        }
        if ($p->experience_level && $p->experience_level !== $j->experience_level) {
            return false;
        }
        if ($p->preferred_job_types && ! in_array($j->job_type, $p->preferred_job_types)) {
            return false;
        }
        if ($p->minimum_salary && ($p->salary_currency !== $j->salary_currency || ! in_array($j->salary_period, ['year', 'yearly', 'annual']) || ($j->salary_max ?? $j->salary_min ?? 0) < $p->minimum_salary)) {
            return false;
        }

        return true;
    }

    private function matchesLocations(JobListing $job, array $locations): bool
    {
        $aliases = ['uae' => 'AE', 'united arab emirates' => 'AE', 'pakistan' => 'PK', 'saudi arabia' => 'SA', 'qatar' => 'QA', 'germany' => 'DE', 'united kingdom' => 'GB', 'uk' => 'GB', 'united states' => 'US'];

        return collect($locations)->contains(function ($location) use ($job, $aliases) {
            $location = Str::lower(trim($location));
            if (isset($aliases[$location]) && $job->country === $aliases[$location]) {
                return true;
            }
            if (strlen($location) === 2) {
                return strtoupper($location) === $job->country;
            }

            return Str::contains(Str::lower($job->location ?? ''), $location);
        });
    }
}
