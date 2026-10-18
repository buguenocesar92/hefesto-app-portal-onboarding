<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Roles dentro del portal.
 *
 * Son tres, y la separación importa: quien aprueba una ejecución nunca es la misma
 * persona que la pidió, salvo que Hefesto lo decida explícitamente en la
 * configuración. Ver App\Acciones\AprobarSolicitud.
 */
enum Rol: string
{
    /** Persona de la PyME cliente. Solo ve lo de su propia empresa. */
    case Cliente = 'cliente';

    /** Persona de Hefesto. Aprueba ejecuciones y ve todo el catálogo. */
    case Aprobador = 'aprobador';

    /** Persona de Hefesto con permiso para revisar, además, las entregas de credenciales. */
    case Administrador = 'administrador';

    /** Etiqueta legible para la interfaz. */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Cliente => 'Cliente',
            self::Aprobador => 'Aprobador de Hefesto',
            self::Administrador => 'Administración de Hefesto',
        };
    }

    /** ¿Puede aprobar una ejecución de aprovisionamiento? */
    public function puedeAprobar(): bool
    {
        return in_array($this, [self::Aprobador, self::Administrador], true);
    }

    /** ¿Es una persona de Hefesto? */
    public function esHefesto(): bool
    {
        return $this !== self::Cliente;
    }
}