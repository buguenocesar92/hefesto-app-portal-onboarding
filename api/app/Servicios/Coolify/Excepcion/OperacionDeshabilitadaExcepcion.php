<?php

declare(strict_types=1);

namespace App\Servicios\Coolify\Excepcion;

use App\Enums\OperacionCoolify;

/**
 * La integración con Coolify está apagada, o la operación está apagada aunque la
 * integración esté encendida.
 *
 * El modo deshabilitado no es una simulación: el trabajo no llama a Coolify y termina
 * marcado como "no ejecutado". Ver docs/10-PRUEBAS-Y-ESTADO.md.
 */
final class OperacionDeshabilitadaExcepcion extends CoolifyExcepcion
{
    public function __construct(
        private readonly OperacionCoolify $operacion,
        private readonly string $razon,
    ) {
        parent::__construct(sprintf(
            'La operación "%s" está deshabilitada: %s',
            $operacion->etiqueta(),
            $razon,
        ));
    }

    public function codigo(): string
    {
        return 'operacion_deshabilitada';
    }

    public function operacion(): OperacionCoolify
    {
        return $this->operacion;
    }

    public function razon(): string
    {
        return $this->razon;
    }
}