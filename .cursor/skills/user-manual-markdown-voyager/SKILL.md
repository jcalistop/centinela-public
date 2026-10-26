---
name: user-manual-markdown-voyager
description: Creates and updates Centinela user manual pages in Markdown with YAML front-matter, following SDD-035 (contextual help for Voyager /admin). Use when the user asks to write “manual de usuario”, “ayuda contextual”, “Voyager”, “/admin”, “drawer”, or to add/update `resources/user-manual/pages/*.md` and `resources/user-manual/index.yaml`.
---

# Manual de usuario (Markdown) para Voyager

## Alcance

- Fuente de verdad: `resources/user-manual/`
    - Páginas: `resources/user-manual/pages/*.md`
    - Índice de reglas: `resources/user-manual/index.yaml`
- Objetivo UX: contenido **contextual**, **escaneable** y **accionable**.

## Flujo de trabajo

### 1) Identificar el contexto (inputs mínimos)

Reunir (o inferir) lo siguiente:

- **Ruta actual**: route name (preferido) y/o path bajo `/admin`.
- **Familia** (si aplica): prefijo tipo `voyager.<slug>.` o `adp.self_evaluation.`.
- **Tareas típicas del usuario**: 1–3 procedimientos que debe poder completar.

### 2) Crear/editar la página Markdown

Crear o editar un archivo en `resources/user-manual/pages/<id>.md` (usar `id` como nombre de archivo).

#### Front‑matter YAML (mínimo)

Siempre intentar incluir:

- `id` (slug)
- `title`
- `version` (SemVer)
- `status` (`draft | review | published | deprecated`)
- `updated_at` (YYYY-MM-DD)

Campos recomendados cuando aporten valor:

- `owner`
- `tags` (array)
- `toc` (bool)
- `applies_to.routes` (array de route names)
- `anchors` (array opcional)

Tolerancia (requisito SDD-035):

- Si el YAML es inválido o falta front‑matter, **no bloquear** el documento. Mantener contenido útil igual.

#### Estructura recomendada por documento

Orden sugerido:

1. **Objetivo** (1–2 párrafos)
2. **Cuándo usarlo** (bullets)
3. **Paso a paso** (procedimientos)
4. **Errores comunes**
5. **Preguntas frecuentes**
6. **Referencias** (links internos, opcional)

Reglas de estilo:

- Bloques cortos, encabezados claros.
- Procedimientos con “**Resultado esperado:** …”.
- Usar el texto exacto de botones/menús si es estable.
- No incluir datos sensibles en ejemplos.

### 3) Mantener `resources/user-manual/index.yaml` (mapeo route→documento)

El índice es una **lista ordenada de reglas**: la **primera coincidencia gana**.

Debe mantener (mínimo):

- `version: 1`
- `defaults.fallback_id: general`
- `rules:` como lista

Tipos de match (MVP):

- `route_exact`
- `route_prefix`
- `route_regex`
- `path_prefix` (solo si realmente falta route name)

Orden recomendado de reglas:

1. `route_exact`
2. `route_prefix`
3. `route_regex`
4. `path_prefix` (si existe)

Buenas prácticas:

- Preferir **prefijos** para Voyager: `voyager.<slug>.` (reduce cardinalidad).
- Para rutas dinámicas por DB (ej. `metas-2026`), usar `route_regex` (ej. `'^metas-\\d+$'`) apuntando a un manual base.
- Mantener reglas específicas antes que reglas amplias.

Ejemplo (patrón del spec):

```yaml
version: 1
defaults:
    fallback_id: general

rules:
    - match: { type: route_exact, value: voyager.dashboard }
      target: { id: centinela-dashboard }

    - match: { type: route_prefix, value: voyager.documentos. }
      target: { id: voyager-documentos }

    - match: { type: route_regex, value: '^metas-\\d+$' }
      target: { id: metas-instrumento }
```

### 4) Checklist rápida antes de terminar

- [ ] El `id` del front‑matter coincide con el nombre de archivo.
- [ ] Incluye al menos 1 procedimiento con “Resultado esperado”.
- [ ] Incluye “Errores comunes” o una advertencia relevante si hay riesgos.
- [ ] `applies_to.routes` contiene el route name o familia principal (si se conoce).
- [ ] `index.yaml` tiene una regla que resuelve al documento (exact/prefix/regex), y no rompe el `fallback_id`.
- [ ] Diagramas en páginas `published`: SVG precompilado (ver § Diagramas abajo), no fence `mermaid` live.

## Diagramas (SDD-212)

Para contenido **`published`** en el manual o reutilizado en «Acerca de»:

1. Crear/editar fuente en `resources/docs/diagrams/<slug>.mmd`.
2. Ejecutar `npm run diagrams:build` → genera `public/docs/diagrams/<slug>.svg`.
3. Referenciar en Markdown:

```markdown
<!-- diagram-source: resources/docs/diagrams/mi-flujo.mmd -->
![Descripción accesible del diagrama](/docs/diagrams/mi-flujo.svg)
```

Convenciones: ver `resources/docs/diagrams/README.md`.

- **No usar** fence ` ```mermaid ` en páginas publicadas (layout pobre en drawer ~560px).
- **Legacy:** fence `mermaid` solo en borradores (`status: draft`); el drawer aún lo renderiza client-side.
- **Specs SDD:** siguen con Mermaid inline; Archify solo a pedido, fuera de este flujo.

## Artefactos soportados (convenciones)

Usar snippets listos desde:

- `templates.md`
- `examples.md`
- `checklist.md`
