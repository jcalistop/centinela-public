<center>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:pixodesk="https://pixodesk.com" viewBox="0 0 200 50" text-rendering="geometricPrecision" shape-rendering="geometricPrecision" fill="none"
width="400px">
  <defs>
    <linearGradient id="1766" x1="-35.347" y1="0" x2="35.347" y2="0" gradientUnits="userSpaceOnUse">
      <stop offset="50%" stop-color="rgb(235,60,70)"/>
      <stop offset="50%" stop-color="rgb(15,105,180)"/>
    </linearGradient>
  </defs>
  <ellipse fill="url(#1766)" stroke="none" transform="translate(32.3253, 24.5644)scale(0.5, 0.5)" rx="35.347" ry="34.441"/>
  <g transform="translate(174.716, 17.9184)translate(-235.9517, -48.3384)">
    <text fill="#eb3c46" xml:space="preserve" transform="translate(122.0544, 62.2356)" data-gn-el-type="text" style="white-space:pre"><tspan font-family="Arial Black" font-size="20px">CENTINELA</tspan><tspan font-family="Roboto-Regular" font-size="40px"> </tspan><tspan font-family="Roboto-Regular" font-size="40px"></tspan></text>
  </g>
</svg>
</center>

---

# Centinela

Plataforma web institucional para el **seguimiento, consolidación y rendición de cuentas** de instrumentos de evaluación en Servicios de Salud y sus establecimientos: criterios homogéneos, **trazabilidad** y evidencia para decisiones basadas en datos. La operación es **multi-Servicio**, con **roles**, **año indicador** en sesión y módulos por instrumento.

## Qué incluye (resumen)

| Ámbito                      | Contenido principal                                                                                                                 |
| --------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| **Metas**                   | Instrumentos 18.834, 19.664 y 20.707: indicadores, carga de datos, importación cuando aplique, vistas por establecimiento o unidad. |
| **COMGES**                  | Compromisos de gestión, cortes, medios verificables, evaluaciones y descargas.                                                      |
| **ADP**                     | Convenios, carga mensual, etapas de evaluación, preliminar, apelaciones y cierre.                                                   |
| **Establecimientos (SNSS)** | Visualización y apoyo operativo del universo de establecimientos según la instancia desplegada.                                     |
| **Administración**          | Usuarios, perfiles, catálogos y soporte diario (panel bajo `/admin`, Voyager + extensiones).                                        |

Narrativa ampliada en **Acerca de** del panel (`/admin/acerca-de`).

## Stack (referencia técnica)

| Componente | Versión / nota                                   |
| ---------- | ------------------------------------------------ |
| PHP        | 8.3 (proyecto alineado con `composer.json` / CI) |
| Laravel    | 11.x                                             |
| Voyager    | 1.7 (panel BREAD y menús)                        |
| Livewire   | 3.x                                              |
| Front      | Bootstrap 5, Vite, Blade                         |

## Adopción por Servicio de Salud

Implementaciones de referencia (fechas indicativas):

- [Servicio de Salud del Reloncaví](https://www.ssdr.gob.cl) — desde 04/2023.
- [Servicio de Salud Araucanía Sur](https://www.araucaniasur.cl) — desde 06/2024.
- [Servicio de Salud Arauco](https://ssarauco.cl/) — desde 05/2025.

## Versiones y documentación de producto

- Reglas de dominio (fuente canónica): [`.github/docs/business/domain-rules.md`](.github/docs/business/domain-rules.md)
- Notas de versión: tags SemVer en `main` (GitHub Releases).

## Cómo participar (institucional)

Si su Servicio de Salud aún no utiliza Centinela o desea ampliar el uso, la coordinación suele hacerse vía dirección del Servicio. En la aplicación desplegada existe información y contacto en **Acerca de** (ruta `/acerca-de`).

## Licencia

El software **Centinela** se distribuye bajo la **EUPL-1.2** (European Union Public Licence). **Derechos de propiedad:** Servicio de Salud del Reloncaví. **Derechos de autor:** José Luis Calisto Peñafiel. Texto: [`LICENSE.md`](LICENSE.md). Autores: [`AUTHORS.md`](AUTHORS.md). Cita: [`CITATION.cff`](CITATION.cff).

## Cómo contribuir (repositorio)

Quien desee colaborar en desarrollo debe coordinar con el mantenedor del repositorio y preferentemente cumplir perfil institucional (funcionariado MINSAL/SNSS, conocimiento Laravel/MVC, Git, Voyager u otro admin CRUD). Las contribuciones se aceptan bajo la misma EUPL-1.2.

- Guía de contribución (ramas, PR, commits, SemVer): [`CONTRIBUTING.md`](CONTRIBUTING.md)
- Seguridad y reporte de vulnerabilidades: [`SECURITY.md`](SECURITY.md)
- Código de conducta: [`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md)

## Arranque local (desarrolladores)

```bash
composer install
# Si no existe .env aún: cp .env.example .env  (no sobrescribir un .env existente)
php artisan key:generate
php artisan migrate
npm install && npm run build
php artisan serve
```

Tras `npm run build` (o `npm run dev`) se sirven los assets Vite. El panel administrativo canónico es Voyager (`/admin`).

### Admin de demo (instalación limpia)

Tras seeders de plataforma (`AdminUserSeeder`, p. ej. vía `DatabaseSeeder`), queda un usuario **solo para laboratorio local**:

| Campo | Valor |
| ----- | ----- |
| Email | `admin@centinela.cl` |
| Password | `password` |

**Obligatorio:** cambiar (o eliminar) estas credenciales en cualquier entorno real (staging, producción, mirror público). No usar el password de demo fuera de máquinas de desarrollo.

Tests y estilo: ver [`CONTRIBUTING.md`](CONTRIBUTING.md).

## Contacto del repositorio

Coordinación institucional vía dirección del Servicio de Salud adoptante y, en la aplicación desplegada, **Acerca de** (`/acerca-de` / `/admin/acerca-de`).

Para vulnerabilidades de seguridad: [`SECURITY.md`](SECURITY.md) (reporte privado de GitHub).

---

_Documentación institucional en el panel: **Acerca de** (`/admin/acerca-de`)._
