<?php

declare(strict_types=1);

namespace App\Servicios\Tenancy;

use Closure;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Contexto de empresa de la petición.
 *
 * La RLS de PostgreSQL (migración 2026_10_17_000007) lee la empresa desde la variable de
 * sesión `app.empresa_id`. Esta clase es la ÚNICA que escribe esa variable y la de
 * `app.acceso_hefesto`. Si alguien las fija por otro lado, el comportamiento deja de
 * estar garantizado: por eso todos los puntos de entrada son públicos y deliberados, y
 * `fijarContexto()` es privado.
 *
 * ALCANCE DE TRANSACCIÓN
 * ----------------------
 * Se usa `set_config(..., true)`: el valor desaparece al confirmar o revertir la
 * transacción. Con un `SET` normal (ámbito de sesión) el valor sobreviviría a la
 * petición y, en el trabajador de colas, que reutiliza la misma conexión para trabajos de
 * empresas distintas, un trabajo podría leer datos de la empresa anterior. Ese es
 * exactamente el fallo que este diseño evita.
 *
 * Por eso `establecer()` EXIGE estar dentro de una transacción y lanza una excepción si no
 * lo está.
 *
 * EL PERSONAL DE HEFESTO
 * ----------------------
 * Hefesto aprueba ejecuciones y necesita ver la cola de todas las empresas. Su contexto
 * no es "la empresa 0": es un contexto distinto, con `app.acceso_hefesto` en `true`, que
 * la política de la base reconoce de forma explícita. Así el caso "ve todo" está escrito
 * en la política, a la vista, en lugar de ser una excepción escondida en una consulta.
 */
class ContextoEmpresa
{
    /** Variable de sesión que lee la política RLS para filtrar por empresa. */
    public const VARIABLE_EMPRESA = 'app.empresa_id';

    /** Variable de sesión que marca un contexto del personal de Hefesto. */
    public const VARIABLE_HFESTO = 'app.acceso_hefesto';

    /**
     * Ejecuta una operación dentro de una transacción con el contexto de una empresa
     * cliente ya fijado. Al salir, PostgreSQL descarta ambas variables.
     *
     * @template T
     *
     * @param  Closure(): T  $operacion
     * @return T
     */
    public function ejecutarAislado(int $empresaId, Closure $operacion): mixed
    {
        return DB::transaction(function () use ($empresaId, $operacion) {
            $this->fijarContexto((string) $empresaId, false);

            return $operacion();
        });
    }

    /**
     * Igual que `ejecutarAislado()`, pero para el personal de Hefesto.
     *
     * @template T
     *
     * @param  Closure(): T  $operacion
     * @return T
     */
    public function ejecutarAisladoComoHefesto(Closure $operacion): mixed
    {
        return DB::transaction(function () use ($operacion) {
            $this->fijarContexto('', true);

            return $operacion();
        });
    }

    /**
     * Fija el contexto dentro de una transacción ya abierta.
     *
     * Se usa desde el middleware, que envuelve la petición entera.
     *
     * @throws RuntimeException si no hay una transaccion abierta.
     */
    public function establecer(int $empresaId): void
    {
        $this->fijarContexto((string) $empresaId, false);
    }

    /**
     * Fija el contexto de Hefesto dentro de una transacción ya abierta.
     */
    public function establecerComoHefesto(): void
    {
        $this->fijarContexto('', true);
    }

    /**
     * Escritura real de las dos variables. Privada a propósito.
     */
    private function fijarContexto(string $empresaId, bool $esHefesto): void
    {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException(
                'No se puede fijar el contexto de empresa fuera de una transacción. '
                . 'Use ContextoEmpresa::ejecutarAislado() o ::ejecutarAisladoComoHefesto().'
            );
        }

        // Se fijan SIEMPRE las dos variables, para que un contexto de empresa nunca
        // herede la bandera de Hefesto de una operación anterior de la misma conexión.
        DB::select('SELECT set_config(?, ?, true)', [self::VARIABLE_EMPRESA, $empresaId]);
        DB::select('SELECT set_config(?, ?, true)', [self::VARIABLE_HFESTO, $esHefesto ? 'true' : 'false']);
    }

    /**
     * Lee las variables de sesión sin pasar por las funciones de la base, que lanzan
     * excepción. Sirve para diagnóstico.
     *
     * @return array{empresa: string|null, hefesto: string|null}
     */
    public function residuoEnConexion(): array
    {
        $empresa = DB::selectOne('SELECT current_setting(?, true) AS valor', [self::VARIABLE_EMPRESA]);
        $hefesto = DB::selectOne('SELECT current_setting(?, true) AS valor', [self::VARIABLE_HFESTO]);

        return [
            'empresa' => $this->normalizar($empresa?->valor ?? null),
            'hefesto' => $this->normalizar($hefesto?->valor ?? null),
        ];
    }

    private function normalizar(?string $valor): ?string
    {
        return $valor === null || $valor === '' ? null : $valor;
    }
}