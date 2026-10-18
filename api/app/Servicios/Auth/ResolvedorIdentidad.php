<?php

declare(strict_types=1);

namespace App\Servicios\Auth;

use App\Enums\Rol;
use App\Models\Empresa;
use App\Models\Usuario;
use App\Servicios\Tenancy\ContextoEmpresa;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Resuelve a qué empresa (o si es personal de Hefesto) pertenece la identidad que entró
 * por Authentik, y devuelve el usuario local del portal.
 *
 * REGLAS, EN ESTE ORDEN
 * ---------------------
 * 1. Si el correo está en la lista de personal de Hefesto (`AUTENTIK_CORREOS_HFESTO`) o
 *    su dominio está en `AUTENTIK_DOMINIOS_HFESTO`, la persona entra con contexto de
 *    Hefesto. No pertenece a ninguna empresa y ve todas las solicitudes: es quien
 *    aprueba.
 * 2. Si no, se resuelve la empresa por DOMINIO DE CORREO: cada empresa declara el
 *    dominio de sus contactos (`dominio_correo`, único en toda la instalación).
 * 3. Si no hay empresa para ese dominio, el acceso se rechaza y queda registrado.
 *
 * POR QUÉ NO SE CONFÍA EN EL CORREO PARA EL ROL
 * ---------------------------------------------
 * Una identidad de Authentik habilita el acceso a la identidad, no al rol de negocio. El
 * rol lo decide Hefesto dentro del portal. El grupo de Authentik solo sirve para el
 * PRIMER alta de una persona del equipo: después manda el rol guardado aquí.
 *
 * La tabla `empresas` NO tiene seguridad de fila a propósito, porque aquí es donde se
 * resuelve la empresa, todavía sin sesión y por lo tanto sin contexto que poner. Desde
 * ese momento en adelante, todo lo demás sí está protegido por RLS.
 */
final class ResolvedorIdentidad
{
    public function __construct(private readonly ContextoEmpresa $contexto)
    {
    }

    /**
     * @param  array{sub: string, correo: string, correo_verificado: bool, nombre: ?string, grupos: list<string>}  $identidad
     */
    public function resolver(array $identidad): Usuario
    {
        $correo = Str::lower(trim($identidad['correo']));

        if ($this->esPersonalDeHefesto($correo)) {
            return $this->contexto->ejecutarAisladoComoHefesto(
                fn (): Usuario => $this->obtenerOCrearUsuario(null, $identidad, $correo)
            );
        }

        $empresa = $this->empresaPorDominio($this->dominioDe($correo));

        if ($empresa === null) {
            Log::warning('acceso.empresa_no_resuelta', [
                'dominio' => $this->dominioDe($correo),
            ]);

            throw new RuntimeException(
                'Esta cuenta no pertenece a ninguna empresa con acceso al portal. '
                . 'Contacta a Hefesto para que den de alta a tu empresa.'
            );
        }

        return $this->contexto->ejecutarAislado(
            (int) $empresa->id,
            fn (): Usuario => $this->obtenerOCrearUsuario($empresa, $identidad, $correo)
        );
    }

    /**
     * ¿Es una identidad del equipo de Hefesto?
     *
     * La lista es configuración, no una tabla. Es deliberado: son pocas personas y la
     * lista tiene que ser revisable en el despliegue, con un cambio de archivo y no con
     * un `INSERT` que nadie recuerda.
     */
    public function esPersonalDeHefesto(string $correo): bool
    {
        $correos = array_map('mb_strtolower', (array) config('authentik.correos_hefesto', []));
        $dominios = array_map('mb_strtolower', (array) config('authentik.dominios_hefesto', []));

        if (in_array($correo, $correos, true)) {
            return true;
        }

        $dominio = $this->dominioDe($correo);

        return $dominio !== '' && in_array($dominio, $dominios, true);
    }

    /**
     * Busca (o crea) el usuario local. Corre dentro del contexto ya establecido.
     */
    private function obtenerOCrearUsuario(?Empresa $empresa, array $identidad, string $correo): Usuario
    {
        $usuario = Usuario::query()->where('authentik_subject', $identidad['sub'])->first();

        if ($usuario !== null) {
            $this->sincronizar($usuario, $identidad, $correo);

            return $usuario;
        }

        $usuario = Usuario::create([
            'empresa_id' => $empresa?->id,
            'nombre' => $this->nombreDe($identidad, $correo),
            'email' => $correo,
            'rol' => $this->rolInicial($empresa, $identidad['grupos']),
            'authentik_subject' => $identidad['sub'],
            'ultimo_acceso' => now(),
        ]);

        Log::info('acceso.alta_de_usuario', [
            'usuario_id' => $usuario->id,
            'empresa_id' => $usuario->empresa_id,
            'rol' => $usuario->rol->value,
        ]);

        return $usuario;
    }

    /**
     * Refresca los datos que Authentik es la fuente de verdad. EL ROL NO SE TOCA: lo
     * administra Hefesto desde el portal, y cambiarlo en cada entrada dejaría que
     * cualquiera se autoasignara el rol.
     */
    private function sincronizar(Usuario $usuario, array $identidad, string $correo): void
    {
        $usuario->forceFill([
            'email' => $correo,
            'ultimo_acceso' => now(),
        ])->save();
    }

    /**
     * Primer alta: el personal de Hefesto que no tiene empresa nace como aprobador si
     * pertenece al grupo configurado; en cualquier otro caso, como cliente.
     */
    private function rolInicial(?Empresa $empresa, array $grupos): Rol
    {
        if ($empresa === null) {
            $grupo = (string) config('authentik.grupo_aprobadores');

            return in_array($grupo, $grupos, true) ? Rol::Aprobador : Rol::Administrador;
        }

        return Rol::Cliente;
    }

    private function empresaPorDominio(string $dominio): ?Empresa
    {
        if ($dominio === '') {
            return null;
        }

        return Empresa::query()
            ->where('dominio_correo', $dominio)
            ->where('estado', 'activa')
            ->first();
    }

    private function dominioDe(string $correo): string
    {
        $posicion = strrpos($correo, '@');

        return $posicion === false ? '' : Str::lower(substr($correo, $posicion + 1));
    }

    private function nombreDe(array $identidad, string $correo): string
    {
        $nombre = trim((string) ($identidad['nombre'] ?? ''));

        if ($nombre !== '') {
            return $nombre;
        }

        return Str::before($correo, '@');
    }
}