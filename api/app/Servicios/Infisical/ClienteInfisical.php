<?php

declare(strict_types=1);

namespace App\Servicios\Infisical;

use App\Servicios\Infisical\Excepcion\InfisicalNoDisponibleExcepcion;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Log;

/**
 * Entrega de credenciales a Infisical.
 *
 * Regla innegociable de este portal: una credencial de cliente NUNCA se escribe en la
 * base de datos de este sistema. Este portal guarda solo el dato de que la entrega
 * quedó hecha, en qué ruta de Infisical, y cuándo. El valor viaja de memoria a memoria:
 * se genera, se entrega y se descarta.
 *
 * ESTADO DE ESTA INTEGRACIÓN: DESHABILITADA.
 *
 * Lo que sí se verificó en la documentación oficial el 17 de octubre de 2026:
 *   - La base es https://<instancia>/api.
 *   - Cada endpoint tiene su propia versión en la ruta.
 *   - Cada instancia publica su especificación OpenAPI en /api/docs/json.
 *   - Existe autenticación con identidades de máquina (acceso universal).
 *
 * Lo que NO se pudo verificar: la ruta exacta de creación de un secreto y la cabecera
 * de autenticación. La página del endpoint no respondió durante la sesión.
 *
 * Por eso, en vez de escribir una ruta inventada que fallaría en producción con un 404
 * misterioso, este cliente se niega a correr. Ver docs/04 y docs/10.
 */
final class ClienteInfisical
{
    public function __construct(
        private readonly Factory $http,
        private readonly ?string $token,
        private readonly string $urlBase,
        private readonly bool $habilitada,
        private readonly bool $rutaVerificada,
        private readonly string $rutaCrear,
    ) {
    }

    public static function desdeConfiguracion(?Factory $http = null): self
    {
        $token = config('infisical.token');

        return new self(
            http: $http ?? app(Factory::class),
            token: is_string($token) && $token !== '' ? $token : null,
            urlBase: rtrim((string) config('infisical.url_base'), '/'),
            habilitada: (bool) config('infisical.habilitada'),
            rutaVerificada: (bool) config('infisical.ruta_verificada'),
            rutaCrear: (string) config('infisical.ruta_crear_secreto'),
        );
    }

    /**
     * Si la integración puede usarse. La interfaz muestra el estado real, no un "todo
     * bien" simulado.
     */
    public function disponible(): bool
    {
        return $this->habilitada
            && $this->rutaVerificada
            && $this->token !== null;
    }

    public function motivoDeNoDisponibilidad(): string
    {
        if (! $this->habilitada) {
            return 'La entrega de credenciales está apagada (INFISICAL_HABILITADA=false).';
        }

        if (! $this->rutaVerificada) {
            return 'Falta confirmar la ruta de creación de secretos contra el OpenAPI de la instancia (INFISICAL_RUTA_VERIFICADA=false).';
        }

        if ($this->token === null) {
            return 'Falta la credencial de la identidad de máquina (INFISICAL_TOKEN).';
        }

        return 'Disponible.';
    }

    /**
     * Registra un secreto nuevo en la ruta indicada dentro de Infisical.
     *
     * @param  array<string, string>  $valores
     * @return array<string, mixed> Datos de la entrega, sin el valor del secreto.
     */
    public function registrarSecreto(string $rutaSecreto, array $valores, string $descripcion = ''): array
    {
        if (! $this->disponible()) {
            throw new InfisicalNoDisponibleExcepcion($this->motivoDeNoDisponibilidad());
        }

        $cuerpo = [
            'secretPath' => $rutaSecreto,
            'projectId' => config('infisical.proyecto'),
            'environment' => config('infisical.entorno'),
            'secretKey' => array_key_first($valores),
            'secretValue' => $valores[array_key_first($valores)],
            'description' => $descripcion,
        ];

        try {
            $respuesta = $this->http
                ->timeout((int) config('infisical.tiempo_espera', 15))
                ->acceptJson()
                ->withToken($this->token)
                ->withBody(json_encode($cuerpo, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'application/json')
                ->send('POST', $this->urlBase . $this->rutaCrear);
        } catch (ConnectionException) {
            throw new InfisicalNoDisponibleExcepcion('No hubo conexión con la instancia de Infisical.');
        }

        // El registro guarda metadatos. El valor del secreto no se escribe en ningún lado.
        Log::info('infisical.secreto_registrado', [
            'ruta_secreto' => $rutaSecreto,
            'clave' => $cuerpo['secretKey'],
            'estado_http' => $respuesta->status(),
            'proyecto' => $cuerpo['projectId'],
            'entorno' => $cuerpo['environment'],
        ]);

        if ($respuesta->status() >= 400) {
            throw new InfisicalNoDisponibleExcepcion(sprintf(
                'Infisical respondió con estado %d al registrar el secreto.',
                $respuesta->status(),
            ));
        }

        return [
            'ruta_secreto' => $rutaSecreto,
            'clave' => $cuerpo['secretKey'],
            'proyecto' => $cuerpo['projectId'],
            'entorno' => $cuerpo['environment'],
            'registrado_en' => now()->toIso8601String(),
        ];
    }

    /**
     * Ruta donde queda la credencial de una empresa, derivada de su identificador.
     * Usa el identificador interno y no el nombre comercial, para que un cambio de
     * razón social no roma la referencia.
     */
    public function rutaParaEmpresa(int $empresaId, string $nombreCorto): string
    {
        $carpeta = trim((string) config('infisical.carpeta_raiz'), '/');
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $nombreCorto) ?? 'empresa');
        $slug = trim($slug, '-');

        return sprintf('%s/%06d-%s', $carpeta, $empresaId, $slug !== '' ? $slug : 'empresa');
    }
}