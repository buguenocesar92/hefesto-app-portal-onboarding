<?php

declare(strict_types=1);

namespace App\Servicios\Coolify;

/**
 * Respuesta de la API de Coolify, ya normalizada.
 *
 * Se queda solo con lo que el portal necesita. No conserva el cuerpo crudo completo
 * porque podría traer datos del despliegue de otro cliente que compartan instancia de
 * Coolify: recordar que Coolify no es multiinquilino.
 */
final class RespuestaCoolify
{
    /**
     * @param  array<string, mixed>  $cuerpo
     * @param  array<string, string>  $encabezados
     */
    public function __construct(
        public readonly int $estado,
        public readonly array $cuerpo,
        public readonly array $encabezados = [],
        public readonly float $duracionMs = 0.0,
    ) {
    }

    public function exitosa(): bool
    {
        return $this->estado >= 200 && $this->estado < 300;
    }

    public function uuid(): ?string
    {
        $uuid = $this->cuerpo['uuid'] ?? null;

        return is_string($uuid) && $uuid !== '' ? $uuid : null;
    }

    public function texto(): ?string
    {
        $mensaje = $this->cuerpo['message'] ?? null;

        return is_string($mensaje) ? $mensaje : null;
    }

    /**
     * Encabezado de espera tras un límite de peticiones, si Coolify lo envió.
     */
    public function esperaSugerida(): ?int
    {
        $valor = $this->encabezados['retry-after'] ?? $this->encabezados['x-ratelimit-reset'] ?? null;

        if ($valor === null || ! is_numeric($valor)) {
            return null;
        }

        return (int) $valor;
    }

    /**
     * Resumen apto para el historial de pasos: sin cuerpos completos, sin secretos.
     *
     * @return array<string, mixed>
     */
    public function resumen(): array
    {
        return [
            'estado_http' => $this->estado,
            'uuid' => $this->uuid(),
            'duracion_ms' => round($this->duracionMs, 1),
            'mensaje' => $this->texto(),
        ];
    }
}