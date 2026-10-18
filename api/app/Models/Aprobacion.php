<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Decisión humana sobre una solicitud.
 *
 * Se escribe antes de ejecutar. Guarda qué operaciones se autorizaron, para que la
 * pregunta "¿qué autorizó exactamente?" tenga respuesta en una tabla y no en un
 * recuerdo.
 *
 * @property string $decision  'aprobada', 'rechazada' o 'cancelada'
 * @property array<int, string> $operaciones
 * @property string|null $huella_credencial
 */
class Aprobacion extends Model
{
    /** @use HasFactory<\Database\Factories\AprobacionFactory> */
    use HasFactory;

    protected $table = 'bitacora_aprobaciones';

    protected $fillable = [
        'solicitud_id',
        'usuario_id',
        'empresa_id',
        'decision',
        'operaciones',
        'comentario',
        'ip_origen',
        'huella_credencial',
    ];

    protected function casts(): array
    {
        return [
            'operaciones' => 'array',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    public function fueAprobacion(): bool
    {
        return $this->decision === 'aprobada';
    }
}