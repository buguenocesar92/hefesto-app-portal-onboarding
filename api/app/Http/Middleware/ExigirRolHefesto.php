<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Deja pasar solo al personal de Hefesto.
 *
 * Protege el área de aprobación. Un cliente que llegue a /api/v1/aprobaciones con una
 * sesión válida recibe 403 antes de que se consulte un solo dato.
 */
class ExigirRolHefesto
{
    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $usuario = $peticion->user();

        if ($usuario === null || ! $usuario->esHefesto()) {
            throw new AccessDeniedHttpException('Esta sección es para el personal de Hefesto.');
        }

        return $siguiente($peticion);
    }
}