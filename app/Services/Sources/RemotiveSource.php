<?php

namespace App\Services\Sources;

use App\Models\JobSource;

class RemotiveSource extends ApiSource
{
    public function minimumInterval(): int
    {
        return 21600;
    }

    public function fetch(JobSource $source): iterable
    {
        $data = $this->get('https://remotive.com/api/remote-jobs', ['category' => 'software-dev']);
        foreach ($this->rows($data, 'jobs') as $j) {
            yield [
                'external_id' => $j['id'] ?? null, 'title' => $j['title'] ?? null, 'company_name' => $j['company_name'] ?? null,
                'location' => $j['candidate_required_location'] ?? null, 'description' => $j['description'] ?? '',
                'application_url' => $j['url'] ?? null, 'published_at' => $j['publication_date'] ?? null,
                'workplace_type' => 'remote', 'job_type' => $j['job_type'] ?? 'unknown', 'salary_text' => $j['salary'] ?? null,
            ];
        }
    }
}
