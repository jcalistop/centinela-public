---
name: centinela-informe-historico
description: >
  Redacta y audita el informe histórico de Centinela en español de Chile
  (docs/ss_reloncavi/2026-09-07-informe-historico-centinela/). Usar al editar docs/ss_reloncavi/2026-09-07-informe-historico-centinela/draft/** o docs/ss_reloncavi/2026-09-07-informe-historico-centinela/sources/**,
  o cuando el usuario mencione informe histórico, gaps-post-corte, SDD-163,
  SDD-224–229, mapa-capitulos-v3, o capítulos history. Modos: reanálisis de
  fuentes (A), redacción (B), reestructura versión lectora (C). No usar para
  manual Voyager, GITHUB_RELEASE_NOTES ni código.
---

# Informe histórico Centinela

## Canon (no duplicar)

| Recurso | Ruta |
| ------- | ---- |
| **Mapa v3.0 (lista cerrada)** | `docs/ss_reloncavi/2026-09-07-informe-historico-centinela/sources/mapa-capitulos-v3.md` |
| Convenciones | `docs/ss_reloncavi/2026-09-07-informe-historico-centinela/sources/convenciones-redaccion.md` |
| Plantilla capítulo | `docs/ss_reloncavi/2026-09-07-informe-historico-centinela/sources/plantilla-capitulo.md` |
| Gaps (congelados SDD-224) | `docs/ss_reloncavi/2026-09-07-informe-historico-centinela/sources/gaps-post-corte.md` |
| Hechos (trazabilidad agente) | `docs/ss_reloncavi/2026-09-07-informe-historico-centinela/sources/checklist-hechos.md` |
| Roadmap / corte | `docs/ss_reloncavi/2026-09-07-informe-historico-centinela/ROADMAP.md` · madre `SDD-163` |
| Checklist editorial | `.cursor/skills/centinela-informe-historico/checklist.md` |

**Corte de contenido vigente:** **v4.11.2** (salvo enmienda explícita en SDD-163).

**Idioma:** español Chile institucional. **No** cargar skills de claridad en inglés ni anglicizar.

---

## Modo A — Reanálisis de fuentes

**Cuándo:** SDD-224 / actualizar inventarios / armar gaps.

1. Leer releases `v4.8.0`→corte; priorizar `GITHUB_RELEASE_NOTES.md`.
2. Sintetizar hitos: *qué cambió para gestión*; salida en español.
3. Escribir/actualizar solo `docs/ss_reloncavi/2026-09-07-informe-historico-centinela/sources/**` (+ nota ROADMAP si el spec lo pide).
4. Cada fila de `gaps-post-corte.md`: `hito | evidencia | capítulo destino | añadir|corregir|omitir`.
5. Nuevos hechos → `checklist-hechos.md` como `pendiente`.
6. **Prohibido** editar `draft/**` en este modo.

---

## Modo B — Redacción de capítulos (legado v2 / mantenimiento puntual)

**Cuándo:** ajuste menor de un MD ya en mapa v3, sin reestructura.

1. Lista cerrada = [`mapa-capitulos-v3.md`](../../../docs/ss_reloncavi/2026-09-07-informe-historico-centinela/sources/mapa-capitulos-v3.md) o el spec hijo.
2. Respetar plantilla v3 (pocas secciones).
3. ADP / manual / Chat IA → **[EN DESARROLLO]**.
4. Frases densas (4–8 líneas); lead con beneficio de gestión.
5. Anti-tells de prosa IA; correr [checklist.md](checklist.md).

**1-in-flight:** no paralelo de dos fusiones grandes.

---

## Modo C — Reestructura / versión lectora (SDD-229+)

**Cuándo:** fusiones de mapa, strip harness, PDF para lectores, o mención de v3.0.

1. **Leer** `mapa-capitulos-v3.md` (única lista de paths).
2. Diff = **un** archivo destino por turno (o un archivo skill/sources en fase 0).
3. Outline del mapa: máx. **4–5** encabezados `##` (resumen: **cero** `##`; glosario: título + términos).
4. **Prohibido en cuerpo `draft/`:**
   - Separadores `---` (solo delimitadores YAML del front matter).
   - IDs `H-\d+`, `CG-\d+`, `G-\d+`.
   - Sección «Control de aprobación».
   - Sigla «CG» suelta → escribir **control de gestión**.
5. Trazabilidad de hechos solo en `checklist-hechos.md` y YAML `sources:` — **no** en prosa.
6. Tablas/figuras: pie `Tabla N — …` / `Figura N — …` y referencia en prosa.
7. Resumen ejecutivo: **solo prosa** (sin tablas ni ilustraciones).
8. Toda sigla nueva → actualizar `00-02-glosario.md`.
9. Tras fusión: `git mv` orígenes absorbidos a `draft/_archive/` según mapa.
10. Checklist Modo C (abajo) antes de cerrar el archivo.

---

## Anti-alucinación

- Solo afirmar lo respaldado por checklist-hechos, release ≤ corte, o antecedentes.
- Si falta evidencia: omitir o «Notas del autor» (no va a LaTeX).
- No copiar audits/ADR técnicos al cuerpo; sintetizar en lenguaje de gestión.

---

## Fuera de alcance

- Skills English / marketing copy / Diátaxis como voz del informe.
- Programa strangler SDD-216+ (código de app).
- Sustituir `/admin/acerca-de` o bodies de GitHub Release.
