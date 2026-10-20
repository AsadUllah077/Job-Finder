<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedJob extends Model
{
    public const STATUSES = ['Saved', 'Interested', 'Applied', 'Interview', 'Offer', 'Rejected', 'Not Interested'];

    protected $guarded = ['id', 'user_id'];

    protected function casts(): array
    {
        return ['saved_at' => 'datetime', 'applied_at' => 'date', 'interview_at' => 'datetime'];
    }

    public function job()
    {
        return $this->belongsTo(JobListing::class, 'job_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
