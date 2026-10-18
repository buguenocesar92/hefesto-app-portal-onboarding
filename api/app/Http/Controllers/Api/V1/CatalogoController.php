<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Kit;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;

/**
 * Catálogo público: kits y planes.
 *
 * Solo lectura y sin datos de autenticación. Devuelve lo mismo para todos, que es
 * justamente lo que se necesita para que el cliente decida antes de iniciar sesión.
 */
class CatalogoController extends Controller
{
    public function kits(): JsonResponse
    {
        $kits = Kit::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->get()
            ->map(fn (Kit $kit): array => [
                'codigo' => $kit->codigo,
                'nombre' => $kit->nombre,
                'categoria' => $kit->categoria,
                'resumen' => $kit->resumen,
                'que_hace' => $kit->que_hace,
                'requisitos' => $kit->requisitos,
                'incluye' => $kit->incluye,
                'licencia' => $kit->licencia,
                'aprovisonable' => $kit->estaAprovisionable(),
                'motivo_no_aprovisonable' => $kit->motivoDeNoAprovisionar(),
            ]);

        return response()->json(['kits' => $kits]);
    }

    public function planes(): JsonResponse
    {
        $planes = Plan::query()
            ->where('activo', true)
            ->where('estado', 'disponible')
            ->get()
            ->map(fn (Plan $plan): array => [
                'codigo' => $plan->codigo,
                'nombre' => $plan->nombre,
                'descripcion' => $plan->descripcion,
                'precio_texto' => $plan->precioMostrado(),
                'precio_es_marcador' => $plan->precio_mensual_clp === 0,
                'kits' => $plan->kitsDelPlan()->map(fn (Kit $kit): array => [
                    'codigo' => $kit->codigo,
                    'nombre' => $kit->nombre,
                    'aprovisonable' => $kit->estaAprovisionable(),
                ]),
            ]);

        return response()->json([
            'planes' => $planes,
            'nota' => 'Los precios los mantiene Hefesto fuera del portal. Los valores aquí son marcadores.',
        ]);
    }
}