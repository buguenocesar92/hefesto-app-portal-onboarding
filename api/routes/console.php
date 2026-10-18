<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
*/

// Limpieza de solicitudes abandonadas: quedan en estado final y nadie las mira después
// de seis meses. No se borra nada de forma automática; el registro de decisiones debe
// sobrevivir más que la interfaz.
Schedule::call(function (): void {
    \App\Models\Solicitud::query()
        ->withoutGlobalScope('empresa')
        ->whereIn('estado', ['aprobada', 'en_ejecucion'])
        ->where('updated_at', '<', now()->subHours(6))
        ->each(function (\App\Models\Solicitud $solicitud): void {
            \Illuminate\Support\Facades\Log::warning('solicitud.atascada', [
                'solicitud_id' => $solicitud->id,
                'estado' => $solicitud->estado->value,
            ]);
        });
})->hourly()->name('avisar-solicitudes-atascadas');