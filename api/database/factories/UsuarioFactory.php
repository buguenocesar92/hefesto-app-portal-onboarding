<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Rol;
use App\Models\Empresa;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    protected $model = Usuario::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            'nombre' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'rol' => Rol::Cliente,
            'ultimo_acceso' => null,
        ];
    }

    public function cliente(Empresa $empresa): static
    {
        return $this->state(fn (): array => [
            'empresa_id' => $empresa->id,
            'rol' => Rol::Cliente,
        ]);
    }

    public function aprobador(): static
    {
        return $this->state(fn (): array => [
            'empresa_id' => null,
            'rol' => Rol::Aprobador,
        ]);
    }

    public function administrador(): static
    {
        return $this->state(fn (): array => [
            'empresa_id' => null,
            'rol' => Rol::Administrador,
        ]);
    }
}