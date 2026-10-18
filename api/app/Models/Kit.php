<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Kit del catálogo de Hefesto.
 *
 * Los catorce kits que aparecen aquí son los mismos del catálogo del estudio. Lo que
 * cambia en esta tabla son dos columnas:
 *
 *  - coolify_tipo y coolify_imagen: los valores que la API de Coolify necesita para
 *    desplegar el kit. Vienen en blanco cuando no se han verificado contra la
 *    documentación, y en ese caso aprovisonable queda en falso y el portal se niega a
 *    ejecutarlo. Un parámetro inventado es peor que un kit todavía no automatizable.
 *
 *  - El precio NO está en esta tabla. Los valores comerciales los mantiene Hefesto
 *    fuera del portal.
 *
 * @property string $codigo
 * @property bool $aprovisonable
 * @property string|null $coolify_tipo
 */
class Kit extends Model
{
    /** @use HasFactory<\Database\Factories\KitFactory> */
    use HasFactory;

    protected $table = 'kits';

    protected $fillable = [
        'codigo',
        'nombre',
        'categoria',
        'resumen',
        'que_hace',
        'requisitos',
        'incluye',
        'licencia',
        'orden',
        'coolify_tipo',
        'coolify_imagen',
        'aprovisonable',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'requisitos' => 'array',
            'incluye' => 'array',
            'aprovisonable' => 'boolean',
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    /**
     * ¿Se puede pedir este kit desde el portal?
     *
     * Activo, visible y con los parámetros de Coolify verificados. Cualquiera de las
     * tres cosas que falte, y el portal no ofrece el kit.
     */
    public function estaAprovisionable(): bool
    {
        return $this->activo && $this->aprovisonable && $this->coolify_tipo !== null;
    }

    /** Texto de la interfaz para los que no se pueden automatizar todavía. */
    public function motivoDeNoAprovisionar(): ?string
    {
        if (! $this->activo) {
            return 'El kit está dado de baja en el catálogo.';
        }

        if (! $this->aprovisonable || $this->coolify_tipo === null) {
            return 'Este kit todavía no se automatizó en el portal. Lo prepara una persona de Hefesto.';
        }

        return null;
    }
}