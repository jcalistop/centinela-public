# Centinela – Agent Instructions

## Sobre el Proyecto

Centinela es una aplicación web de monitoreo de indicadores para Servicios de Salud del MINSAL (Chile). Módulos activos:

- **Compromisos de Gestión (COMGES)** – seguimiento de indicadores anuales con cortes, acciones, evaluaciones y tablas de sensibilidad.
- **Visualizador de Establecimientos SNSS** – panel de centros de salud del Sistema Nacional.

Stack: PHP 8.3 · Laravel 11 · Livewire 3 · Voyager 1.7 · Bootstrap 5 (NO Tailwind) · Vite · Alpine.js (incluido con Livewire).

## Secretos locales / `.env` (obligatorio)

- **No** ejecutar en este workspace: `cp .env.example .env`, `copy .env.example .env`, `Copy-Item .env.example .env -Force` (ni equivalentes que sobrescriban `.env`), salvo petición **explícita** del usuario y aceptación del riesgo (copia previa o historial local del editor).
- `php artisan test` usa variables definidas en `phpunit.xml`; **no** hace falta copiar `.env.example` sobre `.env` para correr tests en local.
- El **CI en GitHub** hace `cp` solo en el runner remoto; **no** repetir ese paso en la máquina de desarrollo sobre el mismo directorio con secretos reales: usar **otro clon** o ruta si hace falta imitar CI a mano.

## Arquitectura Clave

### Patrón Año-Basado (Crítico)

Toda la lógica pivota sobre el año activo guardado en sesión como `session('indicator_year')` (un modelo `Year`).

- El middleware `indicator.year` (`EnsureIndicatorYearIsSet`) garantiza que siempre exista en sesión.
- **Dominio COMGES:** vistas en `resources/views/centinela/comges/` y componentes en `resources/views/components/comges/` (`x-comges.*`). Escala % vs puntaje con `$useScoreScale` / [`ComgesYearMode`](app/Support/Comges/ComgesYearMode.php); legacy en subrutas `components/comges/**/legacy/`.
- **Read indicador:** año &lt; 2025 → Voyager `vendor/voyager/indicadores-comges/read`; año ≥ 2025 → `centinela.comges.indicadores.read`.
- Bifurcación 2025: vistas y alertas COMGES viven en `resources/views/centinela/comges/` y `resources/views/components/comges/`.

### Patrón Bifurcación 2025 por Instrumento (Crítico)

Desde 2025 se aplicaron cambios relevantes en orientaciones técnicas de los instrumentos de evaluación (`indicatorType`), lo que generó bifurcaciones funcionales en múltiples capas del proyecto.

- **Regla de diseño**: antes de modificar lógica COMGES, verificar si el flujo depende de `indicatorType` y del año activo (`session('indicator_year')`).
- **Capas afectadas**: controllers Voyager/custom, [`SearchCommitmentComponent`](app/Livewire/SearchCommitmentComponent.php) + servicios `App\Services\Comges\*`, vistas Blade (`centinela/comges/`, `components/comges/`).
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
- **Modelos con `$casts`**: usar `protected $casts = []` (no el método `casts()`, Laravel 10).
- **Vistas Blade**: `resources/views/centinela/` para vistas custom, `resources/views/vendor/voyager/` para overrides de Voyager.
- **Livewire namespace**: `App\Livewire` (p. ej. `SearchCommitmentComponent`); partials en `resources/views/livewire/comges/partials/`. No usar namespace `Comges2025` para compromisos.
- **Frontend**: Bootstrap 5 y SASS en `resources/sass/`. No usar clases de Tailwind.
- **Puntaje Comges 2025**: `cumplimiento` se interpreta como puntaje (no porcentaje). Ver [docs/](docs/).

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

---

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3.28
- laravel/framework (LARAVEL) - v10
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v3
- livewire/livewire (LIVEWIRE) - v3
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v10

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure - don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

## Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double check the available parameters.

## URLs

- Whenever you share a project URL with the user you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain / IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before any other approaches. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation specific for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The 'search-docs' tool is perfect for all Laravel related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel-ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries - package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit"
3. Quoted Phrases (Exact Position) - query="infinite scroll" - Words must be adjacent and in that order
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit"
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms

=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function \_\_construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters.

### Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments

- Prefer PHPDoc blocks over comments. Never use comments within the code itself unless there is something _very_ complex going on.

## PHPDoc Blocks

- Add useful array shape type definitions for arrays when appropriate.

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

=== herd rules ===

## Laravel Herd

- The application is served by Laravel Herd and will be available at: https?://[kebab-case-project-dir].test. Use the `get-absolute-url` tool to generate URLs for the user to ensure valid URLs.
- You must not run any commands to make the site available via HTTP(s). It is _always_ available through Laravel Herd.

=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] <name>` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v10 rules ===

## Laravel 10

- Use the `search-docs` tool to get version specific documentation.
- Middleware typically live in `app/Http/Middleware/` and service providers in `app/Providers/`.
- Laravel 10 has a `bootstrap/app.php` file that creates the application instance and binds kernel contracts, but does not use it for application configuration like Laravel 11:
    - Middleware registration is in `app/Http/Kernel.php`
    - Exception handling is in `app/Exceptions/Handler.php`
    - Console commands and schedule registration is in `app/Console/Kernel.php`
    - Rate limits likely exist in `RouteServiceProvider` or `app/Http/Kernel.php`
- When using Eloquent model casts, you must use `protected $casts = [];` and not the `casts()` method. The `casts()` method isn't available on models in Laravel 10.

=== livewire/core rules ===

## Livewire Core

- Use the `search-docs` tool to find exact version specific documentation for how to write Livewire & Livewire tests.
- Use the `php artisan make:livewire [Posts\\CreatePost]` artisan command to create new components
- State should live on the server, with the UI reflecting it.
- All Livewire requests hit the Laravel backend, they're like regular HTTP requests. Always validate form data, and run authorization checks in Livewire actions.

## Livewire Best Practices

- Livewire components require a single root element.
- Use `wire:loading` and `wire:dirty` for delightful loading states.
- Add `wire:key` in loops:

    ```blade
    @foreach ($items as $item)
        <div wire:key="item-{{ $item->id }}">
            {{ $item->name }}
        </div>
    @endforeach
    ```

- Prefer lifecycle hooks like `mount()`, `updatedFoo()` for initialization and reactive side effects:

<code-snippet name="Lifecycle hook examples" lang="php">
    public function mount(User $user) { $this->user = $user; }
    public function updatedSearch() { $this->resetPage(); }
</code-snippet>

## Testing Livewire

<code-snippet name="Example Livewire component test" lang="php">
    Livewire::test(Counter::class)
        ->assertSet('count', 0)
        ->call('increment')
        ->assertSet('count', 1)
        ->assertSee(1)
        ->assertStatus(200);
</code-snippet>

    <code-snippet name="Testing a Livewire component exists within a page" lang="php">
        $this->get('/posts/create')
        ->assertSeeLivewire(CreatePost::class);
    </code-snippet>

=== livewire/v3 rules ===

## Livewire 3

### Key Changes From Livewire 2

- These things changed in Livewire 2, but may not have been updated in this application. Verify this application's setup to ensure you conform with application conventions.
    - Use `wire:model.live` for real-time updates, `wire:model` is now deferred by default.
    - Components now use the `App\Livewire` namespace (not `App\Http\Livewire`).
    - Use `$this->dispatch()` to dispatch events (not `emit` or `dispatchBrowserEvent`).
    - Use the `components.layouts.app` view as the typical layout path (not `layouts.app`).

### New Directives

- `wire:show`, `wire:transition`, `wire:cloak`, `wire:offline`, `wire:target` are available for use. Use the documentation to find usage examples.

### Alpine

- Alpine is now included with Livewire, don't manually include Alpine.js.
- Plugins included with Alpine: persist, intersect, collapse, and focus.

### Lifecycle Hooks

- You can listen for `livewire:init` to hook into Livewire initialization, and `fail.status === 419` for the page expiring:

<code-snippet name="livewire:load example" lang="js">
document.addEventListener('livewire:init', function () {
    Livewire.hook('request', ({ fail }) => {
        if (fail && fail.status === 419) {
            alert('Your session expired');
        }
    });

    Livewire.hook('message.failed', (message, component) => {
        console.error(message);
    });

});
</code-snippet>

=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.

=== phpunit/core rules ===

## PHPUnit Core

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit <name>` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should test all of the happy paths, failure paths, and weird paths.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files, these are core to the application.

### Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test`.
- To run all tests in a file: `php artisan test tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --filter=testName` (recommended after making a change to a related file).
  </laravel-boost-guidelines>
