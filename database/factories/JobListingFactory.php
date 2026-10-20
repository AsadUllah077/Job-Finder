<?php

namespace Database\Factories;

use App\Models\JobListing;
use App\Models\JobSource;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobListingFactory extends Factory
{
    protected $model = JobListing::class;

    public function definition(): array
    {
        return ['source_id' => JobSource::firstOrCreate(['slug' => 'arbeitnow'], ['name' => 'Arbeitnow', 'base_url' => 'https://www.arbeitnow.com', 'enabled' => true])->id,
            'external_id' => fake()->uuid(), 'title' => '[DEMO] Senior Laravel Developer', 'company_name' => '[DEMO] Example Software',
            'location' => 'Dubai, UAE', 'country' => 'AE', 'city' => 'Dubai', 'job_type' => 'full_time', 'workplace_type' => 'remote',
            'description' => 'DEMO DATA — fictional listing for development only. Build Laravel REST APIs with PHP, MySQL and Vue.js.',
            'application_url' => 'https://example.com/demo-job', 'experience_level' => 'senior', 'content_hash' => hash('sha256', fake()->uuid()),
            'first_seen_at' => now(), 'last_seen_at' => now(), 'published_at' => now(), 'is_demo' => true, 'is_active' => true];
    }
}
