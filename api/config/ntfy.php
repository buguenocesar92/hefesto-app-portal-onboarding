<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Servidor ntfy
    |--------------------------------------------------------------------------
    |
    | Hefesto ya usa ntfy para alertas, y este portal lo usa para avisarle al cliente en
    | qué paso va su onboarding.
    |
    | Sobre el nombre del tema: la propia documentación de ntfy advierte que, al no
    | haber registro, el tema funciona como una contraseña y conviene que no sea
    | adivinable. Por eso el tema NO es un identificador de empresa: se genera al azar
    | y se guarda hasheado. Ver App\Integraciones\NotificadorNtfy.
    |
    | Verificado el 17 de octubre de 2026 en https://docs.ntfy.sh/publish/
    |
    */

    'url' => env('NTFY_URL', 'https://ntfy.sh'),

    'habilitado' => (bool) env('NTFY_HABILITADO', false),

    'tiempo_espera' => (int) env('NTFY_TIMEOUT', 10),

    /*
    | Prioridad por defecto de los avisos de estado. ntfy acepta los valores
    | min, low, default, high y max.
    */

    'prioridad' => env('NTFY_PRIORIDAD', 'default'),

];