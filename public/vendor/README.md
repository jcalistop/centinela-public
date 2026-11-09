# Vendor estático (CSP self-host)

Pins SDD-324 (críticos):

| Asset | Versión | Origen del pin |
| ----- | ------- | -------------- |
| Open Sans (300/400/700 latin woff2) | `@fontsource/open-sans@5.2.5` | jsDelivr → `public/vendor/open-sans/` |
| ApexCharts | `3.54.1` | jsDelivr → `public/vendor/apexcharts/apexcharts.min.js` |
| Leaflet | `1.9.4` | unpkg → `public/vendor/leaflet/` |

Tiles OSM siguen en `https://{s}.tile.openstreetmap.org` (allowlist CSP-RO `img-src` únicamente).
