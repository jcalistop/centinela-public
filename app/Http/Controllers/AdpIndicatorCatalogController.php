<?php

namespace App\Http\Controllers;

use App\Models\AdpAgreement;
use App\Models\AdpAgreementPeriod;
use App\Models\AdpIndicatorCatalogFormula;
use App\Models\AdpIndicatorCatalogItem;
use App\Models\User;
use App\Services\Adp\AdpIndicatorCatalogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AdpIndicatorCatalogController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', AdpIndicatorCatalogItem::class);

        $items = AdpIndicatorCatalogItem::query()
            ->with('formula')
            ->orderBy('clave')
            ->get();

        return view('centinela.adp.catalog.index', compact('items'));
    }

    public function create(): View
    {
        Gate::authorize('create', AdpIndicatorCatalogItem::class);

        return view('centinela.adp.catalog.form', [
            'item' => new AdpIndicatorCatalogItem,
            'formula' => new AdpIndicatorCatalogFormula,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', AdpIndicatorCatalogItem::class);

        $user = auth()->user();
        if (! $user instanceof User) {
            abort(401);
        }

        $validated = $this->validateItemPayload($request);
        $expression = $this->validateExpressionPayload($request);

        /** @var AdpIndicatorCatalogItem $item */
        $item = AdpIndicatorCatalogItem::query()->create(array_merge($validated, [
            'cen_hie_dependency_id' => $user->cen_hie_dependency_id,
            'created_by_user_id' => $user->id,
        ]));

        AdpIndicatorCatalogFormula::query()->create([
            'adp_indicator_catalog_item_id' => $item->id,
            'expression' => $expression,
            'description' => $request->input('formula_description'),
            'is_active' => true,
        ]);

        return redirect()
            ->route('adp.catalog.index')
            ->with('flash_catalog', 'Ítem de catálogo ADP creado.');
    }

    public function edit(AdpIndicatorCatalogItem $item): View
    {
        Gate::authorize('update', $item);

        $item->loadMissing('formula');

        return view('centinela.adp.catalog.form', [
            'item' => $item,
            'formula' => $item->formula ?? new AdpIndicatorCatalogFormula,
        ]);
    }

    public function update(Request $request, AdpIndicatorCatalogItem $item): RedirectResponse
    {
        Gate::authorize('update', $item);

        $validated = $this->validateItemPayload($request);
        $expression = $this->validateExpressionPayload($request);

        $item->update($validated);

        AdpIndicatorCatalogFormula::query()->updateOrCreate(
            ['adp_indicator_catalog_item_id' => $item->id],
            [
                'expression' => $expression,
                'description' => $request->input('formula_description'),
                'is_active' => true,
            ],
        );

        return redirect()
            ->route('adp.catalog.index')
            ->with('flash_catalog', 'Ítem de catálogo ADP actualizado.');
    }

    public function destroy(AdpIndicatorCatalogItem $item): RedirectResponse
    {
        Gate::authorize('delete', $item);

        $item->delete();

        return redirect()
            ->route('adp.catalog.index')
            ->with('flash_catalog', 'Ítem de catálogo ADP eliminado.');
    }

    public function cloneToPeriod(Request $request, AdpAgreement $agreement, AdpAgreementPeriod $period): RedirectResponse
    {
        Gate::authorize('edit', $agreement);

        $validated = $request->validate([
            'adp_indicator_catalog_item_id' => 'required|integer|exists:adp_indicator_catalog_items,id',
            'adp_objective_id' => 'required|integer|exists:adp_objectives,id',
        ]);

        $service = app(AdpIndicatorCatalogService::class);

        try {
            $service->cloneCatalogItemToObjective(
                (int) $validated['adp_indicator_catalog_item_id'],
                $agreement,
                $period,
                (int) $validated['adp_objective_id'],
                auth()->id(),
            );
        } catch (ValidationException $e) {
            return redirect()
                ->route('adp.self_evaluation.agreement', $agreement)
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('adp.self_evaluation.agreement', $agreement)
            ->with('flash_catalog', 'Indicador copiado desde catálogo al periodo.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateItemPayload(Request $request): array
    {
        return $request->validate([
            'clave' => 'required|string|max:255',
            'nombre' => 'required|string|max:255',
            'objetivo' => 'nullable|string|max:20000',
            'meta_anual_descripcion' => 'nullable|string|max:20000',
            'meta_valor' => 'nullable|numeric',
            'meta_operador' => 'nullable|string|in:>=,>,<=,<,=',
            'meta_reglas' => 'nullable|array',
            'umbral_inferior' => 'nullable|numeric',
            'umbral_superior' => 'nullable|numeric',
            'formula_descripcion' => 'nullable|string|max:20000',
            'fuente_numerador' => 'nullable|string|max:20000',
            'fuente_denominador' => 'nullable|string|max:20000',
            'periodicidad' => 'nullable|string|in:TRIMESTRAL,SEMESTRAL,ANUAL',
            'nivel_aplicacion' => 'nullable|string|max:20000',
            'unidad_medida' => 'nullable|string|max:255',
            'decimales' => 'nullable|integer|min:0|max:6',
            'decimales_modo' => 'nullable|string|in:truncar,redondear',
            'calculation_method' => 'nullable|string|max:32',
            'orden' => 'nullable|integer|min:0|max:65535',
            'estado' => 'nullable|boolean',
        ]);
    }

    /**
     * @return array<int, mixed>
     */
    private function validateExpressionPayload(Request $request): array
    {
        $validated = $request->validate([
            'expression_json' => 'required|string|max:20000',
        ]);

        $decoded = json_decode($validated['expression_json'], true);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'expression_json' => 'JSON inválido para expression.',
            ]);
        }

        return $decoded;
    }
}
