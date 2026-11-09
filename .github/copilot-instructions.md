# Centinela – Agent Instructions

## Sobre el Proyecto

Centinela es una aplicación web de monitoreo de indicadores para Servicios de Salud del MINSAL (Chile). Módulos activos:

- **Compromisos de Gestión (COMGES)** – seguimiento de indicadores anuales con cortes, acciones, evaluaciones y tablas de sensibilidad.
- **Metas sanitarias** – seguimiento de metas e indicadores asociados.
- **Visualizador de Establecimientos SNSS** – panel de centros de salud del Sistema Nacional.
- **Dashboard público** – paneles de información publicados para consulta externa.
- **Mensajería** – compose y audiencia en admin Voyager.
- **Admin Voyager** – BREAD y pantallas operativas bajo `/admin`.

Stack: PHP 8.3 · Laravel 11 · Livewire 4 · Voyager 1.8 · Bootstrap 5 / SASS (NO Tailwind) · Vite · Alpine.js (incluido con Livewire) · Laragon (NO Herd).

Laravel Boost para Cursor vive solo en [`.cursor/rules/laravel-boost.mdc`](../.cursor/rules/laravel-boost.mdc); no copiarlo aquí.

## Secretos locales / `.env` (obligatorio)

- **No** ejecutar en este workspace: `cp .env.example .env`, `copy .env.example .env`, `Copy-Item .env.example .env -Force` (ni equivalentes que sobrescriban `.env`), salvo petición **explícita** del usuario y aceptación del riesgo (copia previa o historial local del editor).
- `php artisan test` usa variables definidas en `phpunit.xml`; **no** hace falta copiar `.env.example` sobre `.env` para correr tests en local.
- El **CI en GitHub** hace `cp` solo en el runner remoto; **no** repetir ese paso en la máquina de desarrollo sobre el mismo directorio con secretos reales: usar **otro clon** o ruta si hace falta imitar CI a mano.

## Arquitectura Clave

### Patrón Año-Basado (Crítico)

Toda la lógica pivota sobre el año activo guardado en sesión como `session('indicator_year')` (un modelo `Year`).

- El middleware `indicator.year` (`EnsureIndicatorYearIsSet`) garantiza que siempre exista en sesión.
- **Dominio COMGES:** vistas en `resources/views/centinela/comges/` y componentes en `resources/views/components/comges/` (`x-comges.*`). Escala % vs puntaje con `$useScoreScale` / [`ComgesYearMode`](../app/Support/Comges/ComgesYearMode.php); legacy en subrutas `components/comges/**/legacy/`.
- **Read indicador:** año &lt; 2025 → Voyager `vendor/voyager/indicadores-comges/read`; año ≥ 2025 → `centinela.comges.indicadores.read`.
- Bifurcación 2025: vistas y alertas COMGES viven en `resources/views/centinela/comges/` y `resources/views/components/comges/`.

### Patrón Bifurcación 2025 por Instrumento (Crítico)

Desde 2025 se aplicaron cambios relevantes en orientaciones técnicas de los instrumentos de evaluación (`indicatorType`), lo que generó bifurcaciones funcionales en múltiples capas del proyecto.

- **Regla de diseño**: antes de modificar lógica COMGES, verificar si el flujo depende de `indicatorType` y del año activo (`session('indicator_year')`).
- **Capas afectadas**: controllers Voyager/custom, [`SearchCommitmentComponent`](../app/Livewire/SearchCommitmentComponent.php) + servicios `App\Services\Comges\*`, vistas Blade (`centinela/comges/`, `components/comges/`).
- **Compatibilidad**: no unificar ni simplificar rutas/queries/vistas sin validar impactos para periodos pre-2025 y post-2025.
- **Criterio de implementación**: cuando existan ramas por instrumento, preservar explícitamente la bifurcación o documentar su refactor con equivalencia funcional verificable.
- **Validación mínima**: toda intervención en COMGES debe comprobar comportamiento por año y por instrumento para evitar regresiones silenciosas.

### Patrón Servicios de Salud (Crítico)

La lógica de negocio se segmenta por Servicio de Salud, usando el `cen_hie_dependency_id` del usuario autenticado para filtrar datos relevantes.

- **Fuente del contexto**: el filtro siempre debe derivarse desde `Auth::user()->cen_hie_dependency_id` (nunca hardcodear dependencia).
- **Capa preferida de aislamiento**: aplicar `scope` de modelo y registrarlo en DataType de Voyager para que la base BREAD de Voyager (vendor) lo ejecute automáticamente en browse/read/edit.
- **Fallback obligatorio**: cuando el modelo no tenga `scope` de dependencia, agregar filtrado explícito por `cen_hie_dependency_id` en controllers/services/queries.
- **Auto-asignación en escritura**: en modelos que guardan datos por dependencia, completar `cen_hie_dependency_id` al persistir para evitar registros huérfanos o cruzados.
- **Livewire COMGES**: en `SearchCommitmentComponent` / `ComgesCommitmentSearchService`, filtrar evaluaciones/documentos por `cen_hie_dependency_id` del usuario autenticado.
- **Autorización**: reforzar la segmentación con policies/gates que ya consideran configuración por dependencia (`current-add-edit`, `current-download`).
- **Regla de consultas**: evitar consultas globales (`Model::all()`/`get()` sin filtros) sobre entidades COMGES con datos sensibles entre servicios.
- **Año + dependencia**: en flujos COMGES, validar conjuntamente `session('indicator_year')` y dependencia para no mezclar datos entre periodos ni servicios.
- **Heterogeneidad conocida**: no todos los modelos COMGES tienen el patrón implementado de forma uniforme; si falta scope, el filtrado manual es obligatorio.
- **Referencias de implementación**: `ComCommitmentController`, `ComgesYearMode`, `ComgesBrowseContext`, `ComgesCommitmentSearchService`, `ComgesCommitmentAlertPresenter`, `ComgesResultsService`, `CentinelaVisualAlertService`, `CurrentCutoffPolicy`, `TCG\\Voyager\\Http\\Controllers\\VoyagerBaseController` (vendor).

### Voyager Admin

- Rutas bajo `/admin`, con Voyager BREAD y controllers personalizados en `app/Http/Controllers/Voyager/`.
- El proyecto usa controladores BREAD custom que extienden `TCG\\Voyager\\Http\\Controllers\\Controller` y sobreescriben lógica por módulo.
- Cuando se requiere comportamiento base de BREAD, la referencia es `TCG\\Voyager\\Http\\Controllers\\VoyagerBaseController` (vendor), no una clase local del proyecto.
- Overrides de vistas Voyager en `resources/views/vendor/voyager/`.
- COMGES: `resources/views/centinela/comges/` (browse, read, carga masiva) y `resources/views/components/comges/` (acciones, indicadores, `commitment-performance`).

### Sistema de Roles

Roles relevantes (gestionados por Voyager): `admin`, `parametrizador`, `control-gestion`, `referente`, `visualizador`.

- Solo `parametrizador` y `admin` pueden ver compromisos sin indicadores configurados.
- Gates: `current-add-edit`, `current-download` (en `CurrentCutoffPolicy`). Solo se relacionan con COMGES.

### Relación de Modelos COMGES

```
ComCommitment → ComIndicator → ComIndicatorCutoff → ComAction → ComEvaluation
                                                              ↳ ComSensitivityTable
                                                              ↳ ComVerifiableDocument
```

Todos los modelos COMGES usan el trait `Auditable` (owen-it/laravel-auditing). Ver modelos en `app/Models/`.

## Convenciones del Proyecto

- **Sin Form Requests**: la validación está inline en controllers Voyager o via atributos `#[Validate]` en Livewire. No crear `app/Http/Requests/` a menos que se solicite.
- **Modelos con `$casts`**: usar `protected $casts = []` (no el método `casts()`); convención del proyecto.
- **Vistas Blade**: `resources/views/centinela/` para vistas custom, `resources/views/vendor/voyager/` para overrides de Voyager.
- **Livewire namespace**: `App\Livewire` (p. ej. `SearchCommitmentComponent`); partials en `resources/views/livewire/comges/partials/`. No usar namespace `Comges2025` para compromisos.
- **Frontend**: Bootstrap 5 y SASS en `resources/sass/`. No usar clases de Tailwind.
- **Puntaje Comges 2025**: `cumplimiento` se interpreta como puntaje (no porcentaje). Ver [docs/](../docs/).

### Base de Datos (Crítico)

- **Origen del esquema**: la base de datos no fue creada mediante migraciones de Laravel; la mayor parte de la estructura se generó con herramientas de tablas/BREAD de Voyager.
- **Fuente de verdad del esquema**: no asumir que `database/migrations/` refleja el estado real. Para conocer estructura, relaciones e índices, consultar directamente la base de datos usando los MCP disponibles.
- **Intervenciones de datos/esquema**: cualquier cambio de base de datos debe definirse primero de forma documentada (decisión del desarrollador + alcance + rollback), para su ejecución manual posterior en producción.
- **Registro de decisiones de BD**: documentar decisión, alcance y rollback antes de ejecutar DDL.
- **Plantilla mínima obligatoria para cambios de BD**: contexto/problema, decisión propuesta, alcance técnico (tablas/columnas/índices/constraints), riesgos, plan de rollback, plan de validación funcional y técnica post-implementación, y pasos de ejecución manual en producción.
- **Propuesta técnica del agente**: para cambios de esquema, el agente debe priorizar especificación y checklist operativo; no entregar ni ejecutar SQL DDL directo (`ALTER`, `DROP`, `CREATE`) salvo solicitud explícita del usuario.
- **Trazabilidad**: registrar siempre el objetivo, riesgo, plan de rollback y validaciones esperadas antes de proponer o ejecutar cambios sobre base de datos.

## Comandos Esenciales

```bash
php artisan test                    # Ejecutar pruebas (PHPUnit, SQLite en memoria)
npm run dev                         # Dev server con Vite
npm run build                       # Build para producción
vendor/bin/pint --dirty             # Formatear código modificado
php artisan tinker                  # REPL para debug/queries
```

## Infraestructura y Monitoreo

- API interna en `/api/schedule/*` protegida con `X-Cron-Token` header (middleware `cron.token`).
- Notificaciones via Email + WhatsApp (opcional). Supervisión y operaciones: monitor externo (`scripts/monitor/`) y API `/api/schedule/*`.
- Auditoría: registros en tabla `audits`, limpieza automática a los 6 meses.

## Documentación Interna

- Monitor externo: `scripts/monitor/`. API interna de schedule: `/api/schedule/*`.
