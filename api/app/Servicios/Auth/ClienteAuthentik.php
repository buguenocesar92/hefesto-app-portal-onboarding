<?php

declare(strict_types=1);

namespace App\Servicios\Auth;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cliente OIDC de Authentik.
 *
 * Hace exactamente tres llamadas, y ninguna de ellas sale del servidor:
 *
 *   1. `POST /application/o/token/`  con el código de autorización.
 *   2. `GET  /application/o/userinfo/` con el token de acceso.
 *   3. `POST /application/o/token/`  para renovar por código (authorization code flow).
 *
 * Las rutas están verificadas contra la documentación oficial de Authentik el
 * 17-10-2026. Ojo con el detalle que más se confunde: `authorize`, `token` y `userinfo`
 * son globales y NO llevan el identificador de la aplicación. Solo `end-session`,
 * `jwks` y la configuración OIDC lo llevan.
 *
 * EL SECRETO DEL CLIENTE NUNCA SALE DE AQUÍ. Se usa para firmar la petición al punto de
 * token y no se registra en ninguna parte: cuando falla una llamada, el mensaje dice
 * qué salió mal, no qué se mandó.
 */
final class ClienteAuthentik
{
    public function __construct(private readonly Factory $http)
    {
    }

    public static function desdeConfiguracion(?Factory $http = null): self
    {
        return new self($http ?? app(Factory::class));
    }

    /** URL de autorización. Es la única que se le da al navegador. */
    public function urlDeAutorizacion(string $estado, string $retoPkce): string
    {
        $parametros = [
            'client_id' => (string) config('authentik.cliente_id'),
            'redirect_uri' => (string) config('authentik.redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(' ', (array) config('authentik.alcances', ['openid'])),
            'state' => $estado,
            'code_challenge' => $retoPkce,
            'code_challenge_method' => 'S256',
        ];

        return $this->url() . '/application/o/authorize/?' . http_build_query($parametros);
    }

    /** Ruta de cierre de sesión en Authentik. Esta sí lleva el identificador. */
    public function urlDeSalida(?string $marcaEstado): ?string
    {
        $url = $this->url() . '/application/o/' . config('authentik.slug') . '/end-session/';

        if ($marcaEstado === null) {
            return null;
        }

        return $url . '?' . http_build_query([
            'post_logout_redirect_uri' => (string) config('app.url'),
            'state' => $marcaEstado,
        ]);
    }

    /**
     * Canjea el código de autorización por un token de acceso.
     *
     * @return array<string, mixed>
     */
    public function canjearCodigo(string $codigo, string $verificadorPkce): array
    {
        $respuesta = $this->enviarToken([
            'grant_type' => 'authorization_code',
            'code' => $codigo,
            'redirect_uri' => (string) config('authentik.redirect_uri'),
            'code_verifier' => $verificadorPkce,
        ]);

        return $this->cuerpoJson($respuesta, 'token');
    }

    /**
     * Consulta los datos del usuario al punto de información.
     *
     * @return array<string, mixed>
     */
    public function informacionDeUsuario(string $tokenAcceso): array
    {
        $respuesta = $this->http
            ->timeout((int) config('authentik.tiempo_espera', 15))
            ->acceptJson()
            ->withToken($tokenAcceso)
            ->send('GET', $this->url() . '/application/o/userinfo/');

        return $this->cuerpoJson($respuesta, 'userinfo');
    }

    /**
     * Formato de la identidad que espera el resolutor.
     *
     * @param  array<string, mixed>  $respuesta
     * @return array{sub: string, correo: string, correo_verificado: bool, nombre: ?string, grupos: list<string>}
     */
    public function normalizarIdentidad(array $respuesta): array
    {
        $sub = $respuesta['sub'] ?? null;

        if (! is_string($sub) || $sub === '') {
            throw new RuntimeException('Authentik no devolvió el identificador del usuario (sub).');
        }

        $correo = $respuesta['email'] ?? null;

        if (! is_string($correo) || $correo === '') {
            throw new RuntimeException(
                'Authentik no devolvió el correo. Revisa que el alcance "email" esté habilitado en el proveedor.'
            );
        }

        $grupos = $respuesta['groups'] ?? [];
        $grupos = is_array($grupos) ? array_values(array_filter($grupos, 'is_string')) : [];

        return [
            'sub' => $sub,
            'correo' => mb_strtolower(trim($correo)),
            // El claim se guarda pero no decide nada: ver ResolvedorIdentidad.
            'correo_verificado' => (bool) ($respuesta['email_verified'] ?? false),
            'nombre' => is_string($respuesta['name'] ?? null) ? $respuesta['name'] : null,
            'grupos' => $grupos,
        ];
    }

    /**
     * @param  array<string, string>  $parametros
     */
    private function enviarToken(array $parametros): mixed
    {
        $secreto = (string) config('authentik.cliente_secreto');

        if ($secreto === '') {
            throw new RuntimeException(
                'Falta AUTHENTIK_CLIENT_SECRET. Sin el secreto del cliente no se puede canjear el código.'
            );
        }

        try {
            return $this->http
                ->timeout((int) config('authentik.tiempo_espera', 15))
                ->acceptJson()
                ->asForm()
                ->withBasicAuth((string) config('authentik.cliente_id'), $secreto)
                ->send('POST', $this->url() . '/application/o/token/', [
                    'body' => $parametros,
                ]);
        } catch (ConnectionException $error) {
            // El mensaje de ConnectionException puede incluir la URL, nunca la cabecera
            // de autorización, pero se registra solo el motivo.
            Log::error('authentik.sin_conexion', ['motivo' => $error->getMessage()]);

            throw new RuntimeException('No hubo conexión con Authentik. Inténtalo de nuevo.', 0, $error);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function cuerpoJson(mixed $respuesta, string $paso): array
    {
        $estado = (int) $respuesta->status();

        if ($estado >= 400) {
            Log::warning('authentik.error', [
                'paso' => $paso,
                'estado_http' => $estado,
            ]);

            throw new RuntimeException(sprintf(
                'Authentik respondió %d en el paso "%s". Revisa el identificador del cliente, '
                . 'el secreto, la dirección de retorno y los alcances.',
                $estado,
                $paso,
            ));
        }

        $cuerpo = $respuesta->json();

        if (! is_array($cuerpo)) {
            throw new RuntimeException('Authentik devolvió una respuesta que no se puede leer como objeto.');
        }

        return $cuerpo;
    }

    private function url(): string
    {
        return rtrim((string) config('authentik.url'), '/');
    }
}