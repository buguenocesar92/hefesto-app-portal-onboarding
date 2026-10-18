<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Servicios\Tenancy\ContextoEmpresa;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija la empresa del usuario autenticado, en las dos capas del aislamiento.
 *
 * CAPA 1 (aplicación). Deja `empresa.actual` en el contenedor, que es lo que activa el
 * alcance global del modelo Solicitud: desde ese momento, una consulta sin filtro
 * explícito devuelve solamente lo de esa empresa. Un cliente de otra empresa no obtiene
 * una lista vacía por casualidad: obtiene una lista vacía siempre.
 *
 * CAPA 2 (base de datos). Envuelve la petición en una transacción con el contexto de
 * empresa fijado, para que la seguridad de fila de PostgreSQL sepa a quién pertenece lo
 * que se pide. Esta capa es la que impide que un error en la aplicación entregue datos de
 * otra empresa.
 *
 * El personal de Hefesto no pertenece a ninguna empresa: para ellos se fija el contexto
 * de Hefesto, que la política de la base reconoce explícitamente.
 *
 * Se aplica al grupo `web` ANTES del middleware `auth` de la ruta. Por eso no usa
 * `$request->user()` todavía: resuelve el usuario por su cuenta con el guard `web`.
 *
 * Peticiones sin sesión (acceso, salida, salud) pasan sin contexto. Si esas rutas tocaran
 * una tabla protegida, `public.app_empresa_id()` lanzaría excepción y la petición
 * fallaría: el aislamiento falla cerrado, nunca abierto.
 */
class ResolverEmpresa
{
    public function __construct(private readonly ContextoEmpresa $contexto)
    {
    }

    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $usuario = auth()->guard('web')->user();

        if ($usuario === null) {
            app()->instance('empresa.actual', null);

            return $siguiente($peticion);
        }

        if ($usuario->esHefesto()) {
            app()->instance('empresa.actual', null);

            return $this->contexto->ejecutarAisladoComoHefesto(
                static fn (): Response => $siguiente($peticion)
            );
        }

        if ($usuario->empresa_id === null) {
            // Una cuenta de cliente sin empresa no puede operar. Se corta aquí, antes de
            // tocar la base de datos.
            abort(403, 'La cuenta no está asociada a ninguna empresa.');
        }

        app()->instance('empresa.actual', $usuario->empresa_id);

        return $this->contexto->ejecutarAislado(
            (int) $usuario->empresa_id,
            static fn (): Response => $siguiente($peticion)
        );
    }
}