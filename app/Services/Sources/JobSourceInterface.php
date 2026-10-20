<?php

namespace App\Services\Sources;

use App\Models\JobSource;

interface JobSourceInterface
{
    /** @return iterable<array<string,mixed>> */
    public function fetch(JobSource $source): iterable;

    public function configured(JobSource $source): bool;

    public function minimumInterval(): int;
}
