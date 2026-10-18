<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estados de un paso del aprovisionamiento.
 *
 * Cada paso queda registrado con su fecha, su resultado y el identificador del recurso
 * en Coolify. El historial es la prueba que se le da al cliente cuando pregunta "¿qué
 * hicieron con mi servidor?".
 */
enum EstadoPaso: string
{
    /** Todavía no se ha intentado. */
    case Pendiente = 'pendiente';

    /** Se está ejecutando ahora. */
    case EnCurso = 'en_curso';

    /** Terminó correctamente. */
    case Completado = 'completado';

    /** Terminó con error. Se guarda el mensaje, nunca la credencial. */
    case Fallido = 'fallido';

    /** Se saltó porque una condición previa no se cumplió. */
    case Omitido = 'omitido';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::EnCurso => 'En curso',
            self::Completado => 'Completado',
            self::Fallido => 'Fallido',
            self::Omitido => 'Omitido',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'gris',
            self::EnCurso => 'azul',
            self::Completado => 'verde',
            self::Fallido => 'rojo',
            self::Omitido => 'ambar',
        };
    }

    public function terminado(): bool
    {
        return in_array($this, [self::Completado, self::Fallido, self::Omitido], true);
    }
}