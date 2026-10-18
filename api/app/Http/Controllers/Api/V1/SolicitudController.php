<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\EstadoSolicitud;
use App\Http\Controllers\Controller;
use App\Http\Requests\CrearSolicitudRequest;
use App\Http\Resources\SolicitudResource;
use App\Models\Solicitud;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Solicitudes del cliente.
 *
 * Todas las consultas pasan por el alcance global de empresa, así que una solicitud de
 * otra empresa ni siquiera se trae desde la base. Además, cada respuesta vuelve a pasar
 * por la política: son dos barreras distintas a propósito.
 */
class SolicitudController extends Controller
{
    public function index(Request $peticion): JsonResponse
    {
        $this->authorize('viewAny', Solicitud::class);

        $solicitudes = Solicitud::query()
            ->with('plan')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json([
            'solicitudes' => SolicitudResource::collection($solicitudes),
        ]);
    }

    public function store(CrearSolicitudRequest $peticion): JsonResponse
    {
        $this->authorize('crear', Solicitud::class);

        $solicitud = Solicitud::create([
            'empresa_id' => $peticion->usuario()->empresa_id,
            'plan_id' => $peticion->planId(),
            'creada_por' => $peticion->usuario()->id,
            'estado' => EstadoSolicitud::PendienteDeRevision,
            'resumen' => $peticion->resumen(),
            'datos' => $peticion->datos(),
        ]);

        return response()->json(
            ['solicitud' => new SolicitudResource($solicitud->load('plan'))],
            201,
        );
    }

    public function show(Request $peticion, Solicitud $solicitud): JsonResponse
    {
        $this->authorize('ver', $solicitud);

        return response()->json([
            'solicitud' => new SolicitudResource($solicitud->load(['plan', 'pasos', 'entregas'])),
        ]);
    }

    public function destroy(Request $peticion, Solicitud $solicitud): JsonResponse
    {
        $this->authorize('cancelar', $solicitud);

        // Cancelar no borra nada: deja el rastro de que existió.
        $solicitud->forceFill([
            'estado' => EstadoSolicitud::Cancelada,
            'motivo_rechazo' => 'Cancelada por el usuario.',
        ])->save();

        return response()->json([
            'mensaje' => 'Solicitud cancelada.',
        ]);
    }
}