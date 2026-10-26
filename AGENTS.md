# Centinela — Agent instructions

## Laravel Boost

Guidelines de Laravel Boost (versiones, convenciones, MCP, PHPUnit, Livewire): cargados por Cursor desde [`.cursor/rules/laravel-boost.mdc`](.cursor/rules/laravel-boost.mdc).

**No duplicar aquí.** Upgrades de Laravel Boost regeneran ese archivo. Índice de reglas locales (Voyager/Livewire, migraciones): [`.cursor/rules/centinela.md`](.cursor/rules/centinela.md).

Dominio de negocio y arquitectura COMGES/Metas: [`.github/copilot-instructions.md`](.github/copilot-instructions.md).

## Local `.env` (mandatory for this workspace)

- Do **not** run `cp .env.example .env`, `copy .env.example .env`, `Copy-Item .env.example .env -Force`, or any equivalent that overwrites `.env`, unless the user explicitly asks and accepts the risk (prefer a backup or editor local history first).
- `php artisan test` / PHPUnit use environment values from `phpunit.xml`; copying `.env.example` onto `.env` is **not** required to run tests locally.
- GitHub Actions copies `.env.example` to `.env` **only on the remote runner**. Do **not** replicate that on a developer machine for the workspace that holds real secrets—use a separate clone or directory if you must mimic CI by hand.
