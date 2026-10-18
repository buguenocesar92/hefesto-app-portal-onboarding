<?php

declare(strict_types=1);

namespace App\Servicios\Coolify;

/**
 * Credencial de la API de Coolify.
 *
 * Existe como objeto y no como cadena suelta por dos razones concretas:
 *
 * 1. No se puede imprimir por accidente. __debugInfo() hace que un var_dump, una
 *    excepción o un volcado en consola muestren la huella, nunca el valor.
 * 2. Se puede pasar por referencia sin que nadie la clone o la concatene.
 *
 * La huella sirve para auditoría: permite saber en los registros qué credencial se usó
 * sin revelar nada. Es un resumen SHA-256 recortado, y no sirve para autenticarse.
 */
final class TokenCoolify
{
    private function __construct(
        private readonly string $valor,
        private readonly string $origen,
    ) {
    }

    /**
     * Construye el token desde la configuración. Si no hay token, devuelve null: el
     * portal puede funcionar en modo deshabilitado sin credencial de Coolify cargada.
     */
    public static function desdeConfiguracion(string $clave = 'token'): ?self
    {
        $valor = config("coolify.{$clave}");

        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        return new self(trim($valor), $clave);
    }

    /** Construye el token directamente. Pensado para las pruebas. */
    public static function dePrueba(string $valor): self
    {
        return new self($valor, 'prueba');
    }

    /**
     * Marcador para cuando no hay credencial configurada.
     *
     * Existe para que el portal pueda funcionar en modo deshabilitado sin cargar un
     * token de Coolify: se recorre el flujo completo, se deja registro y no sale
     * ninguna petición. El valor vacío no se usa nunca, porque toda operación que
     * necesite credencial se rechaza antes por el interruptor general.
     */
    public static function sinConfigurar(): self
    {
        return new self('', 'sin-configurar');
    }

    public function hayValor(): bool
    {
        return $this->valor !== '';
    }

    /** El valor real. Solo lo llama ClienteCoolify, y solo para la cabecera. */
    public function valor(): string
    {
        return $this->valor;
    }

    /** De qué configuración viene, para el registro de auditoría. */
    public function origen(): string
    {
        return $this->origen;
    }

    /**
     * Huella de la credencial: los primeros diez caracteres del resumen SHA-256.
     * Permite distinguir "la credencial del equipo antiguo" de "la nueva" en un registro,
     * sin exponer el secreto.
     */
    public function huella(): string
    {
        return substr(hash('sha256', $this->valor), 0, 10);
    }

    /** Nunca revela el valor. */
    public function __debugInfo(): array
    {
        return [
            'valor' => '[oculto]',
            'huella' => $this->huella(),
            'origen' => $this->origen,
        ];
    }

    /** Representación textual segura, para concatenaciones accidentales. */
    public function __toString(): string
    {
        return '[credencial de Coolify oculta]';
    }
}