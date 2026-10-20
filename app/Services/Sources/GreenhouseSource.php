<?php

namespace App\Services\Sources;

use App\Models\JobSource;

class GreenhouseSource extends ApiSource
{
    public function configured(JobSource $source): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9_-]{1,100}$/D', $source->configuration['board'] ?? '');
    }

    public function fetch(JobSource $source): iterable
    {
        $board = $this->board($source);
        $data = $this->get("https://boards-api.greenhouse.io/v1/boards/{$board}/jobs", ['content' => 'true']);
        foreach ($this->rows($data, 'jobs') as $j) {
            yield [
                'external_id' => isset($j['id']) ? $board.':'.$j['id'] : null, 'title' => $j['title'] ?? null,
                'company_name' => $source->configuration['company'] ?? $board, 'location' => $j['location']['name'] ?? null,
                'description' => html_entity_decode($j['content'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'application_url' => $j['absolute_url'] ?? null,
                // updated_at is not a publication date.
            ];
        }
    }
}
