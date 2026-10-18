# 03. Aprovisionamiento

## El recorrido completo

```
PyME                  Hefesto                    Coolify        Infisical    ntfy
  │                     │                           │               │           │
  ├─ 1. se registra ───►│                           │               │           │
  ├─ 2. elige plan ─────►│                           │               │           │
  ├─ 3. crea solicitud ─►│                           │               │           │
  │                     ├─ 4. revisa y aprueba ────►│               │           │
  │                     │   (bitácora + operaciones)│               │           │
  │                     ├─ 5. trabajo encolado ─────►│               │           │
  │                     │      paso 1: proyecto      │               │           │
  │                     ├──────────────────────────►│ crear proyecto │           │
  │                     │      paso 2: servicio      │               │           │
  │                     ├──────────────────────────►│ crear servicio │           │
  │                     │      paso 3: despliegue    │               │           │
  │                     ├──────────────────────────►│ (fase 2)      │           │
  │                     ├─ 6. entrega de claves ────────────────────►│           │
  │◄─ 7. ve el avance ──┤                           │               │           │
  │◄──────────────────────────────────────────────────────────────────────────┤
```

## La aprobación humana, en detalle

No es un campo que alguien pueda cambiar por error. `Solicitud::puedeEjecutarse()`
exige **tres cosas a la vez**:

1. Que el estado sea `aprobada`.
2. Que exista `aprobada_en` y `aprobada_por`.
3. Que exista una fila en `bitacora_aprobaciones` con decisión `aprobada`.

Si alguien fuerza el estado a mano en la base y deja las otras dos columnas vacías, el
trabajo se ejecuta, comprueba, y no toca Coolify. Está cubierto por la prueba
`sin_aprobacion_registrada_el_trabajo_no_toca_coolify`.

Antes de aprobar, la pantalla muestra **qué operaciones exactas** se van a ejecutar, con
método, ruta, motivo y enlace a la documentación oficial. La persona que aprueba aprueba
esas llamadas concretas, no "el onboarding" en abstracto.

Reglas adicionales:

- Solo `aprobador` y `administrador` pueden aprobar.
- Quien creó la solicitud no puede aprobarla, salvo que Hefesto active
  `PERMITIR_AUTOAPROBACION=true` (por defecto en `false`).
- Aprobar sin marcar ninguna operación ejecutable es un error, no una ejecución vacía.

## Los pasos y cómo se registran

Cada paso es una fila en `pasos_aprovisionamiento`, y su nombre **siempre** es un caso
del enum `OperacionCoolify`. No hay campo libre para el nombre del paso: si no está en el
enum, no se puede registrar, y si no se puede registrar no se ejecutó.

| Orden | Paso | Qué hace | Resultado guardado |
| --- | --- | --- | --- |
| 1 | `crear_proyecto` | Crea un proyecto exclusivo del cliente | UUID del proyecto |
| 2 | `crear_servicio` | Crea el servicio del kit dentro del proyecto | UUID del servicio |
| 3 | `desplegar` | Pone en marcha el servicio | **Omitido en esta versión** |
| 4 | (otro trabajo) | Registra la entrega de credenciales | Ruta en Infisical |

El nombre del proyecto en Coolify es `pyme-<identificador>-<nombre_corto>`. Lleva el
identificador de la empresa para que dos clientes con nombres parecidos nunca compartan
proyecto.

## Qué pasa cuando la integración está apagada

Con `COOLIFY_HABILITADA=false`:

1. El paso 1 lanza una excepción de "operación deshabilitada" y queda **omitido** con el
   motivo.
2. El recorrido se detiene.
3. La solicitud termina en `fallida`, no en `aprovisionada`.
4. La pantalla de seguimiento lo dice: "La preparación se detuvo en un paso. Hefesto lo
   está revisando; tus datos están a salvo."

No hay modo simulado que devuelva un UUID inventado. Un UUID falso en la base sería peor
que nada: alguien creería que hay un proyecto en Coolify y no lo hay.

## El paso 3: despliegue

`POST /api/v1/services` deja `instant_deploy` en `false`, o sea que crear un servicio no
lo pone en marcha. Ponerlo en marcha requiere `POST /api/v1/deploy` y la habilidad
`deploy` de Coolify, que también habilita **reiniciar, detener y cancelar**.

Decisión: el despliegue queda en una fase posterior, apagado con
`COOLIFY_PERMITIR_DESPLIEGUE=false`, y cuando se habilite usará un token aparte
(`COOLIFY_TOKEN_DESPLIEGUE`). Es una credencial más que mantener, y a cambio la principal
no puede detener nada.

Mientras tanto, el paso queda registrado como **omitido** con su motivo. El registro
existe para que nadie asuma que se hizo.

## Entrega de credenciales

**Estado: deshabilitada.** Ver la sección siguiente.

Las reglas que el código ya respeta, y que se pueden verificar leyendo:

- La credencial se genera en memoria, viaja a Infisical y se descarta.
- En la tabla `entregas_credenciales` **no hay columna para el valor**. Hay columna para
  la ruta, la clave, el proyecto y el entorno.
- Con la integración apagada, la entrega queda en estado `pendiente`, no `registrado`. El
  cliente ve "pendiente", no un visto verde.
- El portal nunca transporta un secreto hacia Coolify, y por eso no expone las
  operaciones de variables de entorno.

### Qué se verificó de Infisical

| Dato | Estado |
| --- | --- |
| Base: `https://<instancia>/api` | Verificado |
| Cada endpoint tiene su propia versión en la ruta | Verificado |
| Cada instancia pública su OpenAPI en `/api/docs/json` | Verificado |
| Existe autenticación con identidades de máquina | Verificado |
| **Ruta exacta de creación de un secreto** | **No verificado** |
| **Cabecera exacta de autenticación** | **No verificado** |

La página del endpoint no respondió durante la sesión. En vez de escribir una ruta
inventada que fallaría con un 404 difícil de interpretar en producción, el cliente se
niega a correr hasta que alguien confirme la ruta contra el OpenAPI de la instancia y
ponga `INFISICAL_RUTA_VERIFICADA=true`.

## Notificaciones por ntfy

Se avisa al cliente en tres momentos: al empezar, al terminar y si falla.

El **tema de ntfy es una contraseña**, porque ntfy no tiene registro y cualquiera que lo
adivine puede suscribirse. Por eso:

- Se genera al azar: `hefesto-` seguido de 32 caracteres hexadecimales.
- No se deriva del nombre ni del identificador de la empresa.
- En la base va cifrado, y además se guarda su resumen SHA-256 para detectar que alguien
  lo editó a mano. Si el valor no calza con su resumen, el aviso no sale.

El formato está verificado contra la documentación oficial: `POST` al tema, cuerpo de
texto y las cabeceras `Title`, `Priority` y `Tags`.

## Reintentos

- Fallo de red o límite de peticiones: reintenta hasta dos veces, esperando cada vez más,
  respetando `Retry-After` cuando Coolify lo envía, con un salto aleatorio para que varias
  tareas no repitan a la vez.
- Cualquier otro error (`4xx` que no sea `429`): no se reintenta. Si Coolify dice que el
  cuerpo es inválido, repetir lo mismo solo gasta el límite.
- Un trabajo no reintenta solo más de una vez. Si un paso falla, la solicitud queda en
  `fallida` con el motivo y la reintenta una persona de Hefesto, que sabe si el problema
  se puede repetir sin duplicar recursos.