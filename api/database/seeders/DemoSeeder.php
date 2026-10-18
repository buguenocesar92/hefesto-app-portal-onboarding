<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Rol;
use App\Integraciones\NotificadorNtfy;
use App\Models\Empresa;
use App\Models\Plan;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

/**
 * Datos de ejemplo: un tenant (empresa), un plan y dos personas.
 *
 * Son datos de demostración, no clientes reales. Los correos usan el dominio de
 * ejemplo reservado para documentación (.test), que nunca resuelve en internet.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $tema = NotificadorNtfy::generarTema();

        $empresa = Empresa::updateOrCreate(
            ['nombre_corto' => 'pyme-demo'],
            [
                'razon_social' => 'Comercializadora de Prueba SpA',
                // Dominio de correo: es lo que permite resolver a qué empresa pertenece
                // la identidad que entra por Authentik, antes de que exista sesión.
                'dominio_correo' => 'pyme-demo.test',
                'contacto_nombre' => 'Persona de Contacto',
                'contacto_email' => 'contacto@pyme-demo.test',
                'contacto_telefono' => '+56 9 0000 0000',
                'estado' => 'activa',
                'tema_ntfy' => $tema['tema'],
                'tema_ntfy_huella' => $tema['huella'],
            ],
        );

        $plan = Plan::updateOrCreate(
            ['codigo' => 'plan-inicial-demo'],
            [
                'nombre' => 'Plan inicial (demostración)',
                'descripcion' => 'Ejemplo de plan con tres kits del catálogo. Los precios son '
                    .'marcadores: los mantiene Hefesto fuera del portal.',
                'precio_mensual_clp' => 0,
                'precio_nota' => 'Precio a confirmar con Hefesto',
                'kits' => [
                    'hefesto-vps-administrado',
                    'hefesto-respaldo-gestionado',
                    'hefesto-identidad',
                ],
                'estado' => 'disponible',
                'activo' => true,
            ],
        );

        Usuario::updateOrCreate(
            ['email' => 'cliente@pyme-demo.test'],
            [
                'empresa_id' => $empresa->id,
                'nombre' => 'Persona de Contacto',
                'rol' => Rol::Cliente,
            ],
        );

        Usuario::updateOrCreate(
            ['email' => 'hefesto@ejemplo.test'],
            [
                'empresa_id' => null,
                'nombre' => 'Persona de Hefesto',
                'rol' => Rol::Aprobador,
            ],
        );

        $this->command?->info('Datos de demostración creados:');
        $this->command?->line('  Empresa: ' . $empresa->razon_social . ' (' . $empresa->nombre_corto . ')');
        $this->command?->line('  Plan: ' . $plan->nombre);
        $this->command?->line('  Cliente de ejemplo: cliente@pyme-demo.test');
        $this->command?->line('  Aprobador de ejemplo: hefesto@ejemplo.test');
        $this->command?->warn('  Estos usuarios no tienen contraseña: la identidad viene de Authentik.');
    }
}