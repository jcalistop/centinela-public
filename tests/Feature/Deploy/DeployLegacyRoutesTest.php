<?php

namespace Tests\Feature\Deploy;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use TCG\Voyager\Models\Role;
use Tests\TestCase;

class DeployLegacyRoutesTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $user = User::factory()->create(['role_id' => $adminRole->id]);
        $user->role()->associate($adminRole)->save();

        return $user;
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function legacyDeployRoutesProvider(): array
    {
        return [
            'deploy index' => ['GET', '/admin/system/deploy'],
            'ops db backup' => ['POST', '/admin/system/ops/db-backup'],
            'ops migrate' => ['POST', '/admin/system/ops/migrate'],
            'ops migrate rollback' => ['POST', '/admin/system/ops/migrate-rollback'],
            'ops git pull' => ['POST', '/admin/system/ops/git-pull'],
        ];
    }

    /**
     * @test
     *
     * @dataProvider legacyDeployRoutesProvider
     */
    public function legacy_deploy_routes_return_not_found_for_guest(string $method, string $uri): void
    {
        $response = $this->json($method, $uri);

        $response->assertNotFound();
    }

    /**
     * @test
     *
     * @dataProvider legacyDeployRoutesProvider
     */
    public function legacy_deploy_routes_return_not_found_for_admin(string $method, string $uri): void
    {
        $response = $this->actingAs($this->adminUser())->json($method, $uri);

        $response->assertNotFound();
    }
}
