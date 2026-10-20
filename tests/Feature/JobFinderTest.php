<?php

namespace Tests\Feature;

use App\Jobs\SendJobAlerts;
use App\Jobs\SyncSource;
use App\Models\JobListing;
use App\Models\JobSource;
use App\Models\SearchPreference;
use App\Models\User;
use App\Notifications\JobDigest;
use App\Services\JobNormalizer;
use App\Services\JobSearch;
use App\Services\RelevanceScorer;
use App\Services\Sources\SourceRegistry;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class JobFinderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
    }

    private function source(string $slug = 'arbeitnow', array $config = []): JobSource
    {
        return JobSource::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug), 'base_url' => 'https://example.com', 'enabled' => true, 'configuration' => $config]);
    }

    private function sync(JobSource $s): void
    {
        (new SyncSource($s->id))->handle(app(SourceRegistry::class), app(JobNormalizer::class));
    }

    private function row(array $overrides = []): array
    {
        return array_merge(['slug' => 'laravel-123', 'title' => 'Senior Laravel Developer', 'company_name' => 'Example Co', 'location' => 'Dubai, UAE', 'remote' => true, 'description' => '<p>PHP Laravel MySQL APIs</p>', 'url' => 'https://www.arbeitnow.com/jobs/laravel-123', 'created_at' => now()->timestamp], $overrides);
    }

    public function test_registration_login_logout_and_roles_are_secure(): void
    {
        $this->post('/register', ['name' => 'A Developer', 'email' => 'dev@example.com', 'password' => 'LongPassword123', 'password_confirmation' => 'LongPassword123', 'is_admin' => true])->assertRedirect('/dashboard');
        $user = User::first();
        $this->assertFalse($user->is_admin);
        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertOk();
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'LongPassword123'])->assertRedirect('/dashboard');
    }

    public function test_weak_passwords_are_rejected(): void
    {
        $this->post('/register', ['name' => 'Dev', 'email' => 'dev@example.com', 'password' => 'password', 'password_confirmation' => 'password'])->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_password_reset_changes_credentials(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $this->post('/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'NewPassword1234', 'password_confirmation' => 'NewPassword1234'])->assertRedirect('/login');
        $this->post('/login', ['email' => $user->email, 'password' => 'NewPassword1234'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_sensitive_routes_require_login_and_admin(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/admin')->assertRedirect('/login');
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin')->assertForbidden();
        $s = $this->source();
        $this->post('/admin/sources/'.$s->id.'/sync')->assertForbidden();
        $user->forceFill(['is_admin' => true])->save();
        $this->get('/admin')->assertOk();
    }

    public function test_public_listings_and_details_do_not_require_registration(): void
    {
        $j = JobListing::factory()->create();
        $this->get('/jobs')->assertOk()->assertSee($j->title);
        $this->get('/jobs/'.$j->id)->assertOk()->assertSee('DEMO DATA')->assertSee('noopener noreferrer', false);
    }

    public function test_sync_updates_existing_jobs_without_duplicates(): void
    {
        $s = $this->source();
        Http::fake(['*' => Http::sequence()->push(['data' => [$this->row()], 'links' => ['next' => null]])->push(['data' => [$this->row(['title' => 'Senior PHP Laravel Engineer'])]])]);
        $this->sync($s);
        $this->assertDatabaseCount('jobs', 1);
        $j = JobListing::first();
        $first = $j->first_seen_at->toDateTimeString();
        $this->travel(2)->hours();
        $this->sync($s);
        $this->assertDatabaseCount('jobs', 1);
        $j->refresh();
        $this->assertSame('Senior PHP Laravel Engineer', $j->title);
        $this->assertSame($first, $j->first_seen_at->toDateTimeString());
        $this->assertSame('AE', $j->country);
        $this->assertSame('Dubai', $j->city);
        $this->assertSame('success', $s->fresh()->last_sync_status);
    }

    public function test_external_ids_are_unique_within_each_source_only(): void
    {
        $one = $this->source();
        $two = $this->source('remotive');
        JobListing::factory()->create(['source_id' => $one->id, 'external_id' => 'same']);
        JobListing::factory()->create(['source_id' => $two->id, 'external_id' => 'same']);
        $this->assertDatabaseCount('jobs', 2);
        $this->expectException(UniqueConstraintViolationException::class);
        JobListing::factory()->create(['source_id' => $one->id, 'external_id' => 'same']);
    }

    public function test_sync_failures_preserve_jobs_and_redact_provider_errors(): void
    {
        $s = $this->source();
        $job = JobListing::factory()->create(['source_id' => $s->id]);
        Http::fake(['*' => Http::response('secret_key=private', 503)]);
        $this->sync($s);
        $this->assertTrue($job->fresh()->is_active);
        $this->assertSame('failed', $s->fresh()->last_sync_status);
        $this->assertStringNotContainsString('private', $s->fresh()->last_error);
        $this->assertDatabaseHas('sync_runs', ['status' => 'failed']);
    }

    public function test_sync_cooldowns_and_lock_prevent_repeated_requests(): void
    {
        $s = $this->source();
        Http::fake(['*' => Http::response(['data' => []])]);
        $lock = Cache::lock('sync:'.$s->id, 900);
        $lock->get();
        $this->sync($s);
        Http::assertNothingSent();
        $lock->release();
        $this->sync($s);
        $this->sync($s);
        Http::assertSentCount(1);
    }

    public function test_rate_limit_is_recorded_and_retry_after_is_respected(): void
    {
        $s = $this->source();
        Http::fake(['*' => Http::response([], 429, ['Retry-After' => '7200'])]);
        $this->sync($s);
        $this->assertTrue(Cache::has('cooldown:'.$s->id));
        $this->assertSame('Source rate limit reached.', $s->fresh()->last_error);
        $this->travel(65)->minutes();
        $this->sync($s);
        Http::assertSentCount(1);
    }

    public function test_invalid_schema_does_not_claim_success(): void
    {
        $s = $this->source();
        Http::fake(['*' => Http::response(['unexpected' => 'value'])]);
        $this->sync($s);
        $this->assertSame('failed', $s->fresh()->last_sync_status);
    }

    public function test_pagination_uses_fixed_hosts_not_untrusted_next_urls(): void
    {
        $s = $this->source('arbeitnow', ['max_pages' => 2]);
        Http::fake(['*' => Http::sequence()->push(['data' => [$this->row()], 'links' => ['next' => 'http://127.0.0.1/private']])->push(['data' => [$this->row(['slug' => 'second'])], 'links' => ['next' => null]])]);
        $this->sync($s);
        Http::assertSentCount(2);
        $this->assertDatabaseCount('jobs', 2);
        Http::assertSent(fn ($r) => $r->url() === 'https://www.arbeitnow.com/api/job-board-api?page=2');
    }

    public function test_adapters_map_official_payload_shapes(): void
    {
        $samples = [
            'remotive' => [['jobs' => [['id' => 1, 'title' => 'Laravel Developer', 'company_name' => 'Co', 'url' => 'https://remotive.com/jobs/1', 'publication_date' => '2026-01-01', 'candidate_required_location' => 'Worldwide', 'description' => 'PHP']]], []],
            'remoteok' => [[['legal' => 'terms'], ['id' => 2, 'position' => 'PHP Developer', 'company' => 'Co', 'url' => 'https://remoteok.com/jobs/2', 'description' => 'PHP', 'salary_min' => 50000, 'salary_max' => 70000]], []],
            'greenhouse' => [['jobs' => [['id' => 3, 'title' => 'Laravel Developer', 'absolute_url' => 'https://boards.greenhouse.io/co/3', 'content' => '&lt;p&gt;PHP&lt;/p&gt;', 'location' => ['name' => 'Dubai']]]], ['board' => 'co']],
            'lever' => [[['id' => '4', 'text' => 'Laravel Developer', 'hostedUrl' => 'https://jobs.lever.co/co/4', 'description' => 'PHP', 'categories' => ['location' => 'Lahore', 'commitment' => 'Full-time'], 'country' => 'PK', 'workplaceType' => 'hybrid', 'salaryRange' => ['currency' => 'USD', 'interval' => 'year', 'min' => 50000, 'max' => 70000]]], ['board' => 'co']],
            'adzuna' => [['results' => [['id' => 5, 'title' => 'PHP Developer', 'company' => ['display_name' => 'Co'], 'location' => ['display_name' => 'London'], 'redirect_url' => 'https://www.adzuna.co.uk/jobs/5', 'description' => 'Laravel', 'salary_is_predicted' => 1, 'salary_min' => 80000]]], ['country' => 'gb']],
        ];
        config(['jobfinder.adzuna_id' => 'mock-id', 'jobfinder.adzuna_key' => 'mock-secret']);
        foreach ($samples as $slug => [$payload,$configuration]) {
            Http::swap(new Factory);
            Http::preventStrayRequests();
            Http::fake(['*' => Http::response($payload)]);
            $s = $this->source($slug, $configuration);
            $rows = iterator_to_array(app(SourceRegistry::class)->for($s)->fetch($s));
            $this->assertCount($slug === 'adzuna' ? 2 : 1, $rows, $slug);
            $normalized = app(JobNormalizer::class)->normalize($rows[0]);
            $this->assertNotNull($normalized, $slug);
            $this->assertStringContainsString('Developer', $normalized['title']);
            if ($slug === 'greenhouse') {
                $this->assertNull($normalized['published_at']);
            }
            if ($slug === 'adzuna') {
                $this->assertNull($normalized['salary_min']);
            }
        }
    }

    public function test_unconfigured_credentials_and_board_slugs_do_not_fetch(): void
    {
        $s = $this->source('adzuna');
        config(['jobfinder.adzuna_id' => null]);
        $this->sync($s);
        Http::assertNothingSent();
        $this->assertSame('failed', $s->fresh()->last_sync_status);
        $s = $this->source('lever', ['board' => '../../127.0.0.1']);
        $this->assertFalse(app(SourceRegistry::class)->for($s)->configured($s));
    }

    public function test_normalization_rejects_dangerous_links_and_removes_html(): void
    {
        $n = app(JobNormalizer::class);
        $raw = ['external_id' => '1', 'title' => 'Laravel', 'company_name' => 'Co', 'application_url' => 'javascript:alert(1)', 'description' => ''];
        $this->assertNull($n->normalize($raw));
        $raw['application_url'] = 'http://127.0.0.1/private';
        $this->assertNull($n->normalize($raw));
        $raw['application_url'] = 'https://example.com/job';
        $raw['description'] = '<script>alert(1)</script><p onmouseover="bad()">Safe &amp; sound</p><img src=x onerror=bad()>';
        $data = $n->normalize($raw);
        $this->assertSame('Safe & sound', $data['description']);
        $this->assertNull($data['published_at']);
    }

    public function test_search_filters_and_natural_language(): void
    {
        JobListing::factory()->create(['title' => 'Senior Laravel Developer', 'salary_min' => 70000, 'salary_max' => 90000, 'salary_currency' => 'USD', 'salary_period' => 'year']);
        JobListing::factory()->create(['title' => 'Junior PHP Engineer', 'location' => 'Berlin', 'country' => 'DE', 'city' => 'Berlin', 'workplace_type' => 'onsite', 'experience_level' => 'junior']);
        $f = ['q' => 'Laravel developer in Dubai', 'workplace' => 'remote', 'country' => 'AE', 'experience' => 'senior', 'currency' => 'USD', 'salary_min' => 50000, 'period' => 'year'];
        $jobs = app(JobSearch::class)->results($f, null);
        $this->assertSame(1, $jobs->total());
        $this->get('/jobs?'.http_build_query($f))->assertOk()->assertSee('Senior Laravel Developer')->assertDontSee('Junior PHP Engineer');
        $this->get('/jobs?sort=invalid')->assertSessionHasErrors('sort');
    }

    public function test_search_history_and_saved_filter_are_user_specific(): void
    {
        $u = User::factory()->create();
        $job = JobListing::factory()->create();
        $this->actingAs($u)->post('/jobs/'.$job->id.'/save');
        $this->get('/jobs?q=Laravel&saved=yes')->assertOk();
        $this->assertDatabaseCount('search_histories', 1);
        $this->assertSame(1, app(JobSearch::class)->results(['saved' => 'yes'], $u)->total());
        $this->assertSame(0, app(JobSearch::class)->results(['saved' => 'no'], $u)->total());
        $this->delete('/search-history')->assertRedirect();
        $this->assertDatabaseCount('search_histories', 0);
    }

    public function test_saved_jobs_are_idempotent_and_notes_are_private(): void
    {
        $one = User::factory()->create();
        $two = User::factory()->create();
        $job = JobListing::factory()->create();
        $this->actingAs($one)->post('/jobs/'.$job->id.'/save')->assertRedirect();
        $this->post('/jobs/'.$job->id.'/save');
        $this->assertDatabaseCount('saved_jobs', 1);
        $saved = $one->savedJobs()->first();
        $this->patch('/saved-jobs/'.$saved->id, ['notes' => 'Private interview notes', 'application_status' => 'Interview', 'applied_at' => '2026-10-01', 'interview_at' => '2026-10-20 12:00'])->assertRedirect();
        $this->assertSame('Interview', $saved->fresh()->application_status);
        $this->actingAs($two)->patch('/saved-jobs/'.$saved->id, ['application_status' => 'Rejected'])->assertForbidden();
        $this->delete('/saved-jobs/'.$saved->id)->assertForbidden();
        $this->get('/saved-jobs')->assertDontSee('Private interview notes');
        $this->get('/jobs/'.$job->id)->assertDontSee('Private interview notes');
        $this->actingAs($one)->patch('/saved-jobs/'.$saved->id, ['application_status' => 'Invalid'])->assertSessionHasErrors('application_status');
    }

    public function test_csv_is_private_and_formula_safe(): void
    {
        $u = User::factory()->create();
        $j = JobListing::factory()->create(['title' => '=HYPERLINK("evil")']);
        $u->savedJobs()->create(['job_id' => $j->id, 'saved_at' => now(), 'notes' => '@malicious']);
        $this->actingAs($u);
        $response = $this->get('/saved-jobs/export');
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'@malicious", $csv);
        $this->actingAs(User::factory()->create());
        $this->assertStringNotContainsString('HYPERLINK', $this->get('/saved-jobs/export')->streamedContent());
    }

    public function test_relevance_rewards_preferences_and_penalizes_internships(): void
    {
        $j = JobListing::factory()->make();
        $p = new SearchPreference(['preferred_keywords' => ['Laravel', 'PHP', 'MySQL'], 'preferred_locations' => ['Dubai'], 'remote_only' => true, 'experience_level' => 'senior']);
        $scorer = app(RelevanceScorer::class);
        $score = $scorer->score($j, $p);
        $j->experience_level = 'internship';
        $j->workplace_type = 'onsite';
        $j->location = 'Berlin';
        $j->country = 'DE';
        $this->assertGreaterThan($scorer->score($j, $p), $score);
        $p->weights = ['keywords' => 100, 'location' => 0, 'salary' => 0, 'remote' => 0];
        $j->experience_level = 'senior';
        $this->assertSame(100, $scorer->score($j, $p));
    }

    public function test_alerts_only_send_new_matches_and_prevent_repeat_notifications(): void
    {
        config(['jobfinder.mail_enabled' => true, 'mail.default' => 'smtp']);
        Notification::fake();
        $u = User::factory()->create();
        $u->preference()->create(['preferred_keywords' => ['Laravel'], 'worldwide' => true, 'remote_only' => true, 'email_notifications_enabled' => true, 'alert_frequency' => 'daily', 'alerts_started_at' => now()->subHour()]);
        $j = JobListing::factory()->create(['is_demo' => false]);
        JobListing::factory()->create(['is_demo' => false, 'first_seen_at' => now()->subDays(3)]);
        JobListing::factory()->create(['is_demo' => false, 'workplace_type' => 'onsite']);
        $job = new SendJobAlerts('daily');
        $job->handle(app(RelevanceScorer::class));
        $job->handle(app(RelevanceScorer::class));
        Notification::assertSentToTimes($u, JobDigest::class, 1);
        $this->assertDatabaseCount('alert_deliveries', 1);
        Notification::assertSentTo($u, JobDigest::class, fn ($n) => count($n->jobs) === 1 && str_contains($n->jobs[0]['url'], '/jobs/'.$j->id));
    }

    public function test_unconfigured_mail_and_disabled_alerts_never_claim_delivery(): void
    {
        Notification::fake();
        $u = User::factory()->create();
        $u->preference()->create(['email_notifications_enabled' => true, 'alert_frequency' => 'daily', 'worldwide' => true]);
        JobListing::factory()->create(['is_demo' => false]);
        config(['jobfinder.mail_enabled' => false]);
        (new SendJobAlerts)->handle(app(RelevanceScorer::class));
        Notification::assertNothingSent();
        $this->assertDatabaseCount('alert_deliveries', 0);
    }

    public function test_unsubscribe_links_are_signed_and_require_confirmation(): void
    {
        $u = User::factory()->create();
        $u->preference()->create(['email_notifications_enabled' => true]);
        $this->get('/alerts/unsubscribe/'.$u->id)->assertForbidden();
        $url = URL::signedRoute('alerts.unsubscribe', ['user' => $u->id]);
        $this->get($url)->assertOk();
        $this->assertTrue($u->preference->email_notifications_enabled);
        $this->post($url)->assertOk();
        $this->assertFalse($u->preference->fresh()->email_notifications_enabled);
    }

    public function test_preferences_validate_and_admin_sync_queues_work(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->put('/preferences', ['keywords' => 'Laravel', 'salary_currency' => 'BADCODE'])->assertSessionHasErrors('salary_currency');
        $u->forceFill(['is_admin' => true])->save();
        $s = $this->source();
        Queue::fake();
        $this->post('/admin/sources/'.$s->id.'/sync')->assertRedirect();
        Queue::assertPushed(SyncSource::class);
        $this->put('/admin/sources/'.$s->id, ['enabled' => 1, 'board' => 'https://127.0.0.1', 'max_pages' => 2])->assertSessionHasErrors('board');
    }

    public function test_expired_jobs_are_excluded_without_removing_stale_listings(): void
    {
        JobListing::factory()->create(['expires_at' => now()->subDay()]);
        JobListing::factory()->create(['last_seen_at' => now()->subMonths(2)]);
        $this->assertSame(1, JobListing::active()->count());
    }

    public function test_all_personal_screens_render_and_preferences_persist(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u);
        $this->get('/preferences')->assertOk()->assertSee('A search that fits you.');
        $data = ['keywords' => 'Laravel, PHP, MySQL', 'locations' => 'AE, Dubai, PK', 'remote_only' => 1, 'worldwide' => 0, 'minimum_salary' => 50000, 'salary_currency' => 'USD', 'experience_level' => 'senior', 'preferred_job_types' => ['full_time'], 'email_notifications_enabled' => 1, 'alert_frequency' => 'daily', 'weights' => ['keywords' => 50, 'location' => 20, 'salary' => 10, 'remote' => 20]];
        $this->put('/preferences', $data)->assertSessionHasNoErrors()->assertRedirect();
        $u->refresh();
        $this->assertSame(['Laravel', 'PHP', 'MySQL'], $u->preference->preferred_keywords);
        $this->assertNotNull($u->preference->alerts_started_at);
        $j = JobListing::factory()->create();
        $this->post('/jobs/'.$j->id.'/save');
        $this->get('/saved-jobs')->assertOk()->assertSee($j->title);
        $this->get('/jobs/'.$j->id)->assertOk()->assertSee('Private notes');
        $saved = $u->savedJobs()->first();
        $this->patch('/saved-jobs/'.$saved->id, ['application_status' => 'Interview', 'interview_at' => '2026-10-20T12:00'])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-20 07:00', $saved->fresh()->interview_at->format('Y-m-d H:i'));
        $this->get('/saved-jobs')->assertOk()->assertSee('2026-10-20T12:00', false);
    }

    public function test_alert_transport_failures_do_not_record_success(): void
    {
        config(['jobfinder.mail_enabled' => true, 'mail.default' => 'smtp']);
        $u = User::factory()->create();
        $u->preference()->create(['worldwide' => true, 'email_notifications_enabled' => true, 'alert_frequency' => 'daily', 'alerts_started_at' => now()->subHour()]);
        JobListing::factory()->create(['is_demo' => false]);
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('Simulated mail failure'));
        try {
            (new SendJobAlerts)->handle(app(RelevanceScorer::class));
            $this->fail('Mail failure should propagate for queue retry.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated mail failure', $e->getMessage());
        }
        $this->assertDatabaseCount('alert_deliveries', 0);
        $this->assertFalse(Cache::has('alerts:user:'.$u->id));
    }
}
