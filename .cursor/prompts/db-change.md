# Plantilla de prompt — DB Change (Centinela)

Usa esto cuando el cambio afecte **esquema o datos** (aunque sea pequeño).

## Contexto

- **Alcance documentado**: (qué cambia y qué no)
- **Decisión de arquitectura** (opcional): solo si es transversal (afecta más de un módulo o cambia una convención global).
- **Alcance técnico**:
    - Tablas:
    - Columnas/índices/constraints:
    - Impacto esperado:

## Restricciones (obligatorias)

- **No asumir** que `database/migrations/` refleja el esquema real (histórico Voyager).
- No ejecutar ni entregar DDL directo (`ALTER/DROP/CREATE`) salvo solicitud explícita.
- Debes entregar primero **propuesta documentada** (decisión + alcance + rollback) con:
    - riesgos
    - plan de rollback
    - plan de validación post-ejecución (técnica + funcional)
    - pasos de despliegue: orquestador interno + `php artisan migrate --force` por defecto; DDL manual solo documentado en nota de release (sin scripts `deploy-v*.sh` en repo).

## Solicitud

1. Redacta contexto/problema, decisión, alcance técnico, riesgos, rollback y validación.
2. Si la decisión es transversal, propón alternativas y riesgos.
3. Identifica qué partes del código quedarían impactadas por el cambio de BD.
