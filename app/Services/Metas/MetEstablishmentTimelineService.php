<?php

namespace App\Services\Metas;

use App\Enums\Metas\EstadoConvenioEstablecimiento;
use App\Models\IndicatorType;
use App\Models\MetEstablishmentAgreement;
use App\Models\MetIndicator;
use App\Models\MetIndicatorAppeal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OwenIt\Auditing\Models\Audit;

class MetEstablishmentTimelineService
{
    public const PER_PAGE = 15;

    private const FETCH_CAP = 100;

    /**
     * @return LengthAwarePaginator<int, array{
     *     key: string,
     *     type: string,
     *     occurred_at: Carbon,
     *     title: string,
     *     detail: string|null,
     *     user_name: string|null,
     *     indicator_label: string|null,
     *     indicator_url: string|null
     * }>
     */
    public function paginate(
        int $centerId,
        int $yearId,
        int $indicatorTypeId,
        int $page = 1,
    ): LengthAwarePaginator {
        $events = $this->collectEvents($centerId, $yearId, $indicatorTypeId)
            ->sortByDesc(fn (array $event): int => $event['occurred_at']->getTimestamp())
            ->values();

        $total = $events->count();
        $slice = $events->forPage($page, self::PER_PAGE)->values();

        return new LengthAwarePaginator(
            $slice,
            $total,
            self::PER_PAGE,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'establishmentTimelinePage',
            ],
        );
    }

    /**
     * @return Collection<int, array{
     *     key: string,
     *     type: string,
     *     occurred_at: Carbon,
     *     title: string,
     *     detail: string|null,
     *     user_name: string|null,
     *     indicator_label: string|null,
     *     indicator_url: string|null
     * }>
     */
    public function collectEvents(int $centerId, int $yearId, int $indicatorTypeId): Collection
    {
        return $this->agreementEvents($centerId, $yearId, $indicatorTypeId)
            ->concat($this->appealEvents($centerId, $yearId, $indicatorTypeId));
    }

    /**
     * @return Collection<int, array{
     *     key: string,
     *     type: string,
     *     occurred_at: Carbon,
     *     title: string,
     *     detail: string|null,
     *     user_name: string|null,
     *     indicator_label: string|null,
     *     indicator_url: string|null
     * }>
     */
    private function agreementEvents(int $centerId, int $yearId, int $indicatorTypeId): Collection
    {
        $agreement = MetEstablishmentAgreement::query()
            ->where('cen_center_id', $centerId)
            ->where('year_id', $yearId)
            ->where('indicator_type_id', $indicatorTypeId)
            ->first();

        if ($agreement === null) {
            return collect();
        }

        $audits = Audit::query()
            ->where('auditable_type', MetEstablishmentAgreement::class)
            ->where('auditable_id', $agreement->id)
            ->orderByDesc('created_at')
            ->limit(self::FETCH_CAP)
            ->get();

        $userIds = $audits->pluck('user_id')->filter()->unique()->values();
        $users = User::query()->whereIn('id', $userIds)->get()->keyBy('id');

        return $audits
            ->map(function (Audit $audit) use ($users): ?array {
                $newValues = is_array($audit->new_values) ? $audit->new_values : [];
                $occurredAt = $audit->created_at instanceof Carbon
                    ? $audit->created_at
                    : Carbon::parse($audit->created_at);

                $userName = null;
                if ($audit->user_id !== null && $users->has((int) $audit->user_id)) {
                    $userName = $users->get((int) $audit->user_id)?->name;
                }

                if ($this->isReopenAudit($newValues)) {
                    $motivo = isset($newValues['reopen_motivo'])
                        ? trim((string) $newValues['reopen_motivo'])
                        : '';

                    return [
                        'key' => 'agreement-reopen-'.$audit->id,
                        'type' => 'agreement_reopened',
                        'occurred_at' => $occurredAt,
                        'title' => 'Convenio reabierto',
                        'detail' => $motivo !== '' ? 'Motivo: '.$motivo : null,
                        'user_name' => $userName,
                        'indicator_label' => null,
                        'indicator_url' => null,
                    ];
                }

                if ($this->isSubmitAudit($newValues)) {
                    return [
                        'key' => 'agreement-submit-'.$audit->id,
                        'type' => 'agreement_submitted',
                        'occurred_at' => $occurredAt,
                        'title' => 'Convenio enviado a SS',
                        'detail' => null,
                        'user_name' => $userName,
                        'indicator_label' => null,
                        'indicator_url' => null,
                    ];
                }

                return null;
            })
            ->filter()
            ->values();
    }

    /**
     * @param  array<string, mixed>  $newValues
     */
    private function isReopenAudit(array $newValues): bool
    {
        return array_key_exists('reopened_at', $newValues)
            || array_key_exists('reopen_motivo', $newValues)
            || array_key_exists('reopened_by', $newValues);
    }

    /**
     * @param  array<string, mixed>  $newValues
     */
    private function isSubmitAudit(array $newValues): bool
    {
        if (array_key_exists('submitted_at', $newValues) || array_key_exists('submitted_by', $newValues)) {
            return true;
        }

        $estado = $newValues['estado'] ?? null;

        if ($estado === null) {
            return false;
        }

        $value = $estado instanceof EstadoConvenioEstablecimiento
            ? $estado
            : EstadoConvenioEstablecimiento::tryFrom((string) $estado);

        return $value === EstadoConvenioEstablecimiento::Enviado;
    }

    /**
     * @return Collection<int, array{
     *     key: string,
     *     type: string,
     *     occurred_at: Carbon,
     *     title: string,
     *     detail: string|null,
     *     user_name: string|null,
     *     indicator_label: string|null,
     *     indicator_url: string|null
     * }>
     */
    private function appealEvents(int $centerId, int $yearId, int $indicatorTypeId): Collection
    {
        $indicatorIds = MetIndicator::query()
            ->where('indicator_type_id', $indicatorTypeId)
            ->pluck('id');

        if ($indicatorIds->isEmpty()) {
            return collect();
        }

        $appeals = MetIndicatorAppeal::query()
            ->where('cen_center_id', $centerId)
            ->where('year_id', $yearId)
            ->whereIn('met_indicator_id', $indicatorIds)
            ->with(['metIndicator', 'createdBy', 'updatedBy'])
            ->orderByDesc('id')
            ->limit(self::FETCH_CAP)
            ->get();

        $indicatorType = IndicatorType::query()->find($indicatorTypeId);
        $events = collect();

        foreach ($appeals as $appeal) {
            $indicator = $appeal->metIndicator;
            $label = $indicator
                ? trim(($indicator->clave ? $indicator->clave.' — ' : '').($indicator->nombre ?? ''))
                : null;
            $url = ($indicatorType && $indicator)
                ? route('metas.instrument.indicators.show', [$indicatorType, $indicator]).'#tab-bitacora'
                : null;

            $createdAt = $appeal->submitted_at ?? $appeal->created_at;
            if ($createdAt !== null) {
                $events->push([
                    'key' => 'appeal-created-'.$appeal->id,
                    'type' => 'appeal_created',
                    'occurred_at' => $createdAt instanceof Carbon ? $createdAt : Carbon::parse($createdAt),
                    'title' => 'Apelación registrada',
                    'detail' => $appeal->motivo ? Str::limit(trim((string) $appeal->motivo), 160) : null,
                    'user_name' => $appeal->createdBy?->name,
                    'indicator_label' => $label !== '' ? $label : null,
                    'indicator_url' => $url,
                ]);
            }

            if (
                $appeal->updated_at !== null
                && $appeal->created_at !== null
                && $appeal->updated_at->gt($appeal->created_at->copy()->addSecond())
            ) {
                $events->push([
                    'key' => 'appeal-updated-'.$appeal->id,
                    'type' => 'appeal_updated',
                    'occurred_at' => $appeal->updated_at,
                    'title' => 'Apelación actualizada',
                    'detail' => null,
                    'user_name' => $appeal->updatedBy?->name ?? $appeal->createdBy?->name,
                    'indicator_label' => $label !== '' ? $label : null,
                    'indicator_url' => $url,
                ]);
            }
        }

        return $events->values();
    }
}
