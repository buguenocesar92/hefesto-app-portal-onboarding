<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Acciones\RegistrarEntregaDeCredenciales;
use App\Models\Solicitud;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Trabajo encolado que registra la entrega de credenciales en Infisical.
 *
 * Va encolado y aparte del aprovisionamiento: si el gestor de secretos no responde, no
 * debe dejar la infraestructura a medio camino.
 */
class EntregarCredenciales implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $solicitudId)
    {
        $this->onQueue('credenciales');
    }

    public function handle(RegistrarEntregaDeCredenciales $registrar): void
    {
        $solicitud = Solicitud::query()->withoutGlobalScope('empresa')->find($this->solicitudId);

        if ($solicitud === null) {
            return;
        }

        $registrar->ejecutar($solicitud);
    }

    public function failed(\Throwable $error): void
    {
        Log::error('credencial.trabajo_fallido', [
            'solicitud_id' => $this->solicitudId,
            'mensaje' => $error->getMessage(),
        ]);
    }
}