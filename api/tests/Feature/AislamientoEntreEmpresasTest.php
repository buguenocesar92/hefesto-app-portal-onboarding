<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EstadoSolicitud;
use App\Models\Empresa;
use App\Models\Solicitud;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Aislamiento entre empresas.
 *
 * La pregunta que responde esta suite es la del cliente que hires: ¿una PyME puede ver
 * los datos de otra? La respuesta tiene que ser no, y no por cortesía del código que se
 * escribió bien, sino por una barrera que actúe aunque alguien olvide una condición.
 *
 * Hay dos barreras y se comprueban las dos:
 *   1. El alcance global del modelo Solicitud, que exige empresa_id.
 *   2. La política, que vuelve a comparar empresas en cada acción.
 */
class AislamientoEntreEmpresasTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function un_cliente_no_ve_las_solicitudes_de_otra_empresa(): void
    {
        $empresaA = Empresa::factory()->create();
        $empresaB = Empresa::factory()->create();

        $usuarioA = Usuario::factory()->cliente($empresaA)->create();
        $usuarioB = Usuario::factory()->cliente($empresaB)->create();

        Solicitud::factory()->deEmpresa($empresaA, $usuarioA)->create();
        Solicitud::factory()->deEmpresa($empresaB, $usuarioB)->create();

        $this->actingAs($usuarioA)
            ->getJson('/api/v1/solicitudes')
            ->assertOk()
            ->assertJsonCount(1, 'solicitudes')
            ->assertJsonPath('solicitudes.0.empresa_id', $empresaA->id);
    }

    #[Test]
    public function un_cliente_no_abre_una_solicitud_de_otra_empresa(): void
    {
        $empresaA = Empresa::factory()->create();
        $empresaB = Empresa::factory()->create();

        $usuarioA = Usuario::factory()->cliente($empresaA)->create();
        $usuarioB = Usuario::factory()->cliente($empresaB)->create();

        $solicitudB = Solicitud::factory()->deEmpresa($empresaB, $usuarioB)->create();

        // El alcance global filtra antes de que exista la política: el recurso no se
        // encuentra, que es mejor que un 403 que confirma que existe.
        $this->actingAs($usuarioA)
            ->getJson('/api/v1/solicitudes/' . $solicitudB->id)
            ->assertNotFound();
    }

    #[Test]
    public function un_cliente_no_cancela_una_solicitud_ajena(): void
    {
        $empresaA = Empresa::factory()->create();
        $empresaB = Empresa::factory()->create();

        $usuarioA = Usuario::factory()->cliente($empresaA)->create();
        $usuarioB = Usuario::factory()->cliente($empresaB)->create();

        $solicitudB = Solicitud::factory()->deEmpresa($empresaB, $usuarioB)->create();

        $this->actingAs($usuarioA)
            ->deleteJson('/api/v1/solicitudes/' . $solicitudB->id)
            ->assertNotFound();

        $this->assertSame(
            EstadoSolicitud::PendienteDeRevision,
            $solicitudB->fresh()->estado,
        );
    }

    #[Test]
    public function el_personal_de_hefesto_ve_todas_las_solicitudes(): void
    {
        $empresaA = Empresa::factory()->create();
        $empresaB = Empresa::factory()->create();

        $usuarioA = Usuario::factory()->cliente($empresaA)->create();
        $usuarioB = Usuario::factory()->cliente($empresaB)->create();
        $hefesto = Usuario::factory()->aprobador()->create();

        Solicitud::factory()->deEmpresa($empresaA, $usuarioA)->create();
        Solicitud::factory()->deEmpresa($empresaB, $usuarioB)->create();

        $this->actingAs($hefesto)
            ->getJson('/api/v1/solicitudes')
            ->assertOk()
            ->assertJsonCount(2, 'solicitudes');
    }

    #[Test]
    public function un_cliente_no_entra_al_area_de_aprobacion(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = Usuario::factory()->cliente($empresa)->create();

        $this->actingAs($cliente)
            ->getJson('/api/v1/aprobaciones')
            ->assertForbidden();
    }

    #[Test]
    public function el_cliente_no_ve_identificadores_de_coolify(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = Usuario::factory()->cliente($empresa)->create();

        $solicitud = Solicitud::factory()->deEmpresa($empresa, $cliente)->create([
            'coolify_proyecto_uuid' => 'og888osproyecto',
            'coolify_servicio_uuid' => 'og888osservicio',
        ]);

        $respuesta = $this->actingAs($cliente)
            ->getJson('/api/v1/solicitudes/' . $solicitud->id)
            ->assertOk();

        $cuerpo = $respuesta->getContent();

        $this->assertStringNotContainsString('og888osproyecto', (string) $cuerpo);
        $this->assertStringNotContainsString('og888osservicio', (string) $cuerpo);
        $this->assertNull($respuesta->json('solicitud.coolify'));
    }
}