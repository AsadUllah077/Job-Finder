<?php

namespace Database\Seeders;

use App\Models\JobListing;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        JobListing::factory()->count(12)->sequence(['location' => 'Dubai, UAE', 'country' => 'AE'], ['location' => 'Lahore, Pakistan', 'country' => 'PK'], ['location' => 'Worldwide', 'country' => null])->create();
    }
}
