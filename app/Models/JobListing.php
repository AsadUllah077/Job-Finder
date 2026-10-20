<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobListing extends Model
{
    use HasFactory;

    protected $table = 'jobs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'first_seen_at' => 'datetime', 'last_seen_at' => 'datetime', 'expires_at' => 'datetime', 'is_active' => 'boolean', 'is_demo' => 'boolean'];
    }

    public function source()
    {
        return $this->belongsTo(JobSource::class, 'source_id');
    }

    public function saves()
    {
        return $this->hasMany(SavedJob::class, 'job_id');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
