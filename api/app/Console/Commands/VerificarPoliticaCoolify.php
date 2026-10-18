<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\OperacionCoolify;
use App\Enums\OperacionesProhibidas;
use App\Servicios\Coolify\TokenCoolify;
use Illuminate\Console\Command;

/**
 * Imprime la política de Coolify completa y comprueba que la configuración no contradiga
 * lo que el código afirma.
 *
 * Uso previsto: correrlo en el servidor antes de entregar el portal a un operador. La
 * salida es la misma que se pegó en la documentación, y sirve para responder "¿qué
 * puede hacer este portal en Coolify?" sin abrir el código.
 */
class VerificarPoliticaCoolify extends Command
{
    protected $signature = 'portal:politica-coolify';

    protected $description = 'Muestra las operaciones permitidas y prohibidas de Coolify y verifica la configuración.';

    public function handle(): int
    {
        $this->line('Operaciones permitidas');
        $this->line(str_repeat('-', 96));

        foreach (OperacionCoolify::permitidas() as $operacion) {
            $this->line(sprintf(
                '%-6s %-22s %-24s habilidad: %-8s %s',
                $operacion->metodo(),
                $operacion->ruta(),
                $operacion->etiqueta(),
                $operacion->habilidadRequerida(),
                $operacion->usaCredencialDeDespliegue() ? '[credencial separada]' : '',
            ));
        }

        $this->newLine();
        $this->line('Operaciones prohibidas');
        $this->line(str_repeat('-', 96));

        foreach (OperacionesProhibidas::todas() as $negada) {
            $this->line(sprintf('%-6s %s', $negada['metodo'], $negada['ruta']));
            $this->line('       ' . $negada['motivo']);
        }

        $this->newLine();
        $this->line('Configuración');
        $this->line(str_repeat('-', 96));

        $token = TokenCoolify::desdeConfiguracion('token');
        $this->line('Base de la API .......... ' . config('coolify.url_base'));
        $this->line('Integración activa ..... ' . ((bool) config('coolify.habilitada') ? 'sí' : 'NO'));
        $this->line('Despliegue automático ... ' . ((bool) config('coolify.permitir_despliegue') ? 'sí' : 'NO'));
        $this->line('Credencial cargada ..... ' . ($token instanceof TokenCoolify
            ? 'sí, huella ' . $token->huella()
            : 'NO'));

        if (! $token instanceof TokenCoolify) {
            $this->warn('No hay COOLIFY_TOKEN. El portal puede correr en modo deshabilitado, pero no aprovisionar.');
        }

        $ip = config('coolify.ip_permitidas', []);

        if ($ip === []) {
            $this->warn('No se declaró COOLIFY_IP_PERMITIDAS. La restricción de direcciones debe '
                . 'configurarse en el panel de Coolify, no en este portal.');
        } else {
            $this->line('Direcciones declaradas .. ' . implode(', ', $ip));
        }

        $this->newLine();
        $this->info('Fin de la política. Ninguna operación destructiva está expuesta.');

        return self::SUCCESS;
    }
}