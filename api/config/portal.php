<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Identidad del portal
    |--------------------------------------------------------------------------
    */

    'nombre' => env('APP_NOMBRE', 'Portal de Onboarding de Hefesto'),

    'version' => env('APP_VERSION', '0.1.0'),

    /*
    |--------------------------------------------------------------------------
    | Entorno de la integración con Coolify
    |--------------------------------------------------------------------------
    |
    | Cuando vale "deshabilitado", el portal recorre el flujo completo, deja registro
    | de cada paso y NO llama a Coolify. La pantalla dice que no se ejecutó.
    |
    */

    'modo_aprovisionamiento' => env('MODO_APROVISIONAMIENTO', 'deshabilitado'),

    /*
    | Aprobación humana: quién puede aprobar y si puede ser la misma persona que creó
    | la solicitud.
    |
    */

    'aprobar_requiere_rol' => ['aprobador', 'administrador'],

    'permitir_autoaprobacion' => (bool) env('PERMITIR_AUTOAPROBACION', false),

    /*
    | Avisos al cliente.
    |
    */

    'aviso_al_cliente' => env(
        'AVISO_AL_CLIENTE',
        'La preparación de tu infraestructura se hace en nuestra plataforma de despliegue. '
        .'Una persona de Hefesto revisa y aprueba cada ejecución antes de que ocurra.'
    ),

];