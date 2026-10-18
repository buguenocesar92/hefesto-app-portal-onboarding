<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Empresa cliente. Es el tenant.
 *
 * No lleva alcance global a propósito: es la tabla raíz, y el personal de Hefesto la
 * necesita entera. El aislamiento empieza en Solicitud.
 *
 * @property int $id
 * @property string $nombre_corto
 * @property string $razon_social
 * @property string|null $tema_ntfy
 * @property string|null $tema_ntfy_huella
 */
class Empresa extends Model
{
    /** @use HasFactory<\Database\Factories\EmpresaFactory> */
    use HasFactory;

    protected $table = 'empresas';

    protected $fillable = [
        'nombre_corto',
        'dominio_correo',
        'razon_social',
        'contacto_nombre',
        'contacto_email',
        'contacto_telefono',
        'estado',
        'tema_ntfy',
        'tema_ntfy_huella',
    ];

    /**
     * El tema de ntfy nunca sale de la API. Es una contraseña, no un dato del cliente.
     *
     * @var list<string>
     */
    protected $hidden = [
        'tema_ntfy',
        'tema_ntfy_huella',
    ];

    protected function casts(): array
    {
        return [
            'tema_ntfy' => 'encrypted',
            'tema_ntfy_huella' => 'string',
        ];
    }

    public function solicitudes(): HasMany
    {
        return $this->hasMany(Solicitud::class);
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class);
    }

    public function estaActiva(): bool
    {
        return $this->estado === 'activa';
    }
}