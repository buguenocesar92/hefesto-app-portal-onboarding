<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Acciones\AprobarSolicitud;
use App\Acciones\AprovisionarSolicitud;
use App\Enums\EstadoPaso;
use App\Enums\EstadoSolicitud;
use App\Jobs\EjecutarAprovisionamiento;
use App\Models\Empresa;
use App\Models\Kit;
use App\Models\Plan;
use App\Models\Solicitud;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La aprobación humana es obligatoria.
 *
 * Tres cosas se comprueban aquí:
 *   1. El cliente no puede aprobar, aunque tenga una sesión válida.
 *   2. Sin aprobación registrada, el trabajo encolado no toca Coolify.
 *   3. Con la integración apagada, el recorrido NO se hace pasar por exitoso.
 */
class AprobacionHumanaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function el_cliente_no_puede_aprobar(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = Usuario::factory()->cliente($empresa)->create();
        $solicitud = Solicitud::factory()->deEmpresa($empresa, $cliente)->create();

        $this->actingAs($cliente)
            ->postJson('/api/v1/aprobaciones/' . $solicitud->id, [
                'operaciones' => ['crear_proyecto'],
            ])
            ->assertForbidden();
    }

    #[Test]
    public function sin_aprobacion_registrada_el_trabajo_no_toca_coolify(): void
    {
        Queue::fake();

        $empresa = Empresa::factory()->create();
        $cliente = Usuario::factory()->cliente($empresa)->create();

        // Estado forzado a "aprobada" a mano, sin fila en la bitácora. Exactamente lo que
        // haría un descuido o alguien con acceso a la base.
        $solicitud = Solicitud::factory()->deEmpresa($empresa, $cliente)->create([
            'estado' => EstadoSolicitud::Aprobada,
            'aprobada_en' => null,
            'aprobada_por' => null,
        ]);

        $this->assertFalse($solicitud->tieneAprobacionRegistrada());
        $this->assertFalse($solicitud->puedeEjecutarse());

        app(AprovisionarSolicitud::class)->ejecutar($solicitud->id);

        // No se creó ningún paso: no se llamó a Coolify en ningún momento.
        $this->assertDatabaseCount('pasos_aprovisionamiento', 0);
        $this->assertSame(
            EstadoSolicitud::Aprobada,
            $solicitud->fresh()->estado,
            'El trabajo no debería haber modificado el estado.',
        );
    }

    #[Test]
    public function aprobar_encola_el_trabajo_y_registra_las_operaciones(): void
    {
        Queue::fake();

        $kit = Kit::factory()->aprovisonable()->create();
        $plan = Plan::factory()->create(['kits' => [$kit->codigo]]);

        $empresa = Empresa::factory()->create();
        $cliente = Usuario::factory()->cliente($empresa)->create();
        $hefesto = Usuario::factory()->aprobador()->create();

        $solicitud = Solicitud::factory()->deEmpresa($empresa, $cliente)->create(['plan_id' => $plan->id]);

        $aprobacion = app(AprobarSolicitud::class)->ejecutar($solicitud, $hefesto, 'Revisado.');

        $this->assertTrue($aprobacion->fueAprobacion());
        $this->assertContains('crear_proyecto', $aprobacion->operaciones);
        $this->assertContains('crear_servicio', $aprobacion->operaciones);
        $this->assertDatabaseHas('bitacora_aprobaciones', [
            'solicitud_id' => $solicitud->id,
            'decision' => 'aprobada',
        ]);

        Queue::assertPushed(EjecutarAprovisionamiento::class);
    }

    #[Test]
    public function quien_crea_la_solicitud_no_puede_aprobarla_solo(): void
    {
        $kit = Kit::factory()->aprovisonable()->create();
        $plan = Plan::factory()->create(['kits' => [$kit->codigo]]);

        $empresa = Empresa::factory()->create();
        $hefesto = Usuario::factory()->aprobador()->create();

        $solicitud = Solicitud::factory()->deEmpresa($empresa, $hefesto)->create(['plan_id' => $plan->id]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(AprobarSolicitud::class)->ejecutar($solicitud, $hefesto);
    }

    #[Test]
    public function con_coolify_apagado_el_recorrido_no_se_da_por_exitoso(): void
    {
        Queue::fake();

        config(['coolify.habilitada' => false]);

        $kit = Kit::factory()->aprovisonable()->create();
        $plan = Plan::factory()->create(['kits' => [$kit->codigo]]);

        $empresa = Empresa::factory()->create();
        $cliente = Usuario::factory()->cliente($empresa)->create();
        $hefesto = Usuario::factory()->aprobador()->create();

        $solicitud = Solicitud::factory()->deEmpresa($empresa, $cliente)->create(['plan_id' => $plan->id]);

        app(AprobarSolicitud::class)->ejecutar($solicitud, $hefesto);

        app(AprovisionarSolicitud::class)->ejecutar($solicitud->id);

        $solicitud->refresh();

        // Ni "aprovisionada" ni un paso fingido como completado.
        $this->assertSame(EstadoSolicitud::Fallida, $solicitud->estado);
        $this->assertDatabaseMissing('pasos_aprovisionamiento', [
            'solicitud_id' => $solicitud->id,
            'estado' => EstadoPaso::Completado->value,
        ]);
    }

    #[Test]
    public function la_previa_muestra_las_operaciones_antes_de_aprobar(): void
    {
        $kit = Kit::factory()->aprovisonable()->create();
        $plan = Plan::factory()->create(['kits' => [$kit->codigo]]);

        $empresa = Empresa::factory()->create();
        $cliente = Usuario::factory()->cliente($empresa)->create();
        $hefesto = Usuario::factory()->aprobador()->create();

        $solicitud = Solicitud::factory()->deEmpresa($empresa, $cliente)->create(['plan_id' => $plan->id]);

        $respuesta = $this->actingAs($hefesto)
            ->getJson('/api/v1/aprobaciones/' . $solicitud->id . '/previa')
            ->assertOk();

        $operaciones = $respuesta->json('operaciones');

        $this->assertIsArray($operaciones);
        $this->assertNotEmpty($operaciones);

        foreach ($operaciones as $operacion) {
            $this->assertNotSame('DELETE', $operacion['metodo']);
            $this->assertStringStartsWith('https://coolify.io/docs/', $operacion['documentacion']);
        }

        $this->assertNotNull($respuesta->json('advertencia'));
    }
}