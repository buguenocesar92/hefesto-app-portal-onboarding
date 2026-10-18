<?php

declare(strict_types=1);

namespace App\Servicios\Coolify;

use App\Enums\OperacionCoolify;
use App\Enums\OperacionesProhibidas;
use App\Servicios\Coolify\Excepcion\OperacionDeshabilitadaExcepcion;
use App\Servicios\Coolify\Excepcion\OperacionProhibidaExcepcion;
use App\Servicios\Coolify\Excepcion\RespuestaInesperadaExcepcion;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Único punto del portal que habla con la API de Coolify.
 *
 * Tres propiedades, y las tres importan más que cualquier otra cosa del archivo:
 *
 * 1. NO ES UN PROXY. No existe ningún método público que reciba una URL, un método o
 *    un cuerpo arbitrario. La firma es ejecutar(OperacionCoolify, ...): el método HTTP
 *    y la ruta salen del enum App\Enums\OperacionCoolify, que es código revisable.
 *    Para agregar una operación hay que tocar ese enum a propósito.
 *
 * 2. LA CREDENCIAL NO SALE. Solo se usa para la cabecera Authorization, en memoria, en
 *    el servidor. No se registra, no se devuelve y no se serializa. Los registros
 *    guardan la huella del token, no el token.
 *
 * 3. LAS OPERACIONES DESTRUCTIVAS NO SE PUEDEN PEDIR. Antes de salir, cada llamada pasa
 *    por App\Enums\OperacionesProhibidas y por el patrón de ruta de la operación.
 *
 * Verificado contra la documentación oficial el 17 de octubre de 2026.
 */
final class ClienteCoolify
{
    private const ESPERA_SIN_CREDENCIAL = '/health';

    public function __construct(
        private readonly Factory $http,
        private readonly TokenCoolify $token,
        private readonly string $urlBase,
        private readonly bool $habilitada,
        private readonly int $timeout = 20,
        private readonly int $reintentos = 2,
    ) {
    }

    /**
     * Instancia lista para usar, construida desde el contenedor de dependencias.
     *
     * Si no hay credencial configurada, devuelve un cliente sin token y con la
     * integración apagada, en lugar de lanzar una excepción. Así el portal puede
     * instalarse y recorrerse sin tener la credencial de la plataforma de despliegue:
     * toda operación que la necesite se rechaza antes, con un motivo explícito.
     */
    public static function desdeConfiguracion(?Factory $http = null): self
    {
        $token = TokenCoolify::desdeConfiguracion('token') ?? TokenCoolify::sinConfigurar();

        $http ??= app(Factory::class);

        return new self(
            http: $http,
            token: $token,
            urlBase: rtrim((string) config('coolify.url_base'), '/'),
            habilitada: (bool) config('coolify.habilitada') && $token->hayValor(),
            timeout: (int) config('coolify.timeout', 20),
            reintentos: (int) config('coolify.reintentos', 2),
        );
    }

    /**
     * Ejecuta una operación de la lista permitida.
     *
     * @param  array<string, mixed>  $cuerpo  Solo se usa en las operaciones de creación.
     * @param  string|null  $uuid  Identificador del recurso, cuando la ruta lo pide.
     */
    public function ejecutar(
        OperacionCoolify $operacion,
        array $cuerpo = [],
        ?string $uuid = null,
        array $consulta = [],
    ): RespuestaCoolify {
        $ruta = $this->resolverRuta($operacion, $uuid);

        $this->verificarPolitica($operacion, $ruta);
        $this->verificarEstadoDeLaIntegracion($operacion);

        $token = $this->tokenParaLaOperacion($operacion);
        $url = $this->urlBase . $ruta;

        $peticion = $this->http
            ->timeout($this->timeout)
            ->acceptJson()
            ->withHeaders($this->encabezados($operacion, $token));

        if ($operacion->metodo() === 'POST') {
            $peticion = $peticion->withBody($this->cuerpoJson($cuerpo), 'application/json');
        }

        if ($consulta !== []) {
            $peticion = $peticion->withQueryParameters($consulta);
        }

        $respuesta = $this->enviarConReintentos($peticion, $operacion, $ruta, $url);

        $this->registrarIntento($operacion, $ruta, $respuesta->estado(), $respuesta->duracionMs);

        if (! $respuesta->exitosa()) {
            throw new RespuestaInesperadaExcepcion(
                operacion: $operacion->value,
                estado: $respuesta->estado(),
                mensaje: $respuesta->texto(),
            );
        }

        return $respuesta;
    }

    /**
     * Resuelve la plantilla de ruta de la operación.
     *
     * El identificador se valida como UUID antes de interpolarse. Si no lo es, la
     * petición no sale: así nadie puede meter `../` o un signo de interrogación para
     * alcanzar otra operación del mismo servidor.
     */
    private function resolverRuta(OperacionCoolify $operacion, ?string $uuid): string
    {
        $plantilla = $operacion->ruta();

        if (! str_contains($plantilla, '{uuid}')) {
            return $plantilla;
        }

        if ($uuid === null || ! Str::isUuid($uuid)) {
            throw new OperacionProhibidaExcepcion(
                metodo: $operacion->metodo(),
                ruta: $plantilla,
                motivo: 'La operación exige un identificador con formato UUID y el recibido no lo cumple.',
            );
        }

        return str_replace('{uuid}', $uuid, $plantilla);
    }

    /**
     * Barrido de la política. Es la barrera que se opone a una salida de la lista.
     */
    private function verificarPolitica(OperacionCoolify $operacion, string $ruta): void
    {
        $motivo = OperacionesProhibidas::motivoDelRechazo($operacion->metodo(), $ruta);

        if ($motivo !== null) {
            throw OperacionProhibidaExcepcion::deOperacion($operacion, $motivo);
        }

        if (preg_match($operacion->patronRuta(), $ruta) !== 1) {
            throw OperacionProhibidaExcepcion::deOperacion(
                $operacion,
                'La ruta resuelta no corresponde al patrón declarado por la operación.',
            );
        }
    }

    /**
     * Comprueba el interruptor general y el particular de la operación.
     */
    private function verificarEstadoDeLaIntegracion(OperacionCoolify $operacion): void
    {
        if (! $this->habilitada && $operacion->ruta() !== self::ESPERA_SIN_CREDENCIAL) {
            throw new OperacionDeshabilitadaExcepcion(
                $operacion,
                'La integración con Coolify está apagada (COOLIFY_HABILITADA=false). Nada se ejecutó en la plataforma.',
            );
        }

        if ($operacion->usaCredencialDeDespliegue() && ! config('coolify.permitir_despliegue')) {
            throw new OperacionDeshabilitadaExcepcion(
                $operacion,
                'Disparar despliegues está en una fase posterior (COOLIFY_PERMITIR_DESPLIEGUE=false).',
            );
        }
    }

    /**
     * Elige la credencial. El despliegue usa su propio token porque la habilidad
     * `deploy` también habilita reiniciar, detener y cancelar.
     */
    private function tokenParaLaOperacion(OperacionCoolify $operacion): TokenCoolify
    {
        if ($operacion->usaCredencialDeDespliegue()) {
            $tokenDespliegue = TokenCoolify::desdeConfiguracion('token_despliegue');

            if (! $tokenDespliegue instanceof TokenCoolify) {
                throw new OperacionDeshabilitadaExcepcion(
                    $operacion,
                    'Falta COOLIFY_TOKEN_DESPLIEGUE. Sin credencial separada no se dispara ningún despliegue.',
                );
            }

            return $tokenDespliegue;
        }

        return $this->token;
    }

    /**
     * @return array<string, string>
     */
    private function encabezados(OperacionCoolify $operacion, TokenCoolify $token): array
    {
        // La comprobación de salud es la única llamada sin credencial.
        if ($operacion->ruta() === self::ESPERA_SIN_CREDENCIAL) {
            return [];
        }

        return [
            'Authorization' => 'Bearer ' . $token->valor(),
        ];
    }

    /**
     * @param  array<string, mixed>  $cuerpo
     */
    private function cuerpoJson(array $cuerpo): string
    {
        if ($cuerpo === []) {
            return '{}';
        }

        return json_encode($cuerpo, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Envía la petición y reintenta solo ante fallos de red o límite de peticiones.
     *
     * Nunca reintenta un 4xx que no sea 429: si Coolify dice que el cuerpo es inválido,
     * repetir lo mismo sería gastar el límite sin chances.
     */
    private function enviarConReintentos(
        PendingRequest $peticion,
        OperacionCoolify $operacion,
        string $ruta,
        string $url,
    ): RespuestaCoolify {
        $intento = 0;
        $espera = (int) config('coolify.espera_entre_reintentos', 3);

        while (true) {
            $intento++;

            try {
                $inicio = microtime(true);
                $cruda = $peticion->send($operacion->metodo(), $url);
                $duracion = (microtime(true) - $inicio) * 1000;

                $respuesta = new RespuestaCoolify(
                    estado: $cruda->status(),
                    cuerpo: $cruda->json() ?? [],
                    encabezados: array_change_key_case($cruda->headers(), CASE_LOWER),
                    duracionMs: $duracion,
                );

                if ($respuesta->estado() === 429 && $intento <= $this->reintentos) {
                    $this->esperarAntesDeReintentar($respuesta, $espera, $intento);

                    continue;
                }

                return $respuesta;
            } catch (ConnectionException $error) {
                if ($intento > $this->reintentos) {
                    throw new RespuestaInesperadaExcepcion(
                        operacion: $operacion->value,
                        estado: 0,
                        mensaje: 'No hubo conexión con la instancia de Coolify.',
                    );
                }

                $this->esperarAntesDeReintentar(null, $espera, $intento);
            }
        }
    }

    /**
     * Espera antes de repetir, respetando el valor de Retry-After que indica la
     * documentación oficial de Coolify.
     */
    private function esperarAntesDeReintentar(?RespuestaCoolify $respuesta, int $espera, int $intento): void
    {
        $segundos = $respuesta?->esperaSugerida() ?? ($espera * $intento);

        // Un salto aleatorio evita que varias tareas repitan a la vez después de un 429.
        $segundos += random_int(0, 2);

        Log::warning('coolify.limite_peticiones', [
            'intento' => $intento,
            'espera_segundos' => $segundos,
        ]);

        sleep($segundos);
    }

    /**
     * Registro de auditoría. Solo metadatos: método, ruta, estado, duración y la huella
     * de la credencial. Nunca la credencial, nunca el cuerpo de la petición.
     */
    private function registrarIntento(
        OperacionCoolify $operacion,
        string $ruta,
        int $estado,
        float $duracionMs,
    ): void {
        Log::info('coolify.operacion', [
            'operacion' => $operacion->value,
            'metodo' => $operacion->metodo(),
            'ruta' => $ruta,
            'estado_http' => $estado,
            'duracion_ms' => round($duracionMs, 1),
            'credencial' => $this->token->huella(),
            'origen_credencial' => $this->token->origen(),
        ]);
    }
}