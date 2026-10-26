<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleOpsAbilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $cronToken;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Administrador'],
        );
        $this->admin = User::factory()->create([
            'email' => 'admin-ops@example.com',
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $this->admin->role()->associate($adminRole);
        $this->admin->save();
        $this->cronToken = (string) config('app.cron_secret_token');
    }

    /** @test */
    public function schedule_status_allows_star_ability_token(): void
    {
        $token = $this->admin->createToken('ops-star', ['*'])->plainTextToken;

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Cron-Token' => $this->cronToken,
        ])->getJson('/api/schedule/status')
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function schedule_status_allows_ops_ability_token(): void
    {
        $token = $this->admin->createToken('ops-only', ['ops'])->plainTextToken;

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Cron-Token' => $this->cronToken,
        ])->getJson('/api/schedule/status')
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function schedule_status_denies_read_only_token(): void
    {
        $token = $this->admin->createToken('read-only', ['read'])->plainTextToken;

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Cron-Token' => $this->cronToken,
        ])->getJson('/api/schedule/status')
            ->assertForbidden();
    }
}
