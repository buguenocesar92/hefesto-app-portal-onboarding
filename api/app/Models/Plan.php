<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plan comercial del catálogo.
 *
 * El precio es un marcador. Un precio de 0 significa "a confirmar con Hefesto", y la
 * interfaz lo dice así, sin esconderlo detrás de un guion.
 *
 * @property array<int, string> $kits  Códigos de kit incluidos.
 * @property int $precio_mensual_clp
 */
class Plan extends Model
{
    /** @use HasFactory<\Database\Factories\PlanFactory> */
    use HasFactory;

    protected $table = 'planes';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'precio_mensual_clp',
        'precio_nota',
        'kits',
        'estado',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'kits' => 'array',
            'precio_mensual_clp' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function solicitudes(): HasMany
    {
        return $this->hasMany(Solicitud::class);
    }

    public function estaDisponible(): bool
    {
        return $this->activo && $this->estado === 'disponible';
    }

    /** Los kits del plan, ya con el modelo completo. */
    public function kitsDelPlan()
    {
        return Kit::query()
            ->whereIn('codigo', $this->kits ?? [])
            ->orderBy('orden')
            ->get();
    }

    /**
     * Precio listo para mostrar, en texto. Nunca inventa una cifra.
     */
    public function precioMostrado(): string
    {
        if ($this->precio_mensual_clp > 0) {
            return '$' . number_format($this->precio_mensual_clp, 0, ',', '.') . ' CLP al mes';
        }

        return $this->precio_nota ?? 'Precio a confirmar con Hefesto';
    }
}