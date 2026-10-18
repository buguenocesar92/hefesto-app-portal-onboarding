<?php

declare(strict_types=1);

namespace Tests\Feature\Aislamiento;

use App\Models\Empresa;
use App\Models\Solicitud;
use App\Models\Usuario;
use App\Servicios\Tenancy\ContextoEmpresa;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * AISLAMIENTO ENTRE EMPRESAS, PROBADO EN LA BASE DE DATOS.
 *
 * Estas pruebas van contra PostgreSQL de verdad, porque el aislamiento está implementado
 * con Row Level Security. Contra SQLite pasarían sin comprobar nada.
 *
 * Y son DISTINTAS de `AislamientoEntreEmpresasTest`, que verifica la capa de la
 * aplicación (alcance global del modelo y políticas de Laravel). Esta archivo no prueba
 * el código PHP: prueba que la BASE se niega a entregar filas de otra empresa, aunque
 * alguien pase por alto el filtro en la aplicación.
 *
 * El caso que importa: si un día alguien escribe una consulta sin filtro de empresa en
 * PHP, aquí sigue fallando. La base es la última línea.
 */
class AislamientoEnBaseDeDatosTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sin_contexto_la_consulta_falla_en_vez_de_traer_datos(): void
    {
        $empresa = Empresa::factory()->create();
        Solicitud::factory()->deEmpresa($empresa, Usuario::factory()->cliente($empresa)->create())->create();

        // Se limpia el contexto a propósito: es la situación de una consulta que se
        // escapara del middleware.
        //
        // Lo que se espera NO es "cero filas", sino un error. public.app_empresa_id()
        // lanza excepción cuando no hay contexto y la política aborta la consulta.
        // Devolver cero filas sería más cómodo de probar, pero dejaría pasar el caso
        // peligroso: alguien "arregla" la función devolviendo NULL y termina leyendo
        // filas de la empresa equivocada sin que nada lo delate.
        DB::select('SELECT set_config(?, ?, false)', [ContextoEmpresa::VARIABLE_EMPRESA, '']);
        DB::select('SELECT set_config(?, ?, false)', [ContextoEmpresa::VARIABLE_HFESTO, 'false']);

        $this->expectException(QueryException::class);

        Solicitud::query()->count();
    }

    #[Test]
    public function cada_empresa_solo_ve_sus_propias_filas(): void
    {
        $primera = Empresa::factory()->create();
        $segunda = Empresa::factory()->create();

        Solicitud::factory()
            ->deEmpresa($primera, Usuario::factory()->cliente($primera)->create())
            ->count(3)
            ->create();

        Solicitud::factory()
            ->deEmpresa($segunda, Usuario::factory()->cliente($segunda)->create())
            ->count(2)
            ->create();

        $contexto = new ContextoEmpresa();

        $conteoPrimera = $contexto->ejecutarAislado(
            (int) $primera->id,
            static fn (): int => Solicitud::query()->count()
        );

        $this->assertSame(3, $conteoPrimera);

        $conteoSegunda = $contexto->ejecutarAislado(
            (int) $segunda->id,
            static fn (): int => Solicitud::query()->count()
        );

        $this->assertSame(2, $conteoSegunda);
    }

    #[Test]
    public function una_solicitud_de_otra_empresa_no_existe(): void
    {
        $primera = Empresa::factory()->create();
        $segunda = Empresa::factory()->create();

        $ajena = Solicitud::factory()
            ->deEmpresa($segunda, Usuario::factory()->cliente($segunda)->create())
            ->create();

        $contexto = new ContextoEmpresa();

        $encontrada = $contexto->ejecutarAislado(
            (int) $primera->id,
            static fn () => Solicitud::query()->find($ajena->id)
        );

        $this->assertNull($encontrada, 'La base no debería entregar una fila de otra empresa.');
    }

    #[Test]
    public function no_se_puede_modificar_una_fila_de_otra_empresa(): void
    {
        $primera = Empresa::factory()->create();
        $segunda = Empresa::factory()->create();

        $ajena = Solicitud::factory()
            ->deEmpresa($segunda, Usuario::factory()->cliente($segunda)->create())
            ->create();

        $contexto = new ContextoEmpresa();

        // Contexto de la primera empresa, intento de tocar la fila de la segunda. La
        // política lo tiene que impedir.
        $this->expectException(QueryException::class);

        $contexto->ejecutarAislado((int) $primera->id, static function () use ($ajena): void {
            DB::table('solicitudes')
                ->where('id', $ajena->id)
                ->update(['resumen' => 'secuestrada']);
        });
    }

    #[Test]
    public function no_se_puede_insertar_una_fila_con_la_empresa_de_otra(): void
    {
        $primera = Empresa::factory()->create();
        $segunda = Empresa::factory()->create();

        $contexto = new ContextoEmpresa();

        // El WITH CHECK de la política existe para esto: sin él, se podría guardar una
        // fila invisible, que nadie vería ni podría borrar nunca.
        $this->expectException(QueryException::class);

        $contexto->ejecutarAislado((int) $primera->id, static function () use ($primera, $segunda): void {
            Solicitud::query()->create([
                'empresa_id' => $segunda->id,
                'estado' => 'pendiente_de_revision',
                'resumen' => 'Solicitud con empresa ajena. No debería poder guardarse nunca.',
                'datos' => [],
            ]);
        });
    }

    #[Test]
    public function el_contexto_no_sobrevive_a_la_transaccion(): void
    {
        $empresa = Empresa::factory()->create();
        Solicitud::factory()->deEmpresa($empresa, Usuario::factory()->cliente($empresa)->create())->create();

        $contexto = new ContextoEmpresa();

        $conteo = $contexto->ejecutarAislado(
            (int) $empresa->id,
            static fn (): int => Solicitud::query()->count()
        );

        $this->assertSame(1, $conteo);

        $this->assertSame(
            ['empresa' => null, 'hefesto' => null],
            $contexto->residuoEnConexion(),
            'Las variables de contexto deben desaparecer al terminar la transacción. Si quedan, '
            . 'el trabajador de colas podría leer datos de otra empresa.'
        );
    }

    #[Test]
    public function el_personal_de_hefesto_ve_todas_las_empresas(): void
    {
        $primera = Empresa::factory()->create();
        $segunda = Empresa::factory()->create();

        Solicitud::factory()->deEmpresa($primera, Usuario::factory()->cliente($primera)->create())->create();
        Solicitud::factory()->deEmpresa($segunda, Usuario::factory()->cliente($segunda)->create())->create();

        $contexto = new ContextoEmpresa();

        $conteo = $contexto->ejecutarAisladoComoHefesto(
            static fn (): int => Solicitud::withoutGlobalScope('empresa')->query()->count()
        );

        // Es una decisión del negocio: quien aprueba necesita ver la cola completa. Lo
        // que se comprueba aquí es que la excepción esté escrita en la política de la
        // base y no escondida en una consulta.
        $this->assertSame(2, $conteo);
    }

    #[Test]
    public function la_funcion_de_contexto_falla_cerrada_sin_empresa(): void
    {
        DB::select('SELECT set_config(?, ?, false)', [ContextoEmpresa::VARIABLE_EMPRESA, '']);
        DB::select('SELECT set_config(?, ?, false)', [ContextoEmpresa::VARIABLE_HFESTO, 'false']);

        $this->expectException(QueryException::class);

        DB::select('SELECT public.app_empresa_id()');
    }

    #[Test]
    public function la_bandera_de_hefesto_falso_por_defecto(): void
    {
        $valor = DB::selectOne('SELECT public.app_es_acceso_hefesto() AS valor');

        $this->assertFalse((bool) $valor->valor, 'Sin contexto de Hefesto, la bandera debe ser falsa.');
    }
}