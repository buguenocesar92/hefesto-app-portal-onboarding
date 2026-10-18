# 01. Arquitectura

> PostgreSQL corre con dos roles: uno de arranque y otro de aplicación. Solo el
> de aplicación conecta con el sistema, y es el que las políticas de
> aislamiento pueden alcanzar. Ver [05](05-SEGURIDAD.md).

## Qué es esta aplicación

El Portal de Onboarding y Aprovisionamiento de Hefesto Studio. Una PyME se registra,
mira el catálogo, elige un plan, y Hefesto le deja la infraestructura preparada sin que
nadie tenga que hacerlo a mano.

**Es software propio, escrito para Hefesto.** No es un kit de despliegue de una
herramienta de terceros ni un reempaquetado: usa Coolify, Infisical, Authentik y ntfy
porque Hefesto ya los tiene desplegados, y se apoya en ellos por API.

## El problema que resuelve

Hoy, para contratar una PyME nueva, alguien de Hefesto:

1. Crea la cuenta del cliente.
2. Elige el kit.
3. Configura el servidor.
4. Entrega las credenciales a mano.

Eso son horas por cliente, y el error humano es un costo real. El retorno de este portal
no está en la PyME: está en que **Hefesto deja de gastar horas en trabajo repetitivo**.

## Las cuatro reglas que gobiernan el diseño

1. **La credencial de Coolify no sale del backend.** Nunca llega al navegador, nunca se
   expone por una API propia, nunca se registra. Es lo único en todo el sistema que
   administra la plataforma de despliegue completa.

2. **Las operaciones de Coolify son una lista fija.** Ocho, escritas en un enum. No hay
   proxy genérico. Las operaciones destructivas no están expuestas y están documentadas
   como tales. Ver [04-INTEGRACION-COOLIFY.md](04-INTEGRACION-COOLIFY.md).

3. **Nada se ejecuta sin aprobación humana.** Es una decisión de diseño, no un detalle.
   El estado "aprobada" por sí solo no habilita nada: hace falta una fila en la bitácora
   con persona, fecha y operaciones autorizadas.

4. **Las credenciales viven en Infisical, no en esta base.** El portal guarda dónde
   quedaron, nunca el valor.

## Componentes

```
Navegador
   │
   ▼
┌──────────────────────────┐
│ Nuxt 4 (SSR, Node 22)    │  Renderiza catálogo, seguimiento y aprobación
└────────────┬─────────────┘
             │  HTTP interno
             ▼
┌──────────────────────────┐
│ Laravel 12 (PHP 8.3)     │  Toda la lógica. Único que habla con Coolify,
└───┬────────┬─────────┬────┘  Infisical y ntfy
    │        │         │
    │        │         └── Redis (colas: aprovisionamiento, credenciales, avisos)
    ▼        ▼
PostgreSQL  Coolify  ──►  plataforma de despliegue de Hefesto
(este sistema)
```

El frontend nunca habla con Coolify. No tiene la credencial, no tiene el cliente y no
tiene ninguna función que se lo permita.

## Por qué el trabajo va encolado

El aprovisionamiento llama a una API externa, tarda y puede reintentar. Si ocurriera
dentro de la petición web, el cliente vería la pantalla cargando y una caída de Coolify
se convertiría en un error de página. Encolado en Redis:

- La respuesta web es inmediata.
- El avance se ve en la pantalla de seguimiento, paso a paso.
- Un reintento no le cuesta nada al navegador.

Colas separadas, por si una se satura: `aprovisionamiento`, `credenciales` y
`notificaciones`.

## Modelo de datos en una línea

`empresas` es la raíz. De ella cuelga `usuarios` y `solicitudes`, y de las solicitudes,
`pasos_aprovisionamiento`, `entregas_credenciales` y `bitacora_aprobaciones`.

`kits` y `planes` son catálogo y no pertenecen a ninguna empresa.

## Aislamiento entre clientes

**Dos capas, y la segunda está en la base de datos.**

1. **En la aplicación.** El middleware `ResolverEmpresa` fija la empresa del usuario y el
   modelo `Solicitud` declara un alcance global que exige `empresa_id`. El personal de
   Hefesto lo salta con `withoutGlobalScope('empresa')`, que es una llamada visible en el
   código. Encima de eso, `SolicitudPolicy` vuelve a comparar empresas y roles.
2. **En PostgreSQL.** Seguridad de fila: políticas con `USING` y `WITH CHECK`,
   `FORCE ROW LEVEL SECURITY`, y la empresa viajando con **alcance de transacción**
   (`set_config(..., true)`) para que el trabajador de colas no arrastre el contexto de
   otra empresa. La función de contexto **lanza excepción** cuando no hay contexto: falla
   cerrado, no devuelve cero filas en silencio.

Que la primera exista hace que un olvido en la segunda no produzca una fuga. La segunda
existe para cuando el error está en la primera: una consulta escrita sin filtro, o un
`DB::table()` olvidado, siguen sin poder entregar datos de otra empresa.

Mismo patrón y misma justificación que `apps/portal-autoservicio`. El personal de
Hefesto, que no pertenece a ninguna empresa, tiene su propio contexto
(`app.acceso_hefesto`), escrito explícitamente en la política de la base.

**Lo que NO hay: seguridad de fila sobre la tabla `empresas`.** Se consulta durante el
acceso, cuando todavía no hay sesión y por lo tanto no hay contexto que poner. Es el
mismo punto ciego que en la app hermana, con las mismas compensaciones. El detalle está
en [05-SEGURIDAD.md](05-SEGURIDAD.md).

## Identidad

La trae **Authentik** por OIDC, con flujo de código de autorización y PKCE. El portal no
guarda contraseñas. El token de acceso se usa una vez, en el servidor, para leer la
información del usuario, y se descarta: no va a la sesión, no va a ninguna respuesta y
no llega al navegador.

Rutas verificadas contra la documentación oficial de Authentik el 17-10-2026:
`/application/o/authorize/`, `/application/o/token/` y `/application/o/userinfo/` son
globales y **no** llevan el identificador de la aplicación; solo `end-session`, `jwks` y
la configuración OIDC lo llevan.

## Estructura

```
portal-onboarding/
├── README.md
├── .env.example          # Plantilla, sin ningún secreto real
├── docs/                 # 01 a 10
├── api/                  # Laravel 12
│   ├── app/
│   │   ├── Acciones/     # aprobar, aprovisionar, entregar credenciales
│   │   ├── Enums/        # operación permitida, operación prohibida, estados
│   │   ├── Jobs/         # trabajo encolado
│   │   ├── Servicios/    # contexto de empresa, Coolify, Infisical, Authentik
│   │   ├── Integraciones/# avisos por ntfy
│   │   └── Http/         # controladores, peticiones, recursos, middleware
│   ├── database/         # migraciones, fábricas, semillas
│   └── tests/            # Unit, Feature y comprobaciones estáticas
├── web/                  # Nuxt 4 con SSR
└── deploy/               # Compose de desarrollo y producción, Dockerfiles
```

## Estado

**Código escrito, sin ejecutar.** En el entorno donde se construyó no había Docker, ni
PHP, ni Composer. El detalle de qué se comprobó y qué no está en
[10-PRUEBAS-Y-ESTADO.md](10-PRUEBAS-Y-ESTADO.md).