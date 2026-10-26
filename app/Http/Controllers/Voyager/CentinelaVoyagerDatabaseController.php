<?php

namespace App\Http\Controllers\Voyager;

use App\Support\Voyager\VoyagerSchemaDescriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Http\Controllers\VoyagerDatabaseController;

/**
 * El esquema se modifica solo mediante migraciones y el dump canónico es su
 * fuente de verdad: `/admin/database` queda en solo lectura (listado y detalle).
 */
class CentinelaVoyagerDatabaseController extends VoyagerDatabaseController
{
    /**
     * @param  string  $table
     */
    public function show($table): JsonResponse
    {
        $this->authorize('browse_database');

        $additionalAttributes = [];
        $modelName = Voyager::model('DataType')->where('name', $table)->value('model_name');

        if (filled($modelName)) {
            foreach (app($modelName)->additional_attributes ?? [] as $attribute) {
                $additionalAttributes[$attribute] = [];
            }
        }

        return response()->json(VoyagerSchemaDescriber::describeTable($table)->merge($additionalAttributes));
    }

    public function create(): RedirectResponse
    {
        return $this->rejectSchemaChange();
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->rejectSchemaChange();
    }

    /**
     * @param  string  $table
     */
    public function edit($table): RedirectResponse
    {
        return $this->rejectSchemaChange();
    }

    public function update(Request $request): RedirectResponse
    {
        return $this->rejectSchemaChange();
    }

    /**
     * @param  string  $table
     */
    public function destroy($table): RedirectResponse
    {
        return $this->rejectSchemaChange();
    }

    private function rejectSchemaChange(): RedirectResponse
    {
        $this->authorize('browse_database');

        return redirect()
            ->route('voyager.database.index')
            ->with($this->alertError(
                'La estructura de la base de datos se modifica solo mediante migraciones. Esta acción está deshabilitada en el panel.'
            ));
    }
}
