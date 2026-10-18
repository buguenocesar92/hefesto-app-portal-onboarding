# 06. Instalación

**Nada de lo que está en este documento se ha ejecutado.** El entorno donde se construyó
el portal no tenía Docker, ni PHP, ni Composer. Los comandos son los que se seguirían y
están escritos para que sirvan, pero nadie los ha corrido todavía.

## Requisitos

| Componente | Versión | Nota |
| --- | --- | --- |
| Docker | 24 o superior | Con Compose v2 |
| PHP | 8.3 o superior | Solo dentro del contenedor |
| Composer | 2.8 | Solo dentro del contenedor |
| Node | 22 | Solo dentro del contenedor |
| PostgreSQL | 16.4 | El contenedor lo trae |
| Redis | 7.4 | El contenedor lo trae |

Todas estas versiones están fijadas en los Dockerfiles y en los archivos Compose.

## Desarrollo

```bash
# 1. Configuración.
cp .env.example .env

# 2. Generar la clave de la aplicación.
docker compose -f deploy/compose.desarrollo.yaml run --rm api php artisan key:generate

# 3. Levantar.
docker compose -f deploy/compose.desarrollo.yaml --env-file .env up -d

# 4. Migrar y sembrar.
docker compose -f deploy/compose.desarrollo.yaml exec api php artisan migrate --seed
```

El portal queda en `http://127.0.0.1:3000`. La API responde en
`http://127.0.0.1:8000/api/v1/salud`.

## Qué deja la semilla

| Dato | Valor |
| --- | --- |
| Empresa | Comercializadora de Prueba SpA (`pyme-demo`), dominio `pyme-demo.test` |
| Plan | `plan-inicial-demo`, con tres kits |
| Usuario cliente | `cliente@pyme-demo.test` |
| Usuario de Hefesto | `hefesto@ejemplo.test` (rol aprobador) |

**Estos usuarios no tienen contraseña.** La identidad viene de Authentik. Para probar el
flujo completo hay que tener una instancia de Authentik con el proveedor configurado, y
que el correo de la persona coincida con `AUTHENTIK_CORREOS_HFESTO` o con el
`dominio_correo` de una empresa.

Los correos usan el dominio `.test`, reservado para documentación: nunca resuelve en
internet.

## Producción

```bash
# 1. Configurar .env completo. Los campos obligatorios tienen el formato ${VAR:?texto},
#    y el Compose se niega a levantar si falta alguno.
cp .env.example .env

# 2. Revisar la política de Coolify antes de poner la credencial.
docker compose -f deploy/compose.produccion.yaml run --rm api php artisan portal:politica-coolify

# 3. Levantar.
docker compose -f deploy/compose.produccion.yaml --env-file .env up -d

# 4. Migrar y sembrar el catálogo.
docker compose -f deploy/compose.produccion.yaml exec api php artisan migrate --seed

# 5. Dejar correr el trabajador de colas.
docker compose -f deploy/compose.produccion.yaml exec api php artisan queue:work \
  --queue=aprovisionamiento,credenciales,notificaciones
```

En producción conviene dejar el trabajador como servicio, no como comando manual: el
Compose ya lo trae como `cola`, con reinicio automático.

## Servicios

| Servicio | Función | Puertos |
| --- | --- | --- |
| `proxy` | Caddy, certificates y enrutamiento | 80, 443 |
| `web` | Nuxt con renderizado en el servidor | Solo red interna |
| `api` | Laravel | Solo red interna |
| `cola` | Trabajador de trabajos | Ninguno |
| `tareas` | Tareas programadas | Ninguno |
| `postgres` | Base de datos | Ninguno |
| `redis` | Caché, sesiones y colas | Ninguno |

La red `interna` está marcada `internal: true`: sin salida a internet. Es lo que evita que
el contenedor con la credencial de Coolify quede expuesto, y también impide que un
contenedor comprometido en esa red llame a servicios externos por su cuenta.

## Configurar el acceso con Authentik

Sin esto, el portal no tiene entradas: `middleware('auth')` rechaza todo.

1. En Authentik, crear una aplicación de tipo **Proveedor OAuth2 / OpenID**.
2. Anotar el identificador del cliente y el secreto del cliente.
3. Declarar como **dirección de retorno** la que diga `AUTHENTIK_REDIRECT_URI`. En
   desarrollo, `http://127.0.0.1:3000/auth/callback`.
4. Habilitar los alcances `openid`, `profile` y `email`. Sin `email`, el portal no puede
   saber a qué empresa pertenece quien entra.
5. Anotar el identificador de la aplicación (el "slug"), que aparece en la ficha del
   proveedor y se usa en las rutas de cierre de sesión y claves públicas.
6. Completar en `.env`:

```bash
AUTHENTIK_URL=https://id.ejemplo.cl
AUTHENTIK_SLUG=portal-onboarding
AUTHENTIK_CLIENT_ID=...
AUTHENTIK_CLIENT_SECRET=...
AUTHENTIK_REDIRECT_URI=http://127.0.0.1:3000/auth/callback
```

7. Declarar el personal de Hefesto. **Esto es lo que decide quién puede aprobar**, así que
   deserves revisión antes de cada despliegue:

```bash
AUTHENTIK_CORREOS_HFESTO=persona@hefesto.cl,otra@hefesto.cl
AUTHENTIK_DOMINIOS_HFESTO=
```

Para cada empresa cliente, además, cargar su `dominio_correo`. Es lo que permite resolver
la empresa al entrar:

```sql
UPDATE empresas SET dominio_correo = 'miempresa.cl' WHERE nombre_corto = 'miempresa';
```

El dominio tiene que ser único en toda la instalación.

### Rutas verificadas

Verificadas contra la documentación oficial el 17-10-2026. Autorización, token e
información de usuario **no** llevan el identificador de la aplicación; sí lo llevan el
cierre de sesión, las claves públicas y la configuración OIDC.

| Punto | Ruta |
| --- | --- |
| Autorización | `/application/o/authorize/` |
| Token | `/application/o/token/` |
| Información de usuario | `/application/o/userinfo/` |
| Fin de sesión | `/application/o/<slug>/end-session/` |
| Claves públicas | `/application/o/<slug>/jwks/` |

Para confirmar contra una instancia real:

```bash
curl -s "https://id.ejemplo.cl/application/o/portal-onboarding/.well-known/openid-configuration" | jq .issuer
```

## Verificación posterior a la instalación

```bash
# ¿El portal responde?
curl -s http://127.0.0.1:8000/api/v1/salud

# ¿La política de Coolify está como dice la documentación?
php artisan portal:politica-coolify

# ¿El aislamiento en la base está realmente puesto?
php artisan portal:verificar-aislamiento

# ¿Las pruebas pasan?
php artisan test

# ¿La entrega está limpia (sin CJK, sin YAML inválido)?
python api/tests/estatico/comprobar_entrega.py
```

## Problemas frecuentes que no se pueden verificar todavía

Como nada de esto se ha ejecutado, **no se sabe qué falla**. Los puntos donde más
probablemente aparezcan problemas:

1. **El `composer install` dentro del Dockerfile** puede fallar si el `composer.lock` no
   está en el repositorio. La imagen tiene una alternativa sin `--no-dev` para el primer
   arranque, pero conviene versionar el lock.
2. **El proxy de Caddy** necesita el correo del administrador para los certificados. Sin
   `CORREO_ADMIN` en el entorno, no emite.
3. **El comprobación de salud de la API** en producción apunta a `php-fpm` en el puerto
   9000, que no es un puerto HTTP. Hay que ajustarlo a como se exponga la API.
4. **El flujo de autenticación** no se ha probado contra una instancia de Authentik: las
   rutas están verificadas contra la documentación oficial, no contra una respuesta real.
5. **La seguridad de fila** no se ha ejecutado nunca. Lo primero después de migrar es
   `php artisan portal:verificar-aislamiento`.

Los cuatro están anotados como pendientes en
[10-PRUEBAS-Y-ESTADO.md](10-PRUEBAS-Y-ESTADO.md).