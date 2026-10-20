<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;

class JobNormalizer
{
    public function normalize(array $raw): ?array
    {
        foreach (['external_id', 'title', 'company_name', 'application_url'] as $key) {
            if (! isset($raw[$key]) || trim((string) $raw[$key]) === '') {
                return null;
            }
        }
        if (! $this->safeUrl($raw['application_url'])) {
            return null;
        }
        $description = $this->text($raw['description'] ?? '');
        $title = Str::limit($this->text($raw['title']), 250, '');
        $company = Str::limit($this->text($raw['company_name']), 250, '');
        if ($title === '' || $company === '') {
            return null;
        }
        $externalId = (string) $raw['external_id'];
        if (mb_strlen($externalId) > 191) {
            $externalId = 'sha256:'.hash('sha256', $externalId);
        }
        $location = Str::limit($this->text($raw['location'] ?? ''), 250, '');
        $country = $raw['country'] ?? null;
        $city = null;
        $regions = [
            'AE' => ['UAE', 'United Arab Emirates', 'Dubai', 'Abu Dhabi', 'Sharjah', 'Ajman', 'Ras Al Khaimah', 'Fujairah'],
            'PK' => ['Pakistan', 'Islamabad', 'Rawalpindi', 'Lahore', 'Karachi', 'Peshawar'],
            'SA' => ['Saudi Arabia', 'Riyadh', 'Jeddah', 'Dammam'], 'QA' => ['Qatar', 'Doha'],
            'DE' => ['Germany', 'Berlin', 'Hamburg', 'Munich'], 'GB' => ['United Kingdom', 'London'], 'US' => ['United States'],
        ];
        foreach ($regions as $code => $places) {
            foreach ($places as $i => $place) {
                if (Str::contains(Str::lower($location), Str::lower($place))) {
                    $country = $code;
                    if ($i > 0 && $place !== 'United Arab Emirates') {
                        $city = $place;
                    }
                }
            }
        }
        $aliases = ['UAE' => 'AE', 'UK' => 'GB'];
        $country = $country ? strtoupper($country) : null;
        $country = $aliases[$country] ?? $country;
        $type = Str::lower(str_replace(['_', '-'], ' ', $raw['job_type'] ?? 'unknown'));
        $type = match ($type) {
            'fulltime','full time' => 'full_time', 'parttime','part time' => 'part_time','permanent' => 'permanent','contract','freelance','internship' => $type, default => 'unknown'
        };
        $work = Str::lower($raw['workplace_type'] ?? 'unknown');
        $work = match ($work) {
            'remote','hybrid' => $work,'on-site','onsite' => 'onsite', default => 'unknown'
        };
        $level = match (true) {
            (bool) preg_match('/\b(intern|internship)\b/i', $title) => 'internship', (bool) preg_match('/\b(senior|sr\.?|lead|principal)\b/i', $title) => 'senior', (bool) preg_match('/\b(junior|jr\.?|graduate)\b/i', $title) => 'junior', default => 'unknown'
        };
        $data = [
            'external_id' => $externalId, 'title' => $title, 'company_name' => $company,
            'location' => $location ?: null, 'country' => $country ? Str::limit($country, 100, '') : null, 'city' => $city,
            'job_type' => $type, 'workplace_type' => $work, 'description' => $description, 'requirements' => $this->text($raw['requirements'] ?? '') ?: null,
            'salary_min' => $this->salary($raw['salary_min'] ?? null), 'salary_max' => $this->salary($raw['salary_max'] ?? null),
            'salary_currency' => preg_match('/^[A-Z]{3}$/D', strtoupper($raw['salary_currency'] ?? '')) ? strtoupper($raw['salary_currency']) : null,
            'salary_period' => isset($raw['salary_period']) ? Str::limit($raw['salary_period'], 30, '') : null,
            'salary_text' => isset($raw['salary_text']) ? Str::limit($this->text($raw['salary_text']), 250, '') : null,
            'experience_level' => $level, 'application_url' => $raw['application_url'],
            'company_url' => $this->safeUrl($raw['company_url'] ?? '') ? $raw['company_url'] : null,
            'published_at' => $this->date($raw['published_at'] ?? null), 'expires_at' => $this->date($raw['expires_at'] ?? null),
        ];
        $data['content_hash'] = hash('sha256', json_encode($data));

        return $data;
    }

    public function safeUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return strlen($url) <= 4096 && filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https']) && $host && ! filter_var($host, FILTER_VALIDATE_IP) && str_contains($host, '.') && ! preg_match('/(^localhost$|\.(local|internal|localhost)$)/i', $host) && ! parse_url($url, PHP_URL_USER);
    }

    public function text(string $html): string
    {
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = preg_replace('#<(script|style|iframe)\b[^>]*>.*?</\1>#is', '', $html);
        $html = preg_replace('#</?(p|div|h[1-6]|li|ul|br)\b[^>]*>#i', "\n", $html);

        return trim(preg_replace('/[ \t]+/', ' ', strip_tags($html)));
    }

    private function salary($value): ?float
    {
        return is_numeric($value) && $value > 0 && $value < 999999999999 ? (float) $value : null;
    }

    private function date($value): ?Carbon
    {
        try {
            return $value ? (is_numeric($value) ? Carbon::createFromTimestamp((int) $value)->utc() : Carbon::parse($value)->utc()) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
