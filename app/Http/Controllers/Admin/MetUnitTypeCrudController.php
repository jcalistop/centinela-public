<?php

namespace App\Http\Controllers\Admin;

use App\Models\MetUnitType;
use Illuminate\Http\Request;
use TCG\Voyager\Events\BreadDataDeleted;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Http\Controllers\VoyagerBaseController;

class MetUnitTypeCrudController extends VoyagerBaseController
{
    /**
     * Debe coincidir con `data_types.slug` para este BREAD (ver migración SDD-154).
     */
    protected string $slug = 'unidades-metas';

    /**
     * Impide eliminar tipos de unidad que ya están en uso por unidades de medición.
     *
     * @param  int|string|null  $id
     */
    public function destroy(Request $request, $id)
    {
        $slug = $this->getSlug($request);
        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();

        $ids = [];
        if (empty($id)) {
            $ids = array_filter(explode(',', (string) $request->input('ids')));
        } else {
            $ids[] = $id;
        }

        $inUse = [];
        $affected = 0;

        foreach ($ids as $deleteId) {
            $unitType = MetUnitType::query()->findOrFail($deleteId);
            $this->authorize('delete', $unitType);

            if ($unitType->measurementUnits()->exists()) {
                $inUse[] = $unitType->codigo;

                continue;
            }

            $unitType->delete();
            $affected++;

            if ($dataType !== null) {
                event(new BreadDataDeleted($dataType, $unitType));
            }
        }

        if ($inUse !== []) {
            return redirect()
                ->route("voyager.{$slug}.index")
                ->with([
                    'message' => 'No se puede eliminar: el/los tipo(s) de unidad ('.implode(', ', $inUse).') están en uso por unidades de medición.',
                    'alert-type' => 'error',
                ]);
        }

        return redirect()
            ->route("voyager.{$slug}.index")
            ->with([
                'message' => $affected === 1
                    ? 'Tipo de unidad eliminado correctamente.'
                    : "Se eliminaron {$affected} tipos de unidad correctamente.",
                'alert-type' => 'success',
            ]);
    }
}
