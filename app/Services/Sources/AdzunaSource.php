<?php

namespace App\Services\Sources;

use App\Models\JobSource;
use App\Models\Setting;

class AdzunaSource extends ApiSource
{
    public function configured(JobSource $source): bool
    {
        return filled(config('jobfinder.adzuna_id')) && filled(config('jobfinder.adzuna_key'));
    }

    public function fetch(JobSource $source): iterable
    {
        if (! $this->configured($source)) {
            throw new SourceException('Adzuna credentials are not configured.');
        }
        $country = $source->configuration['country'] ?? 'gb';
        if (! in_array($country, ['gb', 'us', 'at', 'au', 'be', 'br', 'ca', 'ch', 'de', 'es', 'fr', 'in', 'it', 'mx', 'nl', 'nz', 'pl', 'sg', 'za'])) {
            throw new SourceException('Unsupported Adzuna country code.');
        }
        foreach (array_slice($source->configuration['keywords'] ?? Setting::read('keywords', ['Laravel', 'PHP']), 0, 5) as $term) {
            for ($page = 1; $page <= $this->pages($source); $page++) {
                $data = $this->get("https://api.adzuna.com/v1/api/jobs/{$country}/search/{$page}", ['app_id' => config('jobfinder.adzuna_id'), 'app_key' => config('jobfinder.adzuna_key'), 'what' => $term, 'results_per_page' => 50, 'content-type' => 'application/json']);
                $rows = $this->rows($data, 'results');
                foreach ($rows as $j) {
                    yield ['external_id' => isset($j['id']) ? $country.':'.$j['id'] : null, 'title' => $j['title'] ?? null,
                        'company_name' => $j['company']['display_name'] ?? null, 'location' => $j['location']['display_name'] ?? null, 'country' => strtoupper($country),
                        'description' => $j['description'] ?? '', 'application_url' => $j['redirect_url'] ?? null, 'published_at' => $j['created'] ?? null,
                        'job_type' => $j['contract_time'] ?? $j['contract_type'] ?? 'unknown',
                        'salary_min' => empty($j['salary_is_predicted']) ? ($j['salary_min'] ?? null) : null,
                        'salary_max' => empty($j['salary_is_predicted']) ? ($j['salary_max'] ?? null) : null,
                        'salary_currency' => $source->configuration['currency'] ?? null, 'salary_period' => 'year'];
                }
                if (count($rows) < 50) {
                    break;
                }
            }
        }
    }
}
