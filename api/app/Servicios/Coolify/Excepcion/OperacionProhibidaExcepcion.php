<?php

declare(strict_types=1);

namespace App\Servicios\Coolify\Excepcion;

use App\Enums\OperacionCoolify;

/**
 * Se intentó ejecutar una operación que la política de Coolify no permite.
 *
 * Es la excepción más importante del sistema: si aparece en un registro, significa que
 * alguien intentó salir de la lista acotada. Nunca se captura en silencio; el trabajo se
 * marca como fallido y el administrador de Hefesto recibe el aviso.
 */
final class OperacionProhibidaExcepcion extends CoolifyExcepcion
{
    public function __construct(
        private readonly string $metodo,
        private readonly string $ruta,
        private readonly string $motivo,
    ) {
        parent::__construct(sprintf(
            'Operación no permitida: %s %s. Motivo: %s',
            $metodo,
            $ruta,
            $motivo,
        ));
    }

    public static function deOperacion(OperacionCoolify $operacion, string $motivo): self
    {
        return new self($operacion->metodo(), $operacion->ruta(), $motivo);
    }

    public function codigo(): string
    {
        return 'operacion_no_permitida';
    }

    public function metodo(): string
    {
        return $this->metodo;
    }

    public function ruta(): string
    {
        return $this->ruta;
    }

    public function motivo(): string
    {
        return $this->motivo;
    }

    /**
     * @return array<string, string>
     */
    public function contexto(): array
    {
        return [
            'metodo' => $this->metodo,
            'ruta' => $this->ruta,
            'motivo' => $this->motivo,
        ];
    }
}