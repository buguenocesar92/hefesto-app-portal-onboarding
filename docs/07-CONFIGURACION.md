# 07. Configuración

Todas las variables están en [`.env.example`](../.env.example). Acá está para qué sirve
cada una y qué pasa si se configura mal.

## Aplicación

| Variable | Por defecto | Nota |
| --- | --- | --- |
| `APP_ENV` | `produccion` | `produccion`, `local` o `testing` |
| `APP_DEBUG` | `false` | En `true` muestra excepciones. **Nunca en producción** |
| `APP_KEY` | vacío | Obligatoria. Generar con `php artisan key:generate` |
| `APP_URL` | `https://onboarding.hefesto.cl` | Se usa para las cookies y los enlaces |
| `SESSION_SECURE_COOKIE` | `true` | Déjala en `true` bajo HTTPS |

`APP_KEY` también cifra el tema de ntfy en la base. Rotarla sin migrar los datos deja los
temas ilegibles y las notificaciones dejan de salir. El procedimiento está pendiente.

## Base de datos y Redis

| Variable | Por defecto | Nota |
| --- | --- | --- |
| `DB_PASSWORD` | vacío | Obligatoria en producción |
| `REDIS_PASSWORD` | vacío | Obligatoria. Redis sin contraseña es Redis abierto |
| `CACHE_STORE` | `redis` | |
| `QUEUE_CONNECTION` | `redis` | **No poner `sync` en producción**: el trabajo se ejecutaría dentro de la petición web |
| `SESSION_DRIVER` | `redis` | |

## Coolify

| Variable | Por defecto | Consecuencia |
| --- | --- | --- |
| `COOLIFY_URL_BASE` | ejemplo | Instancia seguida de `/api/v1` |
| `COOLIFY_TOKEN` | vacío | Sin esto el portal funciona en modo deshabilitado; con `COOLIFY_HABILITADA=true` y sin token, cada operación se rechaza con un motivo explícito |
| `COOLIFY_HABILITADA` | `false` | **El valor por defecto es no integrado** |
| `COOLIFY_PERMITIR_DESPLIEGUE` | `false` | Fase posterior |
| `COOLIFY_TOKEN_DESPLIEGUE` | vacío | Solo si se habilita el despliegue |
| `COOLIFY_SERVIDOR_UUID` | vacío | Sin esto, el paso 2 falla con un motivo explícito |
| `COOLIFY_ENTORNO` | `production` | |
| `COOLIFY_IP_PERMITIDAS` | vacío | Solo informativa; la restricción real es en Coolify |
| `COOLIFY_TIMEOUT` | `20` | Segundos |
| `COOLIFY_REINTENTOS` | `2` | Reintentos ante fallo de red o límite de peticiones |
| `COOLIFY_ESPERA_REINTENTOS` | `3` | Segundos base de la espera |

### La que más importa

`COOLIFY_HABILITADA=false` es el estado por defecto y tiene que seguir siendo el estado
por defecto. Cada vez que alguien la pone en `true`, está habilitando la escritura en la
plataforma de despliegue de Hefesto. Que sea una decisión consciente y anotada.

### Límites de peticiones

La documentación oficial indica 200 peticiones por minuto para la API y 1000 para
`/api/health`. El cliente lee `Retry-After` cuando Coolify lo envía y respeta ese valor,
sumándole un salto aleatorio para que varios trabajos no repitan al mismo tiempo.

El aprovisionamiento completo usa cuatro llamadas, así que un límite de 200 por minuto no
es un problema en operación normal. Sí lo sería una cola con muchas solicitudes
pendientes de reintento.

## Infisical

| Variable | Por defecto | Nota |
| --- | --- | --- |
| `INFISICAL_HABILITADA` | `false` | |
| `INFISICAL_RUTA_VERIFICADA` | `false` | **No poner en `true` sin confirmar la ruta** |
| `INFISICAL_RUTA_CREAR_SECRETO` | `/v3/secrets` | **Valor de ejemplo.** Confirmar contra `/api/docs/json` |
| `INFISICAL_PROYECTO` | vacío | Identificador del proyecto en Infisical |
| `INFISICAL_ENTORNO` | `production` | |
| `INFISICAL_CARPETA_RAIZ` | `clientes` | Carpeta donde viven las credenciales |

Las tres condiciones tienen que cumplirse juntas para que la entrega funcione: la
integración encendida, la ruta marcada como verificada y la credencial cargada. Si falta
cualquiera, el estado es `pendiente` y el cliente lo ve así.

El valor por defecto de la ruta **es un marcador**, no una ruta verificada. Está elegido
como el más probable según la documentación general, y por eso el portal exige la
confirmación explícita antes de usarlo.

## ntfy

| Variable | Por defecto | Nota |
| --- | --- | --- |
| `NTFY_URL` | `https://ntfy.sh` | El servidor público de ntfy |
| `NTFY_HABILITADO` | `false` | |
| `NTFY_PRIORIDAD` | `default` | Valores: `min`, `low`, `default`, `high`, `max` |

El tema **no se configura**: se genera al azar por empresa, porque es una contraseña. Si
alguien quiere cambiarlo, se regenera y se actualiza su resumen; el código avisa por
registro cuando el valor no calza con el resumen.

## Autenticación

| Variable | Por defecto | Nota |
| --- | --- | --- |
| `AUTHENTIK_URL` | `https://id.ejemplo.cl` | Instancia de identidad |
| `AUTHENTIK_SLUG` | `portal-onboarding` | Identificador de la aplicación. Solo se usa en cierre de sesión y claves públicas |
| `AUTHENTIK_CLIENT_ID` | vacío | **Obligatoria** para que el acceso funcione |
| `AUTHENTIK_CLIENT_SECRET` | vacío | **Obligatoria.** Solo en el servidor |
| `AUTHENTIK_REDIRECT_URI` | `http://127.0.0.1:3000/auth/callback` | Debe coincidir con la registrada en Authentik |
| `AUTHENTIK_CORREOS_HFESTO` | vacío | Correos que entran al área de aprobación |
| `AUTHENTIK_DOMINIOS_HFESTO` | vacío | Dominios que entran al área de aprobación. Más ancho |
| `AUTHENTIK_GRUPO_APROBADORES` | `hefesto-aprobadores` | Grupo que define el rol en el primer alta |

Las dos últimas variables son las más delicadas del archivo, por una razón que conviene
tener clara: **cualquiera que esté en esa lista entra al área de aprobación y ve las
solicitudes de todos los clientes.** Es la decisión que convierte a alguien en personal de
Hefesto, y por eso es configuración revisable en el despliegue y no una tabla que alguien
modifica en caliente.

Para el resto de las empresas clientela, la resolución es por `dominio_correo` de la
tabla `empresas`, que es único en toda la instalación.

## Comportamiento

| Variable | Por defecto | Nota |
| --- | --- | --- |
| `MODO_APROVISIONAMIENTO` | `deshabilitado` | Informativo; lo que manda es `COOLIFY_HABILITADA` |
| `PERMITIR_AUTOAPROBACION` | `false` | Con `true`, quien crea la solicitud puede aprobarla |

## Verificar la configuración

```bash
php artisan portal:politica-coolify
```

Imprime las ocho operaciones permitidas, las 23 prohibidas, y avisa si falta la
credenencial o si no se declararon direcciones IP. Es el comando para correr antes de
cargar la credencial en un servidor nuevo.

Para el aislamiento entre empresas hay otro:

```bash
php artisan portal:verificar-aislamiento
```

Comprueba que el rol de la base no sea superusuario ni tenga `BYPASSRLS`, que las cinco
tablas protegidas tengan seguridad de fila activada, **forzada** y con política, que
`public.app_empresa_id()` falle cerrada sin contexto, y que el contexto no quede
colgando después de la transacción. Sale con código 1 si algo falla.