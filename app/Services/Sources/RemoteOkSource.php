<?php

namespace App\Services\Sources;

use App\Models\JobSource;

class RemoteOkSource extends ApiSource
{
    public function minimumInterval(): int
    {
        return 21600;
    }

    public function fetch(JobSource $source): iterable
    {
        $data = $this->get('https://remoteok.com/api');
        if (! array_is_list($data)) {
            throw new SourceException('Source response schema changed.');
        }
        foreach ($data as $j) {
            if (isset($j['legal'])) {
                continue;
            }
            yield ['external_id' => $j['id'] ?? null, 'title' => $j['position'] ?? null, 'company_name' => $j['company'] ?? null,
                'location' => $j['location'] ?? null, 'description' => $j['description'] ?? '', 'application_url' => $j['url'] ?? null,
                'published_at' => $j['date'] ?? null, 'workplace_type' => 'remote', 'salary_min' => $j['salary_min'] ?? null,
                'salary_max' => $j['salary_max'] ?? null, 'salary_currency' => 'USD', 'salary_period' => 'year'];
        }
    }
}
