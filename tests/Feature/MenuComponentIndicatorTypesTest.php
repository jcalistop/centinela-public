<?php

namespace Tests\Feature;

use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\Role;
use App\Models\ServiceModuleToggle;
use App\Models\User;
use App\Models\Year;
use App\View\Components\MenuComponent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\View\View;
use TCG\Voyager\Models\DataType;
use Tests\TestCase;

class MenuComponentIndicatorTypesTest extends TestCase
{
    use RefreshDatabase;

    private function seedVoyagerDataTypeForIndicatorTypes(): void
    {
        DataType::unguarded(fn () => DataType::create([
            'name' => 'indicator_types',
            'slug' => 'instrumentos-evaluacion',
            'display_name_singular' => 'Tipo de indicador',
            'display_name_plural' => 'Tipos de indicador',
            'model_name' => IndicatorType::class,
            'generate_permissions' => 0,
            'server_side' => 0,
        ]));
    }

    /** @test */
    public function referente_menu_excludes_indicator_types_with_estado_false(): void
    {
        $this->seedVoyagerDataTypeForIndicatorTypes();

        Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);

        $suffix = Str::lower(Str::random(8));
        $inactiveKey = "zzz-menu-inactive-{$suffix}";

        IndicatorType::unguarded(fn () => IndicatorType::create([
            'clave_indicador' => $inactiveKey,
            'nombre_indicador' => "Inactivo menú test {$suffix}",
            'descripcion_indicador' => null,
            'icon_class' => 'voyager-book',
            'route' => "metas-test-{$suffix}",
            'view' => null,
            'estado' => false,
            'orden' => 999,
        ]));

        $user = User::factory()->create(['role_id' => $referenteRole->id, 'cen_hie_dependency_id' => 1]);
        $user->role()->associate($referenteRole)->save();

        $this->actingAs($user);

        $view = (new MenuComponent('indicator_types', 'clave_indicador'))->render();
        $this->assertInstanceOf(View::class, $view);
        $items = $view->getData()['dataTypeContent'];

        $this->assertFalse(
            $items->contains('clave_indicador', $inactiveKey),
            'Referente no debe ver instrumentos con estado inactivo en el menú.'
        );
    }

    /** @test */
    public function administrador_menu_excludes_indicator_types_with_estado_false(): void
    {
        $this->seedVoyagerDataTypeForIndicatorTypes();

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $user = User::factory()->create(['role_id' => $adminRole->id]);
        $user->role()->associate($adminRole)->save();

        $suffix = Str::lower(Str::random(8));
        $inactiveKey = "zzz-menu-inactive-admin-{$suffix}";

        IndicatorType::unguarded(fn () => IndicatorType::create([
            'clave_indicador' => $inactiveKey,
            'nombre_indicador' => "Inactivo menú admin test {$suffix}",
            'descripcion_indicador' => null,
            'icon_class' => 'voyager-book',
            'route' => "metas-test-admin-{$suffix}",
            'view' => null,
            'estado' => false,
            'orden' => 998,
        ]));

        $this->actingAs($user);

        $view = (new MenuComponent('indicator_types', 'clave_indicador'))->render();
        $this->assertInstanceOf(View::class, $view);
        $items = $view->getData()['dataTypeContent'];

        $this->assertFalse(
            $items->contains('clave_indicador', $inactiveKey),
            'Administrador no debe ver instrumentos con estado Off en el menú (capa 1).'
        );
    }

    /** @test */
    public function parametrizador_menu_excludes_indicator_types_with_estado_false(): void
    {
        $this->seedVoyagerDataTypeForIndicatorTypes();

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $paramRole = Role::firstOrCreate(['name' => 'parametrizador'], ['display_name' => 'Parametrizador']);
        $user = User::factory()->create(['role_id' => $paramRole->id, 'cen_hie_dependency_id' => 1]);
        $user->role()->associate($paramRole)->save();

        $suffix = Str::lower(Str::random(8));
        $inactiveKey = "zzz-menu-inactive-param-{$suffix}";

        IndicatorType::unguarded(fn () => IndicatorType::create([
            'clave_indicador' => $inactiveKey,
            'nombre_indicador' => "Inactivo menú param test {$suffix}",
            'descripcion_indicador' => null,
            'icon_class' => 'voyager-book',
            'route' => "metas-test-param-{$suffix}",
            'view' => null,
            'estado' => false,
            'orden' => 997,
        ]));

        $this->actingAs($user);

        $view = (new MenuComponent('indicator_types', 'clave_indicador'))->render();
        $this->assertInstanceOf(View::class, $view);
        $items = $view->getData()['dataTypeContent'];

        $this->assertFalse(
            $items->contains('clave_indicador', $inactiveKey),
            'Parametrizador no debe ver instrumentos con estado Off en el menú (capa 1).'
        );
    }

    /** @test */
    public function control_gestion_menu_includes_estado_true_even_when_habilitado_false(): void
    {
        $this->seedVoyagerDataTypeForIndicatorTypes();

        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $user = User::factory()->create(['role_id' => $cgRole->id, 'cen_hie_dependency_id' => 1]);
        $user->role()->associate($cgRole)->save();

        $suffix = Str::lower(Str::random(8));
        $key = "zzz-menu-cg-{$suffix}";

        $type = IndicatorType::unguarded(fn () => IndicatorType::create([
            'clave_indicador' => $key,
            'nombre_indicador' => "Instrumento CG {$suffix}",
            'descripcion_indicador' => null,
            'icon_class' => 'voyager-book',
            'route' => "metas-test-cg-{$suffix}",
            'view' => null,
            'estado' => true,
            'orden' => 996,
        ]));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $type->id,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'NO_INICIADO',
        ]));

        $this->actingAs($user)->withSession(['indicator_year' => $year]);

        $view = (new MenuComponent('indicator_types', 'clave_indicador'))->render();
        $this->assertInstanceOf(View::class, $view);
        $items = $view->getData()['dataTypeContent'];

        $this->assertTrue(
            $items->contains('clave_indicador', $key),
            'Control de gestión debe ver instrumentos activos aunque el instrumento no esté habilitado para su SS en el año.'
        );
    }

    /** @test */
    public function referente_menu_excludes_when_habilitado_false_for_session_year_and_dependency(): void
    {
        $this->seedVoyagerDataTypeForIndicatorTypes();

        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $user = User::factory()->create(['role_id' => $referenteRole->id, 'cen_hie_dependency_id' => 1]);
        $user->role()->associate($referenteRole)->save();

        $suffix = Str::lower(Str::random(8));
        $key = "zzz-menu-ref-off-{$suffix}";

        $type = IndicatorType::unguarded(fn () => IndicatorType::create([
            'clave_indicador' => $key,
            'nombre_indicador' => "Instrumento ref off {$suffix}",
            'descripcion_indicador' => null,
            'icon_class' => 'voyager-book',
            'route' => "metas-test-ref-off-{$suffix}",
            'view' => null,
            'estado' => true,
            'orden' => 995,
        ]));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $type->id,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'NO_INICIADO',
        ]));

        $this->actingAs($user)->withSession(['indicator_year' => $year]);

        $view = (new MenuComponent('indicator_types', 'clave_indicador'))->render();
        $this->assertInstanceOf(View::class, $view);
        $items = $view->getData()['dataTypeContent'];

        $this->assertFalse(
            $items->contains('clave_indicador', $key),
            'Referente no debe ver instrumento activo si habilitado=false para su SS y año de sesión.'
        );
    }

    /** @test */
    public function control_gestion_menu_excludes_when_service_module_toggle_disabled(): void
    {
        $this->seedVoyagerDataTypeForIndicatorTypes();

        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gesti&oacute;n']);
        $user = User::factory()->create(['role_id' => $cgRole->id, 'cen_hie_dependency_id' => 1]);
        $user->role()->associate($cgRole)->save();

        $suffix = Str::lower(Str::random(8));
        $key = "zzz-menu-cg-sm-{$suffix}";

        $type = IndicatorType::unguarded(fn () => IndicatorType::create([
            'clave_indicador' => $key,
            'nombre_indicador' => "Instrumento CG SM {$suffix}",
            'descripcion_indicador' => null,
            'icon_class' => 'voyager-book',
            'route' => "metas-test-sm-cg-{$suffix}",
            'view' => null,
            'estado' => true,
            'orden' => 993,
        ]));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $type->id,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => true,
        ]));

        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::create([
            'cen_hie_dependency_id' => 1,
            'module_key' => "metas-test-sm-cg-{$suffix}",
            'enabled' => false,
        ]));

        $this->actingAs($user)->withSession(['indicator_year' => $year]);

        $view = (new MenuComponent('indicator_types', 'clave_indicador'))->render();
        $this->assertInstanceOf(View::class, $view);
        $items = $view->getData()['dataTypeContent'];

        $this->assertFalse(
            $items->contains('clave_indicador', $key),
            'Control de gesti&oacute;n no debe ver instrumento cuando service_module_toggle est&aacute; deshabilitado.'
        );
    }

    /** @test */
    public function admin_menu_includes_even_when_service_module_toggle_disabled(): void
    {
        $this->seedVoyagerDataTypeForIndicatorTypes();

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $user = User::factory()->create(['role_id' => $adminRole->id]);
        $user->role()->associate($adminRole)->save();

        $suffix = Str::lower(Str::random(8));
        $key = "zzz-menu-admin-sm-{$suffix}";

        IndicatorType::unguarded(fn () => IndicatorType::create([
            'clave_indicador' => $key,
            'nombre_indicador' => "Instrumento admin SM {$suffix}",
            'descripcion_indicador' => null,
            'icon_class' => 'voyager-book',
            'route' => "metas-test-sm-admin-{$suffix}",
            'view' => null,
            'estado' => true,
            'orden' => 992,
        ]));

        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::create([
            'cen_hie_dependency_id' => 1,
            'module_key' => "metas-test-sm-admin-{$suffix}",
            'enabled' => false,
        ]));

        $this->actingAs($user);

        $view = (new MenuComponent('indicator_types', 'clave_indicador'))->render();
        $this->assertInstanceOf(View::class, $view);
        $items = $view->getData()['dataTypeContent'];

        $this->assertTrue(
            $items->contains('clave_indicador', $key),
            'Administrador debe ver instrumentos aunque service_module_toggle est&eacute; deshabilitado.'
        );
    }

    /** @test */
    public function referente_menu_includes_when_service_module_toggle_default_enabled(): void
    {
        $this->seedVoyagerDataTypeForIndicatorTypes();

        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $user = User::factory()->create(['role_id' => $referenteRole->id, 'cen_hie_dependency_id' => 1]);
        $user->role()->associate($referenteRole)->save();

        $suffix = Str::lower(Str::random(8));
        $key = "zzz-menu-ref-default-{$suffix}";

        $type = IndicatorType::unguarded(fn () => IndicatorType::create([
            'clave_indicador' => $key,
            'nombre_indicador' => "Instrumento ref default {$suffix}",
            'descripcion_indicador' => null,
            'icon_class' => 'voyager-book',
            'route' => "metas-test-sm-ref-default-{$suffix}",
            'view' => null,
            'estado' => true,
            'orden' => 991,
        ]));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $type->id,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => true,
        ]));

        // No se crea ServiceModuleToggle: debe asumirse habilitado por defecto
        $this->actingAs($user)->withSession(['indicator_year' => $year]);

        $view = (new MenuComponent('indicator_types', 'clave_indicador'))->render();
        $this->assertInstanceOf(View::class, $view);
        $items = $view->getData()['dataTypeContent'];

        $this->assertTrue(
            $items->contains('clave_indicador', $key),
            'Referente debe ver instrumento cuando no existe service_module_toggle (default habilitado).'
        );
    }

    /** @test */
    public function control_gestion_menu_excludes_comges_when_service_module_toggle_disabled(): void
    {
        $this->seedVoyagerDataTypeForIndicatorTypes();

        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gesti&oacute;n']);
        $user = User::factory()->create(['role_id' => $cgRole->id, 'cen_hie_dependency_id' => 1]);
        $user->role()->associate($cgRole)->save();

        $suffix = Str::lower(Str::random(8));
        $key = "zzz-menu-comges-sm-{$suffix}";

        $type = IndicatorType::unguarded(fn () => IndicatorType::create([
            'clave_indicador' => $key,
            'nombre_indicador' => "Compromisos de Gesti&oacute;n {$suffix}",
            'descripcion_indicador' => null,
            'icon_class' => 'voyager-book',
            // COMGES usa route = nombre de ruta del BREAD Voyager, no solo 'compromisos'
            'route' => 'voyager.compromisos.index',
            'view' => null,
            'estado' => true,
            'orden' => 990,
        ]));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $type->id,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => true,
        ]));

        // Se deshabilita el módulo usando la key real del módulo 'comges'
        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::create([
            'cen_hie_dependency_id' => 1,
            'module_key' => 'comges',
            'enabled' => false,
        ]));

        $this->actingAs($user)->withSession(['indicator_year' => $year]);

        $view = (new MenuComponent('indicator_types', 'clave_indicador'))->render();
        $this->assertInstanceOf(View::class, $view);
        $items = $view->getData()['dataTypeContent'];

        $this->assertFalse(
            $items->contains('clave_indicador', $key),
            'Control de gesti&oacute;n no debe ver COMGES cuando service_module_toggle con key comges est&aacute; deshabilitado (route=voyager.compromisos.index).'
        );
    }

    /** @test */
    public function admin_menu_includes_comges_even_when_service_module_toggle_disabled(): void
    {
        $this->seedVoyagerDataTypeForIndicatorTypes();

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $user = User::factory()->create(['role_id' => $adminRole->id]);
        $user->role()->associate($adminRole)->save();

        $suffix = Str::lower(Str::random(8));
        $key = "zzz-menu-comges-admin-sm-{$suffix}";

        IndicatorType::unguarded(fn () => IndicatorType::create([
            'clave_indicador' => $key,
            'nombre_indicador' => "Compromisos admin {$suffix}",
            'descripcion_indicador' => null,
            'icon_class' => 'voyager-book',
            'route' => 'voyager.compromisos.index',
            'view' => null,
            'estado' => true,
            'orden' => 989,
        ]));

        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::create([
            'cen_hie_dependency_id' => 1,
            'module_key' => 'comges',
            'enabled' => false,
        ]));

        $this->actingAs($user);

        $view = (new MenuComponent('indicator_types', 'clave_indicador'))->render();
        $this->assertInstanceOf(View::class, $view);
        $items = $view->getData()['dataTypeContent'];

        $this->assertTrue(
            $items->contains('clave_indicador', $key),
            'Administrador debe ver COMGES aunque service_module_toggle con key comges est&eacute; deshabilitado.'
        );
    }
}
