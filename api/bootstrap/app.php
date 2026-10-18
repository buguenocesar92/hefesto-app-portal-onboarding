<?php

declare(strict_types=1);

use App\Acciones\AprovisionarSolicitud;
use App\Acciones\AprobarSolicitud;
use App\Acciones\RegistrarEntregaDeCredenciales;
use App\Http\Middleware\ExigirRolHefesto;
use App\Http\Middleware\ResolverEmpresa;
use App\Integraciones\NotificadorNtfy;
use App\Jobs\EjecutarAprovisionamiento;
use App\Jobs\EntregarCredenciales;
use App\Jobs\NotificarProgreso;
use App\Servicios\Auth\ClienteAuthentik;
use App\Servicios\Auth\ResolvedorIdentidad;
use App\Servicios\Coolify\ClienteCoolify;
use App\Servicios\Infisical\ClienteInfisical;
use App\Servicios\Tenancy\ContextoEmpresa;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/*
|--------------------------------------------------------------------------
| Arranque de la aplicación del Portal de Onboarding de Hefesto
|--------------------------------------------------------------------------
|
| Los puntos de middleware que importan para la seguridad están todos en
| routes/api.php y en este archivo:
|
|   - ResolverEmpresa: fija la empresa del usuario autenticado en el contenedor, lo que
|     activa el alcance global del modelo Solicitud, y abre la transacción con el contexto
|     de empresa que lee la seguridad de fila de PostgreSQL. Son las dos capas.
|   - ValidateCsrfToken: se aplica también a las rutas de escritura de la API,
|     porque el portal usa sesión con cookie y no un token de API.
|   - ExigirRolHefesto: separa el área de aprobación del área del cliente.
|
*/

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/api/salud-interno',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // `empresa` va PRIMERO en el grupo web, antes que `auth`. Tiene que resolver el
        // usuario por su cuenta con el guard `web` y abrir la transacción con el
        // contexto de empresa antes de que cualquier controlador toque la base.
        $middleware->web(prepend: [
            ResolverEmpresa::class,
        ], append: [
            AuthenticateSession::class,
        ]);

        $middleware->alias([
            'empresa' => ResolverEmpresa::class,
            'rol.hefesto' => ExigirRolHefesto::class,
        ]);

        // La API usa sesión con cookie, así que las escrituras pasan por CSRF igual
        // que las del navegador. ValidateCsrfToken ignora GET, HEAD y OPTIONS.
        $middleware->api(prepend: [
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        ]);

        $middleware->trustProxies(at: [
            Request::HEADER_X_FORWARDED_FOR,
            Request::HEADER_X_FORWARDED_HOST,
        ]);
    })
    ->withExceptions(function (Exceptions $excepciones): void {
        // Los errores de validación se devuelven en español y sin filtrar internals.
        $excepciones->render(function (ValidationException $error, Request $peticion) {
            return response()->json([
                'mensaje' => 'Revisa los datos enviados.',
                'errores' => $error->errors(),
            ], 422);
        });

        // Cualquier otra excepción: mensaje breve y sin rastro interno hacia el
        // cliente. El detalle va al registro del servidor.
        $excepciones->render(function (Throwable $error, Request $peticion) {
            $estado = $error instanceof HttpExceptionInterface ? $error->getStatusCode() : 500;

            if ($peticion->expectsJson()) {
                return response()->json([
                    'mensaje' => $estado === 500
                        ? 'Ocurrió un error en el portal. Hefesto fue notificado.'
                        : $error->getMessage(),
                ], $estado);
            }

            return null;
        });
    })
    ->withBindings([
        ClienteCoolify::class => fn (): ClienteCoolify => ClienteCoolify::desdeConfiguracion(),
        ClienteInfisical::class => fn (): ClienteInfisical => ClienteInfisical::desdeConfiguracion(),
        NotificadorNtfy::class => fn (): NotificadorNtfy => NotificadorNtfy::desdeConfiguracion(),
        ClienteAuthentik::class => fn (): ClienteAuthentik => ClienteAuthentik::desdeConfiguracion(),
        ResolvedorIdentidad::class,
        ContextoEmpresa::class,
        AprobarSolicitud::class,
        AprovisionarSolicitud::class,
        RegistrarEntregaDeCredenciales::class,
    ], is_singleton: true)
    ->create();