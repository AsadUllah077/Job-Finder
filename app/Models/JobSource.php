<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobSource extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['configuration' => 'array', 'enabled' => 'boolean', 'last_checked_at' => 'datetime', 'last_synced_at' => 'datetime'];
    }

    public function jobs()
    {
        return $this->hasMany(JobListing::class, 'source_id');
    }

    public function runs()
    {
        return $this->hasMany(SyncRun::class, 'source_id');
    }
}
