<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Servicios\Auth\ClienteAuthentik;
use App\Servicios\Auth\ResolvedorIdentidad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Acceso y salida del portal, con Authentik por OIDC.
 *
 * Flujo (authorization code con PKCE):
 *
 *   1. `GET /auth/entrar` genera una marca de estado y un reto PKCE, los guarda en la
 *      sesión y manda al navegador a Authentik.
 *   2. La persona se identifica en Authentik y vuelve a `/auth/callback` con el código.
 *   3. El servidor canjea el código en `/application/o/token/`, consulta
 *      `/application/o/userinfo/` y descarta el token de acceso.
 *   4. Se resuelve a qué empresa pertenece la identidad y se inicia la sesión.
 *
 * EL TOKEN DE ACCESO NO SE GUARDA Y NO SALE DEL SERVIDOR.
 *
 * El portal no necesita el token después de leer la información del usuario: lo usa una
 * vez y lo descarta. No va en la sesión, no va en la respuesta, no va a la configuración
 * pública de Nuxt. Lo único que viaja al navegador es una cookie de sesión, que no
 * contiene ningún dato de Authentik.
 */
class AuthController extends Controller
{
    private const CLAVE_ESTADO = 'authentik.estado';
    private const CLAVE_PKCE = 'authentik.pkce';
    private const CLAVE_DESTINO = 'authentik.destino';

    public function __construct(
        private readonly ClienteAuthentik $authentik,
        private readonly ResolvedorIdentidad $resolvedor,
    ) {
    }

    /**
     * Estado de la integración, para que la interfaz diga la verdad.
     */
    public function estado(): array
    {
        $listo = config('authentik.cliente_id') !== null
            && config('authentik.cliente_secreto') !== null
            && config('authentik.cliente_id') !== ''
            && config('authentik.cliente_secreto') !== '';

        return [
            'identidad' => 'authentik',
            'listo' => $listo,
            'mensaje' => $listo
                ? 'El acceso se hace con la cuenta corporativa de Hefesto.'
                : 'El acceso con Authentik no está configurado en este entorno. '
                    . 'Faltan AUTHENTIK_CLIENT_ID o AUTHENTIK_CLIENT_SECRET.',
        ];
    }

    public function entrar(Request $peticion): RedirectResponse
    {
        if (! $this->integracionLista()) {
            return back()->withErrors([
                'acceso' => 'El acceso con Authentik no está configurado en este entorno.',
            ]);
        }

        $estado = Str::random(64);

        // PKCE en S256: el reto viaja al navegador y el secreto se queda en el servidor.
        $verificador = Str::random(96);
        $reto = rtrim(strtr(base64_encode(hash('sha256', $verificador, true)), '+/', '-_'), '=');

        $peticion->session()->put(self::CLAVE_ESTADO, $estado);
        $peticion->session()->put(self::CLAVE_PKCE, $verificador);
        $peticion->session()->put(self::CLAVE_DESTINO, $peticion->query('destino'));

        return redirect()->away($this->authentik->urlDeAutorizacion($estado, $reto));
    }

    public function callback(Request $peticion): RedirectResponse
    {
        $estadoEsperado = (string) $peticion->session()->pull(self::CLAVE_ESTADO, '');
        $verificador = (string) $peticion->session()->pull(self::CLAVE_PKCE, '');
        $destino = $peticion->session()->pull(self::CLAVE_DESTINO);

        $estadoRecibido = (string) $peticion->query('state', '');

        if ($estadoEsperado === '' || ! hash_equals($estadoEsperado, $estadoRecibido)) {
            Log::warning('acceso.estado_invalido');

            return redirect('/')->withErrors([
                'acceso' => 'La solicitud de acceso no se pudo validar. Vuelve a intentarlo.',
            ]);
        }

        $error = $peticion->query('error');

        if (is_string($error) && $error !== '') {
            Log::warning('acceso.denegado_por_authentik', ['codigo' => $error]);

            return redirect('/')->withErrors([
                'acceso' => 'No se completó el acceso. Si el problema sigue, escribe a Hefesto.',
            ]);
        }

        $codigo = (string) $peticion->query('code', '');

        if ($codigo === '') {
            return redirect('/')->withErrors([
                'acceso' => 'Authentik no devolvió el código de acceso.',
            ]);
        }

        try {
            $token = $this->authentik->canjearCodigo($codigo, $verificador);
            $datosUsuario = $this->authentik->informacionDeUsuario((string) ($token['access_token'] ?? ''));
            $identidad = $this->authentik->normalizarIdentidad($datosUsuario);
            $usuario = $this->resolvedor->resolver($identidad);
        } catch (RuntimeException $error) {
            Log::warning('acceso.fallido', ['motivo' => $error->getMessage()]);

            return redirect('/')->withErrors(['acceso' => $error->getMessage()]);
        } finally {
            // El token de acceso existió solo durante el canje y la consulta de
            // información. Se descarta aquí explícitamente.
            unset($token);
        }

        $peticion->session()->regenerate();
        auth()->guard('web')->login($usuario, true);
        $peticion->session()->put('ultimo_acceso', now()->toIso8601String());

        return redirect()->to(is_string($destino) && $destino !== '' ? $destino : '/');
    }

    public function salir(Request $peticion): RedirectResponse
    {
        auth()->guard('web')->logout();
        $peticion->session()->invalidate();
        $peticion->session()->regenerateToken();

        $salida = $this->authentik->urlDeSalida(Str::random(32));

        return $salida === null ? redirect('/') : redirect()->away($salida);
    }

    /**
     * Whoami: qué sesión hay abierta. Devuelve lo mínimo, y nada de otras empresas.
     */
    public function quienSoy(Request $peticion): array
    {
        $usuario = $peticion->user();

        if (! $usuario instanceof Usuario) {
            return ['autenticado' => false];
        }

        return [
            'autenticado' => true,
            'nombre' => $usuario->nombre,
            'correo' => $usuario->email,
            'rol' => $usuario->rol->value,
            'es_hefesto' => $usuario->esHefesto(),
        ];
    }

    private function integracionLista(): bool
    {
        $cliente = config('authentik.cliente_id');
        $secreto = config('authentik.cliente_secreto');

        return is_string($cliente) && $cliente !== '' && is_string($secreto) && $secreto !== '';
    }
}