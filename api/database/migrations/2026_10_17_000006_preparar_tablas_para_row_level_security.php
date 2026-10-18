<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Columnas que necesita la seguridad de fila (RLS) para poder aislar por empresa.
 *
 * Dos cosas, y las dos son consecuencia directa de que la política de RLS compara la
 * columna `empresa_id` de cada fila contra la del contexto:
 *
 * 1. `pasos_aprovisionamiento` y `entregas_credenciales` llegaban hasta la empresa a
 *    través de `solicitud_id`. Una política de RLS no puede seguir ese camino: compara
 *    columnas de la fila que se está leyendo. Por eso se agrega `empresa_id` a las dos
 *    tablas. Es redundancia a propósito, y tiene un costo conocido: hay que
 *    mantenerla, porque si una fila queda con un `empresa_id` que no corresponde al de
 *    su solicitud, quedaría invisible para su propia empresa.
 *
 * 2. `empresas.dominio_correo` es lo que permite resolver a qué empresa pertenece la
 *    identidad que entra por Authentik, ANTES de que exista una sesión y por lo tanto
 *    un contexto de empresa. La tabla `empresas` no lleva RLS justamente porque se
 *    consulta en ese momento. Ver la migración siguiente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $tabla): void {
            // Dominio de correo de los contactos de la PyME. Permite resolver la
            // empresa al entrar, sin sesión y sin exponer datos de otras.
            $tabla->string('dominio_correo', 160)->nullable()->unique()->after('nombre_corto');
        });

        Schema::table('pasos_aprovisionamiento', function (Blueprint $tabla): void {
            $tabla->foreignId('empresa_id')->after('solicitud_id')->constrained('empresas')->cascadeOnDelete();
            $tabla->index(['empresa_id', 'estado']);
        });

        Schema::table('entregas_credenciales', function (Blueprint $tabla): void {
            $tabla->foreignId('empresa_id')->after('solicitud_id')->constrained('empresas')->cascadeOnDelete();
            $tabla->index(['empresa_id', 'estado']);
        });

        // Respaldo para datos ya existentes: la empresa se deduce de la solicitud.
        DB::statement(
            'UPDATE pasos_aprovisionamiento p
                SET empresa_id = s.empresa_id
               FROM solicitudes s
              WHERE s.id = p.solicitud_id
                AND p.empresa_id IS NULL'
        );

        DB::statement(
            'UPDATE entregas_credenciales e
                SET empresa_id = s.empresa_id
               FROM solicitudes s
              WHERE s.id = e.solicitud_id
                AND e.empresa_id IS NULL'
        );
    }

    public function down(): void
    {
        Schema::table('entregas_credenciales', function (Blueprint $tabla): void {
            $tabla->dropColumn('empresa_id');
        });

        Schema::table('pasos_aprovisionamiento', function (Blueprint $tabla): void {
            $tabla->dropColumn('empresa_id');
        });

        Schema::table('empresas', function (Blueprint $tabla): void {
            $tabla->dropColumn('dominio_correo');
        });
    }
};