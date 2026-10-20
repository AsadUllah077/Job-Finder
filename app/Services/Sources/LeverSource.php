<?php

namespace App\Services\Sources;

use App\Models\JobSource;

class LeverSource extends GreenhouseSource
{
    public function fetch(JobSource $source): iterable
    {
        $board = $this->board($source);
        $host = ($source->configuration['region'] ?? 'global') === 'eu' ? 'api.eu.lever.co' : 'api.lever.co';
        for ($page = 0; $page < $this->pages($source); $page++) {
            $data = $this->get("https://{$host}/v0/postings/{$board}", ['mode' => 'json', 'skip' => $page * 100, 'limit' => 100]);
            if (! array_is_list($data)) {
                throw new SourceException('Source response schema changed.');
            }
            foreach ($data as $j) {
                yield [
                    'external_id' => isset($j['id']) ? $board.':'.$j['id'] : null, 'title' => $j['text'] ?? null, 'company_name' => $source->configuration['company'] ?? $board,
                    'location' => $j['categories']['location'] ?? null, 'country' => $j['country'] ?? null,
                    'description' => ($j['description'] ?? '').($j['additional'] ?? ''),
                    'requirements' => collect($j['lists'] ?? [])->map(fn ($l) => ($l['text'] ?? '').' '.strip_tags($l['content'] ?? ''))->implode("\n"),
                    'job_type' => $j['categories']['commitment'] ?? 'unknown', 'workplace_type' => $j['workplaceType'] ?? 'unknown',
                    'application_url' => $j['hostedUrl'] ?? null, 'salary_min' => $j['salaryRange']['min'] ?? null, 'salary_max' => $j['salaryRange']['max'] ?? null,
                    'salary_currency' => $j['salaryRange']['currency'] ?? null, 'salary_period' => $j['salaryRange']['interval'] ?? null,
                ];
            }
            if (count($data) < 100) {
                break;
            }
        }
    }
}
