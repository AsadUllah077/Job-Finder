<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            Schema::table('jobs', fn (Blueprint $t) => $t->fullText(['title', 'description', 'company_name', 'location'], 'jobs_search_fulltext'));
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            Schema::table('jobs', fn (Blueprint $t) => $t->dropFullText('jobs_search_fulltext'));
        }
    }
};
