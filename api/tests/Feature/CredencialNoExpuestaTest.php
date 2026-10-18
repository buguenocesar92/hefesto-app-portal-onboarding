<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EstadoSolicitud;
use App\Integraciones\NotificadorNtfy;
use App\Models\Empresa;
use App\Models\Solicitud;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La credencial de Coolify no sale del backend.
 *
 * La prueba mira el contenido literal de las respuestas de la API y el tema de ntfy de
 * la empresa, que es otra credencial y suele olvidarse.
 */
class CredencialNoExpuestaTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN_FALSO = '67|TOKEN-DE-PRUEBA-NO-DEBE-APARECER';

    #[Test]
    public function la_credencial_no_aparece_en_ninguna_respuesta(): void
    {
        config(['coolify.token' => self::TOKEN_FALSO]);

        $empresa = Empresa::factory()->conTemaNtfy()->create();
        $cliente = Usuario::factory()->cliente($empresa)->create();
        $hefesto = Usuario::factory()->aprobador()->create();

        $solicitud = Solicitud::factory()->deEmpresa($empresa, $cliente)->create();

        $respuestas = [
            $this->actingAs($cliente)->getJson('/api/v1/catalogo/kits'),
            $this->actingAs($cliente)->getJson('/api/v1/catalogo/planes'),
            $this->actingAs($cliente)->getJson('/api/v1/solicitudes'),
            $this->actingAs($cliente)->getJson('/api/v1/solicitudes/' . $solicitud->id),
            $this->actingAs($hefesto)->getJson('/api/v1/aprobaciones'),
            $this->actingAs($hefesto)->getJson('/api/v1/aprobaciones/' . $solicitud->id . '/previa'),
            $this->getJson('/api/v1/salud'),
        ];

        foreach ($respuestas as $respuesta) {
            $cuerpo = (string) $respuesta->getContent();

            $this->assertStringNotContainsString(self::TOKEN_FALSO, $cuerpo);
            $this->assertStringNotContainsString('Authorization', $cuerpo);
        }
    }

    #[Test]
    public function el_tema_de_ntfy_tampoco_se_serializa(): void
    {
        $empresa = Empresa::factory()->conTemaNtfy()->create();
        $cliente = Usuario::factory()->cliente($empresa)->create();
        $solicitud = Solicitud::factory()->deEmpresa($empresa, $cliente)->create();

        $respuesta = $this->actingAs($cliente)
            ->getJson('/api/v1/solicitudes/' . $solicitud->id)
            ->assertOk();

        $this->assertStringNotContainsString(
            (string) $empresa->getRawOriginal('tema_ntfy'),
            (string) $respuesta->getContent(),
        );
    }

    #[Test]
    public function la_huella_de_credencial_no_permite_reconstruirla(): void
    {
        $generado = NotificadorNtfy::generarTema();

        $this->assertStringStartsWith('hefesto-', $generado['tema']);
        $this->assertSame(hash('sha256', $generado['tema']), $generado['huella']);
        $this->assertNotSame($generado['tema'], $generado['huella']);
    }

    #[Test]
    public function el_estado_de_la_solicitud_no_filtra_datos_de_otra_empresa(): void
    {
        $empresaA = Empresa::factory()->create();
        $empresaB = Empresa::factory()->create();

        $usuarioA = Usuario::factory()->cliente($empresaA)->create();
        $usuarioB = Usuario::factory()->cliente($empresaB)->create();

        $solicitudB = Solicitud::factory()->deEmpresa($empresaB, $usuarioB)->create([
            'estado' => EstadoSolicitud::Aprovisionada,
        ]);

        $this->actingAs($usuarioA)
            ->getJson('/api/v1/solicitudes')
            ->assertOk()
            ->assertJsonMissing(['id' => $solicitudB->id]);
    }
}