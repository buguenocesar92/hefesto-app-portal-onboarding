<?php

declare(strict_types=1);

namespace App\Acciones;

use App\Enums\EstadoSolicitud;
use App\Enums\OperacionCoolify;
use App\Jobs\EjecutarAprovisionamiento;
use App\Models\Aprobacion;
use App\Models\Solicitud;
use App\Models\Usuario;
use App\Servicios\Coolify\TokenCoolify;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Aprobación humana de una solicitud.
 *
 * Esta clase es la razón por la que el portal no puede crear infraestructura por su
 * cuenta. No hay atajo: una solicitud sin una fila aprobada en bitacora_aprobaciones,
 * con persona y fecha, no puede llegar a EjecutarAprovisionamiento.
 *
 * Tres reglas se comprueban aquí y en ningún otro sitio:
 *
 *  1. Solo una persona de Hefesto con rol de aprobación puede aprobar. Un cliente que
 *     se identifique correctamente sigue sin poder.
 *  2. Quien aprueba no puede ser quien creó la solicitud, salvo que Hefesto lo permita
 *     de forma explícita en configuración.
 *  3. Se anota exactamente qué operaciones de Coolify se autorizan. Si mañana se agrega
 *     una operación al enum, la bitácora de esta aprobación antigua dice que esa
 *     operación no estaba autorizada.
 */
final class AprobarSolicitud
{
    /**
     * Si se permite que quien creó la solicitud también la apruebe. Por defecto no.
     * Solo tiene sentido cuando Hefesto opera con una persona a la vez.
     */
    private readonly bool $permitirAutoaprobacion;

    public function __construct()
    {
        $this->permitirAutoaprobacion = (bool) config('portal.permitir_autoaprobacion', false);
    }

    /**
     * @param  array<int, string>|null  $operacionesConfirmadas  Lo que la persona
     *                                                             marcó en pantalla. Si
     *                                                             viene en blanco, se
     *                                                             toma la lista prevista.
     */
    public function ejecutar(
        Solicitud $solicitud,
        Usuario $quienAprueba,
        string $comentario = '',
        ?array $operacionesConfirmadas = null,
    ): Aprobacion {
        if (! $quienAprueba->puedeAprobar()) {
            throw ValidationException::withMessages([
                'rol' => 'Solo una persona de Hefesto con permiso de aprobación puede aprobar una ejecución.',
            ]);
        }

        if (! $solicitud->estado->permiteAprobar()) {
            throw ValidationException::withMessages([
                'estado' => 'La solicitud está en estado ' . $solicitud->estado->etiqueta() . ' y no se puede aprobar.',
            ]);
        }

        if (! $this->permitirAutoaprobacion && $solicitud->creada_por === $quienAprueba->id) {
            throw ValidationException::withMessages([
                'aprobador' => 'Quien creó la solicitud no puede aprobarla. Otra persona de Hefesto debe revisarla.',
            ]);
        }

        if (! $this->tieneOperacionesAprobables($solicitud)) {
            throw ValidationException::withMessages([
                'kit' => 'Ningún kit del plan seleccionado se puede aprovisionar automáticamente. La prepara una persona de Hefesto.',
            ]);
        }

        return DB::transaction(function () use ($solicitud, $quienAprueba, $comentario, $operacionesConfirmadas): Aprobacion {
            $operaciones = $this->operacionesAutorizadas($solicitud, $operacionesConfirmadas);

            $aprobacion = Aprobacion::create([
                'solicitud_id' => $solicitud->id,
                'usuario_id' => $quienAprueba->id,
                'empresa_id' => $solicitud->empresa_id,
                'decision' => 'aprobada',
                'operaciones' => $operaciones,
                'comentario' => $comentario,
                'ip_origen' => request()->ip(),
                'huella_credencial' => $this->huellaDeCredencial(),
            ]);

            $solicitud->forceFill([
                'estado' => EstadoSolicitud::Aprobada,
                'aprobada_por' => $quienAprueba->id,
                'aprobada_en' => now(),
            ])->save();

            Log::info('solicitud.aprobada', [
                'solicitud_id' => $solicitud->id,
                'aprobador_id' => $quienAprueba->id,
                'operaciones' => $operaciones,
            ]);

            // Solo después de dejar la aprobación escrita se encola el trabajo.
            EjecutarAprovisionamiento::dispatch($solicitud->id);

            return $aprobacion;
        });
    }

    /**
     * Operaciones que se autorizan de verdad: la intersección entre lo que la persona
     * confirmó en pantalla y lo que esta versión del portal tiene previsto ejecutar.
     *
     * Si la intersección queda vacía, la aprobación es un error y no una ejecución sin
     * pasos. Prefiere decir "no autorizaste nada ejecutable" antes que encolar un
     * trabajo que no tiene nada que hacer.
     *
     * @param  array<int, string>|null  $confirmadas
     * @return array<int, string>
     */
    public function operacionesAutorizadas(Solicitud $solicitud, ?array $confirmadas = null): array
    {
        $previstas = $this->operacionesDeLaSolicitud($solicitud);

        if ($confirmadas === null) {
            return $previstas;
        }

        $autorizadas = array_values(array_intersect($previstas, $confirmadas));

        if ($autorizadas === []) {
            throw ValidationException::withMessages([
                'operaciones' => 'No autorizaste ninguna operación que el portal pueda ejecutar.',
            ]);
        }

        return $autorizadas;
    }

    /**
     * Rechaza la solicitud. No se ejecuta nada y queda la decisión registrada.
     */
    public function rechazar(Solicitud $solicitud, Usuario $quienRechaza, string $motivo): Aprobacion
    {
        if (! $quienRechaza->puedeAprobar()) {
            throw ValidationException::withMessages([
                'rol' => 'Solo una persona de Hefesto puede rechazar una solicitud.',
            ]);
        }

        return DB::transaction(function () use ($solicitud, $quienRechaza, $motivo): Aprobacion {
            $aprobacion = Aprobacion::create([
                'solicitud_id' => $solicitud->id,
                'usuario_id' => $quienRechaza->id,
                'empresa_id' => $solicitud->empresa_id,
                'decision' => 'rechazada',
                'operaciones' => [],
                'comentario' => $motivo,
                'ip_origen' => request()->ip(),
            ]);

            $solicitud->forceFill([
                'estado' => EstadoSolicitud::Cancelada,
                'motivo_rechazo' => $motivo,
            ])->save();

            return $aprobacion;
        });
    }

    /**
     * Operaciones que se van a autorizar para esta solicitud, en el orden en que se
     * ejecutarán. Es la lista que se muestra en pantalla antes de aprobar.
     *
     * @return array<int, string>
     */
    public function operacionesDeLaSolicitud(Solicitud $solicitud): array
    {
        $operaciones = [OperacionCoolify::CrearProyecto->value, OperacionCoolify::CrearServicio->value];

        // El despliegue solo entra en la lista si Hefesto lo habilitó de forma
        // explícita. Aparece como pendiente, nunca dado por hecho.
        if (config('coolify.permitir_despliegue')) {
            $operaciones[] = OperacionCoolify::Desplegar->value;
        }

        return $operaciones;
    }

    /**
     * ¿La solicitud tiene al menos un kit que el portal sepa crear?
     */
    private function tieneOperacionesAprobables(Solicitud $solicitud): bool
    {
        $plan = $solicitud->plan;

        if ($plan === null) {
            return false;
        }

        return $plan->kitsDelPlan()
            ->contains(fn ($kit): bool => $kit->estaAprovisionable());
    }

    /**
     * Resumen de la credencial que se usará. Nunca la credencial.
     */
    private function huellaDeCredencial(): ?string
    {
        $token = TokenCoolify::desdeConfiguracion('token');

        return $token?->huella();
    }
}