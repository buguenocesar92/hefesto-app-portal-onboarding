<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de decisiones humanas.
 *
 * Una fila por cada decisión sobre una solicitud: quién aprobó, qué operaciones se iba a
 * ejecutar, con qué huella de credencial y en qué momento.
 *
 * Se escribe antes de ejecutar, no después. Si alguien pregunta en seis meses qué
 * autorizó esto, la respuesta está en esta tabla y no en la memoria de nadie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora_aprobaciones', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('solicitud_id')->constrained('solicitudes')->cascadeOnDelete();
            $tabla->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();

            // 'aprobada', 'rechazada' o 'cancelada'.
            $tabla->string('decision', 24);

            // Operaciones que se autorizaron. Es la lista de App\Enums\OperacionCoolify,
            // visible y auditable antes de la ejecución.
            $tabla->jsonb('operaciones')->default('[]');

            $tabla->text('comentario')->nullable();
            $tabla->string('ip_origen', 64)->nullable();

            // Resumen de la credencial que se usó, nunca la credencial.
            $tabla->char('huella_credencial', 64)->nullable();

            $tabla->timestampsTz();

            $tabla->index(['solicitud_id', 'decision']);
            $tabla->index('empresa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora_aprobaciones');
    }
};