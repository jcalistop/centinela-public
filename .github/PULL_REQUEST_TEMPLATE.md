## Cómo usar esta plantilla

1. **Rama destino:** el PR debe ir contra **`dev`**, salvo hotfix acordado contra `main` (ver [`CONTRIBUTING.md`](../CONTRIBUTING.md)).
2. **Marcar casillas** `[x]` solo cuando el criterio esté verificado; si no aplica, indicar **No aplica** en el comentario del PR o junto al ítem.
3. **CI remoto:** el workflow [`.github/workflows/ci.yml`](workflows/ci.yml) corre en push y PR hacia `dev` y `main`; antes de mergear, el último run del PR debe estar **en verde**.

## Resumen del cambio

_2-4 líneas describiendo qué hace este PR y por qué._

## Definition of Done (marcar antes de mergear)

### Calidad técnica

- [ ] `php artisan test --compact` pasa sin errores (o **No aplica** — solo docs)
- [ ] `vendor/bin/pint --dirty` sin diferencias (o **No aplica** — sin PHP sucio)
- [ ] Sin `dd()`, `dump()`, `var_dump()`, `console.log()` de debug

### Dominio Centinela

- [ ] Bifurcación `ano < 2025` / `ano >= 2025` verificada (o "No aplica")
- [ ] Filtros por `cen_hie_dependency_id` verificados (o "No aplica")
- [ ] Autorización por roles (`admin` / `parametrizador` / `control-gestion` / `referente` / `visualizador`) verificada (o "No aplica")
- [ ] Gates `current-add-edit` / `current-download` verificados (o "No aplica")

### `IndicatorType` / rutas dinámicas (si aplica)

- [ ] Caché `indicator_type_routes` invalidado en producción al desplegar
- [ ] Si se agregó ruta con `view` o `controller`: probada en navegador

### Cambio de BD (si aplica)

- [ ] Migración Laravel en `database/migrations/`
- [ ] DDL manual documentado en nota de release (Centinela no usa scripts `deploy-v*.sh` en repo; orquestador interno)
- [ ] Validación post-ejecución completada (smoke-test + datos intactos)
- [ ] Rollback documentado y probado (o descartado con justificación)

### Validación funcional

- [ ] Happy path verificado manualmente en dev/staging
- [ ] Error path / casos límite verificados
- [ ] Sin regresiones visibles en flujos adyacentes

### Release

- [ ] Notas de versión / tag SemVer actualizados si cierra una campaña
- [ ] Plan de rollback descrito abajo

## Capturas / evidencia manual

_Capturas de pantalla, comandos ejecutados, salidas de tests relevantes._

-

## Plan de rollback

_Cómo revertir si falla en producción (revert del PR, restore de BD, etc.)._

-

## Riesgos residuales / deuda

_Lo que no se pudo cerrar y queda pendiente._
