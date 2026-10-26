<?php

namespace App\Enums\Cen;

enum CenCenterEmergencyType: string
{
    case Case1 = 'Ambulatoria CEAR';
    case Case2 = 'Ambulatoria Especializada';
    case Case3 = 'Hospitalaria Especializada';
    case Case4 = 'No Aplica';
    case Case5 = 'Pendiente';
    case Case6 = 'Urgencia Ambulatoria';
    case Case7 = 'Urgencia Ambulatoria (SAPU)';
    case Case8 = 'Urgencia Ambulatoria (SAR)';
    case Case9 = 'Urgencia Ambulatoria (SUR)';
    case Case10 = 'Urgencia Ambulatoria privada';
    case Case11 = 'Urgencia Hospitalaria (UEH)';
    case Case12 = 'SAPU Dental';
    case Desconocido = 'Desconocido';
}
