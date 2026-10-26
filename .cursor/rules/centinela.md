---
description: Índice de reglas Cursor — Centinela (fuentes de verdad del proyecto)
alwaysApply: true
---

# Reglas del proyecto (Centinela) — índice

Reglas vinculantes para Cursor. Este archivo enlaza fuentes de verdad del producto.

## Reglas Cursor (capas)

| Regla                                                      | Alcance                                                 | Carga            |
| ---------------------------------------------------------- | ------------------------------------------------------- | ---------------- |
| [`laravel-boost.mdc`](laravel-boost.mdc)                   | Laravel Boost (auto-generado; no editar)                | alwaysApply      |
| [`safe-migrations.mdc`](safe-migrations.mdc)               | Guard migraciones destructivas                          | alwaysApply      |
| [`strangler-fig.mdc`](strangler-fig.mdc)                   | Pausa de plataforma Filament / admin Voyager            | contextual       |
| [`php-intelephense-types.mdc`](php-intelephense-types.mdc) | Tipado IDE en PHP                                       | globs `**/*.php` |
| [`db-change.md`](db-change.md)                             | Flujo spec/migración para cambios de BD                 | globs PHP/migrations |

## Fuentes de verdad (leer antes de proponer cambios)

- Arquitectura y convenciones: `AGENTS.md`, `.github/copilot-instructions.md`
- Reglas de negocio: `.github/docs/business/domain-rules.md`
- Template PR: `.github/PULL_REQUEST_TEMPLATE.md`

## Definition of Done mínimo

CI verde, tests del alcance, Pint en archivos sucios, y reglas de `domain-rules.md` aplicables al cambio.
