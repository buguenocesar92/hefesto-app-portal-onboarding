<?php

declare(strict_types=1);

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Punto de entrada del portal de onboarding
|--------------------------------------------------------------------------
|
| Las peticiones que llegan con una ruta desconocida o con un método no permitido se
| devuelven como JSON, no como una página HTML de Laravel. Este backend alimenta a Nuxt,
| que se encarga de la presentación.
|
*/

if (file_exists($mantenimiento = __DIR__ . '/../storage/framework/maintenance.php')) {
    require $mantenimiento;
}

require __DIR__ . '/../vendor/autoload.php';

/** @var Illuminate\Foundation\Application $app */
$app = require_once __DIR__ . '/../bootstrap/app.php';

$app->handleRequest(Request::capture());