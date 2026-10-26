<?php

namespace App\Models;

use App\Enums\Metas\EstadoConvenioEstablecimiento;
use App\Models\Concerns\BelongsToHealthService;
use App\Models\Concerns\FiltersByActiveYear;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use OwenIt\Auditing\Contracts\Auditable;

class MetEstablishmentAgreement extends Model implements Auditable
{
    use BelongsToHealthService;
    use FiltersByActiveYear;
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    public const LEGACY_SIGNATURE_LABEL = 'Convenio registrado antes de la firma digital';

    protected $table = 'met_establishment_agreements';

    protected $fillable = [
        'cen_center_id',
        'year_id',
        'indicator_type_id',
        'cen_hie_dependency_id',
        'estado',
        'submitted_at',
        'submitted_by',
        'reopened_at',
        'reopened_by',
        'reopen_motivo',
    ];

    protected $casts = [
        'estado' => EstadoConvenioEstablecimiento::class,
        'submitted_at' => 'datetime',
        'reopened_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $agreement): void {
            if (empty($agreement->cen_hie_dependency_id) && Auth::check()) {
                $agreement->cen_hie_dependency_id = Auth::user()->cen_hie_dependency_id;
            }

            if (empty($agreement->year_id)) {
                $year = Session::get('indicator_year');
                if ($year instanceof Year) {
                    $agreement->year_id = $year->id;
                }
            }
        });
    }

    public function isEnviado(): bool
    {
        return $this->estado === EstadoConvenioEstablecimiento::Enviado;
    }

    public function isLegacySignature(): bool
    {
        return $this->isEnviado() && $this->submitted_by === null;
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(CenCenter::class, 'cen_center_id');
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    public function indicatorType(): BelongsTo
    {
        return $this->belongsTo(IndicatorType::class);
    }

    public function cenHieDependency(): BelongsTo
    {
        return $this->belongsTo(CenHieDependency::class, 'cen_hie_dependency_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }
}
