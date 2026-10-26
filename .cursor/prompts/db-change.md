# Plantilla de prompt — DB Change (Centinela)

Usa esto cuando el cambio afecte **esquema o datos** (aunque sea pequeño).

## Contexto

- **Spec ID**: SDD-??? (en `.github/docs/sdd/specs/<dominio>/`)
- **Dominio**: metas | adp | comges | chat-ia | supervision | auth | ux | infra | docs
- **ADR** (opcional): `.github/docs/sdd/adr/ADR-NNN-YYYY-MM-DD-...md`. Crear ADR SOLO si la decisión es arquitectónica y transversal (afecta >1 módulo o cambia convención global). Para `db-change` puramente local del módulo, la sección "Cambio de BD" del spec es suficiente. Ver `.github/docs/sdd/adr/README.md`.
- **Alcance**:
    - Tablas:
    - Columnas/índices/constraints:
    - Impacto esperado:

## Restricciones (obligatorias)

- **No asumir** que `database/migrations/` refleja el esquema real (histórico Voyager).
- No ejecutar ni entregar DDL directo (`ALTER/DROP/CREATE`) salvo solicitud explícita.
- Debes entregar primero **propuesta documentada** (spec + ADR si aplica) con:
    - riesgos
    - plan de rollback
    - plan de validación post-ejecución (técnica + funcional)
    - pasos de despliegue: orquestador interno (SDD-097) + `php artisan migrate --force` por defecto; DDL manual solo documentado en spec/nota de release (sin scripts `deploy-v*.sh` en repo — ver `.github/docs/sdd/releases/RUNBOOK.md` y `.github/docs/sdd-kit/profiles/laravel-voyager/deploy.md`).

## Solicitud

1. Redacta el contenido de la sección "Cambio de BD" del spec (tablas, riesgos, rollback, validación, pasos).
2. Si aplica ADR, propón decisión + alternativas + riesgos transversales.
3. Identifica qué partes del código quedarían impactadas por el cambio de BD.
4. Define cómo va a quedar el spec al cerrar (mover a `archive/<YYYY>/<dominio>/` y entrada en `releases/vX.Y.Z/release_vX.Y.Z.md`).
