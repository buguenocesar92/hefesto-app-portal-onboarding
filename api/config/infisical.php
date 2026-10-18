<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Dirección de la instancia de Infisical
    |--------------------------------------------------------------------------
    |
    | Verificado en la documentación oficial el 17 de octubre de 2026:
    | https://infisical.com/docs/api-reference/overview/introduction
    |
    |   Instancia propia:  https://<instancia>/api
    |   Infisical Cloud:    https://app.infisical.com/api
    |
    | Cada endpoint tiene su propia versión en la ruta. La propia documentación lo dice:
    | "para cada endpoint, use la versión más alta que aparezca en esta referencia".
    | Por eso la ruta de creación de secretos NO está fijada en el código: se
    | configura, y el portal se niega a funcionar si alguien no confirmó que esa ruta
    | existe en SU instancia.
    |
    */

    'url_base' => env('INFISICAL_URL_BASE', 'https://infisical.ejemplo.cl/api'),

    /*
    |--------------------------------------------------------------------------
    | Identidad de máquina
    |--------------------------------------------------------------------------
    |
    | La documentación de Infisical describe el acceso universal con identidades de
    | máquina. La cabecera concreta de autenticación y la ruta exacta de creación de
    | secretos NO se pudieron verificar en esta sesión: la página del endpoint no
    | respondió. Por eso la integración arranca apagada.
    |
    | Antes de habilitarla hay que hacer dos cosas, en este orden:
    |
    |   1. Abrir la especificación OpenAPI de la instancia y confirmar la ruta:
    |        https://<instancia>/api/docs/json
    |      Ese es el documento que la propia instancia publica, y es la fuente de
    |      verdad: puede diferir de la documentación pública.
    |   2. Poner INFISICAL_HABILITADA=true e INFISICAL_RUTA_VERIFICADA=true.
    |
    */

    'token' => env('INFISICAL_TOKEN'),

    'habilitada' => (bool) env('INFISICAL_HABILITADA', false),

    /*
    | Confirmación de que una persona de Hefesto revisó la ruta contra el OpenAPI de
    | la instancia. Mientras siga en falso, el portal registra la entrega como
    | "pendiente" y no finge haber guardado nada.
    */

    'ruta_verificada' => (bool) env('INFISICAL_RUTA_VERIFICADA', false),

    'ruta_crear_secreto' => env('INFISICAL_RUTA_CREAR_SECRETO', '/v3/secrets'),

    /*
    | Dónde se guarda cada credencial entregada. La estructura es
    | <proyecto>/<entorno>/<carpeta>, y la carpeta se deriva del nombre de la empresa.
    */

    'proyecto' => env('INFISICAL_PROYECTO'),

    'entorno' => env('INFISICAL_ENTORNO', 'production'),

    'carpeta_raiz' => env('INFISICAL_CARPETA_RAIZ', 'clientes'),

    'tiempo_espera' => (int) env('INFISICAL_TIMEOUT', 15),

];