<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Solicitud;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación de una solicitud hacia el navegador.
 *
 * Lo que NO está aquí, y es lo importante:
 *
 *  - La credencial de Coolify. Nunca estuvo en el modelo, así que no puede aparecer en
 *    la respuesta aunque alguien agregue el campo equivocado más adelante.
 *  - El tema de ntfy de la empresa.
 *  - Los identificadores internos de Coolify (proyecto, servicio, entorno) cuando quien
 *    mira no es personal de Hefesto: un cliente no necesita saber cómo se llama el
 *    proyecto en la plataforma de despliegue, y esa información no le corresponde.
 */
class SolicitudResource extends JsonResource
{
    /** @var Solicitud */
    public $resource;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $peticion): array
    {
        $esHefesto = $this->esHefesto($peticion);

        return [
            'id' => $this->id,
            'plan' => $this->whenLoaded('plan', fn (): array => [
                'codigo' => $this->plan->codigo,
                'nombre' => $this->plan->nombre,
                'precio_texto' => $this->plan->precioMostrado(),
            ]),
            'estado' => $this->estado->value,
            'estado_texto' => $this->estado->etiqueta(),
            'explicacion' => $this->estado->explicacionAlCliente(),
            'color' => $this->estado->color(),
            'resumen' => $this->resumen,
            'datos' => $this->datos,
            'creada' => $this->created_at?->toIso8601String(),
            'aprobada' => $this->aprobada_en?->toIso8601String(),
            'aprobable_por_usted' => $esHefesto && $this->estado->permiteAprobar(),
            'ejecutable' => $this->puedeEjecutarse(),
            'pasos' => $this->whenLoaded('pasos', fn (): array => $this->pasos->map(fn ($paso): array => [
                'orden' => $paso->orden,
                'operacion' => $paso->operacion,
                'etiqueta' => $paso->etiqueta(),
                'estado' => $paso->estado->value,
                'estado_texto' => $paso->estado->etiqueta(),
                'color' => $paso->estado->color(),
                'mensaje' => $paso->mensaje,
                'iniciado' => $paso->iniciado_en?->toIso8601String(),
                'terminado' => $paso->terminado_en?->toIso8601String(),
                // El resultado técnico solo para Hefesto.
                'resultado' => $esHefesto ? $paso->resultado : null,
            ])->all()),
            'entregas' => $this->whenLoaded('entregas', fn (): array => $this->entregas->map(fn ($entrega): array => [
                'tipo' => $entrega->tipo,
                'estado' => $entrega->estado,
                'ubicacion' => $entrega->ubicacionLegible(),
                'entregado' => $entrega->entregado_en?->toIso8601String(),
            ])->all()),
            'coolify' => $esHefesto ? [
                'proyecto' => $this->coolify_proyecto_uuid,
                'servicio' => $this->coolify_servicio_uuid,
            ] : null,
        ];
    }

    private function esHefesto(Request $peticion): bool
    {
        $usuario = $peticion->user();

        return $usuario instanceof Usuario && $usuario->esHefesto();
    }
}