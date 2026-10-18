# 10. Pruebas y estado

## Estado en una línea

**Código escrito, sin ejecutar.**

En el entorno donde se construyó este portal **no había Docker, ni PHP, ni Composer**,
ni PostgreSQL, ni una instancia de Coolify, de Infisical ni de Authentik. Nada de lo que
hay en `api/` y `web/` se ha levantado ni se ha conectado a un sistema real.

Lo que sí se comprobó está en la sección 3. Lo que sigue sin probarse, en la sección 5.

---

## 1. Qué cambió en la segunda ronda de trabajo

| Cambio | Antes | Ahora |
| --- | --- | --- |
| Aislamiento entre empresas | Solo en la aplicación (alcance global y políticas) | **Además en la base**, con seguridad de fila de PostgreSQL |
| Acceso | No implementado: `middleware('auth')` rechazaba todo | **Implementado** por OIDC contra Authentik, con PKCE |
| Verificaciones estáticas | Política de Coolify y limpieza de la entrega | Se agregaron comprobaciones de RLS y de autenticación |
| Documentación | Declaraba ambas carencias como riesgo abierto | Describen lo implementado y lo que falta |

**Lo que no cambió, a propósito:**

- La política de Coolify: las mismas 8 operaciones permitidas y las 23 prohibidas, sin
  proxy. Está bien resuelta y no se tocó.
- La integración de Coolify y la de Infisical **siguen desactivadas**, con la misma
  explicación: falta verificar un parámetro de cada una contra las instancias reales.
- La discrepancia de la documentación de Coolify (`/api/v1/team` frente a
  `/api/v1/teams/current`) queda como estaba.
- El control negativo de la política de Coolify se conserva, y se volvió a correr.

## 2. Lo que NO se pudo probar, y por qué

| Prueba | Motivo |
| --- | --- |
| Suite de PHP (`php artisan test`) | No hay PHP ni Composer |
| Migraciones, incluida la de seguridad de fila, contra PostgreSQL | No hay PostgreSQL |
| `php artisan portal:verificar-aislamiento` | Necesita PostgreSQL |
| Semilla ejecutada | Necesita PostgreSQL |
| Levantar el Compose | No hay Docker |
| Compilar el frontend de Nuxt | No se instaló ninguna dependencia |
| Acceso real contra Authentik | No hay instancia |
| Cliente de Coolify contra una instancia real | No hay instancia |
| Cliente de Infisical contra una instancia real | No hay instancia, y falta verificar la ruta |
| Publicación real en ntfy | No hay servidor |

**Ninguna prueba simulada se presenta como prueba real.**

## 3. Lo que SÍ se comprobó, de verdad

### 3.1 Documentación oficial descargada y leída

El 17 de octubre de 2026, antes de escribir una sola línea de cada cliente:

- **Coolify**: base `/api/v1`, cabecera `Authorization: Bearer`, las cinco habilidades de
  token, el límite de 200 peticiones por minuto, `Retry-After`, y las rutas y cuerpos de
  las ocho operaciones permitidas. Discrepancia encontrada entre la página del endpoint
  (`/api/v1/team`) y los ejemplos en prosa (`/api/v1/teams/current`): documentada, no
  resuelta por adivinanza.
- **Authentik**: la tabla de puntos de acceso del proveedor OAuth 2.0. Se verificó que
  `authorize`, `token` y `userinfo` **no** llevan el identificador de la aplicación, y que
  `end-session`, `jwks` y la configuración OIDC sí lo llevan.
- **Infisical**: base `/api`, versionado por endpoint, OpenAPI en `/api/docs/json`.
- **ntfy**: publicación con `POST` al tema y cabeceras `Title`, `Priority` y `Tags`.

### 3.2 Auditoría estática de la política de Coolify: **ejecutada y correcta**

```bash
python api/tests/estatico/politica_coolify.py
```

Última salida real:

```
Operaciones permitidas: 8
  GET   /health                    salud
  GET   /team                      equipo_autenticado
  POST  /projects                  crear_proyecto
  GET   /projects/{uuid}           leer_proyecto
  POST  /services                  crear_servicio
  GET   /services/{uuid}           leer_servicio
  GET   /deployments/{uuid}        leer_despliegue
  POST  /deploy                    desplegar
Operaciones declaradas como prohibidas: 23
Comprobaciones ejecutadas: 299

RESULTADO: correcto. Ninguna operación destructiva está expuesta.
```

El número de comprobaciones subió de 283 a 299 porque ahora revisa más archivos del
backend; la conclusión no cambió.

### 3.3 Control negativo de esa auditoría: **ejecutado y detectó la falla**

Se copió el código a un directorio temporal, se le agregó a propósito una operación
`DELETE /projects/{uuid}` al enum, y se volvió a correr:

```
Operaciones permitidas: 9
  ...
  DELETE /projects/{uuid}           borrar_proyecto

FALLARON 2 COMPROBACIONES:
  - La operación permitida 'borrar_proyecto' usa el método DELETE.
  - OperacionCoolify.php contiene una llamada con el verbo DELETE.
```

La copia se hizo en un directorio temporal y **no forma parte de la entrega**.

### 3.4 Verificación de la entrega: **ejecutada y correcta**

```bash
python api/tests/estatico/comprobar_entrega.py
```

Comprueba el barrido de CJK y cirílico, que los Compose sean YAML válido, que los JSON
sean válidos, que `.env.example` no traiga secretos reales, y ahora también:

- Que exista Row Level Security, con `WITH CHECK` y `FORCE ROW LEVEL SECURITY`.
- Que el contexto de empresa se fije con **alcance de transacción**.
- Que exista la migración que activa la seguridad de fila.
- Que exista la prueba de aislamiento a nivel de base de datos.
- Que existan las rutas de Authentik verificadas, y que ninguna lleve el identificador de
  la aplicación donde no corresponde.
- Que el frontend no mencione el secreto del cliente, y que la capa HTTP no lea variables
  de entorno directamente ni registre el secreto.

### 3.5 Barrido de caracteres, en dos pasadas

| Pasada | Archivos | Hallazgos |
| --- | --- | --- |
| 1 | 105 | 0 |
| 2 | Todos | 0 |

Herramientas: el verificador propio y, como control independiente, ripgrep con las clases
`\p{Han}`, `\p{Hiragana}`, `\p{Katakana}`, `\p{Hangul}` y `\p{Cyrillic}`.

### 3.6 Cobertura de la suite de PHP (escrita, no ejecutada)

| Archivo | Qué cubre |
| --- | --- |
| `tests/Unit/PoliticaCoolifyTest.php` | 6 pruebas y 10 casos de datos sobre la política |
| `tests/Feature/AislamientoEntreEmpresasTest.php` | 6 pruebas de aislamiento, capa de aplicación |
| `tests/Feature/Aislamiento/AislamientoEnBaseDeDatosTest.php` | 9 pruebas de aislamiento, **capa de base de datos** |
| `tests/Feature/AprobacionHumanaTest.php` | 6 pruebas de la barrera de aprobación |
| `tests/Feature/CredencialNoExpuestaTest.php` | 4 pruebas de que las credenciales no salen |

**Ninguna se ejecutó**, porque no hay PHP ni PostgreSQL.

## 4. Lo primero que hay que hacer al recibir esto

En este orden:

1. `docker compose -f deploy/compose.desarrollo.yaml up -d` y ver si el stack levanta.
2. `php artisan migrate --seed`. **El SQL de las siete migraciones nunca fue validado por
   PostgreSQL.** Es el punto de mayor riesgo de la entrega.
3. `php artisan portal:verificar-aislamiento`. Confirma que la seguridad de fila quedó
   puesta y que el rol de la base no la salta.
4. `php artisan test`. La suite de aislamiento es la que más importa.
5. `php artisan portal:politica-coolify` y comparar la salida con
   [04-INTEGRACION-COOLIFY.md](04-INTEGRACION-COOLIFY.md).
6. Recién después, levantar Authentik y probar el acceso de punta a punta.

## 5. Pendientes, con el riesgo asociado

| # | Pendiente | Riesgo |
| --- | --- | --- |
| 1 | Validar las migraciones contra PostgreSQL real, incluida la de RLS | **Alto.** El SQL nunca corrió |
| 2 | Ejecutar la suite de aislamiento | **Alto.** La seguridad de fila está sin verificar en ejecución |
| 3 | Probar el acceso contra una instancia de Authentik | Medio. Las rutas están verificadas contra la documentación, no contra una respuesta real |
| 4 | Verificar el `type` de cada kit en la instancia de Coolify | **Alto.** Sin esto no hay aprovisionamiento automático |
| 5 | Límite de peticiones en las rutas de escritura y de acceso | Medio. Permite automatizar intentos de acceso |
| 6 | Verificar la ruta y la cabecera de autenticación de Infisical | Medio. La entrega de credenciales no funciona |
| 7 | Comprobación de salud de la API en producción | Medio. Apunta a un puerto que no es HTTP |
| 8 | Content-Security-Policy | Medio |
| 9 | Rotación de `APP_KEY` documentada | Medio. Sin ella, los temas de ntfy quedan ilegibles |
| 10 | Restauración probada desde cero | Medio. Un respaldo no verificado no es un respaldo |
| 11 | Implementar el despliegue automático (`POST /api/v1/deploy`) | Bajo. Hoy está apagado y así es correcto |
| 12 | Seguridad de fila sobre la tabla `empresas` | Bajo, aceptado y documentado |

## 6. Lo que este portal no es

- **No está listo para producción.** No se ha levantado nunca.
- **No atiende clientes reales.** No hay ninguna PyME detrás.
- **No aprovisiona nada todavía.** Los catorce kits están marcados como no
  automatizables, y es lo correcto: falta verificar un parámetro de la API.
- **No entrega credenciales todavía.** La integración con Infisical está apagada por
  falta de verificación de la ruta.
- **No se ha probado el acceso contra Authentik.** Está implementado y sus rutas están
  verificadas contra la documentación oficial, pero ninguna respuesta real lo ha cruzado.

Un onboarding que se ve completo y no aprovisiona nada es peor que uno que dice
"integración pendiente". Este dice integración pendiente, en el README, en la
documentación, y en la propia pantalla del cliente.

---

## Los dos roles de PostgreSQL: escritos, no ejecutados

La separación entre el rol de arranque (`portal_onboarding_admin`) y el rol de
la aplicación (`portal`) está escrita en `compose.produccion.yaml`, en
`deploy/postgres/inicializar-rol.sh` y en
[05-SEGURIDAD.md](05-SEGURIDAD.md). **No se ha ejecutado ninguna vez.**

En el equipo donde se escribió este cambio no había PostgreSQL ni Docker. Queda
pendiente:

1. Levantar el `compose` con el volumen de PostgreSQL vacío y confirmar que el
   rol `portal` se crea sin error.
2. Confirmar con `SELECT rolname, rolsuper, rolbypassrls FROM pg_roles` que el
   rol de la conexión no es superusuario ni salta la RLS.
3. Correr `php artisan portal:verificar-aislamiento` y confirmar que sale con
   código 0.
4. Probar el escenario que motiva todo el cambio: una empresa viendo el
   aprovisionamiento de otra.

Lo único verificado es estático: `bash -n` pasa sobre el script y el `compose`
es YAML válido, ambas cosas comprobadas por `tools/validar_apps.py`. El
razonamiento se apoya en la documentación oficial de PostgreSQL 18, no en una
ejecución.