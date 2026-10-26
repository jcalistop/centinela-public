# Negocio — Centinela

Contexto de producto MINSAL (Servicios de Salud, instrumentos de evaluación, roles operativos).

Contexto de negocio del producto (separado de la metodología de implementación).

**Formalización marco ADR-039:** 2026-07-09 · **Aprobada** por responsable técnico (misma fecha).
**Última pasada de alineamiento `domain-rules`:** 2026-10-07.

---

## Ritual de alineamiento (periódico)

Objetivo: mantener [`domain-rules.md`](domain-rules.md) alineado con plataforma **Released** (sin inventar reglas).

| Campo | Regla |
| ----- | ----- |
| **Disparador** | Tras `close-release` de una campaña que toque reglas de negocio / F0–F2 / roles-tenancy-año; o revisión **trimestral** si no hubo campaña de ese tipo. |
| **Entrada** | Filas Released de la campaña + fecha «Última formalización» de `domain-rules.md`. |
| **Salida** | `domain-rules.md` actualizado **o** nota explícita «sin drift»; inventario de satélites si hubo candidatos a eliminar. |
| **Prohibido** | Inventar reglas no Released; tocar código; enmendar specs en `archive/`; basarse en Draft/In Build/Validating. |

El ritual se aplica tras cada campaña que toque reglas de negocio.

---

## Contexto arquitectónico

La evolución de plataforma (Filament v5, convergencia de instrumentos, campaña `v5.0.0`) quedó en pausa: el producto cerrado opera con **Voyager `/admin`** como único shell administrativo visible.

Las **reglas de negocio** viven en este directorio.

---

## Documentos

| Documento | Audiencia | Contenido |
| --------- | --------- | --------- |
| [domain-rules.md](domain-rules.md) | Desarrollo, agentes IA, revisores PR | Reglas transversales (SS, año, roles, invariantes ADR-039) — **fuente única para specs** |
| [instrumentos-plataforma.md](instrumentos-plataforma.md) | Producto, desarrollo, CG | Visión de plataforma, chasis unificado, convergencia P1–P3, interoperabilidad |
| [comges-ciclo-vida.md](comges-ciclo-vida.md) | CG, parametrizador, referentes, desarrollo | Ciclo de vida COMGES: OOTT, parametrización, etapas, roles |
| [comges-calculo.md](comges-calculo.md) | CG, referentes, desarrollo | Cálculo COMGES: escalas, agregación, cortes, sensibilidad |
| [metas-ciclo-vida.md](metas-ciclo-vida.md) | CG, parametrizador, responsables, referentes, desarrollo | Ciclo de vida Metas: OOTT, etapas, convenio, roles |
| [metas-calculo.md](metas-calculo.md) | CG, parametrizador, soporte, desarrollo | Cálculo Metas: períodos, agregación, semáforos, cumplimiento global |
| [adp-operativo.md](adp-operativo.md) | CG, desarrollo | ADP — borrador formal; suite **en pausa** (no Released) |
| [modulos-roles.md](modulos-roles.md) | Desarrollo, soporte, validación funcional | Matriz módulo × rol × acciones típicas |

---

## Inventario de satélites

Criterio: *obsoleto = contradice Released, o duplica sin valor y nadie lo cita como fuente*.
**Eliminación:** solo filas `eliminar` con aprobación humana en verify + grep sin referencias rotas.

| Artefacto | Decisión | Justificación |
| --------- | -------- | ------------- |
| `domain-rules.md` | **mantener** | Fuente única; actualizado en esta pasada (YearContext, BelongsToHealthService, Metas). |
| `README.md` | **mantener** | Índice + ritual + este inventario. |
| `metas-ciclo-vida.md` | **mantener** | Formalizado 2026-09-01; paridad con glosario domain-rules; citado por specs Metas. |
| `metas-calculo.md` | **mantener** | Fuente de cálculo Metas; citado ampliamente. |
| `comges-ciclo-vida.md` | **mantener** | Fuente ciclo COMGES; citado. |
| `comges-calculo.md` | **mantener** (higiene fecha) | Fuente cálculo COMGES; fecha alineada 2026-10-07 sin cambio de reglas. |
| `instrumentos-plataforma.md` | **mantener** (parches) | Visión P1–P3 / ADR-039; drift F2 cerrado 2026-10-07 (`YearContext` / `BelongsToHealthService` Released; P1–P3 siguen pendientes). |
| `modulos-roles.md` | **mantener** (refresh) | Matriz Metas alineada al ciclo vigente; ADP marcada en pausa (2026-10-07). |
| `adp-operativo.md` | **mantener** (parche banner/enlaces) | Suite ADP en pausa; banner e hipervínculos corregidos 2026-10-07. Contenido operativo sigue siendo borrador. |

**Filas `eliminar` en esta pasada:** ninguna.

---

## Jerarquía de lectura

1. **Nuevo en el proyecto:** [instrumentos-plataforma.md](instrumentos-plataforma.md) → [domain-rules.md](domain-rules.md).
2. **Spec de instrumento:** [domain-rules.md](domain-rules.md) + doc del instrumento (`comges-ciclo-vida`, `comges-calculo`, `metas-ciclo-vida`, `metas-calculo`, `adp-operativo`).
3. **Permisos / UX por rol:** [modulos-roles.md](modulos-roles.md).

---

## Pendiente (negocio)

- ~~Inventario de indicadores replicados COMGES↔Metas~~ — **cerrado**; decisión: **diferir solución a `v5.0.0` (P2)**. Sin specs en v4.x.
- Actualizar matriz ADP en [modulos-roles.md](modulos-roles.md) cuando el módulo entre en producción.
- Refinar permisos Filament tras fase T1 (piloto panel).
- ~~Recorte F2 «futuro» en instrumentos-plataforma~~ — **cerrado** 2026-10-07.
- ~~Refresh `modulos-roles` / fecha `comges-calculo`~~ — **cerrado** 2026-10-07.
