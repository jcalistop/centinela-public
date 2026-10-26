<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected string $cronToken;

    protected function setUp(): void
    {
        parent::setUp();
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Administrador'],
        );
        $this->admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('secret'),
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $this->admin->role()->associate($adminRole);
        $this->admin->save();
        $this->cronToken = (string) config('app.cron_secret_token');
    }

    /** @test */
    public function it_runs_schedule_run_endpoint()
    {
        $token = $this->admin->createToken('test')->plainTextToken;
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Cron-Token' => $this->cronToken,
        ])->postJson('/api/schedule/run');
        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    /** @test */
    public function it_checks_health()
    {
        $response = $this->getJson('/api/health');
        $response->assertStatus(200)->assertJson(['status' => 'ok']);
    }

    /** @test */
    public function it_returns_status()
    {
        $token = $this->admin->createToken('test')->plainTextToken;
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Cron-Token' => $this->cronToken,
        ])->getJson('/api/schedule/status');
        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    /** @test */
    public function it_processes_queue()
    {
        $token = $this->admin->createToken('test')->plainTextToken;
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Cron-Token' => $this->cronToken,
        ])->postJson('/api/schedule/queue/process');
        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    /** @test */
    public function it_rejects_queue_process_for_non_default_queue(): void
    {
        $token = $this->admin->createToken('test')->plainTextToken;
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Cron-Token' => $this->cronToken,
        ])->postJson('/api/schedule/queue/process', ['queue' => 'releases']);

        $response->assertStatus(422)->assertJson(['success' => false]);
    }

    /** @test */
    public function it_rejects_concurrent_request_when_lock_is_held()
    {
        // Simula un lock ya adquirido para el endpoint /api/schedule/run
        $lockKey = 'api_schedule_run';
        $lockFile = storage_path("temp/lock_{$lockKey}.lock");

        $dir = dirname($lockFile);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fp = fopen($lockFile, 'c');
        flock($fp, LOCK_EX | LOCK_NB);

        try {
            $token = $this->admin->createToken('test')->plainTextToken;
            $response = $this->withHeaders([
                'Authorization' => 'Bearer '.$token,
                'X-Cron-Token' => $this->cronToken,
            ])->postJson('/api/schedule/run');

            $response->assertStatus(429)->assertJson(['success' => false]);
            $this->assertStringContainsString('ejecución', $response->json('message'));
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /** @test */
    public function it_allows_request_after_lock_is_released()
    {
        $lockKey = 'api_schedule_status';
        $lockFile = storage_path("temp/lock_{$lockKey}.lock");

        $dir = dirname($lockFile);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Adquirir y liberar el lock antes de la solicitud
        $fp = fopen($lockFile, 'c');
        flock($fp, LOCK_EX | LOCK_NB);
        flock($fp, LOCK_UN);
        fclose($fp);

        $token = $this->admin->createToken('test')->plainTextToken;
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Cron-Token' => $this->cronToken,
        ])->getJson('/api/schedule/status');

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    /** @test */
    public function it_rejects_request_without_cron_token()
    {
        $token = $this->admin->createToken('test')->plainTextToken;
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/schedule/status');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_rejects_request_without_auth_token()
    {
        $response = $this->withHeaders([
            'X-Cron-Token' => $this->cronToken,
        ])->getJson('/api/schedule/status');

        $response->assertStatus(401);
    }

    /** @test */
    public function schedule_backup_requires_authentication(): void
    {
        $this->postJson('/api/schedule/backup/database')->assertUnauthorized();
    }

    /** @test */
    public function schedule_run_requires_authentication(): void
    {
        $this->postJson('/api/schedule/run')->assertUnauthorized();
    }
}
