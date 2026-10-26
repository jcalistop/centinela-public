# Diagramas Mermaid (Centinela)

Fuentes versionadas para documentación orientada al **usuario** (manual contextual, «Acerca de»).

## Convención

| Artefacto | Ruta |
| --------- | ---- |
| Fuente `.mmd` | `resources/docs/diagrams/<slug>.mmd` |
| SVG servido | `public/docs/diagrams/<slug>.svg` |
| Config compartida | `mermaid.config.json` (tema `neutral`) |

Compilar Mermaid (excepto ciclos COMGES y Metas, que se exportan desde Archify):

```bash
npm run diagrams:build
```

**Ciclo COMGES (Archify, Light/classic):**

```bash
npm run diagrams:export-ciclo-comges
```

**Ciclo Metas (Archify, Light/classic):**

```bash
npm run diagrams:export-ciclo-metas
```

**Visión funcional y técnica (Archify, Light/classic):**

```bash
npm run diagrams:export-vision-funcional
npm run diagrams:export-vision-tecnica
```

Requiere Chrome/Chromium y el skill [Archify] (`ARCHIFY_HOME` apunta a `bin/archify.mjs` si no está en `~/.agents/skills/archify`).

Referencia en Markdown:

```markdown
<!-- diagram-source: docs/architecture/modules/comges/ciclo-comges/centinela-ciclo-comges.workflow.json -->
![Ciclo COMGES por Servicio de Salud](/docs/diagrams/ciclo-comges.svg)
```

## Reglas de autoría

- IDs de nodos sin espacios (`camelCase` / `snake_case`).
- Evitar HTML en labels.
- Preferir ≤12 nodos primarios; dividir diagramas densos.
- Tras editar un `.mmd`, ejecutar `npm run diagrams:build` y commitear el SVG.

## Alcance

- **Manual / Acerca de:** SVG precompilado (recomendado para `status: published`).
- **Specs SDD** (`.github/docs/sdd/`): siguen con fence ` ```mermaid ` para revisión en GitHub.
- **Archify:** solo a pedido; no forma parte de este pipeline. Diagramas interactivos de referencia interna viven en [`docs/architecture/`](../../../docs/architecture/README.md).

## Legacy

El drawer de ayuda aún soporta fences ` ```mermaid ` en borradores (`status: draft`). No usar en contenido publicado.
