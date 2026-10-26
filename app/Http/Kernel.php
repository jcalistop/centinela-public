<?php

namespace App\Http;

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\DenyAgentMutations;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\EnforceUploadSizeLimit;
use App\Http\Middleware\EnsureAgentApiEnabled;
use App\Http\Middleware\EnsureAgentAuthenticated;
use App\Http\Middleware\EnsureAgentIpAllowlist;
use App\Http\Middleware\EnsureAgentTokenAbility;
use App\Http\Middleware\EnsureCentinelaSupportEnabled;
use App\Http\Middleware\EnsureComgesBreadModuleEnabled;
use App\Http\Middleware\EnsureIndicatorTypeModuleEnabled;
use App\Http\Middleware\EnsureIndicatorYearIsSet;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\InjectAgentYearContext;
use App\Http\Middleware\LogAgentRequest;
use App\Http\Middleware\PreventConcurrentRequests;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Http\Middleware\ReadOnlyFilePolicy;
use App\Http\Middleware\RecordPlatformUsage;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\TrustHosts;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\ValidateCronToken;
use App\Http\Middleware\ValidateSignature;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use TCG\Voyager\Http\Middleware\VoyagerAdminMiddleware;

class Kernel extends HttpKernel
{
    /**
     * The priority-sorted list of middleware.
     *
     * `Authenticate` runs before route middleware that depend on `$request->user()`
     * (p. ej. Voyager admin y políticas de archivos).
     *
     * @var array<int, class-string|string>
     */
    protected $middlewarePriority = [
        StartSession::class,
        ShareErrorsFromSession::class,
        Authenticate::class,
        VoyagerAdminMiddleware::class,
        EnsureIndicatorYearIsSet::class,
        RecordPlatformUsage::class,
        EnsureComgesBreadModuleEnabled::class,
        SubstituteBindings::class,
        ReadOnlyFilePolicy::class,
    ];

    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        TrustHosts::class,
        TrustProxies::class,
        AddSecurityHeaders::class,
        HandleCors::class,
        PreventRequestsDuringMaintenance::class,
        ValidatePostSize::class,
        TrimStrings::class,
        ConvertEmptyStringsToNull::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            EnsureIndicatorYearIsSet::class,
            RecordPlatformUsage::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            EnsureComgesBreadModuleEnabled::class,
        ],

        'api' => [
            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            ThrottleRequests::class.':api',
            SubstituteBindings::class,
        ],
    ];

    /**
     * The application's middleware aliases.
     *
     * Aliases may be used instead of class names to conveniently assign middleware to routes and groups.
     *
     * @var array<string, class-string|string>
     */
    protected $middlewareAliases = [
        'auth' => Authenticate::class,
        'auth.basic' => AuthenticateWithBasicAuth::class,
        'auth.session' => AuthenticateSession::class,
        'cache.headers' => SetCacheHeaders::class,
        'can' => Authorize::class,
        'guest' => RedirectIfAuthenticated::class,
        'password.confirm' => RequirePassword::class,
        'precognitive' => HandlePrecognitiveRequests::class,
        'signed' => ValidateSignature::class,
        'throttle' => ThrottleRequests::class,
        'verified' => EnsureEmailIsVerified::class,
        'indicator.year' => EnsureIndicatorYearIsSet::class,
        'cron.token' => ValidateCronToken::class,
        'no.overlap' => PreventConcurrentRequests::class,
        'readonly.files' => ReadOnlyFilePolicy::class,
        'upload.size-limit' => EnforceUploadSizeLimit::class,
        'platform.usage' => RecordPlatformUsage::class,
        'centinela.support' => EnsureCentinelaSupportEnabled::class,
        'module.access' => EnsureModuleEnabled::class,
        'module.access.indicator_type' => EnsureIndicatorTypeModuleEnabled::class,
        'module.access.comges_bread' => EnsureComgesBreadModuleEnabled::class,
        'abilities' => CheckAbilities::class,
        'ability' => CheckForAnyAbility::class,
        'agent.enabled' => EnsureAgentApiEnabled::class,
        'agent.ip' => EnsureAgentIpAllowlist::class,
        'agent.auth' => EnsureAgentAuthenticated::class,
        'agent.ability' => EnsureAgentTokenAbility::class,
        'agent.year' => InjectAgentYearContext::class,
        'agent.log' => LogAgentRequest::class,
        'agent.readonly' => DenyAgentMutations::class,
    ];
}
