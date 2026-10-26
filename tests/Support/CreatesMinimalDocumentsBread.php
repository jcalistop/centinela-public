<?php

namespace Tests\Support;

use App\Http\Controllers\Voyager\DocumentController;
use App\Models\Document;
use App\Models\Role;
use Illuminate\Support\Facades\Gate;
use TCG\Voyager\Http\Middleware\VoyagerAdminMiddleware;
use TCG\Voyager\Models\DataRow;
use TCG\Voyager\Models\DataType;
use TCG\Voyager\Models\Permission;
use TCG\Voyager\Policies\BasePolicy;

trait CreatesMinimalDocumentsBread
{
    /**
     * Garantiza BREAD Document (misma convención que producción: name documents, slug documentos).
     */
    protected function seedMinimalDocumentsBreadForEmbeddedBrowse(): DataType
    {
        return DataType::unguarded(function (): DataType {
            $existingByModel = DataType::query()->where('model_name', Document::class)->first();
            if ($existingByModel instanceof DataType) {
                $this->ensureNombreDataRowExists($existingByModel);

                return $existingByModel->fresh();
            }

            $dataType = DataType::create([
                'name' => 'documents',
                'slug' => 'documentos',
                'display_name_singular' => 'Documento',
                'display_name_plural' => 'Documentos',
                'model_name' => Document::class,
                'controller' => DocumentController::class,
                'generate_permissions' => 0,
                'server_side' => 0,
                'details' => [
                    'order_column' => null,
                    'order_display_column' => null,
                    'order_direction' => 'asc',
                    'default_search_key' => 'nombre',
                    'scope' => 'filter',
                ],
            ]);

            $this->ensureNombreDataRowExists($dataType);

            return $dataType->fresh();
        });
    }

    protected function grantDocumentBreadPermissionsToRole(Role $role): void
    {
        $dataType = DataType::query()->where('model_name', Document::class)->first();
        if ($dataType === null) {
            return;
        }

        $suffix = $dataType->name;
        $ids = [];
        foreach (['browse', 'read', 'edit', 'add', 'delete'] as $action) {
            $ids[] = Permission::firstOrCreate(
                ['key' => $action.'_'.$suffix],
                ['table_name' => $dataType->name]
            )->id;
        }
        $role->permissions()->syncWithoutDetaching($ids);
    }

    /**
     * Rutas BREAD Voyager pasan por {@see VoyagerAdminMiddleware}, que exige `browse_admin`.
     */
    protected function grantBrowseAdminPermissionToRole(Role $role): void
    {
        $id = Permission::firstOrCreate(
            ['key' => 'browse_admin'],
            ['table_name' => 'admin']
        )->id;
        $role->permissions()->syncWithoutDetaching([$id]);
    }

    /**
     * Voyager registra políticas BREAD al boot (con `data_types` vacío en PHPUnit).
     * Registrar explícitamente antes de HTTP a controladores Voyager custom.
     */
    protected function registerVoyagerBreadPolicyForModel(string $modelClass): void
    {
        $this->resetVoyagerBasePolicyCache();
        Gate::policy($modelClass, BasePolicy::class);
    }

    protected function resetVoyagerBasePolicyCache(): void
    {
        $property = new \ReflectionProperty(BasePolicy::class, 'datatypes');
        $property->setAccessible(true);
        $property->setValue(null, []);
    }

    protected function ensureNombreDataRowExists(DataType $dataType): void
    {
        DataRow::unguarded(function () use ($dataType): void {
            $exists = DataRow::query()
                ->where('data_type_id', $dataType->getKey())
                ->where('field', 'nombre')
                ->exists();

            if ($exists) {
                return;
            }

            DataRow::create([
                'data_type_id' => $dataType->getKey(),
                'field' => 'nombre',
                'type' => 'text',
                'display_name' => 'Nombre',
                'required' => 0,
                'browse' => 1,
                'read' => 1,
                'edit' => 1,
                'add' => 1,
                'delete' => 0,
                'details' => '{}',
                'order' => 1,
            ]);
        });
    }
}
