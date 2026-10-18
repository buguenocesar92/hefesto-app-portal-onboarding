<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lista ACOTADA y FIJA de las operaciones de Coolify que este portal puede ejecutar.
 *
 * Razón de existir: la API de Coolify se autentica con una credencial que administra
 * TODA la plataforma de despliegue de Hefesto. Si el portal pudiera reenviar peticiones
 * arbitrarias, quien llegue a un endpoint propio del portal tendría, en la práctica, la
 * misma potencia que un administrador de Coolify: borrar la infraestructura de un cliente,
 * detener sus servicios o leer sus secretos.
 *
 * Por eso el cliente HTTP de Coolify (App\Servicios\Coolify\ClienteCoolify) no acepta
 * ni una URL ni un método desde fuera: recibe uno de estos casos. No existe ninguna forma
 * de pasar una ruta arbitraria, y agregar una operación nueva obliga a tocar este archivo,
 * lo que la deja visible en la revisión de código.
 *
 * Todos los datos de esta tabla se verificaron contra la documentación oficial el
 * 17 de octubre de 2026:
 *   https://coolify.io/docs/api/overview
 *   https://coolify.io/docs/api/authorization
 *   https://coolify.io/docs/api/permissions
 *   https://coolify.io/docs/api/endpoints/<seccion>/<operacion>
 *
 * Coolify es software libre bajo licencia Apache-2.0 y autoalojado, sin límites de
 * sitios ni de servidores.
 */
enum OperacionCoolify: string
{
    /** Comprobación de vida de la instancia. No exige credencial. */
    case Salud = 'salud';

    /** Devuelve el equipo al que está ligado el token. No recibe identificadores. */
    case EquipoAutenticado = 'equipo_autenticado';

    /** Crea un proyecto nuevo, que es la unidad de aislamiento por cliente. */
    case CrearProyecto = 'crear_proyecto';

    /** Lee un proyecto existente para confirmar que quedó creado. */
    case LeerProyecto = 'leer_proyecto';

    /** Crea un servicio de un solo clic o personalizado dentro de un proyecto. */
    case CrearServicio = 'crear_servicio';

    /** Lee el estado de un servicio. */
    case LeerServicio = 'leer_servicio';

    /** Lee el resultado de un despliegue. */
    case LeerDespliegue = 'leer_despliegue';

    /**
     * Dispara un despliegue de un recurso ya creado.
     *
     * Está DESHABILITADA por defecto y usa una credencial separada, porque la habilidad
     * `deploy` de Coolify también habilita reiniciar, detener y cancelar. Ver
     * docs/04-INTEGRACION-COOLIFY.md.
     */
    case Desplegar = 'desplegar';

    /**
     * Método HTTP. Solo aparecen GET y POST: ninguna operación permitida borra,
     * detiene ni reinicia nada.
     */
    public function metodo(): string
    {
        return match ($this) {
            self::Salud,
            self::EquipoAutenticado,
            self::LeerProyecto,
            self::LeerServicio,
            self::LeerDespliegue => 'GET',
            self::CrearProyecto,
            self::CrearServicio,
            self::Desplegar => 'POST',
        };
    }

    /**
     * Ruta plantilla relativa a la base `/api/v1`. El único marcador admitido es {uuid}.
     */
    public function ruta(): string
    {
        return match ($this) {
            self::Salud => '/health',
            self::EquipoAutenticado => '/team',
            self::CrearProyecto => '/projects',
            self::LeerProyecto => '/projects/{uuid}',
            self::CrearServicio => '/services',
            self::LeerServicio => '/services/{uuid}',
            self::LeerDespliegue => '/deployments/{uuid}',
            self::Desplegar => '/deploy',
        };
    }

    /**
     * Habilidad que el token de Coolify debe tener. Esta columna es la que hace auditable
     * por qué credencial pedir en el panel de Coolify.
     */
    public function habilidadRequerida(): string
    {
        return match ($this) {
            self::Salud => 'ninguna',
            self::EquipoAutenticado,
            self::LeerProyecto,
            self::LeerServicio,
            self::LeerDespliegue => 'read',
            self::CrearProyecto,
            self::CrearServicio => 'write',
            self::Desplegar => 'deploy',
        };
    }

    /** Si la operación modifica el estado de la plataforma de despliegue. */
    public function esEscritura(): bool
    {
        return in_array($this, [self::CrearProyecto, self::CrearServicio, self::Desplegar], true);
    }

    /** Toda escritura exige aprobación humana previa, sin excepción. */
    public function exigeAprobacionHumana(): bool
    {
        return $this->esEscritura();
    }

    /** Si la operación debe ejecutarse con la credencial de despliegue, separada. */
    public function usaCredencialDeDespliegue(): bool
    {
        return $this === self::Desplegar;
    }

    /**
     * Por qué se permite. Se lee en la pantalla de aprobación para que la persona que
     * aprueba vea la justificación, no solo un identificador.
     */
    public function porquePermitida(): string
    {
        return match ($this) {
            self::Salud => 'Verificar que la instancia responde antes de intentar aprovisionar.',
            self::EquipoAutenticado => 'Confirmar que la credencial sigue vigente y sigue ligada al equipo de Hefesto.',
            self::CrearProyecto => 'Un proyecto nuevo por cliente es la única forma de que Coolify no mezcle sus recursos con los de otro.',
            self::LeerProyecto => 'Confirmar que el proyecto quedó creado y leer su UUID, sin modificar nada.',
            self::CrearServicio => 'Crear el servicio del kit contratado es el acto único de aprovisionamiento que este portal ejecuta.',
            self::LeerServicio => 'Mostrar al cliente en qué estado quedó su servicio, en solo lectura.',
            self::LeerDespliegue => 'Leer el resultado de un despliegue para avanzar el seguimiento, en solo lectura.',
            self::Desplegar => 'Poner en marcha lo que se creó. Exige credencial separada y está deshabilitada por defecto.',
        };
    }

    /** Dirección de la documentación oficial donde se verificó esta operación. */
    public function documentacion(): string
    {
        return match ($this) {
            self::Salud => 'https://coolify.io/docs/api/overview',
            self::EquipoAutenticado => 'https://coolify.io/docs/api/endpoints/teams/get-token-team',
            self::CrearProyecto => 'https://coolify.io/docs/api/endpoints/projects/create-project',
            self::LeerProyecto => 'https://coolify.io/docs/api/endpoints/projects/get-project-by-uuid',
            self::CrearServicio => 'https://coolify.io/docs/api/endpoints/services/create-service',
            self::LeerServicio => 'https://coolify.io/docs/api/endpoints/services/get-service-by-uuid',
            self::LeerDespliegue => 'https://coolify.io/docs/api/endpoints/deployments/get-deployment-by-uuid',
            self::Desplegar => 'https://coolify.io/docs/api/endpoints/deployments/deploy-by-tag-or-uuid',
        };
    }

    /**
     * Patrón que la ruta final DEBE cumplir una vez resueltos los identificadores.
     * Es la barrera que impide que un identificador con barras o puntos salvage el
     * prefijo de la ruta y alcance otra operación.
     */
    public function patronRuta(): string
    {
        $uuid = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

        return match ($this) {
            self::Salud => '#^/health$#',
            self::EquipoAutenticado => '#^/team$#',
            self::CrearProyecto,
            self::CrearServicio,
            self::Desplegar => '#^/[a-z]+$#',
            self::LeerProyecto,
            self::LeerServicio,
            self::LeerDespliegue => '#^/[a-z]+/' . $uuid . '$#',
        };
    }

    /** Etiqueta legible para la interfaz de aprobación. */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Salud => 'Comprobar disponibilidad de Coolify',
            self::EquipoAutenticado => 'Verificar el equipo de la credencial',
            self::CrearProyecto => 'Crear el proyecto del cliente',
            self::LeerProyecto => 'Leer el proyecto creado',
            self::CrearServicio => 'Crear el servicio contratado',
            self::LeerServicio => 'Leer el estado del servicio',
            self::LeerDespliegue => 'Leer el resultado del despliegue',
            self::Desplegar => 'Disparar el despliegue',
        };
    }

    /**
     * Todas las operaciones permitidas, para pintarlas en la documentación y en la
     * pantalla de revisión.
     *
     * @return array<int, self>
     */
    public static function permitidas(): array
    {
        return self::cases();
    }

    /**
     * Operaciones que escriben. Es lo que un humano tiene que aprobar.
     *
     * @return array<int, self>
     */
    public static function deEscritura(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $operacion): bool => $operacion->esEscritura(),
        ));
    }
}