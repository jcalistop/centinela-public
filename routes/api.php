<?php

use App\Http\Controllers\Api\AuthTokenController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\V1\CompromisosController;
use App\Http\Controllers\Api\V1\ContextController;
use App\Http\Controllers\Api\V1\DashboardAlertasController;
use App\Http\Controllers\Api\V1\EstablecimientosController;
use App\Http\Controllers\Api\V1\GlossaryController;
use App\Http\Controllers\Api\V1\HealthController as V1HealthController;
use App\Http\Controllers\Api\V1\IndicadoresController;
use App\Http\Controllers\Api\V1\InstrumentosController;
use App\Http\Controllers\Api\V1\OpenApiController;
use App\Http\Controllers\Api\V1\ResultadosController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\YearsController;
use App\Models\User;
use App\Support\Agent\AgentErrorEnvelope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    $user = $request->user();

    // AG-02 / S-02: tokens de agente no reciben el User completo.
    if ($user instanceof User && $user->hasRole('agente')) {
        return AgentErrorEnvelope::json(
            'FORBIDDEN',
            'GET /api/user no está disponible para tokens de agente.',
            403
        );
    }

    return $user;
});

// Endpoint público (o básico) de salud para monitorización externa

// Endpoint para emitir token Sanctum para admin (rate limit dedicado: ver RouteServiceProvider `admin-api-token`)
Route::post('/admin/token', [AuthTokenController::class, 'issueToken'])
    ->middleware('throttle:admin-api-token');
Route::get('/health', HealthController::class);

/*
|--------------------------------------------------------------------------
| Rutas de Tareas Programadas (Cron externo)
|--------------------------------------------------------------------------
|
| Endpoints protegidos con token para ejecución mediante cron externo
| Requiere header: X-Cron-Token: {CRON_SECRET_TOKEN}
| AG-01: ability ops (tokens admin con * siguen pasando).
|
*/

Route::middleware(['cron.token', 'auth:sanctum', 'abilities:ops'])->prefix('schedule')->group(function () {
    // Limitar a 10 llamadas por minuto y evitar solapamiento por endpoint
    Route::middleware(['throttle:10,1', 'no.overlap'])->group(function () {
        Route::post('/run', [ScheduleController::class, 'runSchedule']);
        Route::get('/status', [ScheduleController::class, 'status']);
        Route::post('/queue/process', [ScheduleController::class, 'processQueue']);
        Route::post('/backup/database', [ScheduleController::class, 'runDatabaseBackup']);
    });
});

/*
|--------------------------------------------------------------------------
| Superficie agentes P3a — api/v1 (SDD-248 + SDD-249)
|--------------------------------------------------------------------------
*/

$agentStack = [
    'agent.enabled',
    'agent.readonly',
    'agent.log',
    'agent.ip',
    'throttle:agent',
    'agent.auth',
    'agent.ability:read',
    'agent.year',
];

Route::prefix('v1')->middleware($agentStack)->group(function () {
    Route::get('/health', V1HealthController::class);
    Route::get('/years', YearsController::class);
    Route::get('/context', ContextController::class);
    Route::get('/instrumentos', InstrumentosController::class);
    Route::get('/compromisos', CompromisosController::class);
    Route::get('/indicadores', [IndicadoresController::class, 'index']);
    Route::get('/indicadores/{id}', [IndicadoresController::class, 'show'])->whereNumber('id');
    Route::get('/search', SearchController::class);
    Route::get('/establecimientos', [EstablecimientosController::class, 'index']);
    Route::get('/establecimientos/{id}', [EstablecimientosController::class, 'show'])->whereNumber('id');
    Route::get('/resultados', ResultadosController::class);
    Route::get('/dashboard/alertas', DashboardAlertasController::class);
    Route::get('/glossary', GlossaryController::class);
    Route::get('/openapi.json', OpenApiController::class);

    // AG-27: envelope explícito (el router Laravel 405 no entra al stack de middleware).
    Route::match(['post', 'put', 'patch', 'delete'], '{path}', function () {
        return AgentErrorEnvelope::json(
            'METHOD_NOT_ALLOWED',
            'La superficie de agentes solo admite lectura (GET).',
            405
        );
    })->where('path', '.*');
});
