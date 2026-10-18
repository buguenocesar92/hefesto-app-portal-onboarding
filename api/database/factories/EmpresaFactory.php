<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Empresa>
 */
class EmpresaFactory extends Factory
{
    protected $model = Empresa::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = $this->faker->unique()->company();

        return [
            'nombre_corto' => Str::slug($nombre) . '-' . Str::lower(Str::random(4)),
            'dominio_correo' => null,
            'razon_social' => $nombre,
            'contacto_nombre' => $this->faker->name(),
            'contacto_email' => $this->faker->unique()->safeEmail(),
            'contacto_telefono' => '+56 9 ' . $this->faker->numerify('#### ####'),
            'estado' => 'activa',
            'tema_ntfy' => null,
            'tema_ntfy_huella' => null,
        ];
    }

    public function suspendida(): static
    {
        return $this->state(fn (): array => ['estado' => 'suspendida']);
    }

    /**
     * Empresa con su tema de ntfy ya generado, como en producción.
     */
    public function conTemaNtfy(): static
    {
        return $this->state(function (): array {
            $tema = 'hefesto-' . bin2hex(random_bytes(16));

            return [
                'tema_ntfy' => $tema,
                'tema_ntfy_huella' => hash('sha256', $tema),
            ];
        });
    }
}