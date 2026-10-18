<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estados de una solicitud de onboarding.
 *
 * La regla que atraviesa todo el flujo: no se pasa de APROBADA a APROVISIONANDO sin que
 * exista una aprobación humana registrada con fecha, persona y operaciones concretas.
 */
enum EstadoSolicitud: string
{
    /** El cliente la creó y está esperando que Hefesto la revise. */
    case PendienteDeRevision = 'pendiente_de_revision';

    /** Hefesto la revisó y pidió un dato al cliente. */
    case NecesitaInformacion = 'necesita_informacion';

    /** Aprobada por una persona de Hefesto. Es el único estado desde el que se ejecuta. */
    case Aprobada = 'aprobada';

    /** Aprobada y con un trabajo encolado que está corriendo. */
    case EnEjecucion = 'en_ejecucion';

    /** Terminó bien: los recursos quedaron creados. */
    case Aprovisionada = 'aprovisionada';

    /** Terminó mal. Queda registrado en qué paso falló, sin datos sensibles. */
    case Fallida = 'fallida';

    /** El cliente o Hefesto la canceló antes de ejecutarla. */
    case Cancelada = 'cancelada';

    /** Etiqueta legible para la interfaz de seguimiento. */
    public function etiqueta(): string
    {
        return match ($this) {
            self::PendienteDeRevision => 'Esperando revisión de Hefesto',
            self::NecesitaInformacion => 'Falta información',
            self::Aprobada => 'Aprobada, lista para executar',
            self::EnEjecucion => 'En ejecución',
            self::Aprovisionada => 'Infraestructura lista',
            self::Fallida => 'Fallida',
            self::Cancelada => 'Cancelada',
        };
    }

    /** Descripción para el cliente, sin jerga interna. */
    public function explicacionAlCliente(): string
    {
        return match ($this) {
            self::PendienteDeRevision => 'Recibimos tu solicitud. Una persona de Hefesto la está revisando antes de tocar cualquier servidor.',
            self::NecesitaInformacion => 'Te escribimos porque nos falta un dato para continuar.',
            self::Aprobada => 'Hefesto aprobó tu solicitud. La preparación empieza en breve.',
            self::EnEjecucion => 'Estamos preparando tu infraestructura.',
            self::Aprovisionada => 'Tu infraestructura quedó creada. Las credenciales están donde acordamos.',
            self::Fallida => 'La preparación se detuvo en un paso. Hefesto lo está revisando; tus datos están a salvo.',
            self::Cancelada => 'La solicitud fue cancelada.',
        };
    }

    /** ¿Se puede aprobar desde este estado? */
    public function permiteAprobar(): bool
    {
        return in_array($this, [self::PendienteDeRevision, self::NecesitaInformacion], true);
    }

    /** ¿Se puede poner en ejecución desde este estado? */
    public function permiteEjecutar(): bool
    {
        return $this === self::Aprobada;
    }

    /** ¿Es un estado final? */
    public function esFinal(): bool
    {
        return in_array($this, [self::Aprovisionada, self::Fallida, self::Cancelada], true);
    }

    /** Color sugerido en la interfaz, para que el estado se lea de un vistazo. */
    public function color(): string
    {
        return match ($this) {
            self::PendienteDeRevision, self::NecesitaInformacion => 'ambar',
            self::Aprobada, self::EnEjecucion => 'azul',
            self::Aprovisionada => 'verde',
            self::Fallida => 'rojo',
            self::Cancelada => 'gris',
        };
    }
}