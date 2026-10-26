<?php

namespace App\Services\Adp;

use App\Models\AdpAgreement;
use App\Models\AdpAgreementPeriod;
use App\Models\AdpIndicator;
use App\Models\AdpIndicatorCatalogItem;
use App\Models\AdpIndicatorFormula;
use App\Models\AdpObjective;
use App\Models\AdpSensitivityTable;
use App\Models\AdpSensitivityTableRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdpIndicatorCatalogService
{
    public function cloneCatalogItemToObjective(
        int $catalogItemId,
        AdpAgreement $agreement,
        AdpAgreementPeriod $period,
        int $objectiveId,
        ?int $userId = null,
    ): AdpIndicator {
        if ((int) $period->adp_agreement_id !== (int) $agreement->id) {
            throw ValidationException::withMessages([
                'period' => 'El periodo no pertenece al convenio.',
            ]);
        }

        $objective = AdpObjective::query()->findOrFail($objectiveId);
        if ((int) $objective->adp_agreement_period_id !== (int) $period->id) {
            throw ValidationException::withMessages([
                'objective' => 'El objetivo no pertenece al periodo.',
            ]);
        }

        $catalog = AdpIndicatorCatalogItem::query()
            ->with('formula')
            ->findOrFail($catalogItemId);

        if ((int) $catalog->cen_hie_dependency_id !== (int) $agreement->cen_hie_dependency_id) {
            throw ValidationException::withMessages([
                'catalog' => 'El ítem de catálogo no pertenece al Servicio de Salud de este convenio.',
            ]);
        }

        return DB::transaction(function () use ($catalog, $objective): AdpIndicator {
            $baseClave = $catalog->clave;
            $clave = $baseClave;
            $i = 2;
            while (AdpIndicator::query()->where('adp_objective_id', $objective->id)->where('clave', $clave)->exists()) {
                $clave = sprintf('%s-%d', $baseClave, $i);
                $i++;
            }

            /** @var AdpIndicator $indicator */
            $indicator = AdpIndicator::query()->create([
                'adp_objective_id' => $objective->id,
                'adp_indicator_catalog_item_id' => $catalog->id,
                'clave' => $clave,
                'nombre' => $catalog->nombre,
                'objetivo' => $catalog->objetivo,
                'meta_anual_descripcion' => $catalog->meta_anual_descripcion,
                'meta_valor' => $catalog->meta_valor,
                'meta_operador' => $catalog->meta_operador,
                'meta_reglas' => $catalog->meta_reglas,
                'umbral_inferior' => $catalog->umbral_inferior,
                'umbral_superior' => $catalog->umbral_superior,
                'formula_descripcion' => $catalog->formula_descripcion,
                'fuente_numerador' => $catalog->fuente_numerador,
                'fuente_denominador' => $catalog->fuente_denominador,
                'periodicidad' => $catalog->periodicidad,
                'nivel_aplicacion' => $catalog->nivel_aplicacion,
                'unidad_medida' => $catalog->unidad_medida,
                'decimales' => $catalog->decimales,
                'decimales_modo' => $catalog->decimales_modo,
                'calculation_method' => $catalog->calculation_method,
                'orden' => $catalog->orden,
                'estado' => $catalog->estado,
            ]);

            if ($catalog->formula !== null) {
                AdpIndicatorFormula::query()->create([
                    'adp_indicator_id' => $indicator->id,
                    'expression' => $catalog->formula->expression,
                    'description' => $catalog->formula->description,
                ]);
            }

            // Clone sensitivity config into the indicator as a sensitivity table
            if ($catalog->sensitivity_config !== null) {
                $config = $catalog->sensitivity_config;
                $table = AdpSensitivityTable::query()->create([
                    'adp_indicator_id' => $indicator->id,
                    'nombre' => $config['nombre'] ?? 'Sensibilidad por defecto',
                ]);

                foreach ($config['rows'] ?? [] as $row) {
                    AdpSensitivityTableRow::query()->create([
                        'adp_sensitivity_table_id' => $table->id,
                        'orden' => $row['orden'],
                        'valor_minimo' => $row['valor_minimo'],
                        'operador_minimo' => $row['operador_minimo'],
                        'valor_maximo' => $row['valor_maximo'],
                        'operador_maximo' => $row['operador_maximo'],
                        'cumplimiento' => $row['cumplimiento'],
                    ]);
                }
            }

            return $indicator;
        });
    }
}
