# Arquitectura — Centinela (Archify)

Diagramas de arquitectura generados con [Archify], como artefactos de referencia interna. Son **independientes** del pipeline Mermaid/SVG del manual (`resources/docs/diagrams/`).

## Convención de carpetas

Cada diagrama vive en su propia carpeta:

```text
docs/architecture/
  README.md
  .gitignore
  {dominio}/
    {slug}/
      centinela-{slug}.architecture.json   # spec (IR) — fuente de verdad
      centinela-{slug}.html                # HTML entregado (abrir en navegador)
      manifest.json                        # versión, fecha y receipt del último deliver
```

| Segmento | Ejemplo | Uso |
| -------- | ------- | --- |
| `{dominio}` | `platform`, `modules`, `infra` | Agrupa diagramas por ámbito |
| `{slug}` | `vision-funcional`, `ciclo-comges` | Nombre estable del diagrama (kebab-case) |

El archivo spec puede ser `*.architecture.json` o `*.workflow.json` según el tipo Archify.

Al agregar un diagrama nuevo: crear la carpeta, autorar el JSON, ejecutar `deliver`, y **actualizar `manifest.json`** con `app_version`, `delivered_at`, `diagram_revision` y el receipt SHA-256.

## Versión y marca temporal (`manifest.json`)

| Campo | Significado |
| ----- | ----------- |
| `app_version` | Versión de Centinela al entregar (alinear con `APP_VERSION` en `.env` / release) |
| `delivered_at` | Fecha ISO del último `deliver` exitoso |
| `diagram_revision` | Revisión del diagrama (incrementar si cambia topología sin correlación 1:1 con release de app) |
| `receipt` | SHA-256 y bytes del spec y del HTML (salida de `archify deliver --json`) |

El spec Archify no admite campos arbitrarios en `meta`; la trazabilidad vive en `manifest.json` junto al diagrama.

## Catálogo actual

| Carpeta | Lente | Abrir HTML |
| ------- | ----- | ---------- |
| [platform/vision-funcional/](platform/vision-funcional/centinela-vision-funcional.html) | Funcional — actores, auth, dashboard, módulos | `centinela-vision-funcional.html` |
| [platform/vision-tecnica/](platform/vision-tecnica/centinela-vision-tecnica.html) | Técnica (runtime) — Laravel, Voyager, Livewire, MySQL, cola | `centinela-vision-tecnica.html` |
| [modules/comges/ciclo-comges/](modules/comges/ciclo-comges/centinela-ciclo-comges.html) | Workflow — ciclo COMGES por SS (OOTT → cierre) | `centinela-ciclo-comges.html` |
| [modules/metas/ciclo-metas/](modules/metas/ciclo-metas/centinela-ciclo-metas.html) | Workflow — ciclo Metas por SS (OOTT → cierre) | `centinela-ciclo-metas.html` |

### Ciclo COMGES (workflow)

```
OOTT → Parametrización global → Configuración SS → Autoevaluación → … → Cerrado
                                      ↓
                            Cortes 1 → 2 → n (según OOTT)
```

- 11 nodos, tarjetas Roles / Cortes (OOTT) / Escala de cumplimiento.
- Companion SVG para Acerca de: export Archify Light/classic → `/docs/diagrams/ciclo-comges.svg` (`npm run diagrams:export-ciclo-comges`).

### Ciclo Metas (workflow)

```
OOTT → Parametrización global → Formulación → Autoevaluación → … → Cerrado
                              ↓
                    CG aplica/metas → Convenio (resp. establecimiento)
```

- 10 nodos, tarjetas Roles / Periodos / Metas 20.707.
- Companion SVG para Acerca de: export Archify Light/classic → `/docs/diagrams/ciclo-metas.svg` (`npm run diagrams:export-ciclo-metas`).

### Visión funcional

```
Usuarios por SS → Autenticación → Dashboard → módulos
                                    ↑
                            Año indicador (condiciona datos)
```

- 9 nodos, boundary «Plataforma Centinela», tarjetas Multi-SS / Roles / Trazabilidad.
- Companion SVG para Acerca de: `/docs/diagrams/vision-funcional.svg` (`npm run diagrams:export-vision-funcional`).

### Visión técnica (runtime)

- 11 nodos, boundary «Aplicación Laravel 10 (PHP 8.3)»; sin despliegue IIS ni monitor externo.
- Tarjetas: Año + Servicio de Salud, Bifurcación 2025, Auditoría.
- Companion SVG para Acerca de: `/docs/diagrams/vision-tecnica.svg` (`npm run diagrams:export-vision-tecnica`).

## Regenerar

Requiere el skill [Archify]. Reemplazar `<archify>` por la ruta a `bin/archify.mjs`.

```bash
# Funcional
node <archify> validate architecture docs/architecture/platform/vision-funcional/centinela-vision-funcional.architecture.json --quality showcase --json
node <archify> deliver architecture docs/architecture/platform/vision-funcional/centinela-vision-funcional.architecture.json docs/architecture/platform/vision-funcional/centinela-vision-funcional.html --quality showcase --json

# Técnica
node <archify> validate architecture docs/architecture/platform/vision-tecnica/centinela-vision-tecnica.architecture.json --quality showcase --json
node <archify> deliver architecture docs/architecture/platform/vision-tecnica/centinela-vision-tecnica.architecture.json docs/architecture/platform/vision-tecnica/centinela-vision-tecnica.html --quality showcase --json

# COMGES — ciclo por SS
node <archify> validate workflow docs/architecture/modules/comges/ciclo-comges/centinela-ciclo-comges.workflow.json --quality showcase --json
node <archify> deliver workflow docs/architecture/modules/comges/ciclo-comges/centinela-ciclo-comges.workflow.json docs/architecture/modules/comges/ciclo-comges/centinela-ciclo-comges.html --quality showcase --json

# Metas — ciclo por SS
node <archify> validate workflow docs/architecture/modules/metas/ciclo-metas/centinela-ciclo-metas.workflow.json --quality showcase --json
node <archify> deliver workflow docs/architecture/modules/metas/ciclo-metas/centinela-ciclo-metas.workflow.json docs/architecture/modules/metas/ciclo-metas/centinela-ciclo-metas.html --quality showcase --json

# SVG Acerca de (Light/classic, requiere Chrome)
npm run diagrams:export-ciclo-comges
npm run diagrams:export-ciclo-metas
npm run diagrams:export-vision-funcional
npm run diagrams:export-vision-tecnica
```

Tras cada `deliver` exitoso: actualizar `manifest.json` de esa carpeta (`delivered_at`, `app_version`, `receipt`, y `diagram_revision` si aplica).

Evidencia de navegador (opcional, no versionar PNG por defecto — ver `.gitignore`):

```bash
node <archify> visual-check docs/architecture/platform/vision-funcional/centinela-vision-funcional.html --json
node <archify> visual-check docs/architecture/platform/vision-tecnica/centinela-vision-tecnica.html --json
```

[Archify]: https://github.com/tt-a1i/archify
