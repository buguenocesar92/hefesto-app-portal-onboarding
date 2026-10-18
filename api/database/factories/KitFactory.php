<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Kit;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kit>
 */
class KitFactory extends Factory
{
    protected $model = Kit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = ucfirst($this->faker->unique()->word());

        return [
            'codigo' => 'kit-' . strtolower($nombre),
            'nombre' => 'Kit ' . $nombre,
            'categoria' => 'Prueba',
            'resumen' => 'Kit de ejemplo para pruebas.',
            'que_hace' => 'Sirve para probar el catálogo.',
            'requisitos' => ['Un servidor con Ubuntu'],
            'incluye' => ['Documentación'],
            'licencia' => 'MIT',
            'orden' => 1,
            'coolify_tipo' => null,
            'coolify_imagen' => null,
            'aprovisonable' => false,
            'activo' => true,
        ];
    }

    /**
     * Kit que el portal sí sabe crear en Coolify.
     */
    public function aprovisionable(): static
    {
        return $this->state(fn (): array => [
            'coolify_tipo' => 'baserow',
            'coolify_imagen' => 'baserow/baserow:v1',
            'aprovisonable' => true,
        ]);
    }
}