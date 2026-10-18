<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Operaciones de la API de Coolify que este portal NUNCA llama.
 *
 * No es una lista de "todavía no implementadas". Son operaciones que existen en la API
 * de Coolify, están disponibles para la credencial que usa este portal, y aun así el
 * código no tiene forma de invocarlas.
 *
 * Cada entrada existe para responder una pregunta concreta: si alguien pide "¿el portal
 * puede borrar un proyecto?", la respuesta tiene que ser verificable, no una promesa.
 * App\Servicios\Coolify\ClienteCoolify consulta esta lista antes de cada llamada y
 * aborta si alguien intenta pasar una de ellas.
 *
 * La regla general: este portal solo CREA recursos nuevos, dentro de un proyecto
 * exclusivo del cliente, y los LEE. Nunca borra, nunca detiene, nunca reinicia y nunca
 * lee secretos.
 */
final class OperacionesProhibidas
{
    /**
     * Operaciones explícitamente negadas.
     *
     * @var array<int, array{metodo: string, ruta: string, motivo: string, verificacion: string}>
     */
    public const NEGADAS = [
        [
            'metodo' => 'DELETE',
            'ruta' => '/api/v1/projects/{uuid}',
            'motivo' => 'Borrar un proyecto elimina los recursos que contiene. Es el peor desenlace posible desde un portal web.',
            'verificacion' => 'https://coolify.io/docs/api/endpoints/projects/delete-project-by-uuid',
        ],
        [
            'metodo' => 'DELETE',
            'ruta' => '/api/v1/services/{uuid}',
            'motivo' => 'Borrar un servicio borra contenedores, redes y datos del cliente.',
            'verificacion' => 'https://coolify.io/docs/api/endpoints/services/delete-service-by-uuid',
        ],
        [
            'metodo' => 'DELETE',
            'ruta' => '/api/v1/applications/{uuid}',
            'motivo' => 'Borrar una aplicación destruye el despliegue del cliente.',
            'verificacion' => 'https://coolify.io/docs/api/endpoints/applications/delete-application-by-uuid',
        ],
        [
            'metodo' => 'DELETE',
            'ruta' => '/api/v1/databases/{uuid}',
            'motivo' => 'Una base de datos borrada se lleva por delante los datos del cliente, que no tienen copia garantizada en este portal.',
            'verificacion' => 'https://coolify.io/docs/api/endpoints/databases/delete-database-by-uuid',
        ],
        [
            'metodo' => 'POST',
            'ruta' => '/api/v1/services/{uuid}/restart',
            'motivo' => 'Reiniciar deja sin servicio a un cliente por una decisión tomada en un portal web.',
            'verificacion' => 'https://coolify.io/docs/api/endpoints/services/restart-service-by-uuid',
        ],
        [
            'metodo' => 'POST',
            'ruta' => '/api/v1/services/{uuid}/stop',
            'motivo' => 'Detener un servicio equivale a dejar fuera al cliente.',
            'verificacion' => 'https://coolify.io/docs/api/endpoints/services/stop-service-by-uuid',
        ],
        [
            'metodo' => 'POST',
            'ruta' => '/api/v1/services/{uuid}/start',
            'motivo' => 'Misma razón que detener, en sentido inverso: esta es una acción de operación diaria de Hefesto, no de onboarding.',
            'verificacion' => 'https://coolify.io/docs/api/endpoints/services/start-service-by-uuid',
        ],
        [
            'metodo' => 'POST',
            'ruta' => '/api/v1/applications/{uuid}/restart',
            'motivo' => 'Reiniciar la aplicación de un cliente.',
            'verificacion' => 'https://coolify.io/docs/api/endpoints/applications/restart-application-by-uuid',
        ],
        [
            'metodo' => 'POST',
            'ruta' => '/api/v1/databases/{uuid}/stop',
            'motivo' => 'Detener una base de datos deja caída a todas las aplicaciones del cliente.',
            'verificacion' => 'https://coolify.io/docs/api/endpoints/databases/stop-database-by-uuid',
        ],
        [
            'metodo' => 'POST',
            'ruta' => '/api/v1/deployments/{uuid}/cancel',
            'motivo' => 'Cancelar un despliegue en curso deja la infraestructura a medio construir.',
            'verificacion' => 'https://coolify.io/docs/api/endpoints/deployments/cancel-deployment-by-uuid',
        ],
        [
            'metodo' => 'POST',
            'ruta' => '/api/v1/services/{uuid}/rollback',
            'motivo' => 'Volver atrás en un despliegue puede dejar el cliente en una versión que él ya no usa.',
            'verificacion' => 'https://coolify.io/docs/api/endpoints/applications/rollback-application-by-uuid',
        ],
        [
            'metodo' => 'POST',
            'ruta' => '/api/v1/services/{uuid}/migrate',
            'motivo' => 'Migrar detiene el servicio y mueve datos entre servidores. Es una operación de riesgo alto con manejo manual.',
            'verificacion' => 'https://coolify.io/docs/api/endpoints/services/migrate-service-by-uuid',
        ],
        [
            'metodo' => 'GET',
            'ruta' => '/api/v1/services/{uuid}/logs',
            'motivo' => 'Los registros de un servicio pueden contener datos personales y claves. Exigen la habilidad read:sensitive, que este portal no pide.',
            'verificacion' => 'https://coolify.io/docs/api/endpoints/services/get-service-logs-by-uuid',
        ],
        [
            'metodo' => 'GET',
            'ruta' => '/api/v1/services/{uuid}/envs',
            'motivo' => 'Leer variables de entorno equivaldría a leer las credenciales del cliente. Es exactamente lo que este portal no debe poder hacer.',
            'verificacion' => 'https://coolify.io/docs/api/endpoints/services/list-envs-by-service-uuid',
        ],
        [
            'metodo' => 'POST',
            'ruta' => '/api/v1/services/{uuid}/envs',
            'motivo' => 'Escribir una variable de entorno obliga a transportar un secreto desde este portal hacia Coolify. Los secretos viven en Infisical, no en este sistema.',
            'verificacion' => 'https://coolify.io/docs/api/endpoints/services/create-env-by-service-uuid',
        ],
        [
            'metodo' => 'PUT',
            'ruta' => '/api/v1/services/{uuid}/storage-backup',
            'motivo' => 'Reemplazar la programación de respaldos de un volumen cambia cuándo y qué se copia. Es operación de Hefesto, no de onboarding.',
            'verificacion' => 'https://coolify.io/docs/api/endpoints/services/set-service-storage-backup-schedule',
        ],
        [
            'metodo' => 'POST',
            'ruta' => '/api/v1/private-keys',
            'motivo' => 'Una clave privada da acceso por SSH a un servidor. Este portal jamás la necesita para aprovisionar.',
            'verificacion' => 'https://coolify.io/docs/api/endpoints/private-keys',
        ],
        [
            'metodo' => 'POST',
            'ruta' => '/api/v1/cloud-tokens',
            'motivo' => 'Agregar un proveedor de nube amplía el alcance del portal hacia la cuenta de un tercero.',
            'verificacion' => 'https://coolify.io/docs/api/endpoints/cloud-tokens/create-cloud-token',
        ],
        [
            'metodo' => 'POST',
            'ruta' => '/api/v1/servers',
            'motivo' => 'Agregar un servidor remoto equivale a dar acceso desde el portal a una máquina ajena.',
            'verificacion' => 'https://coolify.io/docs/api/endpoints/servers',
        ],
        [
            'metodo' => 'GET',
            'ruta' => '/api/v1/teams/{uuid}/members',
            'motivo' => 'La lista de miembros es información interna de Hefesto, no del cliente.',
            'verificacion' => 'https://coolify.io/docs/api/endpoints/teams/get-members-by-team-id',
        ],
        [
            'metodo' => 'PATCH',
            'ruta' => '/api/v1/settings',
            'motivo' => 'La configuración de la instancia controla toda la plataforma. Cambiarla desde un portal de onboarding es un accidente esperando ocurrir.',
            'verificacion' => 'https://coolify.io/docs/api/endpoints/settings',
        ],
        [
            'metodo' => 'POST',
            'ruta' => '/api/v1/applications/public',
            'motivo' => 'Crear aplicaciones desde un repositorio exige además desplegar código arbitrario de terceros.',
            'verificacion' => 'https://coolify.io/docs/api/endpoints/applications/create-public-application',
        ],
        [
            'metodo' => 'POST',
            'ruta' => '/api/v1/databases/postgresql',
            'motivo' => 'Crear una base de datos exige elegir y transportar una contraseña. En esta versión esa contraseña la elige una persona de Hefesto, fuera del portal.',
            'verificacion' => 'https://coolify.io/docs/api/endpoints/databases/create-database-postgresql',
        ],
    ];

    /**
     * Palabras que delatan una operación destructiva en una ruta o un método.
     * Se usa como red de seguridad adicional en el cliente y en la prueba estática.
     *
     * @var array<int, string>
     */
    public const FRAGMENTOS_PELIGROSOS = [
        'delete',
        'destroy',
        'remove',
        'restart',
        'stop',
        'kill',
        'exec',
        'cancel',
        'rollback',
        'migrate',
        'clone',
        'logs',
        'envs',
        'members',
        'settings',
        'private-key',
    ];

    /**
     * ¿El método está prohibido en cualquier circumstance?
     */
    public static function metodoProhibido(string $metodo): bool
    {
        return in_array(strtoupper($metodo), ['DELETE', 'PUT', 'PATCH'], true);
    }

    /**
     * ¿La ruta contiene un fragmento que delata una operación fuera de la lista?
     *
     * Se evalúa sobre la ruta relativa a la base, en minúsculas.
     */
    public static function contieneFragmentoProhibido(string $ruta): bool
    {
        $ruta = mb_strtolower($ruta);

        foreach (self::FRAGMENTOS_PELIGROSOS as $fragmento) {
            if (str_contains($ruta, $fragmento)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Comprobación completa para una ruta relativa a `/api/v1`.
     *
     * Devuelve el motivo del rechazo, o null si la operación es aceptable.
     * Los métodos GET y POST nunca se rechazan por método, solo por fragmento.
     */
    public static function motivoDelRechazo(string $metodo, string $ruta): ?string
    {
        $metodo = strtoupper($metodo);

        if (self::metodoProhibido($metodo)) {
            return "El método {$metodo} no está permitido en este portal: solo se admiten lecturas y creaciones.";

        if (self::contieneFragmentoProhibido($ruta)) {
            return 'La ruta contiene un fragmento prohibido en este portal: ' . $ruta;
        }

        foreach (self::NEGADAS as $operacion) {
            if ($operacion['metodo'] === $metodo && self::coincideRuta($operacion['ruta'], $ruta)) {
                return $operacion['motivo'];
            }
        }

        return null;
    }

    /**
     * Comparación de plantilla: `{uuid}` hace juego con cualquier identificador.
     */
    private static function coincideRuta(string $plantilla, string $ruta): bool
    {
        $patron = '#^' . str_replace('\{uuid\}', '[0-9a-zA-Z-]+', preg_quote($plantilla, '#')) . '$#';

        return (bool) preg_match($patron, $ruta);
    }

    /**
     * Todas las operaciones negadas, para la documentación y para la pantalla de
     * revisión de la interfaz de administración.
     *
     * @return array<int, array{metodo: string, ruta: string, motivo: string, verificacion: string}>
     */
    public static function todas(): array
    {
        return self::NEGADAS;
    }
}