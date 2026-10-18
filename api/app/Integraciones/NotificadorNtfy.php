<?php

declare(strict_types=1);

namespace App\Integraciones;

use App\Models\Empresa;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Log;

/**
 * Avisos de estado al cliente por ntfy.
 *
 * Verificado el 17 de octubre de 2026 en https://docs.ntfy.sh/publish/:
 * publicar es un POST a https://<servidor>/<tema> con el texto del aviso como cuerpo
 * y las cabeceras Title, Priority y Tags.
 *
 * Detalle de seguridad que no es negociable: el tema es la contraseña. No se usa el
 * identificador de la empresa ni su nombre comercial, porque cualquiera que lo adivine
 * podría suscribirse y leer los avisos de otro cliente, e incluso publicar con el
 * nombre de Hefesto. Por eso el tema se genera al azar y en la base solo queda su
 * resumen criptográfico.
 */
final class NotificadorNtfy
{
    public function __construct(
        private readonly Factory $http,
        private readonly string $url,
        private readonly bool $habilitado,
    ) {
    }

    public static function desdeConfiguracion(?Factory $http = null): self
    {
        return new self(
            http: $http ?? app(Factory::class),
            url: rtrim((string) config('ntfy.url'), '/'),
            habilitado: (bool) config('ntfy.habilitado'),
        );
    }

    /**
     * Genera un tema nuevo, aleatorio y no adivinable.
     * Se guarda el valor y su resumen; el resumen es lo que permite comparar sin
     * guardar el secreto dos veces.
     *
     * @return array{tema: string, huella: string}
     */
    public static function generarTema(): array
    {
        $tema = 'hefesto-' . bin2hex(random_bytes(16));

        return [
            'tema' => $tema,
            'huella' => hash('sha256', $tema),
        ];
    }

    public function habilitado(): bool
    {
        return $this->habilitado;
    }

    /**
     * Publica un aviso de estado.
     *
     * Nunca lanza: que ntfy esté caído no puede tumbar un aprovisionamiento que sí
     * terminó bien. El error se registra y el aviso se pierde.
     */
    public function avisar(
        Empresa $empresa,
        string $titulo,
        string $mensaje,
        string $prioridad = 'default',
        array $etiquetas = ['white_check_mark'],
    ): bool {
        if (! $this->habilitado || $empresa->tema_ntfy === null || $empresa->tema_ntfy === '') {
            return false;
        }

        // Si el tema guardado no coincide con su resumen, el aviso no sale: algo cambió
        // la fila y no vale la pena arriesgar una fuga por una notificación.
        if (! hash_equals($empresa->tema_ntfy_huella ?? '', hash('sha256', $empresa->tema_ntfy))) {
            Log::warning('ntfy.tema_incoherente', ['empresa_id' => $empresa->id]);

            return false;
        }

        try {
            $respuesta = $this->http
                ->timeout((int) config('ntfy.tiempo_espera', 10))
                ->withHeaders([
                    'Title' => $titulo,
                    'Priority' => $prioridad,
                    'Tags' => implode(',', $etiquetas),
                ])
                ->send('POST', $this->url . '/' . $empresa->tema_ntfy, [
                    'body' => $mensaje,
                ]);
        } catch (ConnectionException $error) {
            Log::warning('ntfy.sin_conexion', [
                'empresa_id' => $empresa->id,
                'motivo' => $error->getMessage(),
            ]);

            return false;
        }

        Log::info('ntfy.aviso_publicado', [
            'empresa_id' => $empresa->id,
            'estado_http' => $respuesta->status(),
        ]);

        return $respuesta->successful();
    }
}