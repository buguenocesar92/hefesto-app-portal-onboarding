# 05. Seguridad

Este portal maneja una credencial que administra toda la plataforma de despliegue de
Hefesto. Eso lo pone en otra categoría frente a las otras aplicaciones del estudio, y por
eso esta documentación no es una lista de buenas prácticas genéricas.

## El riesgo número uno

> **Que alguien borre la infraestructura de un cliente desde una página web.**

Ese es el escenario que hay que evitar a toda costa. Hay cuatro capas que lo impiden:

### Capa 1: no existe proxy

`ClienteCoolify::ejecutar()` recibe un caso del enum `OperacionCoolify`. No hay ningún
método que reciba una URL, un método o un cuerpo. Agregar una operación obliga a editar
el enum, que es un archivo revisable.

Si en algún momento alguien "optimiza" esto y agrega un método `enviar(string $url, ...)`,
la auditoría estática falla:
`python api/tests/estatico/politica_coolify.py` revisa las firmas públicas del cliente y
rechaza parámetros con nombre de ruta, método o extremo.

### Capa 2: lista de prohibiciones

`OperacionesProhibidas` tiene 23 entradas con motivo y enlace a la documentación oficial.
`DELETE`, `PUT` y `PATCH` se rechazan siempre. Una lista de fragmentos (`delete`,
`restart`, `stop`, `kill`, `exec`, `cancel`, `rollback`, `migrate`, `clone`, `logs`,
`envs`, `members`, `settings`, `private-key`) rechaza el resto.

### Capa 3: patrón de ruta

Después de resolver los identificadores, la ruta final tiene que calzar con el patrón
declarado por la operación. Un identificador que no sea UUID se rechaza **antes** de
construir la dirección, así que un `../` en un identificador no llega a la petición.

### Capa 4: aprobación humana

Ninguna escritura ocurre sin una fila aprobada en `bitacora_aprobaciones`, con persona,
fecha y operaciones listadas.

## La credencial de Coolify

| Aspecto | Cómo se resuelve |
| --- | --- |
| Dónde vive | Solo en el contenedor de la API y en el del trabajador de colas |
| Habilidades | `read` y `write`. Ni `deploy`, ni `read:sensitive`, ni `root` |
| En el navegador | Nunca: no está en la configuración pública de Nuxt |
| En la API propia | Nunca: `SolicitudResource` no la expone y `app/Http/` no la menciona |
| En los registros | Solo su huella: 10 caracteres del SHA-256 |
| En una excepción | `TokenCoolify::__debugInfo()` y `__toString()` la ocultan |
| Red | La API vive en una red sin salida a internet |

La huella permite distinguir credenciales en una auditoría sin permitir autenticarse.

## Credenciales de los clientes

**No están en esta base de datos.** Nunca entraron.

El flujo: se genera la credencial, viaja a Infisical y se descarta. En este sistema queda
la fila que dice dónde quedó, cuándo y con qué clave. La tabla no tiene columna para el
valor, y eso es deliberado: el día que alguien agregue esa columna, un respaldo de esta
base se convierte en un respaldo de los secretos de todos los clientes.

Con la integración apagada, la entrega queda en estado `pendiente`. El cliente ve que
falta, no un visto verde.

## Aislamiento entre clientes

**Dos capas, y la segunda está en la base de datos.**

| Barrera | Dónde | Qué protege |
| --- | --- | --- |
| Alcance global del modelo | `Solicitud::booted()` | Olvidos de filtro en consultas |
| Contexto de empresa en la conexión | `ContextoEmpresa` + middleware `ResolverEmpresa` | Herencia de contexto entre peticiones y trabajos |
| **Seguridad de fila (RLS)** | `2026_10_17_000007_activar_row_level_security.php` | **Consultas que ni siquiera pasan por Laravel** |
| Política `SolicitudPolicy` | Cada acción | Errores de lógica |
| Middleware `rol.hefesto` | Área de aprobación | Un cliente que llega a la puerta equivocada |
| `SolicitudResource` | Serialización | Que un cliente vea identificadores de Coolify ajenos |

### La segunda capa: Row Level Security

La primera capa está en PHP y por lo tanto puede fallar: basta con que alguien escriba una
consulta sin filtro, o que un `DB::table()` se cuele sin el alcance global. La segunda
capa no depende del código de la aplicación.

Es el mismo patrón que ya usa `apps/portal-autoservicio`, con su misma justificación:

- **La empresa viaja con alcance de transacción.** Se fija con
  `set_config('app.empresa_id', ..., true)`. El `true` es lo importante: al confirmar o
  revertir, PostgreSQL descarta el valor. Con un `SET` normal, el trabajador de colas,
  que reutiliza la misma conexión para trabajos de empresas distintas, leería datos de la
  empresa anterior. `ContextoEmpresa::establecer()` además **lanza excepción** si no está
  dentro de una transacción.
- **`WITH CHECK` en todas las políticas.** Sin él, un `INSERT` con el identificador de
  otra empresa pasaría la lectura y dejaría una fila invisible que nadie podría ver ni
  borrar.
- **`FORCE ROW LEVEL SECURITY`.** Sin `FORCE`, el dueño de la tabla saltaría la política, y
  en desarrollo el rol de la aplicación suele ser el dueño.
- **La función de contexto falla cerrado.** `public.app_empresa_id()` lanza excepción si
  no hay contexto. Podría devolver `NULL` y filtrar todo en silencio, pero se eligió que
  se rompa de forma ruidosa: el error aparece en el registro en el momento, y se sabe
  qué operación se escapó del middleware.

Tablas protegidas: `usuarios`, `solicitudes`, `pasos_aprovisionamiento`,
`entregas_credenciales` y `bitacora_aprobaciones`.

`pasos_aprovisionamiento` y `entregas_credenciales` no tenían `empresa_id`: llegaban
a la empresa por `solicitud_id`, y una política de RLS no puede seguir ese camino, porque
compara columnas de la fila que se lee. Por eso la migración
`2026_10_17_000006` agrega la columna. Es redundancia deliberada, con un costo conocido:
si una fila quedara con un `empresa_id` que no corresponde al de su solicitud, quedaría
invisible para su propia empresa.

### El personal de Hefesto ve todo, y está escrito en la política

Quien aprueba necesita ver la cola de todas las empresas. Eso no se resuelve con una
excepción escondida en una consulta, sino con un contexto propio:

- `app.acceso_hefesto` en `true` hace que `public.app_pertenece_al_contexto()` acepte
  cualquier fila.
- La bandera **vale `false` por defecto**. Nunca lanza: su ausencia significa "no es
  personal de Hefesto", que es el lado cerrado.
- Solo `ContextoEmpresa::ejecutarAisladoComoHefesto()` la escribe.

El riesgo está asumido y documentado: **una cuenta de Hefesto ve los datos de todos los
clientes**. Es una decisión del negocio, y que quede escrita en la política hace que se
pueda auditar.

### El punto ciego: la tabla `empresas`

`empresas` **no lleva RLS**, a propósito: se consulta durante el acceso, cuando todavía
no hay sesión y por lo tanto no hay contexto que poner. Es el mismo punto ciego que en la
app hermana.

Compensaciones:

1. El portal nunca lista empresas: solo carga la de la persona autenticada.
2. `php artisan portal:verificar-aislamiento` comprueba que el rol de la base no sea
   superusuario ni tenga `BYPASSRLS`, que cada tabla tenga RLS activada y forzada con
   política, que la función de contexto falle cerrada y que el contexto no quede
   colgando.
3. La lista del personal de Hefesto es configuración revisable en el despliegue, no una
   tabla.

**Riesgo residual:** quien tenga acceso SQL directo a la base puede leer `empresas` con
razón social, RUT y datos de contacto. Las compensaciones 1 y 3 no lo detienen. La
mitigación es el control del rol de base, que es lo que comprueba el comando.

### Pruebas

- `AislamientoEntreEmpresasTest`: capa de aplicación (alcance global y políticas).
- `AislamientoEnBaseDeDatosTest`: capa de base. No prueba PHP: comprueba que PostgreSQL se
  niega a entregar filas de otra empresa, aunque la aplicación no filtre nada. Incluye
  lectura cruzada, modificación cruzada, inserción con empresa ajena, y que el contexto
  no sobreviva a la transacción.

Las dos van contra PostgreSQL de verdad, porque contra SQLite el aislamiento no se
comprueba nada.

## Autenticación

La identidad viene de **Authentik**, igual que en las otras aplicaciones de Hefesto. El
portal no guarda contraseñas: la tabla `usuarios` tiene `email` y `authentik_subject`, y
no usa ningún campo de contraseña.

Implementado por OIDC con flujo de código de autorización y **PKCE en S256**:

1. `GET /auth/entrar` genera una marca de estado y un reto PKCE, los guarda en la sesión
   y manda al navegador a `https://<id>/application/o/authorize/`.
2. La persona se identifica en Authentik y vuelve a `/auth/callback` con el código.
3. El servidor canjea el código en `/application/o/token/` y consulta
   `/application/o/userinfo/`.
4. Se resuelve la empresa, se regenera la sesión y se inicia el acceso.

### Rutas verificadas

Verificadas contra la documentación oficial de Authentik el **17 de octubre de 2026**
([proveedor OAuth 2.0](https://docs.goauthentik.io/add-secure-apps/providers/oauth2/)):

| Punto | Ruta |
| --- | --- |
| Autorización | `/application/o/authorize/` |
| Token | `/application/o/token/` |
| Información de usuario | `/application/o/userinfo/` |
| Revocación | `/application/o/revoke/` |
| Introspección | `/application/o/introspect/` |
| Fin de sesión | `/application/o/<slug>/end-session/` |
| Claves públicas | `/application/o/<slug>/jwks/` |
| Configuración OIDC | `/application/o/<slug>/.well-known/openid-configuration` |

**Ojo con esto, porque es lo que más se confunde:** solo los tres últimos llevan el
identificador de la aplicación. Autorización, token e información de usuario son
endpoints globales, con barra final y sin identificador. Poner el identificador ahí
devuelve un error de ruta.

### El token de acceso no sale del servidor

El portal no lo necesita después de leer la información del usuario: se usa una vez y se
descarta. No va en la sesión, no va en ninguna respuesta, no va a la configuración
pública de Nuxt. Al navegador solo viaja una cookie de sesión, que no contiene nada de
Authentik. El verificador `comprobar_entrega.py` comprueba que el frontend no mencione
`CLIENT_SECRET` en ningún archivo.

### A quién pertenece cada identidad

| Regla | Efecto |
| --- | --- |
| El correo está en `AUTHENTIK_CORREOS_HFESTO`, o su dominio en `AUTHENTIK_DOMINIOS_HFESTO` | Entra con contexto de Hefesto y ve todas las solicitudes |
| El dominio del correo coincide con `empresas.dominio_correo` | Entra como cliente de esa empresa |
| Ninguna de las dos | Acceso rechazado y queda registrado |

El **rol lo decide Hefesto dentro del portal**, no Authentik. El grupo de Authentik solo
sirve para el primer alta del personal: después manda el rol guardado aquí, y por eso
sincronizar la identidad nunca toca el rol. Si lo tocara, cualquiera podría autoasignarse
el de aprobador.

Riesgo conocido y aceptado: **si Authentik se cae, se cae el acceso a todo.** Es el mismo
riesgo de las otras aplicaciones de Hefesto, y la mitigación es la misma: mantener un
acceso de emergencia documentado para el personal.

## Sesión, CSRF y cookies

- Sesión en Redis, no en archivo.
- `SESSION_SECURE_COOKIE=true` en producción.
- `ValidateCsrfToken` se aplica **también** al grupo de rutas de la API, porque el
  navegador se autentica con cookie y no con un token. Sin esa línea, un sitio externo
  podría hacer que el navegador de un aprobador envíe una aprobación.
- CORS: no se configura ninguno. La API no se pública directamente; el proxy interno la
  expone solo en `/api`.

## Validación de entrada

- Todas las entradas de escritura pasan por `FormRequest` con mensajes en español.
- El identificador de plan se valida contra la base de datos, no se acepta a ciegas.
- El campo de dominio tiene una expresión regular.
- El identificador de Coolify se valida como UUID en el cliente HTTP.

## Lo que falta

| Falta | Por qué | Riesgo |
| --- | --- | --- |
| **Pruebas de aislamiento ejecutadas** | El código está escrito, pero nunca se corrió contra PostgreSQL | Alto. La seguridad de fila está sin verificar en ejecución |
| **Límite de peticiones** | No hay `throttle` en las rutas de escritura, incluidas las de acceso | Medio. Permite automatizar intentos de acceso |
| **Autenticación probada contra Authentik real** | No hay instancia en este entorno; las rutas están verificadas contra la documentación, no contra una respuesta real | Medio |
| **Content-Security-Policy** | Caddy envía otras cabeceras, no esta | Medio |
| **Rotación de `APP_KEY`** | Sin procedimiento documentado | Medio: rotarla invalida las sesiones y descifra el tema de ntfy |
| **Seguridad de fila sobre `empresas`** | Excluida a propósito, con compensaciones | Bajo, aceptado y documentado |

Lo primero que hay que hacer es correr las pruebas contra PostgreSQL de verdad:
`php artisan migrate` y `php artisan test`.

## Qué se revisa antes de poner esto en manos de un operador

```bash
# 1. La política de Coolify, completa y legible.
php artisan portal:politica-coolify

# 2. El aislamiento en la base: rol sin BYPASSRLS, RLS activada y forzada,
#    función de contexto que falla cerrada y contexto que no queda colgando.
php artisan portal:verificar-aislamiento

# 3. La misma auditoría de Coolify, sin base de datos ni red.
python api/tests/estatico/politica_coolify.py

# 3. La suite de PHP (necesita PostgreSQL).
php artisan test
```

Y una revisión manual, que ninguna herramienta reemplaza: leer
`api/app/Enums/OperacionesProhibidas.php` y preguntarse si falta algo.

---

## Los dos roles de PostgreSQL

La base del portal de aprovisionamiento corre con **dos** roles, no con uno. La
separación es deliberada: es lo que hace que el aislamiento entre empresas
funcione en vez de quedar como decoración.

### Qué es cada rol

| Rol | Nombre | De dónde sale | Para qué sirve | ¿Lo usa la aplicación? |
|---|---|---|---|---|
| **Arranque** | `portal_onboarding_admin` | `POSTGRES_USER` | Crear la base `portal_onboarding` y las tareas administrativas del despliegue | **Nunca** |
| **Aplicación** | `portal` | `DB_USERNAME`, creado por `deploy/postgres/inicializar-rol.sh` | Todo el tráfico de la API | **Sí, este y solo este** |

El rol de aplicación se llama simplemente `portal`, sin sufijo. Conviene no
confundirlo con el `portal_app` de la aplicación hermana
`portal-autoservicio`: son bases distintas, pero el nombre del rol es igual de
corto en las dos.

El rol de arranque no es una convención del estudio: la imagen oficial de
PostgreSQL ejecuta `initdb --username="$POSTGRES_USER"`, así que ese rol nace
**superusuario**.

### Por qué con un solo rol no servía

La documentación de PostgreSQL 18 lo dice de forma textual:

> *«Superusers and roles with the `BYPASSRLS` attribute always bypass the row
> security system when accessing a table. Table owners normally bypass row
> security as well, though a table owner can choose to be subject to row
> security with `ALTER TABLE ... FORCE ROW LEVEL SECURITY`.*

La segunda frase es la clave. `FORCE ROW LEVEL SECURITY` sujeta al **dueño** de
la tabla, pero **no al superusuario**. Mientras la aplicación conectara con el
rol de `POSTGRES_USER`, ninguna política se iba a aplicar: una empresa habría
visto los aprovisionamientos de las demás, sin error y sin aviso. El aislamiento
entre empresas era decorativo.

### Los atributos van todos escritos

`deploy/postgres/inicializar-rol.sh`, montado en
`/docker-entrypoint-initdb.d/`, crea el rol con `NOSUPERUSER`, `NOCREATEDB`,
`NOCREATEROLE`, `NOINHERIT` y `NOBYPASSRLS` **explícitos todos**, aunque
`NOBYPASSRLS` ya sea el valor por omisión.

Se escriben igual a propósito: un rol verificado no debería depender de un valor
por omisión que una versión nueva puede cambiar. El script también falla
ruidosamente si falta `POSTGRES_DB`, `APP_DB_ROLE` o `APP_DB_PASSWORD`.

### Por qué el rol de aplicación es dueño de la base

Justo después de crearlo, el script corre:

```sql
ALTER DATABASE portal_onboarding OWNER TO portal;
```

Las migraciones de Laravel se ejecutan con el rol de la aplicación y necesitan
**crear tablas**. La única forma de darles ese permiso sin convertirlos en
superusuario es que ese rol sea el dueño de la base. Desde PostgreSQL 15 el
esquema `public` pertenece al dueño de la base, así que con esto el rol ya
puede crear tablas ahí.

Y este es el punto exacto del diseño: al ser el dueño, **las tablas quedan a su
nombre**, y por eso el `FORCE ROW LEVEL SECURITY` que usan las tablas sí lo
sujeto. Ser dueño no es saltarse la política; es lo que hace que la política lo
alcance. Un superusuario la saltearía siempre.

### Cómo se comprueba

```
php artisan portal:verificar-aislamiento
```

El prefijo del comando es `portal:`, igual que en la aplicación hermana. El
comando consulta `pg_roles` sobre el rol de la conexión y falla con código 1 si
ese rol es superusuario o tiene `BYPASSRLS`, y después revisa que la RLS, las
políticas y el contexto de empresa protejan los datos de cada empresa.

Esta aplicación tiene además `verificar-politica-coolify`, que es otra cosa:
revisa la política de despliegue en Coolify, no el aislamiento de la base. No
sirve como comprobación del aislamiento.

**Este comando no se ha ejecutado.** En el equipo donde se escribió este cambio
no había PostgreSQL ni Docker. El diseño es correcto y está sostenido en la
documentación oficial, pero nadie lo ha corrido. Ver
[10-PRUEBAS-Y-ESTADO.md](10-PRUEBAS-Y-ESTADO.md).