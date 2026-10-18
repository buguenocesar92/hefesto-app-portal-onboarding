<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Empresas (los tenants del portal).
 *
 * La empresa es la frontera de aislamiento: todo lo que pertenece a un cliente cuelga
 * de esta tabla, y ninguna consulta del portal cruza ese límite sin un filtro
 * explícito.
 *
 * tema_ntfy se guarda en claro porque el servidor ntfy necesita el valor para publicar.
 * Es un identificador aleatorio, no un dato del negocio, y tema_ntfy_huella permite
 * detectar que alguien lo editó a mano.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->string('nombre_corto', 80)->unique();
            $tabla->string('razon_social', 160);
            $tabla->string('contacto_nombre', 120);
            $tabla->string('contacto_email', 160);
            $tabla->string('contacto_telefono', 40)->nullable();
            $tabla->string('estado', 24)->default('activa');

            // Tema de ntfy para los avisos de estado. No es un dato del negocio.
            // Se cifra en la columna: el ancho de 255 deja espacio para el texto
            // cifrado, que es más largo que el valor original.
            $tabla->string('tema_ntfy', 255)->nullable()->unique();
            $tabla->char('tema_ntfy_huella', 64)->nullable();

            $tabla->timestampsTz();

            $tabla->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};