<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\OperacionCoolify;
use App\Enums\OperacionesProhibidas;
use App\Servicios\Coolify\TokenCoolify;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Comprobaciones sobre la lista acotada de operaciones de Coolify.
 *
 * Estas pruebas no necesitan base de datos ni red: leen el enum y la lista de
 * prohibiciones, que son la fuente de verdad de lo que el portal puede hacer. Si alguien
 * agrega una operación nueva, esta suite obliga a justificar por qué.
 */
class PoliticaCoolifyTest extends TestCase
{
    #[Test]
    public function ninguna_operacion_permitida_es_destructiva(): void
    {
        foreach (OperacionCoolify::permitidas() as $operacion) {
            $this->assertContains(
                $operacion->metodo(),
                ['GET', 'POST'],
                sprintf('La operación %s usa el método %s, que no está permitido.', $operacion->value, $operacion->metodo()),
            );
        }
    }

    #[Test]
    public function ninguna_operacion_permitida_coincide_con_una_prohibida(): void
    {
        foreach (OperacionCoolify::permitidas() as $permitida) {
            $motivo = OperacionesProhibidas::motivoDelRechazo($permitida->metodo(), $permitida->ruta());

            $this->assertNull(
                $motivo,
                sprintf('La operación permitida %s está en la lista de prohibidas: %s', $permitida->value, (string) $motivo),
            );
        }
    }

    #[Test]
    public function la_lista_de_prohibiciones_cubre_lo_que_mas_importa(): void
    {
        $metodos = array_column(OperacionesProhibidas::todas(), 'metodo');
        $rutas = array_column(OperacionesProhibidas::todas(), 'ruta');

        // Borrar cosas: el peor desenlace desde un portal web.
        $this->assertContains('DELETE', $metodos, 'Debe existir al menos una operación DELETE prohibida.');

        foreach (['/api/v1/projects/{uuid}', '/api/v1/services/{uuid}', '/api/v1/databases/{uuid}'] as $ruta) {
            $this->assertContains($ruta, $rutas, sprintf('El borrado de %s tiene que estar prohibido.', $ruta));
        }

        // Detener y reiniciar.
        foreach (['restart', 'stop'] as $fragmento) {
            $this->assertTrue(
                OperacionesProhibidas::contieneFragmentoProhibido('/api/v1/services/abc/' . $fragmento),
                sprintf('El fragmento %s debe considerarse peligroso.', $fragmento),
            );
        }

        // Leer secretos.
        $this->assertTrue(OperacionesProhibidas::contieneFragmentoProhibido('/api/v1/services/abc/logs'));
        $this->assertTrue(OperacionesProhibidas::contieneFragmentoProhibido('/api/v1/services/abc/envs'));
    }

    #[Test]
    public function toda_operacion_que_escribe_exige_aprobacion_humana(): void
    {
        foreach (OperacionCoolify::permitidas() as $operacion) {
            if ($operacion->esEscritura()) {
                $this->assertTrue(
                    $operacion->exigeAprobacionHumana(),
                    sprintf('%s escribe y no exige aprobación.', $operacion->value),
                );
            }
        }
    }

    #[Test]
    public function solo_el_despliegue_usa_credencial_separada(): void
    {
        $separadas = array_values(array_map(
            static fn (OperacionCoolify $operacion): string => $operacion->value,
            array_filter(
                OperacionCoolify::permitidas(),
                static fn (OperacionCoolify $operacion): bool => $operacion->usaCredencialDeDespliegue(),
            ),
        ));

        $this->assertSame([OperacionCoolify::Desplegar->value], $separadas);
    }

    #[Test]
    public function la_credencial_no_se_puede_imprimir(): void
    {
        $token = TokenCoolify::dePrueba('67|valor-secreto-de-prueba');

        $this->assertStringNotContainsString('valor-secreto-de-prueba', print_r($token->huella(), true));
        $this->assertStringNotContainsString('valor-secreto-de-prueba', var_export($token->huella(), true));
        $this->assertStringNotContainsString('valor-secreto-de-prueba', (string) $token);
        $this->assertStringNotContainsString('valor-secreto-de-prueba', print_r($token, true));
        $this->assertSame(10, strlen($token->huella()));
    }

    #[Test]
    public function cada_operacion_documenta_su_verificacion(): void
    {
        foreach (OperacionCoolify::permitidas() as $operacion) {
            $this->assertStringStartsWith(
                'https://coolify.io/docs/',
                $operacion->documentacion(),
                sprintf('%s no tiene documentación oficial asociada.', $operacion->value),
            );

            $this->assertNotSame('', $operacion->porquePermitida());
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function rutasProhibidas(): array
    {
        return [
            'borrar proyecto' => ['DELETE', '/api/v1/projects/abc'],
            'borrar servicio' => ['DELETE', '/api/v1/services/abc'],
            'reiniciar servicio' => ['POST', '/api/v1/services/abc/restart'],
            'detener servicio' => ['POST', '/api/v1/services/abc/stop'],
            'leer registros' => ['GET', '/api/v1/services/abc/logs'],
            'leer variables de entorno' => ['GET', '/api/v1/services/abc/envs'],
            'escribir variable de entorno' => ['POST', '/api/v1/services/abc/envs'],
            'listar miembros del equipo' => ['GET', '/api/v1/teams/abc/members'],
            'modificar configuración' => ['PATCH', '/api/v1/settings'],
            'salto de directorio' => ['GET', '/api/v1/projects/abc/../../databases/otro'],
        ];
    }

    #[Test]
    #[DataProvider('rutasProhibidas')]
    public function las_operaciones_peligrosas_se_rechazan(string $metodo, string $ruta): void
    {
        $this->assertNotNull(
            OperacionesProhibidas::motivoDelRechazo($metodo, $ruta),
            sprintf('La operación %s %s debió ser rechazada.', $metodo, $ruta),
        );
    }
}