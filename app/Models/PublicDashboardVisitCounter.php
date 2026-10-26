<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Total de visitas a GET / del dashboard público.
 * Una fila. El incremento va por query builder para no disparar auditoría (sin IP ni usuario).
 */
class PublicDashboardVisitCounter extends Model
{
    public $timestamps = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'total',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'total' => 'integer',
    ];

    public static function currentTotal(): int
    {
        $total = static::query()->orderBy('id')->value('total');

        if (! is_numeric($total)) {
            return 0;
        }

        return (int) $total;
    }

    public static function recordVisit(): void
    {
        $updated = static::query()->increment('total');

        if ($updated === 0) {
            static::query()->create([
                'total' => 1,
            ]);
        }
    }
}
