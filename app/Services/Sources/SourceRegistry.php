<?php

namespace App\Services\Sources;

use App\Models\JobSource;

class SourceRegistry
{
    public const ADAPTERS = ['arbeitnow' => ArbeitnowSource::class, 'remotive' => RemotiveSource::class, 'remoteok' => RemoteOkSource::class, 'greenhouse' => GreenhouseSource::class, 'lever' => LeverSource::class, 'adzuna' => AdzunaSource::class];

    public function for(JobSource $s): JobSourceInterface
    {
        return app(self::ADAPTERS[$s->slug] ?? throw new SourceException('No approved adapter for this source.'));
    }

    public function status(JobSource $s): string
    {
        if (! $this->for($s)->configured($s)) {
            return 'Setup required';
        }
        if (! $s->enabled) {
            return 'Disabled';
        }

        return $s->last_sync_status === 'success' ? 'Connected' : ($s->last_sync_status === 'failed' ? 'Needs attention' : 'Ready · not verified');
    }
}
