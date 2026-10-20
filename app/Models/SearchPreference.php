<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchPreference extends Model
{
    protected $guarded = ['id', 'user_id'];

    protected function casts(): array
    {
        return ['preferred_keywords' => 'array', 'preferred_locations' => 'array', 'preferred_job_types' => 'array', 'weights' => 'array', 'remote_only' => 'boolean', 'worldwide' => 'boolean', 'email_notifications_enabled' => 'boolean', 'alerts_started_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
