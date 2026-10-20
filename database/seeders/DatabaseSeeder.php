<?php

namespace Database\Seeders;

use App\Models\JobSource;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach ([
            ['Arbeitnow', 'arbeitnow', 'https://www.arbeitnow.com', true, ['max_pages' => 3]],
            ['Remotive', 'remotive', 'https://remotive.com', true, []],
            ['Remote OK', 'remoteok', 'https://remoteok.com', false, []],
            ['Greenhouse', 'greenhouse', 'https://boards-api.greenhouse.io', false, ['max_pages' => 3]],
            ['Lever', 'lever', 'https://api.lever.co', false, ['max_pages' => 3, 'region' => 'global']],
            ['Adzuna', 'adzuna', 'https://api.adzuna.com', false, ['country' => 'gb', 'currency' => 'GBP', 'max_pages' => 2]],
        ] as [$name,$slug,$url,$enabled,$config]) {
            JobSource::firstOrCreate(['slug' => $slug], ['name' => $name, 'base_url' => $url, 'enabled' => $enabled, 'configuration' => $config]);
        }
    }
}
