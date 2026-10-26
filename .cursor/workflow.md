# Rutina diaria recomendada (Cursor + SDD)

Esta guía conecta Cursor con el flujo oficial del repo:

- SDD: `.github/docs/sdd/workflow.md`
- Config SDD: `.github/docs/sdd/sdd.config.yaml`
- Kit: `.github/docs/sdd-kit/` (submodule)
- Checklist DoD: `.github/docs/sdd/checklist-pr.md`
- PR template: `.github/PULL_REQUEST_TEMPLATE.md`
- Arquitectura/convenios: `AGENTS.md` + `.github/copilot-instructions.md`

## Antes de tocar código (Discovery → Draft → Ready)

- Ubica o crea el spec en `.github/docs/sdd/specs/<dominio>/SDD-NNN-slug.md` (dominios: metas, adp, comges, chat-ia, supervision, auth, ux, infra, docs). Plantilla en `.github/docs/sdd/templates/spec-template.md`.
- Si el ítem viene del `BACKLOG.md` sin ID, asígnale `SDD-NNN` correlativo antes de crear el archivo.
- Completa **Definition of Ready** (DoR) del spec:
    - declara tipo (feature/bugfix/refactor/performance/db-change/documentation)
    - asigna dominio, owner, prioridad y versión objetivo
    - define impacto por año (`ano < 2025` / `ano >= 2025`)
    - define impacto por `indicatorType` (si aplica)
    - define impacto por `cen_hie_dependency_id` (si aplica)
    - decide si la decisión es transversal y requiere ADR (ver `.github/docs/sdd/adr/README.md`)

## Durante build (cambios pequeños, contexto explícito)

- Trabaja con PRs pequeños; evita mezclar refactor + feature + db-change sin justificación.
- En Cursor, usa la plantilla: `.cursor/prompts/feature-bugfix.md` para pedir cambios al agente.
- Siempre que toques COMGES, valida la bifurcación por año e instrumento antes de “simplificar”.

## Antes de abrir PR (Validation)

- Completa el checklist: `.github/docs/sdd/checklist-pr.md`
- Verifica mínimo:
    - `php artisan test`
    - `vendor/bin/pint --dirty`
    - sin `dd()/dump()/var_dump()/console.log()`
- Pega el resultado/estado en el PR usando `.github/PULL_REQUEST_TEMPLATE.md`.

## Release y archivado

- Agrega entrada de release note en `.github/docs/sdd/releases/vX.Y.Z/release_vX.Y.Z.md` y mantén la carpeta de versión (plantilla en `.github/docs/sdd/templates/release-template.md`; índice `.github/docs/sdd/releases/README.md`).
- Mueve el spec con `git mv` desde `specs/<dominio>/` a `archive/<YYYY>/<dominio>/`.
- Actualiza la fila correspondiente en `.github/docs/sdd/BACKLOG.md` (Released).
- Si hubo `db-change`:
    - Documenta el "Cambio de BD" en el spec (sección obligatoria).
    - Crea ADR solo si la decisión es transversal.
    - Documenta pasos de despliegue (por defecto `php artisan migrate --force`) + validación post-deploy.
