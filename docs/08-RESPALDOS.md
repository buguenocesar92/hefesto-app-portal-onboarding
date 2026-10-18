# 08. Respaldos

## Qué se respalda

| Cosa | Dónde | Qué guarda | Qué NO guarda |
| --- | --- | --- | --- |
| Base del portal | PostgreSQL | Empresas, usuarios, catálogo, solicitudes, pasos, entregas, bitácora | **Ninguna credencial de cliente** |
| Redis | Archivo `appendonly` | Sesiones, caché, estado de las colas | Nada que no sea reproducible |
| Configuración | Fuera del portal | `.env` del servidor | — |

### Lo que este respaldo NO contiene

**Las credenciales de los clientes no están en esta base.** Viven en Infisical, que se
respalda por separado. Es la razón principal por la que este respaldo se puede compartir,
  auditar o guardar sin que sea un documento secreto.

### Lo que este respaldo SÍ contiene, y hay que proteger

- El tema de ntfy de cada empresa, cifrado con `APP_KEY`. Sin `APP_KEY`, es texto inútil;
  con `APP_KEY`, es la contraseña de las notificaciones de un cliente. **`APP_KEY` y el
  respaldo son un solo secreto.**
- Los identificadores de Coolify de cada cliente. No son secretos, pero son información
  interna.
- Los correos y nombres de contacto de los clientes. Datos personales: su respaldo tiene
  que estar bajo el mismo cuidado que cualquier dato de clientes.

## Procedimiento

Nada de esto se ha ejecutado. El comando es el estándar de PostgreSQL.

```bash
# Respaldo.
docker compose -f deploy/compose.produccion.yaml exec -T postgres \
  pg_dump -U portal -Fc portal_onboarding > respaldos/portal-$(date +%F).dump

# Verificar que el archivo se puede leer.
pg_restore --list respaldos/portal-$(date +%F).dump > /dev/null

# Restauración, en una base vacía.
docker compose -f deploy/compose.produccion.yaml exec -T postgres \
  pg_restore -U portal -d portal_onboarding --clean --if-exists < respaldos/portal-AAAA-MM-DD.dump
```

El directorio `respaldos/` está montado en el contenedor de PostgreSQL en el Compose de
producción.

## Copia de `APP_KEY` y del `.env`

**Por separado, y en un lugar distinto al del respaldo de la base.** Son dos piezas que
solo juntas sirven:

- El respaldo de la base, sin `APP_KEY`, no entrega los temas de ntfy.
- `APP_KEY` sin el respaldo no sirve de nada.

Guardarlas juntas es lo mismo que no separarlas.

## Qué falta

| Falta | Por qué importa |
| --- | --- |
| **Restauración probada desde cero** | Un respaldo no verificado no es un respaldo. Es el pendiente número uno |
| Copia automática fuera del servidor | Hoy el respaldo vive en el mismo servidor que los datos |
| Copia del `.env` en gestor de secretos | Hoy el `.env` vive en el servidor y en el disco |
| Respaldo de Infisical | Lo hace Hefesto con el kit de secretos, fuera de este portal |

La diferencia con el caso de Infisical es importante: allí la clave de cifrado
(`ENCRYPTION_KEY`) es irrecuperable y sin ella el respaldo es texto cifrado inservible.
En este portal el equivalente es `APP_KEY`, y por eso se trata con el mismo cuidado.

## Qué hacer al restaurar

1. Restaurar la base.
2. Cargar el mismo `APP_KEY` con el que se respaldó. Si no, los temas de ntfy quedan
   ilegibles y las notificaciones dejan de salir.
3. Correr `php artisan migrate --status` para confirmar que no falta ninguna migración.
4. **No** correr `migrate --seed`: la semilla sobrescribe datos de demostración que
   podrían haber editado.
5. Revisar `php artisan portal:politica-coolify` para confirmar que la configuración de
   Coolify sigue apagada o encendida como corresponde.
6. Verificar contra una empresa de prueba antes de recibir clientes.