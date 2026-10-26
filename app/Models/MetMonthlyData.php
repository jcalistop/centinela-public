<?php

namespace App\Models;

use App\Enums\Metas\DataFieldInputType;
use App\Models\Concerns\BelongsToHealthService;
use App\Models\Concerns\FiltersByActiveYear;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use OwenIt\Auditing\Contracts\Auditable;

class MetMonthlyData extends Model implements Auditable
{
    use BelongsToHealthService;
    use FiltersByActiveYear;
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'met_data_field_id',
        'cen_center_id',
        'cen_hie_dependency_id',
        'year_id',
        'met_measurement_unit_id',
        'measurement_unit_scope_key',
        'mes',
        'valor',
        'observacion',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'met_data_field_id' => 'integer',
        'cen_center_id' => 'integer',
        'cen_hie_dependency_id' => 'integer',
        'year_id' => 'integer',
        'met_measurement_unit_id' => 'integer',
        'measurement_unit_scope_key' => 'integer',
        'mes' => 'integer',
        'valor' => 'decimal:4',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (MetMonthlyData $monthlyData) {
            $monthlyData->measurement_unit_scope_key = (int) ($monthlyData->met_measurement_unit_id ?? 0);

            if (empty($monthlyData->cen_hie_dependency_id) && Auth::check()) {
                $user = Auth::user();
                if ($user instanceof User) {
                    $monthlyData->cen_hie_dependency_id = $user->cen_hie_dependency_id;
                }
            }

            if (empty($monthlyData->year_id)) {
                $year = Session::get('indicator_year');
                if ($year instanceof Year) {
                    $monthlyData->year_id = $year->id;
                }
            }

            if (Auth::check()) {
                if (! $monthlyData->exists) {
                    $monthlyData->created_by = Auth::id();
                }

                $monthlyData->updated_by = Auth::id();
            }

            $monthlyData->validateMonthConsistency();
        });
    }

    private function validateMonthConsistency(): void
    {
        /** @var MetDataField|null $field */
        $field = $this->relationLoaded('dataField')
            ? $this->getRelation('dataField')
            : $this->dataField()->first();

        if (! $field) {
            return;
        }

        $inputType = $this->resolveEffectiveInputType($field);

        if ($inputType === DataFieldInputType::LineaBase && $this->mes !== null) {
            throw ValidationException::withMessages([
                'mes' => 'Un campo de línea base debe guardarse con mes nulo.',
            ]);
        }

        if ($inputType === DataFieldInputType::Mensual && $this->mes === null) {
            throw ValidationException::withMessages([
                'mes' => 'Un campo mensual debe guardarse con mes entre 1 y 12.',
            ]);
        }

        if ($inputType === DataFieldInputType::Trimestral && ! in_array($this->mes, [3, 6, 9, 12], true)) {
            throw ValidationException::withMessages([
                'mes' => 'Un campo trimestral debe guardarse con mes 3 (marzo), 6 (junio), 9 (septiembre) o 12 (diciembre).',
            ]);
        }

        if ($inputType === DataFieldInputType::Semestral && ! in_array($this->mes, [6, 12], true)) {
            throw ValidationException::withMessages([
                'mes' => 'Un campo semestral debe guardarse con mes 6 (junio) o 12 (diciembre).',
            ]);
        }

        if ($inputType === DataFieldInputType::Anual && $this->mes !== 12) {
            throw ValidationException::withMessages([
                'mes' => 'Un campo anual debe guardarse con mes 12 (diciembre).',
            ]);
        }
    }

    private function resolveEffectiveInputType(MetDataField $field): DataFieldInputType
    {
        $ssId = $this->cen_hie_dependency_id;
        $yearId = $this->year_id;

        if ($ssId !== null && $yearId !== null) {
            return $field->resolveInputType((int) $ssId, (int) $yearId);
        }

        return $field->input_type;
    }

    public function dataField(): BelongsTo
    {
        return $this->belongsTo(MetDataField::class, 'met_data_field_id');
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(CenCenter::class, 'cen_center_id');
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    public function cenHieDependency(): BelongsTo
    {
        return $this->belongsTo(CenHieDependency::class, 'cen_hie_dependency_id');
    }

    public function measurementUnit(): BelongsTo
    {
        return $this->belongsTo(MetMeasurementUnit::class, 'met_measurement_unit_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
