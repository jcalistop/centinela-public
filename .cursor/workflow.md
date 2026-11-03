# Rutina diaria (Cursor + producto)

Esta guía conecta Cursor con el flujo de contribución del repo público:

- Contribución: `CONTRIBUTING.md`
- Reglas de negocio: `.github/docs/business/domain-rules.md`
- Plantilla PR: `.github/PULL_REQUEST_TEMPLATE.md`
- Arquitectura/convenios: `AGENTS.md` + `.github/copilot-instructions.md`

## Antes de tocar código

- Acota el alcance por escrito (qué cambia y qué no).
- Si tocas COMGES, valida la bifurcación por año e instrumento antes de simplificar.
- Cambios de BD: documentar decisión, alcance y rollback (ver `.cursor/rules/db-change.md`).

## Antes de abrir PR

- `php artisan test` del alcance
- `vendor/bin/pint --dirty`
- Completar `.github/PULL_REQUEST_TEMPLATE.md`
