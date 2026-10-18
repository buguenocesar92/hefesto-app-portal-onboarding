<?php

declare(strict_types=1);

namespace App\Servicios\Coolify\Excepcion;

/**
 * Coolify respondió algo que no coincide con lo que su documentación declara.
 *
 * Ocurre con frecuencia cuando una versión cambia el contrato. Preferimos fallar de
 * forma visible antes que asumir una forma de respuesta que no existe.
 */
final class RespuestaInesperadaExcepcion extends CoolifyExcepcion
{
    public function __construct(
        private readonly string $operacion,
        private readonly int $estado,
        private readonly ?string $mensaje,
    ) {
        parent::__construct(sprintf(
            'La operación "%s" respondió con estado %d%s.',
            $operacion,
            $estado,
            $mensaje !== null && $mensaje !== '' ? ': ' . $mensaje : '',
        ));
    }

    public function codigo(): string
    {
        return 'respuesta_inesperada';
    }

    public function operacion(): string
    {
        return $this->operacion;
    }

    public function estado(): int
    {
        return $this->estado;
    }

    /**
     * @return array<string, int|string|null>
     */
    public function contexto(): array
    {
        return [
            'operacion' => $this->operacion,
            'estado_http' => $this->estado,
            'mensaje' => $this->mensaje,
        ];
    }
}