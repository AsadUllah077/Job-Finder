<?php

namespace App\Services\Sources;

use App\Models\JobSource;
use Illuminate\Support\Facades\Http;

abstract class ApiSource implements JobSourceInterface
{
    public function configured(JobSource $source): bool
    {
        return true;
    }

    public function minimumInterval(): int
    {
        return 3600;
    }

    protected function get(string $url, array $params = []): array
    {
        // URLs are constructed exclusively by adapters. Never follow redirects to arbitrary hosts.
        try {
            $r = Http::acceptJson()->withHeaders(['User-Agent' => 'JobFinder/1.0 (public job feed reader)'])
                ->connectTimeout(10)->timeout(config('jobfinder.http_timeout'))->withoutRedirecting()->get($url, $params);
        } catch (\Throwable $e) {
            throw new SourceException('Source connection failed; check connectivity or try later.');
        }
        if ($r->status() === 429) {
            $header = $r->header('Retry-After');
            $delay = is_numeric($header) ? (int) $header : max(60, (int) strtotime($header) - time());
            throw new SourceException('Source rate limit reached.', min(86400, max(60, $delay)));
        }
        if (! $r->successful()) {
            throw new SourceException('Source returned HTTP '.$r->status().'.');
        }
        $data = $r->json();
        if (! is_array($data)) {
            throw new SourceException('Source returned an invalid JSON document.');
        }

        return $data;
    }

    protected function rows(array $data, string $key): array
    {
        if (! isset($data[$key]) || ! is_array($data[$key])) {
            throw new SourceException('Source response schema changed.');
        }

        return $data[$key];
    }

    protected function pages(JobSource $s): int
    {
        return max(1, min(10, (int) ($s->configuration['max_pages'] ?? 3)));
    }

    protected function board(JobSource $s): string
    {
        $board = $s->configuration['board'] ?? '';
        if (! preg_match('/^[a-zA-Z0-9_-]{1,100}$/D', $board)) {
            throw new SourceException('Set a valid public board slug before syncing.');
        }

        return $board;
    }
}
