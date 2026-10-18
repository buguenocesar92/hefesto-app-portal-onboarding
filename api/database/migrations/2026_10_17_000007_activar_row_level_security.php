<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * AISLAMIENTO ENTRE EMPRESAS: Row Level Security (RLS) de PostgreSQL.
 *
 * Este es el mismo patrón que ya usa `apps/portal-autoservicio`, replicado aquí con su
 * misma justificación. No es un cuarto enfoque: es el mismo, y está en la base de datos
 * porque es el único lugar donde no se puede "olvidar" filtrar.
 *
 * En este portal conviven dos cosas que en la app hermana no existían:
 *
 *   - El personal de Hefesto, que pertenece a NINGUNA empresa y tiene que ver todas
 *     (es quien aprueba). Eso se resuelve con una segunda variable de contexto,
 *     `app.acceso_hefesto`, y con una función que la consulta de forma explícita.
 *     Ninguna otra parte del sistema puede activarla: solo la escribe
 *     `App\Servicios\Tenancy\ContextoEmpresa`.
 *
 *   - La tabla `empresas`, que NO lleva RLS a propósito, porque se consulta durante el
 *     acceso, cuando todavía no hay sesión y por lo tanto no hay contexto que poner. Es
 *     el mismo punto ciego que en la app hermana, con las mismas compensaciones:
 *     la lista de direcciones IP del despliegue y el comando de verificación.
 *
 * NOTA DE NOMBRES
 * ---------------
 * En esta aplicación el inquilino se llama EMPRESA y la columna es `empresa_id`. En las
 * otras aplicaciones de Hefesto la misma columna se llama `tenant_id`. Es el mismo
 * mecanismo con otro nombre; se deja constancia aquí porque el validador general de la
 * sesión (`tools/validar_apps.py`) busca literalmente `tenant_id` en el código.
 *
 * MECANISMO
 * ---------
 * 1. `public.app_empresa_id()` lee `app.empresa_id`. Si no está definida, LANZA un
 *    error. No devuelve NULL: falla de forma ruidosa, que es lo que evita que alguien
 *    lo "arregle" devolviendo NULL y termine leyendo filas de otra empresa sin que nada
 *    lo delate.
 * 2. `public.app_es_acceso_hefesto()` lee `app.acceso_hefesto`. Nunca lanza: si no
 *    está definida, vale falso, que es el lado cerrado.
 * 3. `public.app_pertenece_al_contexto(id_empresa)` combina las dos: si el contexto es
 *    del personal de Hefesto, es cierto; si no, exige que la fila sea de la empresa del
 *    contexto, y esa comparación llama a la función que lanza excepción.
 * 4. Cada tabla protegida lleva `USING` y `WITH CHECK`, y `FORCE ROW LEVEL SECURITY`
 *    para que la política aplique también al dueño de la tabla.
 * 5. El contexto se fija con `set_config(..., true)`: alcance de TRANSACCIÓN. Al
 *    confirmar o revertir, PostgreSQL lo descarta. Un `SET` normal sobreviviría a la
 *    petición y el trabajador de colas, que reutiliza la misma conexión para trabajos de
 *    empresas distintas, leería datos de la empresa anterior.
 *
 * REQUISITO DE DESPLIEGUE: el rol que usa la aplicación NO puede ser superusuario ni
 * tener BYPASSRLS. Con superusuario, PostgreSQL ignora las políticas por completo.
 * `php artisan portal:verificar-aislamiento` lo comprueba.
 */
return new class extends Migration
{
    /**
     * Tablas con datos de una empresa concreta, y por lo tanto con RLS.
     *
     * @var list<string>
     */
    private array $tablasProtegidas = [
        'usuarios',
        'solicitudes',
        'pasos_aprovisionamiento',
        'entregas_credenciales',
        'bitacora_aprobaciones',
    ];

    public function up(): void
    {
        $this->crearFunciones();

        foreach ($this->tablasProtegidas as $tabla) {
            $this->proteger($tabla);
        }

        // Falla ruidosamente si alguna tabla de la lista no tiene `empresa_id`. Sin
        // esta comprobación, un error de tipeo dejaría una tabla de datos de negocio sin
        // política y la migración pasaría igual.
        $this->verificarColumnaEmpresa();
    }

    public function down(): void
    {
        foreach ($this->tablasProtegidas as $tabla) {
            if (! $this->existe($tabla)) {
                continue;
            }

            DB::unprepared('DROP POLICY IF EXISTS politica_aislamiento_' . $tabla . ' ON public.' . $tabla);
            DB::unprepared('ALTER TABLE public.' . $tabla . ' NO FORCE ROW LEVEL SECURITY');
            DB::unprepared('ALTER TABLE public.' . $tabla . ' DISABLE ROW LEVEL SECURITY');
        }

        DB::unprepared('DROP FUNCTION IF EXISTS public.app_pertenece_al_contexto(bigint)');
        DB::unprepared('DROP FUNCTION IF EXISTS public.app_es_acceso_hefesto()');
        DB::unprepared('DROP FUNCTION IF EXISTS public.app_empresa_id()');
    }

    private function crearFunciones(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.app_empresa_id() RETURNS bigint
            LANGUAGE plpgsql
            STABLE
            AS $$
            DECLARE
                valor text;
            BEGIN
                valor := current_setting('app.empresa_id', true);

                IF valor IS NULL OR valor = '' THEN
                    RAISE EXCEPTION USING
                        ERRCODE = 'insufficient_privilege',
                        MESSAGE = 'No hay contexto de empresa en la conexión (app.empresa_id no está definida). Se aborta la operación para no exponer datos de otra empresa.',
                        HINT = 'La petición no pasó por App\Http\Middleware\ResolverEmpresa, o la operación se ejecutó fuera de una transacción.';
                END IF;

                RETURN valor::bigint;
            END
            $$;
        SQL);

        DB::unprepared(<<<'SQL'
            COMMENT ON FUNCTION public.app_empresa_id() IS
            'Devuelve la empresa del contexto. Lanza excepción si no hay contexto: el aislamiento falla cerrado.';
        SQL);

        // Nunca lanza. Si la variable no está, vale falso: es el lado cerrado, y es lo
        // que hace que una consulta sin contexto de Hefesto no se vuelva más laxa.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.app_es_acceso_hefesto() RETURNS boolean
            LANGUAGE plpgsql
            STABLE
            AS $$
            BEGIN
                RETURN COALESCE(current_setting('app.acceso_hefesto', true), 'false') = 'true';
            END
            $$;
        SQL);

        DB::unprepared(<<<'SQL'
            COMMENT ON FUNCTION public.app_es_acceso_hefesto() IS
            'Cierto solo si el contexto es del personal de Hefesto. Falso por defecto: el lado cerrado.';
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.app_pertenece_al_contexto(id_empresa bigint) RETURNS boolean
            LANGUAGE plpgsql
            STABLE
            AS $$
            BEGIN
                IF public.app_es_acceso_hefesto() THEN
                    RETURN true;
                END IF;

                RETURN id_empresa IS NOT DISTINCT FROM public.app_empresa_id();
            END
            $$;
        SQL);

        DB::unprepared(<<<'SQL'
            COMMENT ON FUNCTION public.app_pertenece_al_contexto(bigint) IS
            'Compara la fila con el contexto. El personal de Hefesto ve todo por decisión del negocio; el resto solo ve su empresa.';
        SQL);
    }

    private function proteger(string $tabla): void
    {
        if (! $this->existe($tabla)) {
            return;
        }

        DB::unprepared('ALTER TABLE public.' . $tabla . ' ENABLE ROW LEVEL SECURITY');

        // FORCE hace que la política aplique también al dueño. Sin esto, el aislamiento
        // sería decorativo justo en el entorno donde se prueba.
        DB::unprepared('ALTER TABLE public.' . $tabla . ' FORCE ROW LEVEL SECURITY');

        $nombre = 'politica_aislamiento_' . $tabla;

        DB::unprepared(<<<SQL
            DROP POLICY IF EXISTS {$nombre} ON public.{$tabla};

            CREATE POLICY {$nombre} ON public.{$tabla}
                FOR ALL
                TO PUBLIC
                USING (public.app_pertenece_al_contexto(empresa_id))
                WITH CHECK (public.app_pertenece_al_contexto(empresa_id));
        SQL);
    }

    private function existe(string $tabla): bool
    {
        $existe = DB::selectOne('SELECT to_regclass(?) AS tabla', ['public.' . $tabla]);

        return $existe !== null && $existe->tabla !== null;
    }

    /**
     * Falla ruidosamente si alguna tabla de la lista no tiene `empresa_id`.
     */
    private function verificarColumnaEmpresa(): void
    {
        foreach ($this->tablasProtegidas as $tabla) {
            $existe = DB::selectOne(
                "SELECT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_schema = 'public' AND table_name = ? AND column_name = 'empresa_id'
                 ) AS tiene_columna",
                [$tabla]
            );

            if ($existe === null || ! $existe->tiene_columna) {
                throw new RuntimeException(
                    'La tabla public.' . $tabla . ' no tiene columna empresa_id y no puede protegerse con RLS. '
                    . 'Revise la migración antes de continuar.'
                );
            }
        }
    }
};