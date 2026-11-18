# Vendor estático (CSP self-host)

Pins SDD-324 (críticos):

| Asset | Versión | Origen del pin |
| ----- | ------- | -------------- |
| Open Sans (300/400/700 latin woff2) | `@fontsource/open-sans@5.2.5` | jsDelivr → `public/vendor/open-sans/` |
| ApexCharts | `3.54.1` | jsDelivr → `public/vendor/apexcharts/apexcharts.min.js` |
| Leaflet | `1.9.4` | unpkg → `public/vendor/leaflet/` |

Tiles OSM siguen en `https://{s}.tile.openstreetmap.org` (allowlist CSP-RO `img-src` únicamente).

Pins SDD-325 (ola 2):

| Asset | Versión | Origen del pin |
| ----- | ------- | -------------- |
| Mermaid (UMD) | `11` (dist pin jsDelivr) | → `public/vendor/mermaid/mermaid.min.js` |
| SortableJS | `1.15.6` | → `public/vendor/sortablejs/Sortable.min.js` |
| Tabler core | `1.0.0-beta17` | → `public/vendor/tabler/` |
| bootstrap-rtl | `3.4.0` | cdnjs → `public/vendor/bootstrap-rtl/bootstrap-rtl.css` |

Nota: Mermaid ESM no se vendoriza (imports relativos); Acerca de usa el mismo UMD.

## Subdirectorio e IIS (SDD-337)

- URLs de assets self-host: emitir con `asset(...)` desde Blade. No hardcodear rutas root-absolute (`/vendor/...`) en JS estático.
- Open Sans: `open-sans.css` usa `url("files/….woff2")` relativas al CSS; si el CSS se sirve vía `asset('vendor/open-sans/open-sans.css')`, las fuentes resuelven bajo el mismo prefijo de aplicación.
- En IIS, mapear MIME `.woff2` → `font/woff2` (y `.woff` → `font/woff` si aplica). Sin ese mapa el navegador puede descartar la fuente aunque el archivo exista.
