<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => 'plan-' . strtolower($this->faker->unique()->word()),
            'nombre' => 'Plan de prueba',
            'descripcion' => 'Plan de ejemplo para pruebas.',
            'precio_mensual_clp' => 0,
            'precio_nota' => 'Precio a confirmar con Hefesto',
            'kits' => [],
            'estado' => 'disponible',
            'activo' => true,
        ];
    }
}