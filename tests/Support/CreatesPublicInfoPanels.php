<?php

namespace Tests\Support;

use App\Models\PublicInfoPanel;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Fixtures compartidas de los tests de curaduría de paneles públicos en Voyager (SDD-306).
 */
trait CreatesPublicInfoPanels
{
    use CreatesMinimalDocumentsBread;

    protected User $admin;

    protected User $referente;

    protected User $parametrizador;

    protected function setUpPanelUsers(): void
    {
        Year::unguarded(fn (): Year => Year::create(['ano' => 2026, 'default' => 1]));

        $roles = [];
        foreach (['admin' => 'Administrador', 'referente' => 'Referente', 'parametrizador' => 'Parametrizador'] as $name => $display) {
            $roles[$name] = Role::firstOrCreate(['name' => $name], ['display_name' => $display]);
            $this->grantBrowseAdminPermissionToRole($roles[$name]);
        }

        $dependencyId = (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-PANELES',
            'nombre' => 'Servicio paneles',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['admin' => 'admin', 'referente' => 'referente', 'parametrizador' => 'parametrizador'] as $property => $roleName) {
            $this->{$property} = User::factory()->create([
                'role_id' => $roles[$roleName]->id,
                'cen_hie_dependency_id' => $dependencyId,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createPanel(array $attributes = []): PublicInfoPanel
    {
        return PublicInfoPanel::unguarded(fn (): PublicInfoPanel => PublicInfoPanel::create(array_merge([
            'slug' => 'panel-de-prueba',
            'tipo' => PublicInfoPanel::TIPO_HTML_BUNDLE,
            'titulo' => 'Panel de prueba',
            'descripcion' => 'Descripción de prueba',
            'publicado' => false,
            'orden' => 0,
        ], $attributes)));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createBundlePanel(string $slug = 'bundle-completo', array $attributes = []): PublicInfoPanel
    {
        return $this->createPanel(array_merge([
            'slug' => $slug,
            'titulo' => 'Panel '.$slug,
            'html_path' => $this->storeAsset($slug.'/panel.html', '<html><body>ok</body></html>'),
            'csv_path' => $this->storeAsset($slug.'/datos.csv', "a,b\n1,2\n"),
            'preview_png_path' => $this->storePreview($slug),
        ], $attributes));
    }

    protected function storeAsset(string $path, string $contents): string
    {
        $relative = 'public-info-panels/'.$path;
        Storage::disk(config('voyager.storage.disk', 'private'))->put($relative, $contents);

        return $relative;
    }

    protected function storePreview(string $slug): string
    {
        return $this->storeAsset($slug.'/preview.png', $this->pngBytes());
    }

    protected function pngBytes(): string
    {
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    }
}
