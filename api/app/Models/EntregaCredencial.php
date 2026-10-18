<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Constancia de una credencial entregada.
 *
 * Esta tabla guarda DÓNDE quedó la credencial, nunca la credencial. No hay columna para
 * el valor, y no se debe agregar una: sería el momento exacto en que un respaldo de
 * esta base se convierte en un respaldo de los secretos de todos los clientes.
 *
 * @property string $ruta_infisical
 * @property string $clave
 * @property string $estado  'registrado' o 'pendiente'
 */
class EntregaCredencial extends Model
{
    /** @use HasFactory<\Database\Factories\EntregaCredencialFactory> */
    use HasFactory;

    protected $table = 'entregas_credenciales';

    protected $fillable = [
        'solicitud_id',
        'empresa_id',
        'tipo',
        'ruta_infisical',
        'clave',
        'proyecto',
        'entorno',
        'estado',
        'entregado_en',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'entregado_en' => 'datetime',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class);
    }

    public function quedoRegistrada(): bool
    {
        return $this->estado === 'registrado';
    }

    /**
     * Texto para la pantalla del cliente. Dice la verdad sobre dónde está la credencial
     * sin mostrarla.
     */
    public function ubicacionLegible(): string
    {
        return $this->quedoRegistrada()
            ? 'Disponible en el gestor de secretos de Hefesto, en ' . $this->ruta_infisical
            : 'Pendiente: Hefesto la activa y avisa cuando esté lista';
    }
}