<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Solicitud;
use App\Models\Usuario;

/**
 * Quién puede hacer qué con una solicitud.
 *
 * La regla de fondo es corta: un cliente solo ve y toca solicitudes de su propia
 * empresa; el personal de Hefesto ve todas, pero solo quien tiene rol de aprobación
 * puede aprobar.
 *
 * Importante: estas comprobaciones son la SEGUNDA barrera. La primera es el alcance
 * global del modelo Solicitud, que ya impide que una consulta sin filtro devuelva
 * solicitudes de otra empresa. Si alguien olvida una autorización, el alcance global
 * sigue ahí.
 */
final class SolicitudPolicy
{
    /**
     * Ver la lista de solicitudes de la propia empresa.
     */
    public function viewAny(Usuario $usuario): bool
    {
        if ($usuario->esHefesto()) {
            return true;
        }

        return ($usuario->empresa?->estaActiva() ?? false);
    }

    public function ver(Usuario $usuario, Solicitud $solicitud): bool
    {
        if ($usuario->esHefesto()) {
            return true;
        }

        return $usuario->empresa_id !== null
            && $usuario->empresa_id === $solicitud->empresa_id;
    }

    public function crear(Usuario $usuario): bool
    {
        // Solo un cliente de una empresa activa solicita. El personal de Hefesto no
        // usa el portal para pedirse infrastructure a sí mismo.
        return ! $usuario->esHefesto() && ($usuario->empresa?->estaActiva() ?? false);
    }

    public function actualizar(Usuario $usuario, Solicitud $solicitud): bool
    {
        if (! $this->ver($usuario, $solicitud)) {
            return false;
        }

        // Mientras la ejecución no empezó, el cliente puede agregar notas.
        if ($usuario->esHefesto()) {
            return true;
        }

        return in_array($solicitud->estado->value, ['pendiente_de_revision', 'necesita_informacion'], true);
    }

    public function aprobar(Usuario $usuario, Solicitud $solicitud): bool
    {
        return $usuario->puedeAprobar() && $solicitud->estado->permiteAprobar();
    }

    public function rechazar(Usuario $usuario, Solicitud $solicitud): bool
    {
        return $usuario->puedeAprobar() && $solicitud->estado->permiteAprobar();
    }

    public function cancelar(Usuario $usuario, Solicitud $solicitud): bool
    {
        if ($usuario->esHefesto()) {
            return true;
        }

        return $this->ver($usuario, $solicitud) && in_array($solicitud->estado->value, [
            'pendiente_de_revision',
            'necesita_informacion',
        ], true);
    }

    /**
     * Ver el detalle técnico, con identificadores de Coolify, es solo para Hefesto.
     */
    public function verDetalleTecnico(Usuario $usuario): bool
    {
        return $usuario->esHefesto();
    }
}