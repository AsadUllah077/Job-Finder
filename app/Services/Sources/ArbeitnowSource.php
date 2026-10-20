<?php

namespace App\Services\Sources;

use App\Models\JobSource;

class ArbeitnowSource extends ApiSource
{
    public function fetch(JobSource $source): iterable
    {
        for ($page = 1; $page <= $this->pages($source); $page++) {
            $data = $this->get('https://www.arbeitnow.com/api/job-board-api', ['page' => $page]);
            foreach ($this->rows($data, 'data') as $j) {
                yield [
                    'external_id' => $j['slug'] ?? null, 'title' => $j['title'] ?? null, 'company_name' => $j['company_name'] ?? null,
                    'location' => $j['location'] ?? null, 'description' => $j['description'] ?? '', 'application_url' => $j['url'] ?? null,
                    'workplace_type' => ! empty($j['remote']) ? 'remote' : 'unknown', 'job_type' => $j['job_types'][0] ?? 'unknown',
                    'published_at' => $j['created_at'] ?? null,
                ];
            }
            if (empty($data['links']['next'])) {
                break;
            }
        }
    }
}
