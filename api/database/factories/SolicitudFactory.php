<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstadoSolicitud;
use App\Models\Empresa;
use App\Models\Solicitud;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Solicitud>
 */
class SolicitudFactory extends Factory
{
    protected $model = Solicitud::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            'plan_id' => null,
            'creada_por' => null,
            'estado' => EstadoSolicitud::PendienteDeRevision,
            'resumen' => 'Solicitud de ejemplo para pruebas.',
            'datos' => [],
        ];
    }

    public function deEmpresa(Empresa $empresa, Usuario $autor): static
    {
        return $this->state(fn (): array => [
            'empresa_id' => $empresa->id,
            'creada_por' => $autor->id,
        ]);
    }

    public function aprobada(): static
    {
        return $this->state(fn (): array => [
            'estado' => EstadoSolicitud::Aprobada,
            'aprobada_en' => now(),
        ]);
    }
}