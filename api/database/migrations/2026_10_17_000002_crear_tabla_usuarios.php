<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Usuarios del portal.
 *
 * La identidad la trae Authentik, igual que en las otras aplicaciones de Hefesto: el
 * portal no guarda contraseñas. empresa_id queda en blanco para el personal de Hefesto,
 * que opera para todos los clientes y no pertenece a ninguno.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('empresa_id')
                ->nullable()
                ->constrained('empresas')
                ->nullOnDelete();
            $tabla->string('nombre', 120);
            $tabla->string('email', 160)->unique();
            $tabla->string('rol', 24);
            $tabla->string('authentik_subject', 160)->nullable()->unique();
            $tabla->timestampTz('ultimo_acceso')->nullable();
            $tabla->rememberToken();
            $tabla->timestampsTz();

            $tabla->index(['empresa_id', 'rol']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};