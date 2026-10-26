<?php

namespace App\Services\Metas;

use App\Enums\Metas\EstadoConvenioEstablecimiento;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\MetEstablishmentAgreement;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MetEstablishmentAgreementService
{
    public function __construct(
        private readonly MetEstablishmentIndicatorConfigService $configService,
    ) {}

    public function find(
        int $centerId,
        int $yearId,
        int $indicatorTypeId,
    ): ?MetEstablishmentAgreement {
        return MetEstablishmentAgreement::query()
            ->where('cen_center_id', $centerId)
            ->where('year_id', $yearId)
            ->where('indicator_type_id', $indicatorTypeId)
            ->first();
    }

    public function estado(
        int $centerId,
        int $yearId,
        int $indicatorTypeId,
    ): EstadoConvenioEstablecimiento {
        $agreement = $this->find($centerId, $yearId, $indicatorTypeId);

        return $agreement?->estado ?? EstadoConvenioEstablecimiento::Borrador;
    }

    public function isEnviado(int $centerId, int $yearId, int $indicatorTypeId): bool
    {
        return $this->estado($centerId, $yearId, $indicatorTypeId) === EstadoConvenioEstablecimiento::Enviado;
    }

    public function isEditable(int $centerId, int $yearId, int $indicatorTypeId): bool
    {
        return ! $this->isEnviado($centerId, $yearId, $indicatorTypeId);
    }

    /**
     * @param  array<int, int>  $centerIds
     * @return Collection<int, MetEstablishmentAgreement>
     */
    public function agreementsByCenterId(
        int $indicatorTypeId,
        array $centerIds,
        int $yearId,
    ): Collection {
        if ($centerIds === []) {
            return collect();
        }

        return MetEstablishmentAgreement::query()
            ->where('indicator_type_id', $indicatorTypeId)
            ->where('year_id', $yearId)
            ->whereIn('cen_center_id', $centerIds)
            ->with('submittedBy')
            ->get()
            ->keyBy(fn (MetEstablishmentAgreement $a) => (int) $a->cen_center_id);
    }

    /**
     * @return list<array{id: int, codigo: string, nombre: ?string, message: string}>
     */
    public function incompleteUnitsForSubmit(
        int $centerId,
        int $yearId,
        int $indicatorTypeId,
        int $cenHieDependencyId,
        bool $usesInternalUnitMeasurement,
    ): array {
        if (! $usesInternalUnitMeasurement) {
            $issues = $this->configService->issuesByCenterId(
                $indicatorTypeId,
                [$centerId],
                $yearId,
                $cenHieDependencyId,
                false,
            );

            return $issues->has($centerId) ? [['id' => 0, 'codigo' => '', 'nombre' => null, 'message' => $issues->get($centerId)['message']]] : [];
        }

        return $this->configService
            ->unitStatusesForCenter($indicatorTypeId, $centerId, $yearId, $cenHieDependencyId)
            ->filter(fn (array $status) => ! $status['is_complete'])
            ->map(fn (array $status) => [
                'id' => $status['id'],
                'codigo' => $status['codigo'],
                'nombre' => $status['nombre'],
                'message' => $status['message'] ?? 'configuración incompleta',
            ])
            ->values()
            ->all();
    }

    public function assertCompleteForSubmit(
        int $centerId,
        int $yearId,
        int $indicatorTypeId,
        int $cenHieDependencyId,
        bool $usesInternalUnitMeasurement,
    ): void {
        $incomplete = $this->incompleteUnitsForSubmit(
            $centerId,
            $yearId,
            $indicatorTypeId,
            $cenHieDependencyId,
            $usesInternalUnitMeasurement,
        );

        if ($incomplete === []) {
            return;
        }

        if ($usesInternalUnitMeasurement) {
            $names = collect($incomplete)
                ->map(fn (array $u) => $u['nombre'] ?: $u['codigo'])
                ->filter()
                ->implode(', ');

            throw ValidationException::withMessages([
                'configGeneral' => "No se puede enviar: faltan unidades completas ({$names}).",
            ]);
        }

        throw ValidationException::withMessages([
            'configGeneral' => $incomplete[0]['message'],
        ]);
    }

    public function submit(
        int $centerId,
        int $yearId,
        int $indicatorTypeId,
        int $cenHieDependencyId,
        User $user,
    ): MetEstablishmentAgreement {
        $type = IndicatorType::query()->findOrFail($indicatorTypeId);

        $this->assertCompleteForSubmit(
            $centerId,
            $yearId,
            $indicatorTypeId,
            $cenHieDependencyId,
            $type->usesInternalUnitMeasurement(),
        );

        $agreement = $this->findOrNew($centerId, $yearId, $indicatorTypeId, $cenHieDependencyId);
        $agreement->estado = EstadoConvenioEstablecimiento::Enviado;
        $agreement->submitted_at = now();
        $agreement->submitted_by = $user->id;
        $agreement->save();

        return $agreement->fresh(['submittedBy']);
    }

    public function reopen(
        int $centerId,
        int $yearId,
        int $indicatorTypeId,
        int $cenHieDependencyId,
        User $user,
        string $motivo,
    ): MetEstablishmentAgreement {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw ValidationException::withMessages([
                'reopen_motivo' => 'El motivo de reapertura es obligatorio.',
            ]);
        }

        if (! $user->hasRole(['control-gestion', 'admin'])) {
            abort(403);
        }

        $setting = IndicatorTypeSetting::getData($indicatorTypeId);

        if (! $setting || ! $setting->etapa_vigente->permiteFormulacionConvenio()) {
            throw ValidationException::withMessages([
                'reopen_motivo' => 'Solo se puede reabrir un convenio en etapa Formulación.',
            ]);
        }

        $agreement = $this->find($centerId, $yearId, $indicatorTypeId);

        if ($agreement === null || ! $agreement->isEnviado()) {
            throw ValidationException::withMessages([
                'reopen_motivo' => 'Solo se puede reabrir un convenio enviado.',
            ]);
        }

        $agreement->estado = EstadoConvenioEstablecimiento::Borrador;
        $agreement->reopened_at = now();
        $agreement->reopened_by = $user->id;
        $agreement->reopen_motivo = $motivo;
        $agreement->cen_hie_dependency_id = $cenHieDependencyId;
        $agreement->save();

        return $agreement->fresh();
    }

    public function returnToBorradorForNewUnit(
        int $centerId,
        int $yearId,
        int $indicatorTypeId,
        int $cenHieDependencyId,
    ): ?MetEstablishmentAgreement {
        $agreement = $this->find($centerId, $yearId, $indicatorTypeId);

        if ($agreement === null || ! $agreement->isEnviado()) {
            return $agreement;
        }

        $agreement->estado = EstadoConvenioEstablecimiento::Borrador;
        $agreement->cen_hie_dependency_id = $cenHieDependencyId;
        $agreement->save();

        return $agreement;
    }

    private function findOrNew(
        int $centerId,
        int $yearId,
        int $indicatorTypeId,
        int $cenHieDependencyId,
    ): MetEstablishmentAgreement {
        $agreement = $this->find($centerId, $yearId, $indicatorTypeId);

        if ($agreement !== null) {
            $agreement->cen_hie_dependency_id = $cenHieDependencyId;

            return $agreement;
        }

        $agreement = new MetEstablishmentAgreement([
            'cen_center_id' => $centerId,
            'year_id' => $yearId,
            'indicator_type_id' => $indicatorTypeId,
            'cen_hie_dependency_id' => $cenHieDependencyId,
            'estado' => EstadoConvenioEstablecimiento::Borrador,
        ]);

        return $agreement;
    }
}
