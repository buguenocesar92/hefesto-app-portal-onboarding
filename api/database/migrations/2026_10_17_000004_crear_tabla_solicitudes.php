<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitudes de onboarding, pasos y entregas de credenciales.
 *
 * Refresh es un punto único de entrada en el esquema del cliente: desde ahí hacia abajo
 * todo se alcanza por empresa. Eso hace que el aislamiento sea una sola condición y no
 * una decisión distinta en cada consulta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $tabla->foreignId('plan_id')->nullable()->constrained('planes')->nullOnDelete();
            $tabla->foreignId('creada_por')->nullable()->constrained('usuarios')->nullOnDelete();

            $tabla->string('estado', 32)->default('pendiente_de_revision');
            $tabla->text('resumen')->nullable();
            $tabla->jsonb('datos')->default('{}');

            // Aprobación humana. Sin estas dos columnas, la solicitud no puede pasar a
            // ejecución: el trabajo lo comprueba antes de tocar Coolify.
            $tabla->foreignId('aprobada_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->timestampTz('aprobada_en')->nullable();
            $tabla->text('motivo_rechazo')->nullable();

            // Identificadores que Coolify devolvió. No son secretos.
            $tabla->string('coolify_proyecto_uuid', 64)->nullable();
            $tabla->string('coolify_servicio_uuid', 64)->nullable();
            $tabla->string('coolify_entorno_uuid', 64)->nullable();

            $tabla->timestampsTz();

            $tabla->index(['empresa_id', 'estado']);
            $tabla->index('estado');
        });

        Schema::create('pasos_aprovisionamiento', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('solicitud_id')->constrained('solicitudes')->cascadeOnDelete();
            $tabla->unsignedSmallInteger('orden');
            $tabla->string('operacion', 40);
            $tabla->string('estado', 24)->default('pendiente');
            $tabla->text('mensaje')->nullable();

            // Resultado resumido: nunca contiene la credencial ni el cuerpo crudo.
            $tabla->jsonb('resultado')->default('{}');
            $tabla->string('error_codigo', 60)->nullable();
            $tabla->timestampTz('iniciado_en')->nullable();
            $tabla->timestampTz('terminado_en')->nullable();
            $tabla->timestampsTz();

            $tabla->unique(['solicitud_id', 'orden']);
            $tabla->index(['solicitud_id', 'estado']);
        });

        Schema::create('entregas_credenciales', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('solicitud_id')->constrained('solicitudes')->cascadeOnDelete();

            // Qué se entregó: la contraseña del servicio, la del panel, la ruta, etc.
            $tabla->string('tipo', 60);

            // Dónde quedó, para que Hefesto lo comparta sin volver a generarlo.
            $tabla->string('ruta_infisical', 200);
            $tabla->string('clave', 120);
            $tabla->string('proyecto', 120)->nullable();
            $tabla->string('entorno', 60)->nullable();

            // 'registrado' significa "el valor está en Infisical". 'pendiente' significa
            // que la integración está apagada y NADIE tiene esa credencial todavía.
            $tabla->string('estado', 24)->default('pendiente');
            $tabla->timestampTz('entregado_en')->nullable();
            $tabla->text('notas')->nullable();
            $tabla->timestampsTz();

            $tabla->index(['solicitud_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entregas_credenciales');
        Schema::dropIfExists('pasos_aprovisionamiento');
        Schema::dropIfExists('solicitudes');
    }
};