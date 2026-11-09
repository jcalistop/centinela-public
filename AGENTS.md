# Centinela — Agent instructions

## Qué es

Plataforma de monitoreo de instrumentos MINSAL (COMGES, Metas, establecimientos SNSS, dashboard público y mensajería). Admin visible = Voyager `/admin`.

## Stack (según `composer.lock`)

PHP 8.3 · Laravel 11 · Livewire 4 · Voyager 1.8 · Bootstrap 5 / SASS · **no** Tailwind · Laragon (**no** Herd).

## Precedencia (este orden gana)

1. Reglas de negocio en el taller del workspace (`domain-rules.md`) — primera fuente
2. Convenciones Centinela — este archivo + `.github/copilot-instructions.md` (sin dump Boost)
3. Laravel Boost — `.cursor/rules/laravel-boost.mdc` (auto-generado; **cede** si choca con 1 o 2)

## Invariantes

- Año activo: `YearContext` / `session('indicator_year')`.
- Tenancy SS: filtrar por `cen_hie_dependency_id` del usuario.
- Bifurcación COMGES: flujos distintos para año `< 2025` vs `≥ 2025`.
- No sobrescribir `.env` local (ver sección siguiente).

## Punteros

| Artefacto | Rol |
| --------- | --- |
| Reglas de negocio en el taller del workspace | Negocio (primera fuente; no versionadas en este repo) |
| [`.github/copilot-instructions.md`](.github/copilot-instructions.md) | Guía de producto / arquitectura |
| [`.cursor/rules/centinela.md`](.cursor/rules/centinela.md) | Índice de reglas Cursor |
| [`CONTRIBUTING.md`](CONTRIBUTING.md) | Contribución humana |
| [`.cursor/rules/laravel-boost.mdc`](.cursor/rules/laravel-boost.mdc) | Boost (Cursor); no editar a mano ni duplicar aquí |

## Local `.env` (mandatory for this workspace)

- Do **not** run `cp .env.example .env`, `copy .env.example .env`, `Copy-Item .env.example .env -Force`, or any equivalent that overwrites `.env`, unless the user explicitly asks and accepts the risk (prefer a backup or editor local history first).
- `php artisan test` / PHPUnit use environment values from `phpunit.xml`; copying `.env.example` onto `.env` is **not** required to run tests locally.
- GitHub Actions copies `.env.example` to `.env` **only on the remote runner**. Do **not** replicate that on a developer machine for the workspace that holds real secrets—use a separate clone or directory if you must mimic CI by hand.
