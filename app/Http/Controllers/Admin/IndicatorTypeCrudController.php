<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\StoreIndicatorTypeRequest;
use App\Http\Requests\UpdateIndicatorTypeRequest;
use App\Models\IndicatorType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Http\Controllers\VoyagerBaseController;

class IndicatorTypeCrudController extends VoyagerBaseController
{
    /**
     * Debe coincidir con `data_types.slug` para este BREAD (ver migración).
     */
    protected string $slug = 'instrumentos-evaluacion';

    public function index(Request $request): mixed
    {
        // Listado estándar Voyager (bread.browse: cabecera, acciones, columnas del BREAD, DataTables, etc.).
        // Para instrumentos-evaluacion, el orden por defecto debe seguir el campo `orden`.
        // Si el usuario elige ordenar por otra columna desde la UI (order_by/sort_order),
        // respetamos esos parámetros.
        $orderBy = $request->query('order_by');
        if ($orderBy === null || trim((string) $orderBy) === '') {
            // Voyager lee `order_by` desde querystring, así que lo seteamos ahí.
            $request->query->set('order_by', 'orden');
            $request->query->set('sort_order', 'asc');
        }

        return parent::index($request);
    }

    public function create(Request $request): mixed
    {
        $slug = $this->getSlug($request);
        $dataType = Voyager::model('DataType')->where('slug', $slug)->firstOrFail();
        $this->authorize('add', app($dataType->model_name));

        $indicatorType = new IndicatorType([
            'measurement_granularity' => 'establishment',
            'estado' => false,
            'icon_class' => 'voyager-book',
        ]);

        return Voyager::view("voyager::{$slug}.edit-add", [
            'indicatorType' => $indicatorType,
            'dataType' => $dataType,
            'metasRouteSlugs' => IndicatorType::configMetasRouteSlugs(),
            'routesInUse' => array_values(array_filter(
                IndicatorType::query()->pluck('route')->all(),
                static fn ($r) => $r !== null && $r !== ''
            )),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $slug = $this->getSlug($request);
        $dataType = Voyager::model('DataType')->where('slug', $slug)->firstOrFail();
        $this->authorize('add', app($dataType->model_name));

        $form = StoreIndicatorTypeRequest::createFrom($request);
        $form->setContainer(app());
        $form->setRedirector(app('redirect'));
        $form->validateResolved();

        IndicatorType::query()->create(array_merge($form->validated(), ['view' => null]));

        return redirect()
            ->route("voyager.{$dataType->slug}.index")
            ->with(['message' => 'Tipo de indicador creado.', 'alert-type' => 'success']);
    }

    public function edit(Request $request, $id): mixed
    {
        $slug = $this->getSlug($request);
        $dataType = Voyager::model('DataType')->where('slug', $slug)->firstOrFail();
        $indicatorType = IndicatorType::query()->findOrFail($id);
        $this->authorize('edit', $indicatorType);

        return Voyager::view("voyager::{$slug}.edit-add", [
            'indicatorType' => $indicatorType,
            'dataType' => $dataType,
        ]);
    }

    public function update(Request $request, $id): RedirectResponse
    {
        $slug = $this->getSlug($request);
        $dataType = Voyager::model('DataType')->where('slug', $slug)->firstOrFail();
        $indicatorType = IndicatorType::query()->findOrFail($id);
        $this->authorize('edit', $indicatorType);

        $form = UpdateIndicatorTypeRequest::createFrom($request);
        $form->setContainer(app());
        $form->setRedirector(app('redirect'));
        $form->validateResolved();

        $indicatorType->update(array_merge($form->validated(), ['view' => null]));

        return redirect()
            ->route("voyager.{$dataType->slug}.index")
            ->with(['message' => 'Tipo de indicador actualizado.', 'alert-type' => 'success']);
    }
}
