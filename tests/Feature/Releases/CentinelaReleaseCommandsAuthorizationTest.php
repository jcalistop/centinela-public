<?php

declare(strict_types=1);

namespace Tests\Feature\Releases;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use TCG\Voyager\Models\Role;
use Tests\TestCase;

class CentinelaReleaseCommandsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function sync_releases_command_fails_without_admin(): void
    {
        $role = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $user->role()->associate($role)->save();

        $this->actingAs($user);

        $this->artisan('centinela:github:sync-releases')
            ->assertFailed();
    }

    /** @test */
    public function notify_command_fails_without_admin(): void
    {
        $this->artisan('centinela:release:notify-control-gestion')
            ->assertFailed();
    }
}
