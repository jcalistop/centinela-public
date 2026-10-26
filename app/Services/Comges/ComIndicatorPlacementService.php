<?php

namespace App\Services\Comges;

use App\Models\ComCommitment;
use App\Models\ComIndicator;
use App\Models\Year;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ComIndicatorPlacementService
{
    public function nextOrderForCommitment(int $commitmentId): int
    {
        $max = ComIndicator::query()
            ->where('com_commitment_id', $commitmentId)
            ->max('orden');

        return (int) $max + 1;
    }

    public function assignOrderOnCreate(ComIndicator $indicator): void
    {
        $commitmentId = (int) $indicator->com_commitment_id;

        if ($commitmentId <= 0) {
            return;
        }

        $indicator->orden = $this->nextOrderForCommitment($commitmentId);
        $indicator->save();
    }

    public function placeAtEndOfCommitment(ComIndicator $indicator, int $commitmentId): void
    {
        $indicator->com_commitment_id = $commitmentId;
        $indicator->orden = $this->nextOrderForCommitment($commitmentId);
        $indicator->save();
    }

    /**
     * @param  array<int, array{id: int|string, indicator_ids: array<int, int|string>}>  $commitmentsPayload
     *
     * @throws ValidationException
     */
    public function applyStructure(array $commitmentsPayload, Year $sessionYear): void
    {
        $payloadIndicatorIds = [];
        $commitmentIdsInPayload = [];

        foreach ($commitmentsPayload as $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages([
                    'structure' => 'Estructura de ordenación inválida.',
                ]);
            }

            $commitmentId = (int) ($row['id'] ?? 0);
            $commitmentIdsInPayload[] = $commitmentId;

            foreach ($row['indicator_ids'] ?? [] as $indicatorId) {
                $payloadIndicatorIds[] = (int) $indicatorId;
            }
        }

        if ($payloadIndicatorIds !== [] && count($payloadIndicatorIds) !== count(array_unique($payloadIndicatorIds))) {
            throw ValidationException::withMessages([
                'structure' => 'Hay indicadores duplicados en la estructura enviada.',
            ]);
        }

        $scopeIndicatorIds = ComIndicator::query()
            ->whereHas('comCommitment', fn ($query) => $query->where('year_id', $sessionYear->id))
            ->orderBy('com_commitment_id')
            ->orderBy('orden')
            ->orderBy('clave')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $sortedPayload = $payloadIndicatorIds;
        sort($sortedPayload);
        $sortedScope = $scopeIndicatorIds;
        sort($sortedScope);

        if ($sortedPayload !== $sortedScope) {
            throw ValidationException::withMessages([
                'structure' => 'La estructura no coincide con los indicadores del año activo.',
            ]);
        }

        $validCommitmentIds = ComCommitment::query()
            ->where('year_id', $sessionYear->id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($commitmentIdsInPayload as $commitmentId) {
            if (! in_array($commitmentId, $validCommitmentIds, true)) {
                throw ValidationException::withMessages([
                    'structure' => 'Uno o más compromisos no pertenecen al año activo.',
                ]);
            }
        }

        DB::transaction(function () use ($commitmentsPayload): void {
            foreach ($commitmentsPayload as $row) {
                $commitmentId = (int) ($row['id'] ?? 0);

                foreach (array_values($row['indicator_ids'] ?? []) as $index => $indicatorId) {
                    ComIndicator::query()
                        ->whereKey((int) $indicatorId)
                        ->update([
                            'com_commitment_id' => $commitmentId,
                            'orden' => $index + 1,
                        ]);
                }
            }
        });
    }
}
