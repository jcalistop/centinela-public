<?php

namespace Tests\Unit\Support\Voyager;

use App\Models\User;
use App\Support\Voyager\VoyagerUserBladeFieldsHydration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VoyagerUserBladeFieldsHydrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_hydrate_sets_name_and_unidad_anexo_from_request(): void
    {
        $user = new User;

        $request = Request::create('/admin/users', 'POST', [
            'name' => ' Ana ',
            'unidad' => 'U1',
            'anexo' => '12',
        ]);

        VoyagerUserBladeFieldsHydration::hydrateFromPublishedBlade($user, $request);

        $this->assertSame('Ana', $user->name);
        $this->assertSame('U1', $user->unidad);
        $this->assertSame(12, $user->anexo);
    }

    public function test_hydrate_sets_password_using_model_cast(): void
    {
        $user = new User;

        $request = Request::create('/admin/users', 'POST', [
            'password' => 'secreto-muy-largo-88',
        ]);

        VoyagerUserBladeFieldsHydration::hydrateFromPublishedBlade($user, $request);

        $this->assertTrue(Hash::check('secreto-muy-largo-88', $user->password));
    }

    public function test_persist_second_save_when_name_corrected(): void
    {
        $user = User::factory()->create(['name' => 'Viejo']);

        $request = Request::create('/admin/users/'.$user->id, 'PUT', [
            'name' => 'Nuevo nombre',
        ]);

        VoyagerUserBladeFieldsHydration::persistIfOutOfSync($user->fresh(), $request);

        $this->assertSame('Nuevo nombre', $user->fresh()->name);
    }
}
