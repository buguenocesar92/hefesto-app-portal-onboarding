<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Rol;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Usuario del portal.
 *
 * La contraseña no está aquí: la trae Authentik, igual que en las otras aplicaciones de
 * Hefesto. La clase extiende Authenticatable para que el middleware de sesión funcione,
 * pero el campo de contraseña nunca se usa.
 *
 * @property int $id
 * @property int|null $empresa_id
 * @property Rol $rol
 * @property string $email
 */
class Usuario extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UsuarioFactory> */
    use HasFactory;
    use Notifiable;

    protected $table = 'usuarios';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'email',
        'rol',
        'authentik_subject',
        'ultimo_acceso',
    ];

    protected $hidden = [
        'remember_token',
        'authentik_subject',
    ];

    protected function casts(): array
    {
        return [
            'rol' => Rol::class,
            'ultimo_acceso' => 'datetime',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function esHefesto(): bool
    {
        return $this->rol->esHefesto();
    }

    public function puedeAprobar(): bool
    {
        return $this->rol->puedeAprobar();
    }

    /**
     * A qué empresa pertenece a ojos del portal.
     *
     * El personal de Hefesto no pertenece a ninguna empresa: ve todo lo aprobado, y esa
     * es exactamente la razón por la que su rol no puede crear solicitudes de cliente.
     */
    public function empresaId(): ?int
    {
        return $this->esHefesto() ? null : $this->empresa_id;
    }
}