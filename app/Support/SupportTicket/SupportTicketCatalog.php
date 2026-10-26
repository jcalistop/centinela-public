<?php

namespace App\Support\SupportTicket;

use App\Enums\SupportTicketArea;

/**
 * Catálogo de áreas y orientación para el módulo Soporte (SDD-048).
 */
final class SupportTicketCatalog
{
    /**
     * @return array<int, string>
     */
    public static function guidanceItemsForArea(SupportTicketArea $area): array
    {
        return match ($area) {
            SupportTicketArea::Tecnico => [
                'Errores o fallas de la plataforma',
                'Problemas de rendimiento (demora en la carga de datos, etc.)',
                'Archivos que no se descargan o suben correctamente',
                'Ideas y propuestas de mejora técnica',
                'Cualquier otro problema o consulta técnica relacionada con el uso de la plataforma',
            ],
            SupportTicketArea::Gestion => [
                'Creación y permisos de usuarios',
                'Problemas con resultados o indicadores',
                'Procesos, plazos o calendario operativo',
                'Asignaciones y responsables del Servicio de Salud',
                'Cualquier otro problema o consulta relacionada con su Servicio de Salud',
            ],
        };
    }

    public static function guidanceHeadingForArea(SupportTicketArea $area): string
    {
        return match ($area) {
            SupportTicketArea::Tecnico => 'Comunícate con el área técnica cuando tu consulta esté relacionada con:',
            SupportTicketArea::Gestion => 'Comunícate con el área de gestión cuando tu consulta esté relacionada con:',
        };
    }

    public static function labelForArea(SupportTicketArea $area): string
    {
        return match ($area) {
            SupportTicketArea::Tecnico => 'Técnico',
            SupportTicketArea::Gestion => 'Gestión',
        };
    }
}
