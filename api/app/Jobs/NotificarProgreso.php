<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Integraciones\NotificadorNtfy;
use App\Models\Solicitud;
use App\Servicios\Tenancy\ContextoEmpresa;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso de progreso al cliente por ntfy.
 *
 * El mensaje describe el estado en palabras, sin identificadores internos y sin nada
 * que Permita deducir la estructura de la plataforma de despliegue.
 */
class NotificarProgreso implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $solicitudId,
        public readonly string $momento,
    ) {
        $this->onQueue('notificaciones');
    }

    public function handle(NotificadorNtfy $notificador, ContextoEmpresa $contexto): void
    {
        // También desde el trabajador de colas, así que necesita su contexto explícito.
        $contexto->ejecutarAisladoComoHefesto(function () use ($notificador): void {
            $this->publicar($notificador);
        });
    }

    private function publicar(NotificadorNtfy $notificador): void
    {
        $solicitud = Solicitud::query()->withoutGlobalScope('empresa')->with('empresa')->find($this->solicitudId);

        if ($solicitud === null) {
            return;
        }

        [$titulo, $mensaje, $prioridad] = match ($this->momento) {
            'inicio' => [
                'Hefesto empezó con tu infraestructura',
                'Estamos preparando lo que contrataste. Te avisamos cuando termine.',
                'default',
            ],
            'fin' => [
                'Tu infraestructura quedó lista',
                'Los recursos ya existen en Coolify. Las credenciales siguen el canal acordado.',
                'high',
            ],
            'fallo' => [
                'Tu onboarding necesita atención',
                'Nos detuvimos en un paso. Hefesto lo revisa; tus datos no se movieron.',
                'high',
            ],
            default => [
                'Actualización de tu onboarding',
                'Tu solicitud cambió de estado.',
                'default',
            ],
        };

        $notificador->avisar($solicitud->empresa, $titulo, $mensaje, $prioridad);
    }
}