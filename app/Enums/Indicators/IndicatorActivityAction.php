<?php

namespace App\Enums\Indicators;

enum IndicatorActivityAction: string
{
    case ConfigChanged = 'config_changed';
    case SensitivitySelected = 'sensitivity_selected';
    case EvaluationLoaded = 'evaluation_loaded';
    case VerificationLoaded = 'verification_loaded';
    case AppealLoaded = 'appeal_loaded';
    case ReferentAssigned = 'referent_assigned';
    case CenterApplied = 'center_applied';
    case DataLoaded = 'data_loaded';
    case InputAggregationChanged = 'input_aggregation_changed';
    case ManualNote = 'manual_note';

    public function label(): string
    {
        return match ($this) {
            self::ConfigChanged => 'Configuración',
            self::SensitivitySelected => 'Sensibilidad',
            self::EvaluationLoaded => 'Evaluación',
            self::VerificationLoaded => 'Medio de verificación',
            self::AppealLoaded => 'Apelación',
            self::ReferentAssigned => 'Referente',
            self::CenterApplied => 'Aplicación establecimiento',
            self::DataLoaded => 'Carga de datos',
            self::InputAggregationChanged => 'Tipo entrada / agregación',
            self::ManualNote => 'Nota manual',
        };
    }

    public function iconClass(): string
    {
        return match ($this) {
            self::ConfigChanged => 'voyager-settings',
            self::SensitivitySelected => 'voyager-params',
            self::EvaluationLoaded => 'voyager-check',
            self::VerificationLoaded => 'voyager-file-text',
            self::AppealLoaded => 'voyager-warning',
            self::ReferentAssigned => 'voyager-person',
            self::CenterApplied => 'voyager-company',
            self::DataLoaded => 'voyager-upload',
            self::InputAggregationChanged => 'voyager-list',
            self::ManualNote => 'voyager-edit',
        };
    }
}
