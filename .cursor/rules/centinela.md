---
description: Índice de reglas Cursor — Centinela (fuentes de verdad del proyecto)
alwaysApply: true
---

# Reglas del proyecto (Centinela) — índice

Reglas vinculantes para Cursor. Este archivo enlaza fuentes de verdad del producto.

## Precedencia (este orden gana)

1. Reglas de negocio en el taller del workspace (`domain-rules.md`) — primera fuente
2. Convenciones Centinela — `AGENTS.md` + `.github/copilot-instructions.md`
3. Laravel Boost — `laravel-boost.mdc` (auto-generado; **cede** si choca con 1 o 2)

## Reglas Cursor (capas)

| Regla                                                      | Alcance                                                 | Carga            |
| ---------------------------------------------------------- | ------------------------------------------------------- | ---------------- |
| [`laravel-boost.mdc`](laravel-boost.mdc)                   | Laravel Boost (auto-generado; no editar)                | alwaysApply      |
| [`safe-migrations.mdc`](safe-migrations.mdc)               | Guard migraciones destructivas                          | alwaysApply      |
| [`strangler-fig.mdc`](strangler-fig.mdc)                   | Pausa de plataforma Filament / admin Voyager            | contextual       |
| [`php-intelephense-types.mdc`](php-intelephense-types.mdc) | Tipado IDE en PHP                                       | globs `**/*.php` |
| [`db-change.md`](db-change.md)                             | Flujo de migración para cambios de BD                   | globs PHP/migrations |

## Fuentes de verdad (leer antes de proponer cambios)

- Reglas de negocio: taller del workspace (`domain-rules.md`; no path local en este repo)
- Arquitectura y convenciones: `AGENTS.md`, `.github/copilot-instructions.md`
- Template PR: `.github/PULL_REQUEST_TEMPLATE.md`

## Checklist de calidad mínimo

CI verde, tests del alcance, Pint en archivos sucios, y reglas de `domain-rules.md` aplicables al cambio.
