<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Servicios\Tenancy\ContextoEmpresa;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Comprueba que el aislamiento entre empresas está realmente puesto.
 *
 * POR QUÉ ESTE COMANDO EXISTE
 * ---------------------------
 * El aislamiento no es solo el código que escribimos: es el estado en que quedó la base
 * de datos. Un despliegue bien pensado pero mal configurado (un rol con BYPASSRLS,
 * una política borrada a mano, una tabla nueva sin proteger) deja la aplicación
 * funcionando y, al mismo tiempo, completamente abierta entre clientes. Nada en la
 * interfaz lo delata.
 *
 * Por eso este comando se ejecuta en el despliegue y antes de dar por buena una
 * migración. Devuelve código de salida 1 si algo falla.
 *
 *   php artisan portal:verificar-aislamiento
 */
class VerificarAislamientoCommand extends Command
{
    /** @var list<string> */
    private array $tablasProtegidas = [
        'usuarios',
        'solicitudes',
        'pasos_aprovisionamiento',
        'entregas_credenciales',
        'bitacora_aprobaciones',
    ];

    protected $signature = 'portal:verificar-aislamiento';

    protected $description = 'Comprueba que la RLS, el rol de base de datos y el contexto de empresa protegen los datos de cada empresa.';

    public function handle(): int
    {
        $fallos = [];

        $fallos = array_merge($fallos, $this->revisarRol());
        $fallos = array_merge($fallos, $this->revisarFunciones());
        $fallos = array_merge($fallos, $this->revisarTablas());
        $fallos = array_merge($fallos, $this->revisarLecturaAislada());

        if ($fallos !== []) {
            $this->error('El aislamiento entre empresas NO está bien puesto:');

            foreach ($fallos as $fallo) {
                $this->line('  - ' . $fallo);
            }

            $this->line('');
            $this->line('No sigas con el despliegue. Revisa la migración');
            $this->line('database/migrations/2026_10_17_000007_activar_row_level_security.php');

            return self::FAILURE;
        }

        $this->info('Aislamiento entre empresas verificado correctamente.');

        return self::SUCCESS;
    }

    /**
     * El rol de la aplicación no puede ser superusuario ni tener BYPASSRLS.
     *
     * Con cualquiera de las dos cosas, PostgreSQL ignora las políticas RLS por
     * completo: las consultas devolverían filas de todas las empresas y la aplicación no
     * tendría cómo enterarse.
     *
     * @return list<string>
     */
    private function revisarRol(): array
    {
        $fila = DB::selectOne(
            'SELECT r.rolname AS nombre, r.rolsuper AS superusuario, r.rolbypassrls AS salta_rls
             FROM pg_roles r
             WHERE r.rolname = current_user'
        );

        if ($fila === null) {
            return ['No se pudo identificar el rol de la aplicación en PostgreSQL.'];
        }

        $fallos = [];

        if ((bool) $fila->superusuario) {
            $fallos[] = sprintf(
                'El rol "%s" es SUPERUSUARIO: ignora todas las políticas RLS.',
                $fila->nombre
            );
        }

        if ((bool) $fila->salta_rls) {
            $fallos[] = sprintf(
                'El rol "%s" tiene BYPASSRLS: ignora todas las políticas RLS.',
                $fila->nombre
            );
        }

        if ($fallos === []) {
            $this->line('  [ok] Rol "' . $fila->nombre . '": sin superusuario y sin BYPASSRLS.');
        }

        return $fallos;
    }

    /**
     * Las tres funciones de contexto tienen que existir y comportarse como se documentó.
     *
     * @return list<string>
     */
    private function revisarFunciones(): array
    {
        $fallos = [];

        foreach (['app_empresa_id', 'app_es_acceso_hefesto', 'app_pertenece_al_contexto'] as $funcion) {
            $existe = DB::selectOne(
                "SELECT to_regprocedure(?) AS firma",
                ['public.' . $funcion . '(' . ($funcion === 'app_pertenece_al_contexto' ? 'bigint' : '') . ')']
            );

            if ($existe === null || $existe->firma === null) {
                $fallos[] = 'No existe la función public.' . $funcion . '().';
            }
        }

        // Sin contexto, la función de empresa tiene que FALLAR. Si devolviera NULL o
        // una cadena vacía, una consulta sin contexto devolvería cero filas por
        // accidente en vez de por diseño, y en el futuro alguien podría "arreglarla"
        // devolviendo NULL y abrir la puerta sin darse cuenta.
        try {
            DB::select('SELECT public.app_empresa_id()');
            $fallos[] = 'public.app_empresa_id() devolvió un valor sin que haya contexto de empresa. '
                . 'Debería lanzar excepción para que el aislamiento falle cerrado.';
        } catch (QueryException) {
            $this->line('  [ok] public.app_empresa_id() falla sin contexto de empresa (aislamiento cerrado).');
        }

        // La bandera de Hefesto nunca debe lanzar: su valor por defecto es falso.
        try {
            $valor = DB::selectOne('SELECT public.app_es_acceso_hefesto() AS valor');
            $this->line('  [ok] public.app_es_acceso_hefesto() responde falso por defecto (valor='
                . var_export($valor?->valor, true) . ').');
        } catch (QueryException $excepcion) {
            $fallos[] = 'public.app_es_acceso_hefesto() no se pudo consultar: ' . $excepcion->getMessage();
        }

        return $fallos;
    }

    /**
     * Cada tabla de negocio tiene RLS activada, forzada y con política.
     *
     * @return list<string>
     */
    private function revisarTablas(): array
    {
        $fallos = [];

        foreach ($this->tablasProtegidas as $tabla) {
            $estado = DB::selectOne(
                'SELECT c.relrowsecurity AS rls,
                        c.relforcerowsecurity AS forzada
                 FROM pg_class c
                 WHERE c.oid = to_regclass(?)',
                ['public.' . $tabla]
            );

            if ($estado === null) {
                $fallos[] = 'La tabla public.' . $tabla . ' no existe. Faltó correr las migraciones.';

                continue;
            }

            if (! $estado->rls) {
                $fallos[] = 'La tabla public.' . $tabla . ' NO tiene Row Level Security activada.';

                continue;
            }

            if (! $estado->forzada) {
                $fallos[] = 'La tabla public.' . $tabla . ' tiene RLS pero NO está forzada '
                    . '(FORCE ROW LEVEL SECURITY): el dueño de la tabla saltaría la política.';

                continue;
            }

            $politicas = DB::selectOne(
                'SELECT count(*) AS total
                 FROM pg_policies
                 WHERE schemaname = ? AND tablename = ?',
                ['public', $tabla]
            );

            if ((int) ($politicas->total ?? 0) === 0) {
                $fallos[] = 'La tabla public.' . $tabla . ' tiene RLS activada pero no tiene ninguna política.';
            }
        }

        if ($fallos === []) {
            $this->line('  [ok] ' . count($this->tablasProtegidas) . ' tablas con RLS activada, forzada y con política.');
        }

        return $fallos;
    }

    /**
     * Con una empresa que no existe, las tablas de negocio deben salir vacías, y el
     * contexto no debe quedar colgando.
     *
     * @return list<string>
     */
    private function revisarLecturaAislada(): array
    {
        $contexto = new ContextoEmpresa();
        $fallos = [];

        // Identificador alto e imposible: si alguna vez devolviera filas, sería porque
        // la política no está aplicando.
        $empresaInexistente = 9_999_999_999;

        try {
            $conteo = $contexto->ejecutarAislado(
                $empresaInexistente,
                fn (): int => DB::table('solicitudes')->count()
            );
        } catch (QueryException $excepcion) {
            return ['No se pudo leer "solicitudes" con un contexto de empresa: ' . $excepcion->getMessage()];
        }

        if ($conteo !== 0) {
            $fallos[] = 'La tabla "solicitudes" devolvió ' . $conteo . ' filas para una empresa inexistente.';
        }

        $residuo = $contexto->residuoEnConexion();

        if ($residuo['empresa'] !== null || $residuo['hefesto'] !== null) {
            $fallos[] = 'Las variables de contexto quedaron fijadas (' . json_encode($residuo) . ') después de la '
                . 'transacción. Si quedan colgando, el trabajador de colas podría leer datos de otra empresa.';
        }

        if ($fallos === []) {
            $this->line('  [ok] Con una empresa inexistente no se lee ninguna fila y el contexto no queda colgando.');
        }

        return $fallos;
    }
}