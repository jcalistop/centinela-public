<?php

use App\Models\IndicatorType;
use App\Models\MetCenterInclusion;
use App\Models\MetEstablishmentAgreement;
use App\Services\Metas\MetEstablishmentIndicatorConfigService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $configService = app(MetEstablishmentIndicatorConfigService::class);

        $types = IndicatorType::query()
            ->where('route', 'like', 'metas-%')
            ->get(['id', 'route']);

        foreach ($types as $type) {
            $usesInternalUnits = $type->usesInternalUnitMeasurement();

            $scopes = MetCenterInclusion::query()
                ->where('indicator_type_id', $type->id)
                ->select('year_id', 'cen_hie_dependency_id')
                ->distinct()
                ->get();

            foreach ($scopes as $scope) {
                $centerIds = MetCenterInclusion::query()
                    ->where('indicator_type_id', $type->id)
                    ->where('year_id', $scope->year_id)
                    ->where('cen_hie_dependency_id', $scope->cen_hie_dependency_id)
                    ->pluck('cen_center_id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();

                if ($centerIds === []) {
                    continue;
                }

                $issues = $configService->issuesByCenterId(
                    (int) $type->id,
                    $centerIds,
                    (int) $scope->year_id,
                    (int) $scope->cen_hie_dependency_id,
                    $usesInternalUnits,
                );

                foreach ($centerIds as $centerId) {
                    if ($issues->has($centerId)) {
                        continue;
                    }

                    $exists = MetEstablishmentAgreement::query()
                        ->where('cen_center_id', $centerId)
                        ->where('year_id', (int) $scope->year_id)
                        ->where('indicator_type_id', (int) $type->id)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    MetEstablishmentAgreement::query()->create([
                        'cen_center_id' => $centerId,
                        'year_id' => (int) $scope->year_id,
                        'indicator_type_id' => (int) $type->id,
                        'cen_hie_dependency_id' => (int) $scope->cen_hie_dependency_id,
                        'estado' => 'ENVIADO',
                        'submitted_at' => $now,
                        'submitted_by' => null,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('met_establishment_agreements')
            ->whereNull('submitted_by')
            ->whereNull('reopened_at')
            ->where('estado', 'ENVIADO')
            ->delete();
    }
};
