<?php

namespace App\Support\Alerts;

enum VisualAlertPlacement: string
{
    case CutoffLabel = 'cutoff_label';
    case EvaluationAuto = 'evaluation_auto';
    case EvaluationPreliminar = 'evaluation_preliminar';
    case DashboardList = 'dashboard_list';
}
