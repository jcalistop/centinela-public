---
description: Flujo para cambios de esquema o datos (Centinela)
globs:
    - "**/*.php"
    - "database/migrations/**/*"
alwaysApply: false
---

# Regla específica — Cambios de BD (Centinela)

Si un cambio es de esquema o datos, el orden de trabajo es:

1. **Documentar** el cambio (contexto/problema, tablas afectadas, riesgos, rollback, validación) antes de escribir la migración.
2. **Decisión transversal** solo si cambia una convención global, afecta más de un módulo, introduce dependencia externa, o reemplaza una decisión previa. Un cambio local (FK, índice, columna, enum, tabla auxiliar de un módulo) no exige ese extra.
3. Implementación en código (migración Laravel en `database/migrations/`) y validación.
4. **Deploy**: orquestador interno (panel Deploy `/admin`) + backup + `php artisan migrate --force`. DDL manual o comandos one-off: documentar en la nota de release — **este repositorio no usa scripts `deploy-v*.sh`**.
5. Registrar el cambio en las notas de la versión.

No asumir que `database/migrations/` refleja el esquema real: revisar también la tabla `centinela_dependency_*` si la versión de despliegue lo amerita.
