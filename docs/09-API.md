# 09. API

Base: `/api/v1`. Todas las respuestas son JSON. La autenticación es la sesión que entrega
el servidor, con verificación CSRF en las escrituras.

## Zonas

| Zona | Ruta | Sesión | Rol |
| --- | --- | --- | --- |
| Acceso | `/auth/*` | Parcial | Ninguno |
| Salud | `/api/v1/salud` | No | Ninguno |
| Catálogo | `/api/v1/catalogo/*` | No | Ninguno |
| Solicitudes | `/api/v1/solicitudes*` | Sí | Cliente de la empresa, o Hefesto |
| Aprobaciones | `/api/v1/aprobaciones*` | Sí | Solo Hefesto |

## Acceso

Rutas del navegador, en el grupo `web` (sesión con cookie y CSRF).

### `GET /auth/entrar`

Genera la marca de estado y el reto PKCE, los guarda en la sesión y redirige a Authentik.
Acepta `?destino=` para volver a una página concreta después de entrar.

### `GET /auth/callback`

Vuelve de Authentik con el código. Canjea el código, consulta la información del usuario,
resuelve la empresa y abre la sesión. Después redirige al destino guardado o a la raíz.

Rechaza el acceso si: la marca de estado no calza, Authentik devuelve un error, no hay
código, el correo no pertenece a ninguna empresa, o el personal de Hefesto no está
configurado.

### `POST /auth/salir`

Cierra la sesión, invalida la sesión y redirige al cierre de sesión de Authentik
(`/application/o/<slug>/end-session/`).

### `GET /auth/estado`

```json
{
  "identidad": "authentik",
  "listo": true,
  "mensaje": "El acceso se hace con la cuenta corporativa de Hefesto."
}
```

Cuando `listo` es `false`, el mensaje dice qué variable falta. La interfaz lo muestra en
lugar de fallar al intentar entrar.

### `GET /auth/quien-soy`

```json
{
  "autenticado": true,
  "nombre": "Persona de Contacto",
  "correo": "contacto@pyme-demo.test",
  "rol": "cliente",
  "es_hefesto": false
}
```

Devuelve únicamente datos de la sesión actual. Sin sesión: `{"autenticado": false}`.

## Salud

### `GET /api/v1/salud`

```json
{
  "estado": "ok",
  "servicio": "portal-onboarding",
  "version": "0.1.0"
}
```

## Catálogo

### `GET /api/v1/catalogo/kits`

Sin sesión. Devuelve los catorce kits activos.

```json
{
  "kits": [
    {
      "codigo": "hefesto-base-datos-interna",
      "nombre": "Base de Datos Interna",
      "categoria": "Negocio",
      "resumen": "Base de datos tipo planilla, autoalojada, sin límite de filas.",
      "que_hace": "Entrega una base de datos con interfaz propia y API...",
      "requisitos": ["PostgreSQL con la extensión pgvector si se usan funciones de similitud"],
      "incluye": ["Base de datos", "Interfaz de administración"],
      "licencia": "MIT en la edición abierta",
      "aprovisonable": false,
      "motivo_no_aprovisonable": "Este kit todavía no se automatizó en el portal. Lo prepara una persona de Hefesto."
    }
  ]
}
```

### `GET /api/v1/catalogo/planes`

```json
{
  "planes": [
    {
      "codigo": "plan-inicial-demo",
      "nombre": "Plan inicial (demostración)",
      "precio_texto": "Precio a confirmar con Hefesto",
      "precio_es_marcador": true,
      "kits": [
        { "codigo": "hefesto-vps-administrado", "nombre": "VPS Administrado", "aprovisonable": false }
      ]
    }
  ],
  "nota": "Los precios los mantiene Hefesto fuera del portal. Los valores aquí son marcadores."
}
```

## Solicitudes

### `GET /api/v1/solicitudes`

Devuelve hasta 50 solicitudes de la empresa del usuario. El personal de Hefesto ve todas.

### `POST /api/v1/solicitudes`

```json
{
  "plan_id": 1,
  "resumen": "Necesito la base de datos interna para ordenar los pedidos.",
  "dominio": "miempresa.cl",
  "contacto_tecnico": "persona@miempresa.cl"
}
```

Reglas: el plan tiene que existir y estar disponible; el plan tiene que traer al menos un
kit activo. El campo `datos` **no acepta credenciales**: si el cliente escribe una
contraseña en el formulario, no se guarda en esta base.

Respuesta `201`:

```json
{
  "solicitud": {
    "id": 1,
    "estado": "pendiente_de_revision",
    "estado_texto": "Esperando revisión de Hefesto",
    "explicacion": "Recibimos tu solicitud. Una persona de Hefesto la está revisando..."
  }
}
```

### `GET /api/v1/solicitudes/{id}`

Devuelve la solicitud con sus pasos y entregas. Los identificadores de Coolify
(`coolify_proyecto_uuid`, `coolify_servicio_uuid`) **solo aparecen para el personal de
Hefesto**: para un cliente vienen en `null`.

Una solicitud de otra empresa devuelve `404`, no `403`: no se confirma que exista.

### `DELETE /api/v1/solicitudes/{id}`

Cancela. No borra: deja el rastro.

## Aprobaciones (solo Hefesto)

### `GET /api/v1/aprobaciones`

Solicitudes esperando revisión, con los kits automatizables y su tipo en Coolify.

### `GET /api/v1/aprobaciones/{id}/previa`

**La pantalla de aprobación.** Devuelve exactamente qué se va a ejecutar:

```json
{
  "solicitud_id": 1,
  "puede_ejecutar": false,
  "tiene_aprobacion": false,
  "operaciones": [
    {
      "clave": "crear_proyecto",
      "etiqueta": "Crear el proyecto del cliente",
      "metodo": "POST",
      "ruta": "/projects",
      "porque": "Un proyecto nuevo por cliente es la única forma de que Coolify no mezcle sus recursos con los de otro.",
      "documentacion": "https://coolify.io/docs/api/endpoints/projects/create-project"
    },
    {
      "clave": "crear_servicio",
      "etiqueta": "Crear el servicio contratado",
      "metodo": "POST",
      "ruta": "/services",
      "porque": "Crear el servicio del kit contratado es el acto único de aprovisionamiento que este portal ejecuta.",
      "documentacion": "https://coolify.io/docs/api/endpoints/services/create-service"
    }
  ],
  "advertencia": "La integración con Coolify está apagada: aprobar creará la solicitud, pero no se ejecutará nada en la plataforma."
}
```

Lo que **no** aparece en esa lista, no se ejecuta. En particular nada borra, detiene ni
reinicia.

### `POST /api/v1/aprobaciones/{id}`

```json
{
  "operaciones": ["crear_proyecto", "crear_servicio"],
  "comentario": "Revisado: el kit es correcto para este cliente."
}
```

Rechaza con `422` si:

- Quien aprueba no tiene rol de aprobación.
- La solicitud no está en un estado aprobable.
- Quien aprueba es quien la creó (salvo `PERMITIR_AUTOAPROBACION=true`).
- Alguna operación no está en la lista permitida.
- Alguna operación elegida es de solo lectura.
- El plan no tiene ningún kit automatizable.
- No se marcó ninguna operación ejecutable.

Respuesta `200`:

```json
{
  "mensaje": "Solicitud aprobada y encolada.",
  "aprobacion": {
    "id": 1,
    "decision": "aprobada",
    "operaciones": ["crear_proyecto", "crear_servicio"],
    "fecha": "2026-10-17T14:03:00-03:00"
  }
}
```

### `POST /api/v1/aprobaciones/{id}/rechazar`

```json
{ "motivo": "Falta confirmar el dominio antes de preparar la infraestructura." }
```

## Errores

| Código | Cuerpo | Cuándo |
| --- | --- | --- |
| `401` | Sesión no válida o vencida | Sin autenticar |
| `403` | `{"mensaje": "..."}` | Rol insuficiente, o la acción no corresponde al estado |
| `404` | `{"message": "Resource not found."}` | No existe, o es de otra empresa |
| `422` | `{"mensaje": "Revisa los datos enviados.", "errores": {...}}` | Validación |
| `500` | `{"mensaje": "Ocurrió un error en el portal. Hefesto fue notificado."}` | Error no previsto |

Los errores `5xx` nunca incluyen el rastro interno. El detalle va al registro del servidor.

## Endpoints que NO existen

No hay ningún endpoint del portal que:

- Reenvíe peticiones a Coolify.
- Devuelva la credencial de Coolify, ni su huella.
- Devuelva una credencial de cliente.
- Cree, modifique o borre un recurso de Coolify fuera de las dos operaciones aprobadas.
- Devuelva el tema de ntfy de una empresa.

La lista completa de rutas está en `api/routes/api.php`. Si alguna vez aparece una ruta
que no esté en ese archivo, no pertenece a este portal.