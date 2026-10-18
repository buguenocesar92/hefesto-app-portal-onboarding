<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Acciones\AprovisionarSolicitud;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Trabajo encolado que aprovisiona una solicitud aprobada.
 *
 * Por qué encolado y no dentro de la petición web: el aprovisionamiento llama a una API
 * externa, puede tardar y puede reintentar. Si ocurriera dentro de la petición, el
 * cliente vería la pantalla cargando y una caída de Coolify se convertiría en un
 * error de página. Encolado, la respuesta web es inmediata y el avance se ve en el
 * seguimiento.
 *
 * Sin `public $tries` alto ni reintentos agresivos: si un paso falla, la solicitud
 * queda en estado fallido con el motivo, y la reintenta una persona de Hefesto.
 */
class EjecutarAprovisionamiento implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Un reintento: el propio cliente de Coolify ya reintenta por red y por límite. */
    public int $tries = 2;

    /** Si el trabajo falla, no se reintenta en la misma corrida. */
    public bool $failOnTimeout = true;

    public int $timeout = 300;

    public function __construct(public readonly int $solicitudId)
    {
        $this->onQueue('aprovisionamiento');
    }

    public function handle(AprovisionarSolicitud $aprovisionar): void
    {
        $aprovisionar->ejecutar($this->solicitudId);
    }

    public function failed(\Throwable $error): void
    {
        Log::error('aprovisionamiento.trabajo_fallido', [
            'solicitud_id' => $this->solicitudId,
            'mensaje' => $error->getMessage(),
        ]);
    }
}