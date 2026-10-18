<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Dirección de la instancia de Coolify
    |--------------------------------------------------------------------------
    |
    | Coolify se autoaloja. La base de su API es siempre la instancia seguida de
    | /api/v1. Verificado en https://coolify.io/docs/api/overview el 17-10-2026.
    |
    | La comprobación de salud NO necesita credencial y sirve para confirmar que la
    | instancia existe antes de intentar cualquier otra cosa.
    |
    */

    'url_base' => env('COOLIFY_URL_BASE', 'https://coolify.ejemplo.cl/api/v1'),

    /*
    |--------------------------------------------------------------------------
    | Credencial principal
    |--------------------------------------------------------------------------
    |
    | ATENCIÓN: esta credencial administra toda la plataforma de despliegue de Hefesto.
    | Solo vive en el backend. No se devuelve en ninguna respuesta de la API propia,
    | no aparece en los registros y no se envía al navegador bajo ninguna circunstancia.
    |
    | Hay que pedirla en el panel de Coolify con EXACTAMENTE estas habilidades:
    |
    |   lectura  -> sí    (operaciones de lectura y de salud)
    |   escritura -> sí    (crear proyecto y crear servicio)
    |   despliegue -> NO   (reinicia, detiene y cancela: no lo necesitamos para crear)
    |   lectura sensible -> NO  (leería registros, claves y variables de entorno)
    |   root -> NO          (anula las comprobaciones de habilidad)
    |
    | El token pertenece a una persona de Hefesto que debe ser administradora o
    |propietaria del equipo, y queda ligado a ese equipo. Ver
    | https://coolify.io/docs/api/permissions
    |
    */

    'token' => env('COOLIFY_TOKEN'),

    /*
    | Credencial separada para disparar despliegues.
    |
    | Deshabilitada por defecto. La habilidad `deploy` de Coolify habilita también
    | reiniciar, detener y cancelar, así que no se pide en la credencial principal.
    | Cuando Hefesto active esta fase, tiene que ser un SEGUNDO token, con un usuario
    | distinto si es posible, y con la lista de direcciones de IP de Coolify
    | restringida a la IP de salida de este portal.
    |
    */

    'token_despliegue' => env('COOLIFY_TOKEN_DESPLIEGUE'),

    'permitir_despliegue' => (bool) env('COOLIFY_PERMITIR_DESPLIEGUE', false),

    /*
    | Interruptor general.
    |
    | Con false, el portal registra solicitudes, los guarda en la cola y NO llama a
    | Coolify. Es el modo recomendado para instalar y para demonstrations: el flujo se
    | puede recorrer completo sin tocar la plataforma de despliegue. No es una
    | simulación que se disfraza de éxito: la respuesta dice explícitamente que el
    | aprovisionamiento no se ejecutó.
    |
    */

    'habilitada' => (bool) env('COOLIFY_HABILITADA', false),

    /*
    | Tiempos de espera y reintentos.
    |
    | La documentación oficial indica 200 peticiones por minuto para la API y 1000 para
    | /api/health, con las cabeceras X-RateLimit-Limit, X-RateLimit-Remaining,
    | X-RateLimit-Reset y Retry-After. Ante un 429 se respeta Retry-After.
    | Verificado el 17-10-2026.
    |
    */

    'timeout' => (int) env('COOLIFY_TIMEOUT', 20),

    'reintentos' => (int) env('COOLIFY_REINTENTOS', 2),

    'espera_entre_reintentos' => (int) env('COOLIFY_ESPERA_REINTENTOS', 3),

    /*
    |--
    | Servidor y entorno donde se crea el servicio del kit.
    |--
    |
    | La API de Coolify exige un servidor (server_uuid) al crear un servicio. Hefesto
    | decide a qué servidores van los clientes nuevos; el portal no elige servidor por
    | su cuenta. Un identificador mal escrito se detecta al leer el servidor, y la
    | lectura es una operación permitida (solo lectura).
    |
    */

    'servidor_uuid' => env('COOLIFY_SERVIDOR_UUID'),

    'entorno_nombre' => env('COOLIFY_ENTORNO', 'production'),

    /*
    | Lista de direcciones de IP a las que se puede exigir el acceso a la API.
    |
    | Solo se usa para mostrar una advertencia en el comando de verificación. La
    | restricción real se configura en Coolify, no aquí.
    |
    */

    'ip_permitidas' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('COOLIFY_IP_PERMITIDAS', '')),
    ))),

];