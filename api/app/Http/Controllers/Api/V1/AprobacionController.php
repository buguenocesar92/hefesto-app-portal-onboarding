<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Acciones\AprobarSolicitud;
use App\Enums\OperacionCoolify;
use App\Http\Controllers\Controller;
use App\Http\Requests\AprobarSolicitudRequest;
use App\Models\Solicitud;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Área de aprobación de Hefesto.
 *
 * Las rutas que apuntan a este controlador están detrás del middleware rol.hefesto, así
 * que un cliente no llega ni a la puerta. Aun así, cada acción vuelve a comprobar la
 * política y el rol: dos capas, porque el middleware se puede quitar por error en una
 * edición futura y esta capa no.
 *
 * Antes de aprobar, la pantalla muestra la lista exacta de operaciones de Coolify que se
 * van a ejecutar. Aprobar es aprobar esa lista, no un "sí" genérico.
 */
class AprobacionController extends Controller
{
    public function __construct(private readonly AprobarSolicitud $aprobar)
    {
    }

    public function index(Request $peticion): JsonResponse
    {
        $solicitudes = Solicitud::query()
            ->withoutGlobalScope('empresa')
            ->whereIn('estado', ['pendiente_de_revision', 'necesita_informacion'])
            ->with(['empresa', 'plan'])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'solicitudes' => $solicitudes->map(fn (Solicitud $solicitud): array => [
                'id' => $solicitud->id,
                'empresa' => $solicitud->empresa->razon_social,
                'nombre_corto' => $solicitud->empresa->nombre_corto,
                'plan' => $solicitud->plan?->nombre,
                'estado' => $solicitud->estado->value,
                'creada' => $solicitud->created_at?->toIso8601String(),
                'resumen' => $solicitud->resumen,
                // Lo que Hefesto tiene que revisar antes de decir que sí.
                'kits_automatizables' => $solicitud->plan
                    ? $solicitud->plan->kitsDelPlan()
                        ->filter(fn ($kit): bool => $kit->estaAprovisionable())
                        ->map(fn ($kit): array => ['codigo' => $kit->codigo, 'tipo_coolify' => $kit->coolify_tipo])
                        ->values()
                        ->all()
                    : [],
            ]),
        ]);
    }

    /**
     * Qué se va a ejecutar exactamente. La pantalla de aprobación muestra esto.
     */
    public function previa(Solicitud $solicitud): JsonResponse
    {
        $this->authorize('aprobar', $solicitud);

        $operaciones = $this->aprobar->operacionesDeLaSolicitud($solicitud);

        return response()->json([
            'solicitud_id' => $solicitud->id,
            'puede_ejecutar' => $solicitud->puedeEjecutarse(),
            'tiene_aprobacion' => $solicitud->tieneAprobacionRegistrada(),
            'operaciones' => array_map(static fn (string $clave): array => [
                'clave' => $clave,
                'etiqueta' => OperacionCoolify::from($clave)->etiqueta(),
                'metodo' => OperacionCoolify::from($clave)->metodo(),
                'ruta' => OperacionCoolify::from($clave)->ruta(),
                'porque' => OperacionCoolify::from($clave)->porquePermitida(),
                'documentacion' => OperacionCoolify::from($clave)->documentacion(),
            ], $operaciones),
            'advertencia' => config('coolify.habilitada')
                ? null
                : 'La integración con Coolify está apagada: aprobar creará la solicitud, pero no se ejecutará nada en la plataforma.',
        ]);
    }

    public function aprobar(AprobarSolicitudRequest $peticion, Solicitud $solicitud): JsonResponse
    {
        $this->authorize('aprobar', $solicitud);

        $aprobacion = $this->aprobar->ejecutar(
            $solicitud,
            $peticion->usuario(),
            (string) ($peticion->validated()['comentario'] ?? ''),
            $peticion->validated()['operaciones'],
        );

        return response()->json([
            'mensaje' => 'Solicitud aprobada y encolada.',
            'aprobacion' => [
                'id' => $aprobacion->id,
                'decision' => $aprobacion->decision,
                'operaciones' => $aprobacion->operaciones,
                'fecha' => $aprobacion->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function rechazar(Request $peticion, Solicitud $solicitud): JsonResponse
    {
        $this->authorize('rechazar', $solicitud);

        $datos = $peticion->validate([
            'motivo' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'motivo.required' => 'Escribe el motivo: el cliente lo va a leer.',
        ]);

        $this->aprobar->rechazar($solicitud, $peticion->usuario(), $datos['motivo']);

        return response()->json(['mensaje' => 'Solicitud rechazada.']);
    }
}