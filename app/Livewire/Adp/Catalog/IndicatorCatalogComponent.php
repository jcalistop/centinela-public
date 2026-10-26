<?php

namespace App\Livewire\Adp\Catalog;

use App\Livewire\Adp\Traits\ResolvesAdpInstrument;
use App\Models\AdpIndicatorCatalogItem;
use App\Models\User;
use App\Services\Adp\AdpSensitivityRowsValidator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class IndicatorCatalogComponent extends Component
{
    use ResolvesAdpInstrument;

    private function authenticatedUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    public string $search = '';

    // Modal de creación/edición
    public bool $showModal = false;

    public ?int $editingId = null;

    // Form fields
    public string $clave = '';

    public string $nombre = '';

    public string $objetivo = '';

    public string $metaAnualDescripcion = '';

    public string $metaValor = '';

    public string $metaOperador = '';

    public string $umbralInferior = '0';

    public string $umbralSuperior = '100';

    public string $formulaDescripcion = '';

    public string $fuenteNumerador = '';

    public string $fuenteDenominador = '';

    public string $periodicidad = 'ANUAL';

    public string $nivelAplicacion = '';

    public string $unidadMedida = '';

    public string $decimales = '2';

    public string $decimalesModo = 'redondear';

    public string $calculationMethod = 'percent';

    public string $orden = '';

    public bool $estado = true;

    // Modal ficha del indicador
    public bool $showFichaModal = false;

    /** @var array<string, mixed> */
    public array $fichaData = [];

    // Modal sensibilidad
    public bool $showSensitivityModal = false;

    public ?int $sensitivityItemId = null;

    public string $sensitivityTableName = '';

    /** @var array<int, array{orden: int, valor_minimo: string, operador_minimo: string, valor_maximo: string, operador_maximo: string, cumplimiento: string}> */
    public array $sensitivityRows = [];

    public function mount(?int $indicatorTypeId = null): void
    {
        $this->indicatorTypeId = $this->resolveIndicatorTypeId($indicatorTypeId);
    }

    public function render(): View
    {
        $user = $this->authenticatedUser();

        $canManage = $user->hasRole(['admin', 'parametrizador']);
        $canViewSensitivity = $user->hasRole(['admin', 'control-gestion']);

        $items = AdpIndicatorCatalogItem::query()
            ->with('formula')
            ->when($this->search !== '', fn ($q) => $q->where(function ($q): void {
                $q->where('clave', 'like', '%'.$this->search.'%')
                    ->orWhere('nombre', 'like', '%'.$this->search.'%');
            }))
            ->orderBy('clave')
            ->get();

        return view('livewire.adp.catalog.indicator-catalog-component', compact(
            'items',
            'canManage',
            'canViewSensitivity',
        ));
    }

    public function openCreate(): void
    {
        $this->resetFormFields();
        $this->editingId = null;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $item = AdpIndicatorCatalogItem::query()->findOrFail($id);
        $this->editingId = $item->id;
        $this->clave = $item->clave;
        $this->nombre = $item->nombre;
        $this->objetivo = (string) ($item->objetivo ?? '');
        $this->metaAnualDescripcion = (string) ($item->meta_anual_descripcion ?? '');
        $this->metaValor = $item->meta_valor !== null ? (string) $item->meta_valor : '';
        $this->metaOperador = (string) ($item->meta_operador ?? '');
        $this->umbralInferior = $item->umbral_inferior !== null ? (string) $item->umbral_inferior : '0';
        $this->umbralSuperior = $item->umbral_superior !== null ? (string) $item->umbral_superior : '100';
        $this->formulaDescripcion = (string) ($item->formula_descripcion ?? '');
        $this->fuenteNumerador = (string) ($item->fuente_numerador ?? '');
        $this->fuenteDenominador = (string) ($item->fuente_denominador ?? '');
        $this->periodicidad = $item->periodicidad ?? 'ANUAL';
        $this->nivelAplicacion = (string) ($item->nivel_aplicacion ?? '');
        $this->unidadMedida = (string) ($item->unidad_medida ?? '');
        $this->decimales = (string) ($item->decimales ?? '2');
        $this->decimalesModo = $item->decimales_modo ?? 'redondear';
        $this->calculationMethod = $item->calculation_method ?? 'percent';
        $this->orden = $item->orden !== null ? (string) $item->orden : '';
        $this->estado = (bool) ($item->estado ?? true);
        $this->showModal = true;
    }

    public function save(): void
    {
        abort_unless($this->authenticatedUser()->hasRole(['admin', 'parametrizador']), 403);

        $this->validate([
            'clave' => 'required|string|max:255',
            'nombre' => 'required|string|max:255',
            'objetivo' => 'nullable|string|max:20000',
            'metaAnualDescripcion' => 'nullable|string|max:20000',
            'metaValor' => 'nullable|numeric',
            'metaOperador' => 'nullable|string|in:>=,>,<=,<,=',
            'umbralInferior' => 'nullable|numeric|min:0|max:100',
            'umbralSuperior' => 'nullable|numeric|min:0|max:100',
            'formulaDescripcion' => 'nullable|string|max:20000',
            'fuenteNumerador' => 'nullable|string|max:20000',
            'fuenteDenominador' => 'nullable|string|max:20000',
            'periodicidad' => 'required|string|in:TRIMESTRAL,SEMESTRAL,ANUAL',
            'nivelAplicacion' => 'nullable|string|max:20000',
            'unidadMedida' => 'nullable|string|max:255',
            'decimales' => 'nullable|integer|min:0|max:6',
            'decimalesModo' => 'nullable|string|in:truncar,redondear',
            'calculationMethod' => 'nullable|string|max:32',
            'orden' => 'nullable|integer|min:0|max:65535',
            'estado' => 'boolean',
        ]);

        $data = [
            'clave' => $this->clave,
            'nombre' => $this->nombre,
            'objetivo' => $this->objetivo ?: null,
            'meta_anual_descripcion' => $this->metaAnualDescripcion ?: null,
            'meta_valor' => $this->metaValor !== '' ? $this->metaValor : null,
            'meta_operador' => $this->metaOperador ?: null,
            'umbral_inferior' => $this->umbralInferior !== '' ? $this->umbralInferior : null,
            'umbral_superior' => $this->umbralSuperior !== '' ? $this->umbralSuperior : null,
            'formula_descripcion' => $this->formulaDescripcion ?: null,
            'fuente_numerador' => $this->fuenteNumerador ?: null,
            'fuente_denominador' => $this->fuenteDenominador ?: null,
            'periodicidad' => $this->periodicidad,
            'nivel_aplicacion' => $this->nivelAplicacion ?: null,
            'unidad_medida' => $this->unidadMedida ?: null,
            'decimales' => $this->decimales !== '' ? (int) $this->decimales : 2,
            'decimales_modo' => $this->decimalesModo ?: null,
            'calculation_method' => $this->calculationMethod ?: 'percent',
            'orden' => $this->orden !== '' ? (int) $this->orden : null,
            'estado' => (bool) $this->estado,
        ];

        if ($this->editingId !== null) {
            $item = AdpIndicatorCatalogItem::query()->findOrFail($this->editingId);
            $item->update($data);
            $this->dispatch('toast', type: 'success', message: 'Ítem de catálogo actualizado.');
        } else {
            $data['cen_hie_dependency_id'] = $this->authenticatedUser()->cen_hie_dependency_id;
            $data['created_by_user_id'] = $this->authenticatedUser()->id;
            AdpIndicatorCatalogItem::query()->create($data);
            $this->dispatch('toast', type: 'success', message: 'Ítem de catálogo creado.');
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        abort_unless($this->authenticatedUser()->hasRole(['admin', 'parametrizador']), 403);

        $item = AdpIndicatorCatalogItem::query()->findOrFail($id);
        $item->delete();

        $this->dispatch('toast', type: 'success', message: 'Ítem de catálogo eliminado.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->editingId = null;
        $this->resetFormFields();
    }

    // --- Ficha modal ---

    public function openFichaModal(int $id): void
    {
        $item = AdpIndicatorCatalogItem::query()
            ->with('formula')
            ->findOrFail($id);

        $this->fichaData = [
            'id' => $item->id,
            'clave' => $item->clave,
            'nombre' => $item->nombre,
            'objetivo' => $item->objetivo,
            'estado' => (bool) $item->estado,
            'periodicidad' => $item->periodicidad,
            'unidad_medida' => $item->unidad_medida,
            'decimales' => $item->decimales,
            'decimales_modo' => $item->decimales_modo,
            'orden' => $item->orden,
            'meta_valor' => $item->meta_valor,
            'meta_operador' => $item->meta_operador,
            'meta_anual_descripcion' => $item->meta_anual_descripcion,
            'meta_reglas' => $item->meta_reglas ? json_encode($item->meta_reglas, JSON_UNESCAPED_UNICODE) : null,
            'umbral_inferior' => $item->umbral_inferior,
            'umbral_superior' => $item->umbral_superior,
            'nivel_aplicacion' => $item->nivel_aplicacion,
            'formula_descripcion' => $item->formula_descripcion,
            'fuente_numerador' => $item->fuente_numerador,
            'fuente_denominador' => $item->fuente_denominador,
            'formula_expression' => $item->formula?->expression ? json_encode($item->formula->expression, JSON_UNESCAPED_UNICODE) : null,
            'calculation_method' => $item->calculation_method,
        ];

        $this->showFichaModal = true;
    }

    public function closeFichaModal(): void
    {
        $this->showFichaModal = false;
        $this->fichaData = [];
    }

    // --- Sensibilidad modal ---

    public function openSensitivityModal(int $id): void
    {
        $item = AdpIndicatorCatalogItem::query()->findOrFail($id);

        $this->sensitivityItemId = $item->id;

        $config = $item->sensitivity_config;

        if ($config !== null && isset($config['nombre'])) {
            $this->sensitivityTableName = $config['nombre'];
            $this->sensitivityRows = [];
            foreach ($config['rows'] ?? [] as $row) {
                $this->sensitivityRows[] = [
                    'orden' => (int) ($row['orden'] ?? count($this->sensitivityRows) + 1),
                    'valor_minimo' => (string) ($row['valor_minimo'] ?? '0'),
                    'operador_minimo' => $row['operador_minimo'] ?? '>=',
                    'valor_maximo' => (string) ($row['valor_maximo'] ?? '0'),
                    'operador_maximo' => $row['operador_maximo'] ?? '<=',
                    'cumplimiento' => (string) ($row['cumplimiento'] ?? '0'),
                ];
            }
        } else {
            $this->sensitivityTableName = '';
            $this->sensitivityRows = [];
            $this->addSensitivityRow();
        }

        $this->showSensitivityModal = true;
    }

    public function addSensitivityRow(): void
    {
        $this->sensitivityRows[] = [
            'orden' => count($this->sensitivityRows) + 1,
            'valor_minimo' => '0',
            'operador_minimo' => '>=',
            'valor_maximo' => '0',
            'operador_maximo' => '<=',
            'cumplimiento' => '0',
        ];
    }

    public function removeSensitivityRow(int $index): void
    {
        unset($this->sensitivityRows[$index]);
        $this->sensitivityRows = array_values($this->sensitivityRows);

        foreach ($this->sensitivityRows as $i => &$row) {
            $row['orden'] = $i + 1;
        }
    }

    public function saveSensitivity(): void
    {
        abort_unless($this->authenticatedUser()->hasRole(['admin', 'control-gestion']), 403);
        abort_unless($this->sensitivityItemId !== null, 403);

        $this->validate([
            'sensitivityTableName' => 'required|string|max:255',
        ]);

        $normalized = [];
        foreach ($this->sensitivityRows as $row) {
            $normalized[] = [
                'orden' => (int) ($row['orden'] ?? 0),
                'valor_minimo' => (float) ($row['valor_minimo'] ?? 0),
                'operador_minimo' => $row['operador_minimo'] ?? '>=',
                'valor_maximo' => (float) ($row['valor_maximo'] ?? 0),
                'operador_maximo' => $row['operador_maximo'] ?? '<=',
                'cumplimiento' => (float) ($row['cumplimiento'] ?? 0),
            ];
        }

        try {
            AdpSensitivityRowsValidator::validate($normalized);
        } catch (ValidationException $e) {
            $this->addError('sensitivityRows', $e->getMessage());

            return;
        }

        $item = AdpIndicatorCatalogItem::query()->findOrFail($this->sensitivityItemId);

        // Build rows array (without "orden" duplicated — keep simple data)
        $rowsData = [];
        foreach ($normalized as $row) {
            $rowsData[] = [
                'orden' => $row['orden'],
                'valor_minimo' => $row['valor_minimo'],
                'operador_minimo' => $row['operador_minimo'],
                'valor_maximo' => $row['valor_maximo'],
                'operador_maximo' => $row['operador_maximo'],
                'cumplimiento' => $row['cumplimiento'],
            ];
        }

        $item->update([
            'sensitivity_config' => [
                'nombre' => $this->sensitivityTableName,
                'rows' => $rowsData,
            ],
        ]);

        $this->dispatch('toast', type: 'success', message: 'Configuración de sensibilidad guardada.');
    }

    public function clearSensitivity(): void
    {
        abort_unless($this->sensitivityItemId !== null, 403);

        $item = AdpIndicatorCatalogItem::query()->findOrFail($this->sensitivityItemId);
        $item->update(['sensitivity_config' => null]);

        $this->sensitivityTableName = '';
        $this->sensitivityRows = [];
        $this->addSensitivityRow();

        $this->dispatch('toast', type: 'success', message: 'Configuración de sensibilidad eliminada.');
    }

    public function closeSensitivityModal(): void
    {
        $this->showSensitivityModal = false;
        $this->sensitivityItemId = null;
        $this->sensitivityTableName = '';
        $this->sensitivityRows = [];
    }

    // --- Helpers ---

    private function resetFormFields(): void
    {
        $this->clave = '';
        $this->nombre = '';
        $this->objetivo = '';
        $this->metaAnualDescripcion = '';
        $this->metaValor = '';
        $this->metaOperador = '';
        $this->umbralInferior = '0';
        $this->umbralSuperior = '100';
        $this->formulaDescripcion = '';
        $this->fuenteNumerador = '';
        $this->fuenteDenominador = '';
        $this->periodicidad = 'ANUAL';
        $this->nivelAplicacion = '';
        $this->unidadMedida = '';
        $this->decimales = '2';
        $this->decimalesModo = 'redondear';
        $this->calculationMethod = 'percent';
        $this->orden = '';
        $this->estado = true;
    }
}
