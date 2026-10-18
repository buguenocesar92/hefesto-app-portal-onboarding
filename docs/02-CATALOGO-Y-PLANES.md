# 02. Catálogo y planes

## Los catorce kits

El catálogo del portal es el mismo de Hefesto. No hay fourteenth kit inventado ni
herramientas duplicadas.

| # | Código | Kit | Herramienta | Licencia |
| --- | --- | --- | --- | --- |
| 1 | `hefesto-facturacion-chile` | Facturación Chile | LibreDTE | AGPL-3.0 |
| 2 | `hefesto-apps-internas` | Apps Internas | Appsmith Community | Apache-2.0 |
| 3 | `hefesto-vps-administrado` | VPS Administrado | Coolify | Apache-2.0 |
| 4 | `hefesto-ia-aplicada` | IA Aplicada | Dify | Apache-2.0 modificada |
| 5 | `hefesto-observabilidad` | Observabilidad | SigNoz | MIT en la aplicación |
| 6 | `hefesto-respaldo-gestionado` | Respaldo Gestionado | Kopia | Apache-2.0 |
| 7 | `hefesto-antispam` | Antispam | Rspamd | Apache-2.0 |
| 8 | `hefesto-correo-corporativo` | Correo Corporativo | Mailu | MIT |
| 9 | `hefesto-base-datos-interna` | Base de Datos Interna | Baserow | MIT en la edición abierta |
| 10 | `hefesto-secretos` | Secretos | Infisical | MIT en el núcleo |
| 11 | `hefesto-observabilidad-ia` | Observabilidad de IA | Langfuse | MIT en el núcleo |
| 12 | `hefesto-identidad` | Identidad | Authentik | MIT |
| 13 | `hefesto-seguridad` | Seguridad | CrowdSec | MIT |
| 14 | `hefesto-acceso-remoto` | Acceso Remoto | Headscale | BSD-3-Clause |

Cada kit trae en el catálogo: un resumen de una línea, qué hace, qué requisitos tiene
(armas del servidor, dominio, certificados) y qué incluye.

Los riesgos conocidos de cada herramienta están en el `CATALOGO.md` del estudio y **no**
se repiten aquí: son responsabilidad de quien vende, y ya están documentados una vez.

## Precios: son marcadores

**El catálogo comercial lo mantiene Hefesto, no este portal.**

La tabla `planes` tiene `precio_mensual_clp`, y el valor por defecto de la semilla es `0`,
que significa "precio a confirmar con Hefesto". La interfaz muestra el texto del campo
`precio_nota` y, cuando el precio es cero, lo dice explícitamente con la etiqueta
`precio_es_marcador`.

No hay un precio real en este repositorio. Cuando Hefesto defina los precios, se cargan en
la tabla y la interfaz los muestra; el código no necesita cambiar.

## Planes

Un plan es un conjunto de códigos de kit más un nombre. La semilla trae un plan de
demostración con tres kits:

| Código | Nombre | Precio |
| --- | --- | --- |
| `plan-inicial-demo` | Plan inicial (demostración) | A confirmar con Hefesto |

Un plan puede estar `disponible`, `agotado` o `retirado`, y tener la marca `activo` en
falso para esconderlo sin borrarlo.

## Por qué ningún kit se puede automatizar todavía

Los catorce kits están en el catálogo con `aprovisonable = false`.

Razón: para que el portal cree un servicio, la API de Coolify necesita el valor `type`
del servicio de un solo clic. Ese valor **no se pudo verificar** contra la instancia de
Hefesto. La documentación pública nombra algunos ejemplos (`actualbudget`,
`calibre-web`, `gitea-with-mysql`), y ninguno corresponde a un kit de Hefesto.

Adivinarlo habría producido dos males: un error en producción que nadie sabría
interpretar, y proyectos vacíos creados en la plataforma de despliegue de un cliente
real. El catálogo muestra el kit con una etiqueta que dice "lo prepara una persona de
Hefesto", que es la verdad.

Para automatizar un kit:

```sql
UPDATE kits
SET coolify_tipo = 'valor-verificado-en-la-instancia',
    coolify_imagen = 'imagen:etiqueta',
    aprovisonable = true
WHERE codigo = 'hefesto-base-datos-interna';
```

## Requisitos que declara cada kit

Los requisitos son de información, no técnicos: el portal no valida que el servidor
tenga 8 gigabytes de memoria. La validación técnica es trabajo de Hefesto, y por eso
tampoco es automatizable.

| Kit | Requisitos que declara el catálogo |
| --- | --- |
| Facturación Chile | Certificados digitales del cliente, respaldo externo configurado |
| Apps Internas | Acceso a los datos que se quieran mostrar |
| VPS Administrado | Servidor con Ubuntu, dominio apuntando al servidor |
| IA Aplicada | Documentos para la base de conocimiento |
| Observabilidad | Métricas de uso estimadas |
| Respaldo Gestionado | Almacenamiento externo configurado |
| Antispam | Dominio con registros DNS configurados |
| Correo Corporativo | Dominio delegado, puerto 25 saliente habilitado por el proveedor |
| Base de Datos Interna | PostgreSQL con la extensión pgvector si se usan funciones de similitud |
| Secretos | Respaldo de la clave de cifrado, guardada aparte |
| Observabilidad de IA | Aplicación de IA ya configurada |
| Identidad | Acceso alternativo de emergencia documentado |
| Seguridad | Comprobación previa del acceso administrativo |
| Acceso Remoto | Versión fijada del cliente de red |

El caso del puerto 25 merece atención: los proveedores de servidor suelen bloquearlo, y
cuando lo hacen el servidor recibe pero no envía, sin error visible. El requisito está
declarado porque es la causa de soporte más común de "no nos llegan los correos".

## Cómo se actualiza el catálogo

El catálogo vive en la base, no en el código. Para cambiar un kit se edita la fila. La
semilla `CatalogoKitsSeeder` usa `updateOrCreate` sobre el código, así que se puede
volver a correr sin duplicar, y conviene correrla cuando cambie el catálogo del estudio.