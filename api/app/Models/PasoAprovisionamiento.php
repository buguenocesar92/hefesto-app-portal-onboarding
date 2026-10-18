<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoPaso;
use App\Enums\OperacionCoolify;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un paso del aprovisionamiento.
 *
 * El nombre del paso es siempre un caso de OperacionCoolify. No hay campo libre para el
 * nombre, y eso es deliberado: si no está en el enum, no se puede registrar, y si no se
 * puede registrar no se ejecutó.
 *
 * @property EstadoPaso $estado
 * @property string $operacion
 * @property array<string, mixed> $resultado
 */
class PasoAprovisionamiento extends Model
{
    /** @use HasFactory<\Database\Factories\PasoAprovisionamientoFactory> */
    use HasFactory;

    protected $table = 'pasos_aprovisionamiento';

    protected $fillable = [
        'solicitud_id',
        'empresa_id',
        'orden',
        'operacion',
        'estado',
        'mensaje',
        'resultado',
        'error_codigo',
        'iniciado_en',
        'terminado_en',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoPaso::class,
            'resultado' => 'array',
            'orden' => 'integer',
            'iniciado_en' => 'datetime',
            'terminado_en' => 'datetime',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class);
    }

    /** La operación de Coolify que representa este paso. */
    public function operacionCoolify(): ?OperacionCoolify
    {
        return OperacionCoolify::tryFrom($this->operacion);
    }

    public function etiqueta(): string
    {
        return $this->operacionCoolify()?->etiqueta() ?? $this->operacion;
    }

    public function marcarEnCurso(): void
    {
        $this->forceFill([
            'estado' => EstadoPaso::EnCurso,
            'iniciado_en' => now(),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    public function marcarCompletado(array $resultado, ?string $mensaje = null): void
    {
        $this->forceFill([
            'estado' => EstadoPaso::Completado,
            'resultado' => $resultado,
            'mensaje' => $mensaje,
            'terminado_en' => now(),
        ])->save();
    }

    public function marcarFallido(string $mensaje, ?string $codigo = null): void
    {
        $this->forceFill([
            'estado' => EstadoPaso::Fallido,
            'mensaje' => $mensaje,
            'error_codigo' => $codigo,
            'terminado_en' => now(),
        ])->save();
    }

    public function marcarOmitido(string $motivo): void
    {
        $this->forceFill([
            'estado' => EstadoPaso::Omitido,
            'mensaje' => $motivo,
            'terminado_en' => now(),
        ])->save();
    }
}