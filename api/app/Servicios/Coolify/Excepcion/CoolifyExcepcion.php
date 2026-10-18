<?php

declare(strict_types=1);

namespace App\Servicios\Coolify\Excepcion;

use RuntimeException;

/**
 * Base de los errores de la integración con Coolify.
 *
 * Estos mensajes terminan en el historial de pasos, que es visible para el cliente.
 * Por eso ninguno puede contener el cuerpo de la respuesta ni la credencial: describen
 * qué se intentó y qué conviene hacer, nada más.
 */
abstract class CoolifyExcepcion extends RuntimeException
{
    /**
     * Código corto y estable, para poder consultar y agrupar errores sin leer el texto.
     */
    abstract public function codigo(): string;
}