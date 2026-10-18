# Portal de Onboarding y Aprovisionamiento

**Hefesto Studio** — donde una PyME se suscribe, elige lo que necesita, y Hefesto le
prepara su infraestructura.

> **Estado: código escrito, sin ejecutar.**
> En el entorno donde se construyó este portal no había Docker, ni PHP, ni Composer, ni
> una instancia de Coolify, ni una de Infisical. **Nada de lo que hay aquí se ha
> levantado ni se ha conectado a un sistema real.**
>
> **La integración con Coolify está DESHABILITADA** y **la de Infisical también**. No
> porque fallen, sino porque falta verificar un parámetro cada una contra las
> instancias reales de Hefesto. Es preferible entregar aprovisionamiento deshabilitado
> que un endpoint inventado que falla en producción.
>
> **Este portal no está listo para producción ni para atender clientes reales.** El
> detalle de qué se comprobó y qué no está en
> [`docs/10-PRUEBAS-Y-ESTADO.md`](docs/10-PRUEBAS-Y-ESTADO.md).

---

## Qué problema resuelve

Hefesto ya tiene catorce kits de despliegue listos. Hoy, para contratar una PyME nueva,
alguien de Hefesto hace todo a mano: crea la cuenta, elige el kit, configura el servidor y
entrega las credenciales.

Eso son horas por cliente. **El retorno de este portal no está en la PyME: está en que
Hefesto deja de gastar horas en trabajo repetitivo.**

## Qué es y qué no es

**Es** software original escrito por Hefesto: una aplicación propia, multi-cliente.

**No es** un kit de despliegue de una herramienta de terceros. Usa Coolify, Infisical,
Authentik y ntfy porque Hefesto ya los tiene desplegados, y se apoya en ellos por API.

**No es** un panel de control. No borra, no detiene, no reinicia. Ver más abajo.

## El riesgo número uno, y cómo se contiene

Este proyecto se diferencia de los otros en que **sí necesita una credencial de
administrador**: la API de Coolify da control sobre toda la plataforma de despliegue de
Hefesto.

> La credencial de Coolify otorga control sobre toda la plataforma de despliegue.
> **Manipúlela con extremo cuidado**, y estas son las reglas concretas:

1. **Solo vive en el backend.** Nunca llega al navegador, nunca se expone por una API
   propia, nunca se registra. Los registros guardan su huella: diez caracteres del
   resumen SHA-256.
2. **Solo se llama a una lista acotada y fija de operaciones.** Ocho, escritas en un
   enum revisable. **No hay ningún proxy genérico** que reenvíe peticiones arbitrarias a
   la API: eso convertiría al portal en un acceso sin restricciones a la plataforma de
   despliegue.
3. **Toda operación destructiva queda no expuesta**, y documentada como tal. Borrar,
   detener, reiniciar, cancelar, migrar, leer registros o leer variables de entorno no se
   pueden pedir desde este portal. Son 23 entradas en la lista de prohibidas, cada una con
   su motivo y su enlace a la documentación oficial.
4. **Se comprueba automáticamente.** `python api/tests/estatico/politica_coolify.py`
   audita el código y falla si aparece una operación destructiva. Y
   `php artisan portal:politica-coolify` imprime la política completa en el servidor.

| Permitidas | Método | Ruta |
| --- | --- | --- |
| Comprobar disponibilidad | `GET` | `/api/v1/health` |
| Verificar el equipo de la credencial | `GET` | `/api/v1/team` |
| Crear el proyecto del cliente | `POST` | `/api/v1/projects` |
| Leer el proyecto creado | `GET` | `/api/v1/projects/{uuid}` |
| Crear el servicio contratado | `POST` | `/api/v1/services` |
| Leer el estado del servicio | `GET` | `/api/v1/services/{uuid}` |
| Leer el resultado del despliegue | `GET` | `/api/v1/deployments/{uuid}` |
| Disparar el despliegue (deshabilitada) | `POST` | `/api/v1/deploy` |

El detalle completo, con el motivo de cada decisión y lo que falta verificar, está en
[`docs/04-INTEGRACION-COOLIFY.md`](docs/04-INTEGRACION-COOLIFY.md).

## Aislamiento entre clientes

Dos capas, y la segunda está en la base de datos.

La primera vive en la aplicación: el middleware fija la empresa del usuario y el modelo
exige el filtro. La segunda vive en **PostgreSQL**, con seguridad de fila: políticas con
`WITH CHECK` para proteger también la escritura, `FORCE ROW LEVEL SECURITY` para que la
política aplique al dueño de la tabla, y la empresa viajando con **alcance de
transacción**, para que un trabajo encolado no arrastre el contexto de otra empresa. La
función de contexto **lanza excepción** cuando no hay contexto: falla cerrado, no devuelve
cero filas en silencio.

Es el mismo patrón que ya usa el Portal de Autoservicio, con su misma justificación, y hay
una prueba dedicada que demuestra que **la base de datos** bloquea la lectura cruzada, no
solo la aplicación.

## Aprobación humana: es diseño, no un detalle

**El portal no crea infraestructura sin que una persona de Hefesto lo autorice.**

No basta con que el estado sea "aprobada". Hace falta una fila en la bitácora con
persona, fecha y la lista exacta de operaciones de Coolify que se autorizan. Si alguien
fuerza el estado a mano en la base, el trabajo se comprueba y no toca Coolify. Hay una
prueba dedicada a esto.

La pantalla de aprobación muestra, antes de confirmar, cada operación con su método, su
ruta, por qué está permitida y el enlace a la documentación oficial. Quien aprueba no
aprueba "el onboarding": aprueba esas llamadas concretas.

## Las credenciales de los clientes viven en Infisical

**Nunca en la base de datos de este portal.** La credencial se genera, viaja a Infisical
y se descarta. Aquí queda solo dónde quedó, cuándo y con qué clave. La tabla no tiene
columna para el valor, y no se debe agregar.

Con la integración apagada, la entrega queda en estado `pendiente`: el cliente ve que
falta, no un visto verde.

## Stack

- **Backend:** Laravel 12 con PostgreSQL 16.
- **Frontend:** Nuxt 4 con renderizado en el servidor, sobre Node 22.
- **Colas y caché:** Redis 7. El aprovisionamiento va encolado, nunca bloqueando una
  petición web.
- **Despliegue:** Docker Compose con versiones fijadas.
- **Identidad:** Authentik por OIDC, con flujo de código de autorización y PKCE. El portal
  no guarda contraseñas y el token de acceso se descarta en el servidor después de leer la
  información del usuario.

## Estructura

```
portal-onboarding/
├── README.md
├── .env.example          # Plantilla, sin ningún secreto real
├── docs/                 # 01 a 10
├── api/                  # Laravel
│   ├── app/Enums/        # operaciones permitidas y prohibidas
│   ├── app/Servicios/    # clientes de Coolify e Infisical
│   └── tests/            # Unit, Feature y comprobaciones estáticas
├── web/                  # Nuxt con SSR
└── deploy/               # Compose de desarrollo y producción, Dockerfiles
```

## Documentación

| Documento | Qué resuelve |
|---|---|
| [01 Arquitectura](docs/01-ARQUITECTURA.md) | Cómo se arma y por qué así |
| [02 Catálogo y planes](docs/02-CATALOGO-Y-PLANES.md) | Los catorce kits, los precios y por qué ninguno es automatizable todavía |
| [03 Aprovisionamiento](docs/03-APROVISIONAMIENTO.md) | El recorrido, los pasos y la entrega de credenciales |
| [04 Integración Coolify](docs/04-INTEGRACION-COOLIFY.md) | Qué se puede hacer, qué no, y qué falta verificar |
| [05 Seguridad](docs/05-SEGURIDAD.md) | Las cuatro capas, el aislamiento y lo que falta |
| [06 Instalación](docs/06-INSTALACION.md) | Levantar el stack |
| [07 Configuración](docs/07-CONFIGURACION.md) | Cada variable y qué pasa si se configura mal |
| [08 Respaldos](docs/08-RESPALDOS.md) | Qué se respalda y qué no |
| [09 API](docs/09-API.md) | Endpoints y ejemplos |
| [10 Pruebas y estado](docs/10-PRUEBAS-Y-ESTADO.md) | Qué se comprobó y qué no |

## Inicio rápido

Requiere Docker y Docker Compose.

```bash
cp .env.example .env
# Completar APP_KEY, DB_PASSWORD y REDIS_PASSWORD

docker compose -f deploy/compose.desarrollo.yaml --env-file .env up -d
docker compose -f deploy/compose.desarrollo.yaml exec api php artisan migrate --seed
```

El portal queda en `http://127.0.0.1:3000`.

**Nota:** estos comandos están escritos y son los que se seguirían, pero **no se han
ejecutado**. Ver [`docs/06-INSTALACION.md`](docs/06-INSTALACION.md).

## Verificación automática

```bash
# Auditoría de la política de Coolify, sin base de datos ni red.
python api/tests/estatico/politica_coolify.py

# Barrido de caracteres CJK y cirílicos, YAML, JSON, .env.example, RLS y autenticación.
python api/tests/estatico/comprobar_entrega.py

# La política completa de Coolify en el servidor.
php artisan portal:politica-coolify

# El aislamiento en la base: rol sin BYPASSRLS, RLS forzada, contexto que no queda colgando.
php artisan portal:verificar-aislamiento
```

Las dos primeras **se ejecutaron** durante la construcción y dieron correcto. Las dos
últimas necesitan el entorno levantado.

## Lo que falta, en orden

1. **Validar las migraciones contra PostgreSQL real**, incluida la de seguridad de fila. El
   SQL nunca corrió.
2. **Ejecutar la suite de pruebas**, en particular la de aislamiento en la base de datos.
3. **Probar el acceso contra una instancia de Authentik.** Está implementado y sus rutas
   están verificadas contra la documentación oficial, pero ninguna respuesta real lo ha
   cruzado.
4. **Verificar el parámetro `type` de cada kit** en la instancia de Coolify. Sin eso, no
   hay aprovisionamiento automático.
5. **Verificar la ruta de creación de secretos en Infisical.** Sin eso, no hay entrega de
   credenciales.

El detalle está en
[`docs/10-PRUEBAS-Y-ESTADO.md`](docs/10-PRUEBAS-Y-ESTADO.md).

## Licencia

Software propietario de Hefesto Studio. Laravel y Nuxt son MIT. Coolify es Apache-2.0.
Infisical es MIT en su núcleo, con licencia propia en el directorio `ee/`.