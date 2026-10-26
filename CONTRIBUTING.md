# Contribuir a Centinela

La obra en este repositorio está sujeta a la **EUPL-1.2**; titularidad y autoría en [`LICENSE.md`](LICENSE.md). Las contribuciones se entienden bajo la misma licencia.

## Español

### Rama base y PRs

- Abrí pull requests contra la rama **`dev`** (no contra `main`, salvo acuerdo explícito del equipo).
- Usá la plantilla de PR: `.github/PULL_REQUEST_TEMPLATE.md`.

### Convenciones Git

**Ramas**

- **`main`:** línea desplegada en producción (según proceso interno del equipo).
- **`dev`:** integración del trabajo en curso; la mayoría de PRs apuntan aquí.
- **`deps-integration`:** solo para **actualizaciones automáticas de Dependabot** (Composer, npm, GitHub Actions). Dependabot abre PRs con **base** `deps-integration`, no `dev`, para mantener limpia la cola de revisión en `dev`. Cuando el equipo revisa y prueba, se integra **`deps-integration` → `dev`** con un PR semanal. No usar la rama `dependabot` como línea de integración (conflicto de namespace con refs `dependabot/*`).
- Prefijos habituales en nombres de rama: `feature/`, `maintenance/`, `bugfix/` (flujo típico hacia `dev`).
- **`hotfix/`:** correcciones críticas; solo se fusionan directo a `main` si el equipo acuerda ese atajo (evitar ramas largas fuera de `dev`).

**Commits**

Formato recomendado: `tipo(alcance): descripción breve` (estilo [Conventional Commits](https://www.conventionalcommits.org/)).

Tipos frecuentes en este repo:

| Tipo       | Uso                                                                                    |
| ---------- | -------------------------------------------------------------------------------------- |
| `feat`     | Nueva funcionalidad                                                                    |
| `fix`      | Corrección de bug o error                                                              |
| `docs`     | Solo documentación o comentarios de documentación                                      |
| `style`    | Formato, ortografía en textos, sin cambio de lógica                                    |
| `refactor` | Reestructuración interna sin cambiar comportamiento                                    |
| `perf`     | Mejora de rendimiento                                                                  |
| `test`     | Tests nuevos o ajustes de tests                                                        |
| `chore`    | Mantenimiento (deps, CI, tooling); antes a veces se usaba `helper` en el mismo sentido |

**Versionado (SemVer) y tags**

- **MAJOR** (`X`.y.z): cambios importantes de API o de modelo de datos / compatibilidad.
- **MINOR** (x.**Y**.z): funcionalidad relevante compatible con versiones anteriores.
- **PATCH** (x.y.**Z**): correcciones o mejoras pequeñas compatibles.

Al cerrar una versión, conviene **etiquetar en el remoto** el commit acordado en `main` (por ejemplo `v5.8.0`), alineado con lo que declare el equipo en despliegue y comunicación.

### Calidad local (antes de pedir revisión)

- Tests: `php artisan test --compact`
- Estilo: `vendor/bin/pint --dirty` (o `vendor/bin/pint --test` para comprobar sin escribir)

### Despliegue

- **No hay despliegue automático** desde GitHub hacia el servidor de producción: el merge en `dev`/`main` no implica release en el entorno propio del MINSAL.

### Secretos y uso del repositorio

- **No** subas `.env` ni secretos al repositorio. Para política de reporte de vulnerabilidades, ver `SECURITY.md`.
- No versionar logs de producción, dumps, ni paths/credenciales de entornos reales. Usar placeholders en examples.

---

## English (short)

- **License:** EUPL-1.2. See [`LICENSE.md`](LICENSE.md) (ownership: Servicio de Salud del Reloncaví; copyright: José Luis Calisto Peñafiel). Contributions are under the same license.
- Open PRs against **`dev`** unless the team agrees otherwise.
- **Git:** `main` is production line; `dev` is integration. **`deps-integration`** is the Dependabot integration branch (PRs from Dependabot target `deps-integration`, not `dev`); merge `deps-integration` → `dev` when ready. Branch prefixes: `feature/`, `bugfix/`, etc.; `hotfix/` toward `main` only by explicit team agreement. Commits: `type(scope): summary` (Conventional Commits); common types: `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `chore`. SemVer + remote tag on `main`.
- Run `php artisan test --compact` and fix style with Pint before requesting review.
- **There is no automatic deploy** from GitHub to the production server; deployment is manual per internal procedures.
- Do not commit secrets, `.env`, production logs, or real environment paths; see `SECURITY.md` for vulnerability reporting.
