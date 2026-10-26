## Panel y arquitectura de aplicación

Centinela es una aplicación **Laravel** servida bajo el prefijo **`/admin`**. No existe un frontend público de aplicación separado: la experiencia operativa (instrumentos, administración y soporte) se concentra en el panel administrativo, extendido sobre **Voyager** para menús, roles y catálogos BREAD.

Los flujos de carga, cálculo y resultados de instrumentos se implementan principalmente con componentes **Livewire** y vistas **Blade**, integrados en el mismo panel. Las rutas de negocio (COMGES, Metas, ADP, etc.) conviven con las pantallas nativas de Voyager; desde el año indicador **2025**, parte del módulo COMGES se sirve por rutas dedicadas (`centinela.comges.*`) en lugar del BREAD histórico.

## Stack de referencia

Las versiones exactas dependen del despliegue y del archivo de dependencias del proyecto al momento del release.

| Componente | Versión / nota |
| ---------- | -------------- |
| PHP | 8.3 |
| Laravel | 10.x |
| Voyager | 1.7 (panel BREAD y menús) |
| Livewire | 4.x (componentes server-side) |
| Persistencia | Eloquent; motor relacional **MySQL** |
| Front | Bootstrap 5, Vite, Blade |
| API / tokens (cuando aplique) | Laravel Sanctum |
| Auditoría de cambios | `owen-it/laravel-auditing` sobre modelos operativos |
| Documentación en panel | Markdown renderizado (`spatie/laravel-markdown`) |
| Importación / exportación tabular | CSV y Excel (`league/csv`, `spatie/simple-excel`) |

## Contexto operativo en runtime

Tras el inicio de sesión, la plataforma mantiene en **sesión** el **año indicador** activo y el **Servicio de Salud** del usuario. Esos valores condicionan consultas, menús y resultados en los módulos de negocio.

| Elemento | Función |
| -------- | ------- |
| **Año indicador** (`indicator_year`) | Filtra datos y vistas al periodo seleccionado en el panel. |
| **Servicio de Salud** (`cen_hie_dependency_id`) | Segrega la información entre territorios adoptantes (multi-SS). |
| **Roles Voyager** | Autorizan acciones según perfil (`admin`, `parametrizador`, `control-gestion`, `referente`, `visualizador`). |

El cambio de año o de contexto no altera la arquitectura: actualiza el filtro con el que Eloquent y los componentes Livewire resuelven los datos mostrados.

## Capas principales

La siguiente tabla resume las piezas que aparecen en la visión técnica del diagrama inferior.

| Capa | Responsabilidad |
| ---- | ---------------- |
| **Autenticación** | Sesión web y, cuando corresponde, tokens Sanctum para API interna. |
| **Voyager Admin** | Catálogos BREAD, usuarios, menús y administración transversal bajo `/admin`. |
| **Vistas Blade + Livewire** | Pantallas de instrumentos, formularios dinámicos, tablas y acciones de carga o cierre. |
| **Controllers y Services** | Reglas de negocio, autorización (policies), orquestación de importaciones, cálculos y descargas. |
| **API interna** (`/api`) | Endpoints consumidos desde el panel o integraciones controladas, con Sanctum. |
| **Cache y sesión** | Almacenamiento de preferencias de sesión y caché de aplicación (driver configurable por entorno). |
| **MySQL** | Persistencia relacional vía Eloquent; histórico operativo y tablas de auditoría. |
| **Cola de trabajos** | Tareas diferidas (importaciones extensas, notificaciones, auditoría en cola) procesadas por un **worker** (`queue:work`). |

En despliegues típicos la cola usa driver **database**; el worker debe estar activo en el servidor para completar trabajos encolados.

## Seguridad y trazabilidad

- **Acceso:** rutas bajo `/admin` exigen autenticación; las acciones sensibles se validan con roles y policies por módulo.
- **Segregación territorial:** los usuarios operan sobre el Servicio de Salud asignado; perfiles globales (`admin`) son la excepción documentada.
- **Auditoría:** cambios relevantes en modelos operativos pueden registrarse en la tabla `audits`, con retención configurable (referencia: seis meses en producción).
- **CSRF y validación:** formularios web y componentes Livewire aplican las protecciones estándar de Laravel.

## Visión técnica (runtime)

Componentes de la aplicación bajo `/admin`: autenticación, capa Voyager/Livewire, persistencia y procesos en segundo plano.

<!-- diagram-source: docs/architecture/platform/vision-tecnica/centinela-vision-tecnica.architecture.json (export SVG Light/classic: npm run diagrams:export-vision-tecnica) -->
![Visión técnica de Centinela](/docs/diagrams/vision-tecnica.svg)

## Extensibilidad y despliegue

Los nuevos desarrollos se incorporan como rutas, controladores, componentes Livewire y vistas bajo el mismo panel, reutilizando autenticación, menús, roles y el contexto de Servicio de Salud y año indicador en sesión.

Cada **Servicio de Salud adoptante** opera una instancia (o entorno) con sus propios datos; la confidencialidad territorial no depende de particiones lógicas dentro de una sola base compartida entre territorios no relacionados.

Para el historial de versiones desplegadas consulte **[Versiones liberadas](/admin/acerca-de/releases)**. Para incidencias o consultas de infraestructura institucional, use **[Soporte](/admin/soporte)**.
