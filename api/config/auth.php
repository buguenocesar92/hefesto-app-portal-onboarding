<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Guardia de sesión
    |--------------------------------------------------------------------------
    |
    | El portal no guarda contraseñas: la identidad la demuestra Authentik. El modelo
    | `Usuario` extiende Authenticatable para que la sesión funcione, pero el campo de
    | contraseña nunca se usa ni se guarda.
    |
    */

    'defaults' => [
        'guard' => 'web',
        'passwords' => 'usuarios',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'usuarios',
        ],
    ],

    'providers' => [
        'usuarios' => [
            'driver' => 'eloquent',
            'model' => App\Models\Usuario::class,
        ],
    ],

    'passwords' => [
        'usuarios' => [
            'provider' => 'usuarios',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Autenticación de la API
    |--------------------------------------------------------------------------
    |
    | La API la consume el frontend de Nuxt con la MISMA cookie de sesión, no con un
    | token de API. Es lo que permite que la verificación CSRF tenga sentido: el
    | navegador es quien escribe, y el token va en la cabecera.
    |
    */

    'password_timeout' => 10800,

];