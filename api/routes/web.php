<?php

declare(strict_types=1);

use App\Http\Controllers\Web\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas del navegador: acceso, salida y sesión
|--------------------------------------------------------------------------
|
| Estas rutas viven en el grupo `web`, que es el que trae la sesión con cookie y la
| verificación CSRF. Van FUERA del grupo que exige `auth`, porque el acceso es
| justamente lo que no tiene sesión todavía.
|
| El middleware `empresa` se aplica antes que `auth` en el resto de la aplicación (ver
| bootstrap/app.php). Aquí no hace falta: mientras no haya sesión no hay contexto de
| empresa, y las tablas protegidas no se tocan.
|
*/

Route::get('/auth/entrar', [AuthController::class, 'entrar'])->name('auth.entrar');

Route::get('/auth/callback', [AuthController::class, 'callback'])->name('auth.callback');

Route::post('/auth/salir', [AuthController::class, 'salir'])->name('auth.salir');

// Estado de la integración, para que la interfaz diga la verdad en vez de fallar al
// intentar entrar.
Route::get('/auth/estado', [AuthController::class, 'estado'])->name('auth.estado');

// Quién está dentro. Devuelve únicamente datos de la sesión actual.
Route::get('/auth/quien-soy', [AuthController::class, 'quienSoy'])->name('auth.quien_soy');