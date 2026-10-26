<?php

namespace App\Enums\Cen;

use App\Models\CenCenterSyncRun;

enum CenCenterSyncRunChangeAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case SoftDeleted = 'soft_deleted';
    case Skipped = 'skipped';
    case Restored = 'restored';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Creados',
            self::Updated => 'Actualizados',
            self::SoftDeleted => 'Baja',
            self::Skipped => 'Omitidos',
            self::Restored => 'Restaurados',
        };
    }

    public function runCountColumn(): string
    {
        return match ($this) {
            self::Created => 'created_count',
            self::Updated => 'updated_count',
            self::SoftDeleted => 'soft_deleted_count',
            self::Skipped => 'skipped_count',
            self::Restored => 'restored_count',
        };
    }

    public function countForRun(CenCenterSyncRun $run): int
    {
        return (int) ($run->{$this->runCountColumn()} ?? 0);
    }

    /**
     * Primera pestaña con actividad según totales persistidos en la corrida (misma fuente que el historial).
     */
    public static function defaultForRun(CenCenterSyncRun $run): self
    {
        foreach ([self::Updated, self::Created, self::SoftDeleted, self::Skipped, self::Restored] as $action) {
            if ($action->countForRun($run) > 0) {
                return $action;
            }
        }

        return self::Created;
    }
}
