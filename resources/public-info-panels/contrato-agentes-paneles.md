# Contratos para agentes — paneles públicos Centinela

## Qué es este documento

Este texto es el **contrato de entrega** para quienes preparan un panel destinado al dashboard público de Centinela (personas o agentes de IA **ajenos** a la plataforma).

No es un manual de administración ni asume que conozcas Filament, Voyager u otras pantallas internas. Describe **qué archivos** debes entregar, **qué reglas de seguridad** respetar y cómo empaquetarlos en un **ZIP** para que un curador humano lo revise y, si corresponde, lo publique.

**Importante:** este contrato **no sustituye** la revisión humana ni el análisis automático de seguridad al guardar. Nada se publica solo por generar o subir un ZIP.

## Qué es un panel público en Centinela

Un panel nacional curado aparece en el hub público cuando un administrador lo publica. Hay dos variantes:

| Tipo (`tipo` en el manifest) | Qué entrega | Qué ve el visitante |
| ----------------------------- | ----------- | ------------------- |
| `html_bundle` | HTML + CSV + PNG de vista previa | Panel interactivo en iframe aislado + descarga CSV |
| `embed_externo` | URL https en host permitido + PNG de vista previa | Iframe hacia el origen externo (p. ej. Power BI) |

Solo se aceptan datos **agregados y públicos**, sin datos personales identificables.

## Contrato ZIP (precarga)

Empaqueta exactamente lo indicado. El ZIP sirve para **precargar** el formulario del curador; **no** crea ni publica el panel por sí solo.

### Archivos permitidos (nombres exactos)

| Archivo | Obligatoriedad |
| -------- | -------------- |
| `manifest.json` **o** `panel.json` | Obligatorio |
| `preview.png` | Obligatorio (ambos tipos) |
| `panel.html` | Obligatorio si `html_bundle` |
| `datos.csv` | Obligatorio si `html_bundle` |

No incluyas carpetas `lib/`, ni `.js`/`.css` sueltos, ni otros nombres. En `html_bundle`, scripts y estilos van **dentro** del HTML.

Tamaños orientativos: ZIP ≤ ~55 MB; HTML ≤ 25 MB; CSV ≤ 50 MB; PNG ≤ 10 MB.

### `manifest.json` (ejemplo `html_bundle`)

```json
{
  "titulo": "Título del panel (máx. 100)",
  "descripcion": "Descripción pública (máx. 400)",
  "tipo": "html_bundle",
  "autor": "Nombre opcional",
  "autor_email": "correo@opcional.cl",
  "institucion": "Institución opcional",
  "version_panel": "1.0"
}
```

### `manifest.json` (ejemplo `embed_externo`)

```json
{
  "titulo": "Panel Power BI nacional",
  "descripcion": "Indicadores públicos…",
  "tipo": "embed_externo",
  "embed_url": "https://app.powerbi.com/view?r=…",
  "autor": "Nombre opcional",
  "institucion": "Institución opcional",
  "version_panel": "2026-10"
}
```

`embed_url` debe ser **https** y pertenecer a la allowlist de Centinela (p. ej. Power BI, Tableau Public/Cloud, Looker Studio, Datawrapper, Flourish, ArcGIS dashboards públicos). El contenido en el origen debe ser públicamente accesible (p. ej. “Publish to web”).

Metadatos opcionales: `autor`, `autor_email`, `institucion`, `version_panel`. Obligatorios en el manifest: `titulo`, `descripcion`, `tipo`.

## Reglas para `html_bundle`

1. HTML autónomo + CSV + PNG del mismo paquete.
2. Datos vía `fetch('./datos.csv')` (o nombre relativo equivalente). Centinela remapea `*.csv` relativos al CSV curado.
3. Librerías de charts **embebidas** en el HTML — **sin CDN**.
4. UTF-8; sin secretos, tokens ni PII innecesaria.
5. No forzar `<base href="https://...">`; Centinela puede inyectar la base en la respuesta.

### Prohibido (el scan del servidor rechaza / alto riesgo)

- `<script src="https://...">` o `src="//..."` (CDN / externo)
- `javascript:` en `href` / `src` / `action`
- `<meta http-equiv="refresh" … url=https://…>`
- `<iframe>`, `<object>`, `<embed>`
- Credenciales o API keys en el HTML/JS
- Intentar leer cookies / `localStorage` de Centinela, o `window.parent` / `top.location` / `postMessage` a `*`

El HTML publicado se muestra en iframe sandboxed (`allow-scripts`, **sin** `allow-same-origin`).

## Checklist antes de entregar

- [ ] ZIP solo con entradas del contrato (manifest + archivos del tipo)
- [ ] `titulo` y `descripcion` en el manifest
- [ ] Si bundle: JS/CSS en el HTML (sin CDN) y CSV relativo
- [ ] Si embed: `embed_url` https allowlist + `preview.png`
- [ ] Preview es PNG estático
- [ ] Sin secretos ni PII innecesaria

## Cómo se publica (contexto para terceros)

1. Generas el ZIP según este contrato.
2. Lo envías al administrador de la plataforma.
3. Un humano revisa, completa lo faltante y decide publicar.
4. Solo entonces el panel puede aparecer en el dashboard público.

## Control de versión

| Campo | Valor |
| ----- | ----- |
| **Versión** | 1.3 |
| **Fecha** | 2026-10-17 |
| **Autor** | José Calisto — Departamento de Control de Gestión, Servicio de Salud del Reloncaví |
