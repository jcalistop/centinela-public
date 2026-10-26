<?php

namespace Tests\Feature\Filament;

use App\Livewire\Filament\PanelContextBar;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Support\Voyager\YearDropdownCatalog;
use App\Support\YearContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class PanelContextBarTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        YearDropdownCatalog::invalidate();

        $ssId = (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-CTX',
            'nombre' => 'SS Contexto Filament',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $this->user = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => $ssId,
        ]);
        $this->user->role()->associate($role)->save();
    }

    public function test_renders_year_options_and_ss_label(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        $this->actingAs($this->user);
        app(YearContext::class)->setCurrent($year);

        Livewire::test(PanelContextBar::class)
            ->assertSet('yearId', $year->id)
            ->assertSee('2026')
            ->assertSee('SS Contexto Filament');
    }

    public function test_changing_year_updates_year_context_session(): void
    {
        $yearA = Year::unguarded(fn () => Year::create(['ano' => 2025, 'default' => 0]));
        $yearB = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        $this->actingAs($this->user);
        app(YearContext::class)->setCurrent($yearA);

        Livewire::test(PanelContextBar::class)
            ->set('yearId', $yearB->id)
            ->assertRedirect();

        $this->assertSame($yearB->id, app(YearContext::class)->current()?->id);
    }
}
