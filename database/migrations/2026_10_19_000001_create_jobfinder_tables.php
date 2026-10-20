<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->boolean('is_admin')->default(false));
        Schema::create('job_sources', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('base_url');
            $t->string('integration_type')->default('api');
            $t->boolean('enabled')->default(false);
            $t->json('configuration')->nullable();
            $t->timestamp('last_checked_at')->nullable();
            $t->timestamp('last_synced_at')->nullable();
            $t->string('last_sync_status')->default('never');
            $t->text('last_error')->nullable();
            $t->timestamps();
        });
        Schema::create('jobs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('source_id')->constrained('job_sources')->cascadeOnDelete();
            $t->string('external_id', 191);
            $t->string('title');
            $t->string('company_name')->index();
            $t->string('location')->nullable();
            $t->string('country')->nullable()->index();
            $t->string('city')->nullable()->index();
            $t->string('job_type')->default('unknown')->index();
            $t->string('workplace_type')->default('unknown')->index();
            $t->longText('description');
            $t->text('requirements')->nullable();
            $t->decimal('salary_min', 14, 2)->nullable();
            $t->decimal('salary_max', 14, 2)->nullable();
            $t->string('salary_currency', 3)->nullable();
            $t->string('salary_period')->nullable();
            $t->string('salary_text')->nullable();
            $t->string('experience_level')->default('unknown')->index();
            $t->text('application_url');
            $t->text('company_url')->nullable();
            $t->timestamp('published_at')->nullable()->index();
            $t->dateTime('first_seen_at')->index();
            $t->dateTime('last_seen_at');
            $t->timestamp('expires_at')->nullable()->index();
            $t->string('content_hash', 64)->index();
            $t->boolean('is_active')->default(true);
            $t->boolean('is_demo')->default(false);
            $t->timestamps();
            $t->unique(['source_id', 'external_id']);
            $t->index(['is_active', 'published_at']);
        });
        Schema::create('saved_jobs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $t->text('notes')->nullable();
            $t->dateTime('saved_at');
            $t->string('application_status')->default('Saved')->index();
            $t->date('applied_at')->nullable();
            $t->dateTime('interview_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'job_id']);
        });
        Schema::create('search_preferences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->json('preferred_keywords')->nullable();
            $t->json('preferred_locations')->nullable();
            $t->boolean('remote_only')->default(false);
            $t->boolean('worldwide')->default(false);
            $t->decimal('minimum_salary', 14, 2)->nullable();
            $t->string('salary_currency', 3)->default('USD');
            $t->string('experience_level')->nullable();
            $t->json('preferred_job_types')->nullable();
            $t->json('weights')->nullable();
            $t->boolean('email_notifications_enabled')->default(false);
            $t->string('alert_frequency')->default('daily');
            $t->timestamp('alerts_started_at')->nullable();
            $t->timestamps();
        });
        Schema::create('sync_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('source_id')->constrained('job_sources')->cascadeOnDelete();
            $t->dateTime('started_at');
            $t->timestamp('finished_at')->nullable();
            $t->string('status')->index();
            $t->unsignedInteger('fetched')->default(0);
            $t->unsignedInteger('created')->default(0);
            $t->unsignedInteger('skipped')->default(0);
            $t->text('error')->nullable();
            $t->timestamps();
        });
        Schema::create('search_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->json('filters');
            $t->timestamps();
        });
        Schema::create('alert_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'job_id']);
        });
        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->json('value');
        });
    }

    public function down(): void
    {
        foreach (['settings', 'alert_deliveries', 'search_histories', 'sync_runs', 'search_preferences', 'saved_jobs', 'jobs', 'job_sources'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_admin'));
    }
};
