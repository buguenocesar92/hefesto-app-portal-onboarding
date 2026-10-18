# 04. Integración con Coolify

Este es el documento más importante del portal. Aquí se explica qué puede hacer el
portal sobre la plataforma de despliegue de Hefesto, qué no puede, y por qué está
dividido exactamente así.

**Estado: integración DESHABILITADA.** Está el interruptor en
`COOLIFY_HABILITADA=false`, que es el valor por defecto. Está apagada porque no se pudo
verificar, contra la instancia real de Hefesto, el parámetro que crea un servicio. Ver
la sección 7.

---

## 1. Por qué esta aplicación necesita una credencial de administrador

Las otras aplicaciones de Hefesto no la necesitan: el Portal de Autoservicio y el Panel
de Operación del Cliente solo leen. Esta es la excepción, y conviene decirlo claro:

> La credencial de Coolify administra **toda** la plataforma de despliegue de Hefesto.
> Con ella se puede crear, borrar, detener y reiniciar la infraestructura de cualquier
> cliente. Se manipula con extremo cuidado.

Por eso este portal no recibe la credencial para "integrarse con Coolify". La recibe para
ejecutar **una lista cerrada de ocho operaciones**, y nada más.

## 2. Qué se verificó y dónde

Todo lo que sigue se comprobó el **17 de octubre de 2026** contra la documentación
oficial, no de memoria ni de guías antiguas:

| Dato verificado | Fuente |
| --- | --- |
| Base de la API: `https://<instancia>/api/v1` | [overview](https://coolify.io/docs/api/overview) |
| Autenticación: cabecera `Authorization: Bearer <token>` | [authorization](https://coolify.io/docs/api/authorization) |
| El token tiene un identificador, un separador `\|` y el secreto; se envía completo | [authorization](https://coolify.io/docs/api/authorization) |
| Salud sin credencial: `/api/health` y `/api/v1/health` | [overview](https://coolify.io/docs/api/overview) |
| Habilidades de token: `read`, `read:sensitive`, `write`, `deploy`, `root` | [permissions](https://coolify.io/docs/api/permissions) |
| `write`, `deploy` y `root` exigen que el dueño del token sea administrador o propietario del equipo | [permissions](https://coolify.io/docs/api/permissions) |
| En una instancia propia hay que habilitar el acceso a la API y se puede restringir por dirección IP | [authorization](https://coolify.io/docs/api/authorization) |
| Límite: 200 peticiones por minuto; `/api/health`, 1000 por minuto | [rate-limits](https://coolify.io/docs/api/rate-limits) |
| Ante un 429 se respeta `Retry-After` | [rate-limits](https://coolify.io/docs/api/rate-limits) |

Coolify es software libre bajo licencia **Apache-2.0**, autoalojado y sin límites de
sitios ni de servidores.

### Discrepancia encontrada en la documentación oficial

La página del endpoint del equipo indica `GET /api/v1/team`, mientras que los ejemplos en
prosa de las páginas de autorización, peticiones y límites usan
`GET /api/v1/teams/current`. **No son lo mismo.**

Qué se hizo aquí: se implementó `/api/v1/team`, que es la ruta del esquema generado y la
que aparece en la página del endpoint. Queda anotado en el enum para que quien pruebe en
la instancia lo confirme y ajuste si corresponde. No se implementaron las dos: una lista
acotada no admite rutas alternativas sin revisión.

## 3. Operaciones permitidas

Ocho, y solo ocho. La definición está en
[`api/app/Enums/OperacionCoolify.php`](../api/app/Enums/OperacionCoolify.php), y ese
archivo es la fuente de verdad: agregar una operación obliga a tocarlo.

| Método | Ruta | Para qué | Habilidad |
| --- | --- | --- | --- |
| `GET` | `/api/v1/health` | Comprobar que la instancia responde. No usa credencial | ninguna |
| `GET` | `/api/v1/team` | Confirmar que la credencial sigue vigente y ligada a su equipo | `read` |
| `POST` | `/api/v1/projects` | Crear el proyecto exclusivo del cliente | `write` |
| `GET` | `/api/v1/projects/{uuid}` | Confirmar que el proyecto quedó creado | `read` |
| `POST` | `/api/v1/services` | Crear el servicio del kit contratado | `write` |
| `GET` | `/api/v1/services/{uuid}` | Mostrar el estado del servicio | `read` |
| `GET` | `/api/v1/deployments/{uuid}` | Leer el resultado de un despliegue | `read` |
| `POST` | `/api/v1/deploy` | Poner en marcha lo creado. **Deshabilitada por defecto** | `deploy` |

Cuerpos verificados para las creaciones:

- `POST /api/v1/projects` : `name` (obligatorio), `description` (opcional). Responde
  `201` con `{ "uuid": "..." }`.
- `POST /api/v1/services` : `type`, `name`, `project_uuid` (obligatorio),
  `environment_name` o `environment_uuid` (uno de los dos), `server_uuid`
  (obligatorio), `destination_uuid` (opcional), `instant_deploy` (por defecto `false`),
  `docker_compose_raw` (opcional).
- `POST /api/v1/deploy` : identificador `uuid` y/o `tag` en la consulta. Responde con
  `{ "deployments": [ { "message", "resource_uuid", "deployment_uuid" } ] }`.

### Por qué solo estas

- **Un proyecto por cliente.** Es la única forma de que los recursos de un cliente no
  aparezcan junto a los de otro dentro de la misma instancia de Coolify, que no es
  multiinquilino.
- **Crear y leer.** El portal arma el sistema nuevo y después informa. No administra lo
  que ya está andando: reinicios, respaldos y despliegue continuo siguen siendo trabajo
  de Hefesto.
- **Desplegar va aparte.** `POST /api/v1/services` deja `instant_deploy` en `false`, o
  sea que crear un servicio no lo pone en marcha. Ponerlo en marcha exige la habilidad
  `deploy`, que en Coolify también habilita reiniciar, detener y cancelar. Por eso el
  despliegue usa un **segundo token**, en `COOLIFY_TOKEN_DESPLIEGUE`, y está apagado con
  `COOLIFY_PERMITIR_DESPLIEGUE=false`.

## 4. Operaciones prohibidas

No son "todavía no implementadas". Son operaciones que **no se pueden pedir** desde este
portal. La lista completa, con motivo y enlace a la documentación oficial, está en
[`api/app/Enums/OperacionesProhibidas.php`](../api/app/Enums/OperacionesProhibidas.php)
y son 23 entradas. Resumen:

**Nunca se expone, bajo ningún circumstance:**

- Borrar un proyecto, un servicio, una aplicación o una base de datos.
- Reiniciar, detener o iniciar un servicio, una aplicación o una base de datos.
- Cancelar un despliegue o volver atrás con un `rollback`.
- Migrar un recurso a otro servidor, moverlo o clonarlo.
- Leer registros de un servicio, una aplicación o una base de datos.
- Leer o escribir variables de entorno.
- Crear claves privadas, tokens de nube o servidores nuevos.
- Ver los miembros del equipo o cambiar la configuración de la instancia.
- Crear una base de datos con contraseña (ver más abajo por qué).

### Cómo se hace cumplir

1. **El cliente no acepta una ruta.** `ClienteCoolify::ejecutar()` recibe un caso del enum
   `OperacionCoolify`, nunca una cadena. No hay ningún método público que reciba una URL,
   un método o un cuerpo. Agregar una operación nueva exige editar el enum, y eso se ve
   en la revisión de código.
2. **Barrido antes de salir.** Cada llamada pasa por
   `OperacionesProhibidas::motivoDelRechazo()`, que revisa el método y la ruta.
   `DELETE`, `PUT` y `PATCH` se rechazan siempre; una lista de fragmentos
   (`delete`, `restart`, `stop`, `logs`, `envs`, `members`, `settings`…) rechaza el resto.
3. **Patrón de ruta por operación.** Después de resolver los identificadores, la ruta
   final tiene que calzar con el patrón declarado por la operación. Un identificador que
   no sea UUID se rechaza antes de construir la dirección.
4. **Comprobación automática.** `php artisan portal:politica-coolify` imprime la lista
   completa y revisa la configuración. Y
   `python api/tests/estatico/politica_coolify.py` hace lo mismo leyendo el código, sin
   needing base de datos ni red.

### Por qué no hay un proxy

Un proxy que reenvía peticiones arbitrarias a la API de Coolify sería, en la práctica, un
panel de control de la plataforma de despliegue con la sesión del portal. Cualquier
error de autorización, o cualquier funcionalidad futura mal pensada, se convertiría en la
posibilidad de borrar la infraestructura de un cliente desde una página web.

El costo de esta decisión es que agregar operaciones es más lento. Es el costo correcto.

### Por qué no se crean bases de datos con contraseña

`POST /api/v1/databases/postgresql` acepta una contraseña en el cuerpo. Si el portal la
eligiera, tendría que generarla y transportarla hasta Coolify, y quedaría una credencial
en tránsito entre dos sistemas, además de en la base de datos interna de Coolify. El
portal no transporta secretos. La base de datos la crea una persona de Hefesto, que
guarda la contraseña en Infisical.

## 5. La credencial

### Habilidades que hay que pedir

| Habilidad | ¿Se pide? | Motivo |
| --- | --- | --- |
| `read` | Sí | Leer proyectos, servicios y despliegues |
| `write` | Sí | Crear el proyecto y el servicio del cliente |
| `deploy` | **No**, en la credencial principal | También habilita reiniciar, detener y cancelar |
| `read:sensitive` | **No** | Leería registros, claves privadas y valores de variables de entorno |
| `root` | **No** | Anula las comprobaciones de habilidad |

### Dónde vive

Solo en el contenedor de la API y en el del trabajador de colas. Nunca en el frontend,
nunca en el navegador, nunca en una respuesta de la API, nunca en el registro de trabajo.

El frontend de Nuxt no tiene ninguna variable con ese nombre, y
`python api/tests/estatico/politica_coolify.py` comprueba que el código de `app/Http/`
no la mencione.

### Registro sin revelarla

El registro guarda la **huella**: los primeros diez caracteres del resumen SHA-256 del
token. Permite distinguir la credencial nueva de la antigua en una auditoría, y no
permite autenticarse. Además, el objeto `TokenCoolify` implementa `__debugInfo()` y
`__toString()`, así que un `var_dump`, un volcado en consola o un rastro de excepción
muestran `[credencial de Coolify oculta]`.

### Protecciones que hay que activar en Coolify

Estas no se pueden hacer desde el portal:

1. Habilitar el acceso a la API en la instancia.
2. Restringir por lista de direcciones IP, dejando únicamente la salida del portal. En
   `deploy/compose.produccion.yaml` la API vive en una red sin salida a internet, y el
   contenedor que tiene la credencial es el único que puede salir de ella.
3. Revocar el token si el equipo que lo usa deja de necesitarlo.

## 6. Modo deshabilitado

Con `COOLIFY_HABILITADA=false`, que es el valor por defecto:

- La comprobación de salud (`/api/v1/health`) sí funciona, porque no usa credencial.
- Cualquier otra operación lanza una excepción y **no sale ninguna petición**.
- Los pasos quedan registrados como **omitidos**, con el motivo.
- La solicitud termina en estado `fallida`, no en `aprovisionada`.

No hay modo "simulado" que devuelva un UUID inventado. Un identificador falso en la base
sería peor que nada: alguien creería que hay un proyecto en Coolify y no lo hay.

## 7. Lo que falta verificar

**Ningún kit es aprovisionable todavía.** Los catorce kits del catálogo quedan con
`aprovisonable = false`.

Para crear un servicio, la API de Coolify necesita el valor `type` del servicio de un
solo clic, y ese valor no se pudo confirmar contra la instancia de Hefesto. La
documentación pública menciona algunos ejemplos (`actualbudget`, `calibre-web`,
`gitea-with-mysql`) y ninguno corresponde a un kit de Hefesto. Adivinar el valor sería
producir un fallo en producción, con la ventaja de crear proyectos vacíos en la
plataforma de un cliente.

**Lista de verificación para habilitar, en orden:**

1. En la instancia de Hefesto, confirmar el valor de `type` para cada kit que se quiera
   automatizar.
2. Cargarlo en la tabla `kits` (`coolify_tipo`, `coolify_imagen`, `aprovisonable`).
3. Configurar `COOLIFY_SERVIDOR_UUID` con el servidor destino.
4. Crear el token en Coolify con `read` y `write`, y **solo** ese.
5. Activar el acceso a la API y restringir por direcciones IP.
6. Poner `COOLIFY_HABILITADA=true`.
7. Probar con una empresa de prueba, revisar la bitácora de aprobaciones y el historial
   de pasos.
8. Recién entonces, evaluar `COOLIFY_PERMITIR_DESPLIEGUE` con su credencial separada.

## 8. Verificación automática incluida

```bash
# Lista completa de operaciones permitidas y prohibidas, más la configuración.
php artisan portal:politica-coolify

# La misma auditoría, leyendo el código, sin base de datos ni red.
python api/tests/estatico/politica_coolify.py
```

La segunda se ejecutó durante la construcción de este portal y dio correcto con 283
comprobaciones. Se verificó además que detecta una operación destructiva inyectada a
propósito en una copia del código. El detalle está en
[10-PRUEBAS-Y-ESTADO.md](10-PRUEBAS-Y-ESTADO.md).