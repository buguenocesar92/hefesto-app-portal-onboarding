<?php

declare(strict_types=1);

namespace App\Acciones;

use App\Enums\EstadoSolicitud;
use App\Enums\EstadoPaso;
use App\Enums\OperacionCoolify;
use App\Jobs\EntregarCredenciales;
use App\Jobs\NotificarProgreso;
use App\Models\Kit;
use App\Models\PasoAprovisionamiento;
use App\Models\Solicitud;
use App\Servicios\Coolify\ClienteCoolify;
use App\Servicios\Coolify\Excepcion\CoolifyExcepcion;
use App\Servicios\Coolify\Excepcion\OperacionDeshabilitadaExcepcion;
use App\Servicios\Coolify\Excepcion\OperacionProhibidaExcepcion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ejecución del aprovisionamiento de una solicitud aprobada.
 *
 * El recorrido es siempre el mismo, y cada paso queda escrito en la tabla de pasos con
 * su resultado. La primera comprobación de este método es la importante:
 *
 *     if (! $solicitud->puedeEjecutarse()) { return; }
 *
 * Es decir, si alguien accomplishmentó el estado a mano, borró la bitácora o cambió una
 * fila en la base, el trabajo no toca Coolify. La aprobación humana no es un estado que
 * se pueda saltar escribiendo en el campo estado.
 *
 * Cuando la integración está apagada (COOLIFY_HABILITADA=false), el recorrido NO se
 * salta en silencio: los pasos quedan marcados como omitidos con el motivo, y la
 * solicitud termina en estado "fallida" con una explicación honesta. Un onboarding que
 * se ve completo y no aprovisionó nada es peor que uno que dice "no se ejecutó".
 */
final class AprovisionarSolicitud
{
    public function __construct(
        private readonly ClienteCoolify $coolify,
        private readonly RegistrarEntregaDeCredenciales $entregas,
        private readonly \App\Servicios\Tenancy\ContextoEmpresa $contexto,
    ) {
    }

    /**
     * Punto de entrada.
     *
     * El trabajo se ejecuta desde el trabajador de colas, que no pasa por el middleware
     * y por lo tanto no tiene contexto de empresa. Como el aprovisionamiento es una tarea
     * del sistema, que recorre solicitudes de cualquier empresa, se abre en contexto de
     * Hefesto: es el mismo que usa la persona que aprobó, y la política de la base lo
     * reconoce explícitamente.
     *
     * Todo lo que está dentro corre en una transacción con ese contexto, y PostgreSQL
     * descarta el contexto al terminar. Si este envoltorio faltara, la primera consulta
     * fallaría con una excepción de la base: el aislamiento falla cerrado.
     */
    public function ejecutar(int $solicitudId): void
    {
        $this->contexto->ejecutarAisladoComoHefesto(fn (): mixed => $this->ejecutarEnContexto($solicitudId));
    }

    private function ejecutarEnContexto(int $solicitudId): void
    {
        $solicitud = Solicitud::query()->withoutGlobalScope('empresa')->findOrFail($solicitudId);

        // Barrera de aprobación. Se comprueba antes de cualquier otra cosa.
        if (! $solicitud->puedeEjecutarse()) {
            Log::warning('aprovisionamiento.sin_aprobacion', ['solicitud_id' => $solicitudId]);

            return;
        }

        $solicitud->forceFill(['estado' => EstadoSolicitud::EnEjecucion])->save();

        NotificarProgreso::dispatch($solicitud->id, 'inicio');

        try {
            $pasos = $this->recorrido($solicitud);
        } catch (CoolifyExcepcion $error) {
            $this->marcarFallida($solicitud, $error->getMessage(), $error->codigo());

            return;
        }

        if (! $pasos) {
            $this->marcarFallida(
                $solicitud,
                'Ninguna operación se ejecutó. Revisa la configuración de la integración con Coolify.',
                'sin_pasos',
            );

            return;
        }

        $solicitud->forceFill(['estado' => EstadoSolicitud::Aprovisionada])->save();

        NotificarProgreso::dispatch($solicitud->id, 'fin');
        EntregarCredenciales::dispatch($solicitud->id);
    }

    /**
     * Recorre los pasos y devuelve cuántos se completaron de verdad.
     */
    private function recorrido(Solicitud $solicitud): int
    {
        $plan = $solicitud->plan;
        $kit = $plan?->kitsDelPlan()->first(fn (Kit $item): bool => $item->estaAprovisionable());

        if ($kit === null) {
            Log::warning('aprovisionamiento.sin_kit', ['solicitud_id' => $solicitud->id]);

            return 0;
        }

        $proyecto = $this->pasoCrearProyecto($solicitud);
        if ($proyecto === null) {
            return 0;
        }

        $servicio = $this->pasoCrearServicio($solicitud, $kit, $proyecto);
        if ($servicio === null) {
            return 0;
        }

        // El despliegue queda registrado como omitido mientras siga deshabilitado. La
        // fila existe, con su motivo, para que nadie crea que se hizo.
        $this->pasoDespliegue($solicitud, $servicio);

        return 2;
    }

    /**
     * Paso 1: crear el proyecto del cliente. Es la unidad de aislamiento en Coolify.
     */
    private function pasoCrearProyecto(Solicitud $solicitud): ?string
    {
        $paso = $this->registrarPaso($solicitud, 1, OperacionCoolify::CrearProyecto);
        $paso->marcarEnCurso();

        try {
            $respuesta = $this->coolify->ejecutar(
                OperacionCoolify::CrearProyecto,
                [
                    'name' => $this->nombreProyecto($solicitud),
                    'description' => 'Proyecto de onboarding creado por el portal de Hefesto.',
                ],
            );
        } catch (OperacionDeshabilitadaExcepcion $error) {
            $paso->marcarOmitido($error->getMessage());

            return null;
        }

        $uuid = $respuesta->uuid();

        if ($uuid === null) {
            $paso->marcarFallido('Coolify respondió sin identificador de proyecto.', 'respuesta_sin_uuid');

            return null;
        }

        $solicitud->forceFill(['coolify_proyecto_uuid' => $uuid])->save();
        $paso->marcarCompletado($respuesta->resumen(), 'Proyecto creado en Coolify.');

        return $uuid;
    }

    /**
     * Paso 2: crear el servicio del kit dentro del proyecto ya creado.
     */
    private function pasoCrearServicio(Solicitud $solicitud, Kit $kit, string $proyectoUuid): ?string
    {
        $paso = $this->registrarPaso($solicitud, 2, OperacionCoolify::CrearServicio);
        $paso->marcarEnCurso();

        $servidor = config('coolify.servidor_uuid');

        if (! is_string($servidor) || $servidor === '') {
            $paso->marcarFallido(
                'No hay servidor de Coolify configurado (COOLIFY_SERVIDOR_UUID). Hefesto debe indicar uno.',
                'servidor_no_configurado',
            );

            return null;
        }

        try {
            $respuesta = $this->coolify->ejecutar(
                OperacionCoolify::CrearServicio,
                [
                    'type' => $kit->coolify_tipo,
                    'name' => $kit->codigo . '-' . $solicitud->empresa->nombre_corto,
                    'description' => 'Servicio del kit ' . $kit->nombre . ', creado por el portal de Hefesto.',
                    'project_uuid' => $proyectoUuid,
                    'environment_name' => (string) config('coolify.entorno_nombre', 'production'),
                    'server_uuid' => $servidor,
                    'instant_deploy' => false,
                ],
            );
        } catch (OperacionDeshabilitadaExcepcion $error) {
            $paso->marcarOmitido($error->getMessage());

            return null;
        }

        $uuid = $respuesta->uuid();

        if ($uuid === null) {
            $paso->marcarFallido('Coolify respondió sin identificador de servicio.', 'respuesta_sin_uuid');

            return null;
        }

        $solicitud->forceFill(['coolify_servicio_uuid' => $uuid])->save();
        $paso->marcarCompletado($respuesta->resumen(), 'Servicio creado en Coolify.');

        return $uuid;
    }

    /**
     * Paso 3: disparar el despliegue. Hoy queda en una fase posterior.
     */
    private function pasoDespliegue(Solicitud $solicitud, string $servicioUuid): void
    {
        $paso = $this->registrarPaso($solicitud, 3, OperacionCoolify::Desplegar);

        if (! config('coolify.permitir_despliegue')) {
            $paso->marcarOmitido(
                'El despliegue automático está en una fase posterior. El servicio quedó creado, pero sin poner en marcha.'
            );

            return;
        }

        $paso->marcarEnCurso();

        try {
            $respuesta = $this->coolify->ejecutar(
                OperacionCoolify::Desplegar,
                [],
                $servicioUuid,
                ['uuid' => $servicioUuid],
            );
        } catch (OperacionDeshabilitadaExcepcion $error) {
            $paso->marcarOmitido($error->getMessage());

            return;
        } catch (OperacionProhibidaExcepcion $error) {
            $paso->marcarFallido($error->getMessage(), $error->codigo());

            return;
        }

        $paso->marcarCompletado($respuesta->resumen(), 'Despliegue disparado.');
    }

    /**
     * Crea o reutiliza el paso de una operación para esta solicitud.
     */
    private function registrarPaso(Solicitud $solicitud, int $orden, OperacionCoolify $operacion): PasoAprovisionamiento
    {
        return PasoAprovisionamiento::firstOrCreate(
            ['solicitud_id' => $solicitud->id, 'orden' => $orden],
            [
                // La empresa viaja en cada fila: es lo que permite que la seguridad de
                // fila de PostgreSQL filtre este paso sin tener que seguir la relación
                // con la solicitud.
                'empresa_id' => $solicitud->empresa_id,
                'operacion' => $operacion->value,
                'estado' => EstadoPaso::Pendiente,
                'resultado' => [],
            ],
        );
    }

    /**
     * Nombre del proyecto en Coolify. Incluye el identificador de la empresa para que
     * dos clientes con razón social parecida nunca compartan proyecto.
     */
    private function nombreProyecto(Solicitud $solicitud): string
    {
        return sprintf('pyme-%06d-%s', $solicitud->empresa_id, $solicitud->empresa->nombre_corto);
    }

    private function marcarFallida(Solicitud $solicitud, string $mensaje, string $codigo): void
    {
        DB::transaction(function () use ($solicitud, $mensaje, $codigo): void {
            $solicitud->forceFill([
                'estado' => EstadoSolicitud::Fallida,
            ])->save();

            Log::error('aprovisionamiento.fallido', [
                'solicitud_id' => $solicitud->id,
                'codigo' => $codigo,
                'mensaje' => $mensaje,
            ]);
        });

        NotificarProgreso::dispatch($solicitud->id, 'fallo');
    }
}