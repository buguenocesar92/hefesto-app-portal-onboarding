<?php

declare(strict_types=1);

namespace App\Servicios\Infisical\Excepcion;

use RuntimeException;

/**
 * Infisical no está disponible, o su configuración no está confirmada.
 *
 * El mensaje dice qué falta, para que la persona que va a operarlo sepa exactamente qué
 * revisar. No contiene el valor de ningún secreto.
 */
final class InfisicalNoDisponibleExcepcion extends RuntimeException
{
    public function codigo(): string
    {
        return 'infisical_no_disponible';
    }
}