<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de kits y catálogo de planes.
 *
 * El precio es un marcador, no un precio real: los valores comerciales los mantiene
 * Hefesto fuera de este sistema. Ver docs/02-CATALOGO-Y-PLANES.md.
 *
 * coolify_tipo y coolify_imagen son opcionales a propósito. Solo los kits que Hefesto
 * haya verificado contra la API de Coolify los traen informados; el resto queda en
 * blanco y el portal se niega a aprovisionarlos en vez de inventar el parámetro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kits', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->string('codigo', 60)->unique();
            $tabla->string('nombre', 140);
            $tabla->string('categoria', 60);
            $tabla->text('resumen');
            $tabla->text('que_hace');
            $tabla->jsonb('requisitos')->default('[]');
            $tabla->jsonb('incluye')->default('[]');
            $tabla->string('licencia', 120)->nullable();
            $tabla->unsignedInteger('orden')->default(0);

            // Parámetros de Coolify, verificados por kit. Vienen en blanco si no se
            // han verificado, y el portal no los inventa.
            $tabla->string('coolify_tipo', 120)->nullable();
            $tabla->string('coolify_imagen', 200)->nullable();
            $tabla->boolean('aprovisonable')->default(false);

            $tabla->boolean('activo')->default(true);
            $tabla->timestampsTz();

            $tabla->index(['activo', 'orden']);
        });

        Schema::create('planes', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->string('codigo', 60)->unique();
            $tabla->string('nombre', 140);
            $tabla->text('descripcion');

            // Marcador de precio. 0 significa "precio a confirmar con Hefesto".
            $tabla->unsignedInteger('precio_mensual_clp')->default(0);
            $tabla->string('precio_nota', 200)->nullable();

            // Lista de códigos de kit incluidos en el plan.
            $tabla->jsonb('kits')->default('[]');

            $tabla->string('estado', 24)->default('disponible');
            $tabla->boolean('activo')->default(true);
            $tabla->timestampsTz();

            $tabla->index(['activo', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planes');
        Schema::dropIfExists('kits');
    }
};