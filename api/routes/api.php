<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AprobacionController;
use App\Http\Controllers\Api\V1\CatalogoController;
use App\Http\Controllers\Api\V1\SolicitudController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas del portal
|--------------------------------------------------------------------------
|
| Tres zonas, separadas a propósito:
|
|   1. Catálogo, sin sesión. Es lo que una PyME ve antes de registrarse.
|   2. Solicitudes del cliente, con sesión. El middleware `empresa` se aplica a todo el
|      grupo web en bootstrap/app.php, antes que `auth`: fija la empresa del usuario en
|      el contenedor (alcance global del modelo) y abre la transacción con el contexto
|      que lee la seguridad de fila de PostgreSQL.
|   3. Aprobación, solo personal de Hefesto, con el middleware `rol.hefesto`.
|
| El portal usa sesión con cookie, no tokens de API para el navegador, así que las
| escrituras pasan por CSRF. La verificación vive en bootstrap/app.php.
|
*/

Route::get('/v1/salud', fn () => response()->json([
    'estado' => 'ok',
    'servicio' => 'portal-onboarding',
    'version' => config('app.version', '0.1.0'),
]))->name('api.salud');

// 1. Catálogo público.
Route::get('/v1/catalogo/kits', [CatalogoController::class, 'kits'])->name('api.kits');
Route::get('/v1/catalogo/planes', [CatalogoController::class, 'planes'])->name('api.planes');

// Sesión: la resuelve Authentik en el borde. Para desarrollo local hay un comando que
// crea el usuario de prueba; ver docs/06-INSTALACION.md.
Route::middleware(['web', 'auth'])->group(function (): void {
    // 2. Solicitudes del cliente.
    Route::get('/v1/solicitudes', [SolicitudController::class, 'index'])->name('api.solicitudes');
    Route::post('/v1/solicitudes', [SolicitudController::class, 'store'])->name('api.solicitudes.crear');
    Route::get('/v1/solicitudes/{solicitud}', [SolicitudController::class, 'show'])->name('api.solicitudes.ver');
    Route::delete('/v1/solicitudes/{solicitud}', [SolicitudController::class, 'destroy'])->name('api.solicitudes.cancelar');

    // 3. Aprobación, solo Hefesto.
    Route::middleware('rol.hefesto')->group(function (): void {
        Route::get('/v1/aprobaciones', [AprobacionController::class, 'index'])->name('api.aprobaciones');
        Route::get('/v1/aprobaciones/{solicitud}/previa', [AprobacionController::class, 'previa'])
            ->name('api.aprobaciones.previa');
        Route::post('/v1/aprobaciones/{solicitud}', [AprobacionController::class, 'aprobar'])
            ->name('api.aprobaciones.aprobar');
        Route::post('/v1/aprobaciones/{solicitud}/rechazar', [AprobacionController::class, 'rechazar'])
            ->name('api.aprobaciones.rechazar');
    });
});