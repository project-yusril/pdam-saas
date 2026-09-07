<?php

use App\Http\Middleware\CheckModuleAccess;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\ContentSecurityPolicy;
use App\Http\Middleware\CorsWhitelist;
use App\Http\Middleware\DataConsent;
use App\Http\Middleware\InputSanitizer;
use App\Http\Middleware\LockAccount;
use App\Http\Middleware\SecureHeaders;
use App\Http\Middleware\SetTenant;
use App\Http\Middleware\TrackFailedLogin;
use App\Http\Middleware\WafMiddleware;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->alias([
            'tenant' => SetTenant::class,
            'module' => CheckModuleAccess::class,
            'permission' => CheckPermission::class,
            // Sanctum token abilities (tidak auto-teralias di Laravel 11)
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);
        $middleware->prependToPriorityList(SubstituteBindings::class, SetTenant::class);

        // Security: headers, lock, CORS, CSP, WAF, input sanitization, data consent
        $middleware->api(append: [
            SecureHeaders::class,
            LockAccount::class,
            TrackFailedLogin::class,
            InputSanitizer::class,
            WafMiddleware::class,
            CorsWhitelist::class,
            ContentSecurityPolicy::class,
            DataConsent::class,
        ]);

        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Render semua exception di jalur API dengan format ApiResponse standar
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return match (true) {
                $e instanceof ValidationException => ApiResponse::error(
                    'VALIDATION_ERROR',
                    'Data yang dikirim tidak valid.',
                    $e->errors(),
                    422,
                ),
                $e instanceof AuthenticationException => ApiResponse::error(
                    'UNAUTHENTICATED',
                    'Anda belum login atau sesi telah berakhir.',
                    null,
                    401,
                ),
                $e instanceof AuthorizationException => ApiResponse::error(
                    'FORBIDDEN',
                    'Anda tidak memiliki izin untuk aksi ini.',
                    null,
                    403,
                ),
                $e instanceof ModelNotFoundException,
                $e instanceof NotFoundHttpException => ApiResponse::error(
                    'NOT_FOUND',
                    'Data atau endpoint tidak ditemukan.',
                    null,
                    404,
                ),
                $e instanceof HttpExceptionInterface => ApiResponse::error(
                    'HTTP_ERROR',
                    $e->getMessage() ?: 'Terjadi kesalahan.',
                    null,
                    $e->getStatusCode(),
                ),
                default => ApiResponse::error(
                    'SERVER_ERROR',
                    config('app.debug') ? $e->getMessage() : 'Terjadi kesalahan pada server.',
                    config('app.debug') ? ['exception' => class_basename($e)] : null,
                    500,
                ),
            };
        });
    })->create();
