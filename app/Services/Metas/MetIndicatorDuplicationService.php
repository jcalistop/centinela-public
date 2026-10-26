<?php

namespace App\Services\Metas;

use App\Enums\Metas\MetIndicatorPriority;
use App\Models\IndicatorType;
use App\Models\MetDataField;
use App\Models\MetFormula;
use App\Models\MetIndicator;
use App\Support\Metas\MetFormulaTokenPresenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MetIndicatorDuplicationService
{
    /**
     * Duplica un indicador Metas junto con sus campos de datos, fórmula y ámbitos.
     */
    public function duplicate(
        MetIndicator $source,
        int $destYearId,
        int $destIndicatorTypeId,
        string $newClave,
    ): MetIndicator {
        $this->validateClaveUniqueness($newClave);

        $newOrden = app(MetIndicatorCatalogPersistenceService::class)
            ->nextOrdenForNewIndicator($destIndicatorTypeId, $destYearId);

        return DB::transaction(function () use ($source, $destYearId, $destIndicatorTypeId, $newClave, $newOrden): MetIndicator {
            $destType = IndicatorType::query()->findOrFail($destIndicatorTypeId);
            $prioridad = $destType->route === 'metas-19664'
                ? ($source->prioridad ?? MetIndicatorPriority::SinPrioridad)
                : MetIndicatorPriority::SinPrioridad;

            // 1. Crear el nuevo indicador con atributos del origen
            $newIndicator = MetIndicator::query()->create([
                'indicator_type_id' => $destIndicatorTypeId,
                'year_id' => $destYearId,
                'clave' => $newClave,
                'nombre' => $source->nombre,
                'objetivo' => $source->objetivo,
                'meta_anual_descripcion' => $source->meta_anual_descripcion,
                'meta_valor' => $source->meta_valor,
                'meta_operador' => $source->meta_operador,
                'meta_reglas' => $source->meta_reglas,
                'umbral_inferior' => $source->umbral_inferior,
                'umbral_superior' => $source->umbral_superior,
                'nivel_aplicacion' => $source->nivel_aplicacion,
                'exclusiones' => $source->exclusiones,
                'unidad_medida' => $source->unidad_medida,
                'decimales' => $source->decimales,
                'decimales_modo' => $source->decimales_modo,
                'orden' => $newOrden,
                'estado' => $source->estado,
                'prioridad' => $prioridad,
            ]);

            // 2. Copiar campos de datos (met_data_fields)
            $this->duplicateDataFields($source, $newIndicator);

            // 3. Copiar fórmula (met_formula)
            $this->duplicateFormula($source, $newIndicator);

            // 4. Copiar ámbitos (indicator_ambito_assignments) — morphToMany
            $ambitoIds = $source->indicatorAmbitos()->pluck('indicator_ambitos.id')->toArray();
            if ($ambitoIds !== []) {
                $newIndicator->indicatorAmbitos()->sync($ambitoIds);
            }

            return $newIndicator->fresh();
        });
    }

    /**
     * Verifica que la clave no exista ya en met_indicators.
     */
    public function validateClaveUniqueness(string $clave): void
    {
        if (MetIndicator::query()->where('clave', $clave)->exists()) {
            throw ValidationException::withMessages([
                'duplicateClave' => "El código \"{$clave}\" ya está en uso en otro indicador.",
            ]);
        }
    }

    private function duplicateDataFields(MetIndicator $source, MetIndicator $dest): void
    {
        $fields = MetDataField::query()
            ->where('met_indicator_id', $source->id)
            ->orderBy('orden')
            ->get();

        $persistence = app(MetIndicatorCatalogPersistenceService::class);

        foreach ($fields as $index => $field) {
            $variableKey = $persistence->indexToVariableKey($index);

            MetDataField::query()->create([
                'met_indicator_id' => $dest->id,
                'label' => $field->label,
                'variable_key' => $variableKey,
                'componente' => $field->componente->value,
                'input_type' => $field->input_type->value,
                'aggregation_type' => $field->aggregation_type?->value,
                'fuente' => $field->fuente,
                'orden' => $index,
            ]);
        }
    }

    private function duplicateFormula(MetIndicator $source, MetIndicator $dest): void
    {
        $formula = MetFormula::query()->where('met_indicator_id', $source->id)->first();

        if ($formula === null) {
            return;
        }

        $tokens = $formula->expression;
        $description = MetFormulaTokenPresenter::describe($tokens);

        MetFormula::query()->create([
            'met_indicator_id' => $dest->id,
            'expression' => $tokens,
            'description' => $description,
        ]);
    }
}
