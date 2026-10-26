<?php

namespace App\Http\Controllers\Voyager;

use App\Support\Voyager\VoyagerSchemaDescriber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use TCG\Voyager\Database\Schema\SchemaManager;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Http\Controllers\VoyagerBreadController;

/**
 * `create()` y `edit()` siguen a los de Voyager 1.8.0 salvo la fuente de
 * `fieldOptions`; mantenerlos en sintonía si se actualiza Voyager.
 */
class CentinelaVoyagerBreadController extends VoyagerBreadController
{
    /**
     * @param  string  $table
     */
    public function create(Request $request, $table): View
    {
        $this->authorize('browse_bread');

        $dataType = Voyager::model('DataType')->whereName($table)->first();

        $data = $this->prefilledBreadInfo($table);
        $data['fieldOptions'] = VoyagerSchemaDescriber::describeTable(
            filled($dataType?->model_name) ? app($dataType->model_name)->getTable() : $table
        );

        return Voyager::view('voyager::tools.bread.edit-add', $data);
    }

    /**
     * @param  string  $table
     */
    public function edit($table): View
    {
        $this->authorize('browse_bread');

        $dataType = Voyager::model('DataType')->whereName($table)->firstOrFail();

        $fieldOptions = VoyagerSchemaDescriber::describeTable(
            filled($dataType->model_name) ? app($dataType->model_name)->getTable() : $dataType->name
        );

        $isModelTranslatable = is_bread_translatable($dataType);
        $tables = SchemaManager::listTableNames();
        $dataTypeRelationships = Voyager::model('DataRow')
            ->where('data_type_id', '=', $dataType->id)
            ->where('type', '=', 'relationship')
            ->get();
        $scopes = filled($dataType->model_name) ? $this->getModelScopes($dataType->model_name) : [];

        return Voyager::view('voyager::tools.bread.edit-add', compact(
            'dataType',
            'fieldOptions',
            'isModelTranslatable',
            'tables',
            'dataTypeRelationships',
            'scopes',
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function prefilledBreadInfo(string $table): array
    {
        $displayName = Str::singular(implode(' ', explode('_', Str::title($table))));
        $modelNamespace = config('voyager.models.namespace') ?: app()->getNamespace();

        return [
            'isModelTranslatable' => true,
            'table' => $table,
            'slug' => Str::slug($table),
            'display_name' => $displayName,
            'display_name_plural' => Str::plural($displayName),
            'model_name' => $modelNamespace.Str::studly(Str::singular($table)),
            'generate_permissions' => true,
            'server_side' => false,
        ];
    }
}
