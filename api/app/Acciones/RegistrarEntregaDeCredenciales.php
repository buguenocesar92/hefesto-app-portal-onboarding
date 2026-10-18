<?php

declare(strict_types=1);

namespace App\Acciones;

use App\Models\EntregaCredencial;
use App\Models\Solicitud;
use App\Servicios\Infisical\ClienteInfisical;
use App\Servicios\Infisical\Excepcion\InfisicalNoDisponibleExcepcion;
use Illuminate\Support\Facades\Log;

/**
 * Registro de las credenciales que se entregan al cliente nuevo.
 *
 * El valor de la credencial se genera, se entrega a Infisical y se descarta. En este
 * sistema queda solo la dirección donde quedó. Eso es deliberado: la base de datos del
 * portal tiene que poder respaldarse y compartirse sin convertirse en el lugar donde
 * viven los secretos de todos los clientes.
 *
 * ESTADO: la integración con Infisical está deshabilitada porque la ruta exacta de
 * creación de secretos no se pudo verificar. Con la integración apagada, las entregas
 * se registran en estado "pendiente": el cliente ve que falta, no una marca verde falsa.
 */
final class RegistrarEntregaDeCredenciales
{
    public function __construct(
        private readonly ClienteInfisical $infisical,
        private readonly \App\Servicios\Tenancy\ContextoEmpresa $contexto,
    ) {
    }

    /**
     * Se ejecuta desde el trabajador de colas, sin el contexto que deja el middleware.
     * Al ser una tarea del sistema, corre en contexto de Hefesto, igual que el
     * aprovisionamiento que la precede.
     */
    public function ejecutar(Solicitud $solicitud): void
    {
        $this->contexto->ejecutarAisladoComoHefesto(fn (): mixed => $this->registrar($solicitud));
    }

    private function registrar(Solicitud $solicitud): void
    {
        $kit = $solicitud->plan?->kitsDelPlan()->first();

        if ($kit === null) {
            return;
        }

        $ruta = $this->infisical->rutaParaEmpresa($solicitud->empresa_id, $solicitud->empresa->nombre_corto);
        $clave = 'portal-onboarding/estado';

        $existente = EntregaCredencial::firstOrNew([
            'solicitud_id' => $solicitud->id,
            'tipo' => 'acceso-inicial',
        ]);

        $existente->fill([
            'empresa_id' => $solicitud->empresa_id,
            'ruta_infisical' => $ruta . '/' . $clave,
            'clave' => $clave,
            'proyecto' => config('infisical.proyecto'),
            'entorno' => config('infisical.entorno'),
        ]);

        if (! $this->infisical->disponible()) {
            $existente->fill([
                'estado' => 'pendiente',
                'entregado_en' => null,
                'notas' => $this->infisical->motivoDeNoDisponibilidad(),
            ])->save();

            Log::warning('credencial.pendiente', [
                'solicitud_id' => $solicitud->id,
                'motivo' => $this->infisical->motivoDeNoDisponibilidad(),
            ]);

            return;
        }

        try {
            $resultado = $this->infisical->registrarSecreto(
                rutaSecreto: $ruta . '/' . $clave,
                valores: ['estado' => 'infraestructura creada por el portal de Hefesto'],
                descripcion: 'Constancia de entrega para la solicitud ' . $solicitud->id,
            );
        } catch (InfisicalNoDisponibleExcepcion $error) {
            $existente->fill([
                'estado' => 'pendiente',
                'notas' => $error->getMessage(),
            ])->save();

            return;
        }

        $existente->fill([
            'estado' => 'registrado',
            'entregado_en' => now(),
            'ruta_infisical' => $resultado['ruta_secreto'],
            'proyecto' => $resultado['proyecto'],
            'entorno' => $resultado['entorno'],
        ])->save();
    }
}