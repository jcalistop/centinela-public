<?php

namespace App\Enums\Cen;

enum CenCenterCertification: string
{
    case Case1 = 'No Aplica';
    case Case2 = 'Centro de Salud Familiar';
    case Case3 = 'Autogestionado en Red ( EAR)';
    case Case4 = 'Invitados a ser EAR';
    case Case5 = 'No certificado como CESFAM';
    case Case6 = 'NO';
    case Desconocido = 'Desconocido';
}
