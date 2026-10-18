<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Identidad: Authentik por OIDC
    |--------------------------------------------------------------------------
    |
    | Verificado contra la documentación oficial de Authentik el 17-10-2026
    | (https://docs.goauthentik.io/add-secure-apps/providers/oauth2/):
    |
    |   Autorización            /application/o/authorize/
    |   Token                   /application/o/token/
    |   Información de usuario  /application/o/userinfo/
    |   Revocación              /application/o/revoke/
    |   Introspección           /application/o/introspect/
    |   Fin de sesión           /application/o/<slug>/end-session/
    |   Claves públicas (JWKS)  /application/o/<slug>/jwks/
    |   Configuración OIDC      /application/o/<slug>/.well-known/openid-configuration
    |
    | OJO con esto, porque es fácil de recordar mal: SOLO los tres últimos llevan el
    | identificador de la aplicación. Autorización, token e información de usuario son
    | globales, con barra final y sin identificador. Poner el identificador ahí devuelve
    | un error de ruta en Authentik.
    |
    */

    'url' => rtrim((string) env('AUTHENTIK_URL', 'https://id.ejemplo.cl'), '/'),

    /*
    | Identificador de la aplicación creada en Authentik. Es el "slug" de la aplicación,
    | y aparece en la ficha del proveedor OAuth2.
    */
    'slug' => env('AUTHENTIK_SLUG', 'portal-onboarding'),

    'cliente_id' => env('AUTHENTIK_CLIENT_ID'),

    /*
    | Secreto del cliente. Solo vive en el servidor. Nunca se manda al navegador, nunca
    | entra en la configuración pública de Nuxt y nunca se registra.
    */
    'cliente_secreto' => env('AUTHENTIK_CLIENT_SECRET'),

    /*
    | Dónde vuelve el navegador después de identificarse. Tiene que coincidir exactamente
    | con una de las direcciones registradas en el proveedor de Authentik.
    */
    'redirect_uri' => env('AUTHENTIK_REDIRECT_URI', 'http://127.0.0.1:3000/auth/callback'),

    /*
    | Alcances solicitados. `openid` es obligatorio; `profile` trae el nombre y `email`
    | trae el correo, que es con lo que se resuelve a qué empresa pertenece la persona.
    */
    'alcances' => ['openid', 'profile', 'email'],

    /*
    |--------------------------------------------------------------------------
    | Personal de Hefesto
    |--------------------------------------------------------------------------
    |
    | Estas identidades NO pertenecen a ninguna empresa, así que no se resuelven por
    | dominio de correo. Se declaran una por una, y son las únicas que pueden entrar al
    | área de aprobación.
    |
    | La lista se revisa antes de cada despliegue. Agregar aquí una dirección equivale a
    | darle a esa persona acceso a todas las solicitudes de todos los clientes.
    |
    */

    'correos_hefesto' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('AUTHENTIK_CORREOS_HFESTO', '')),
    ))),

    /*
    | Dominios de correo del personal de Hefesto. Más cómodo que listar cada dirección,
    | pero es más ancho: cualquier cuenta de ese dominio entra al área de aprobación.
    | Úsese solo si el dominio es de Hefesto.
    */
    'dominios_hefesto' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('AUTHENTIK_DOMINIOS_HFESTO', '')),
    ))),

    /*
    | Grupo de Authentik que, en el PRIMER alta, nace con rol de aprobador. Después de
    | eso manda el rol guardado en el portal, que es lo que Hefesto administra.
    */
    'grupo_aprobadores' => env('AUTHENTIK_GRUPO_APROBADORES', 'hefesto-aprobadores'),

    /*
    | Duración máxima de una solicitud de acceso pendiente, en segundos. Transcurrido ese
    | tiempo, el enlace deja de servir.
    */
    'estado_maximo' => (int) env('AUTHENTIK_ESTADO_MAXIMO', 600),

    /*
    | Si el portal exige una sesión de navegador ya iniciada antes de mandar a Authentik.
    */
    'exigir_sesion_previa' => (bool) env('AUTHENTIK_EXIGIR_SESION_PREVIA', false),

    'tiempo_espera' => (int) env('AUTHENTIK_TIMEOUT', 15),

];