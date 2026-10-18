/**
 * Cliente de la API de Laravel.
 *
 * Dos reglas que no se rompen en ningún lado del frontend:
 *
 *  1. Nunca se manda la credencial de Coolify. Este frontend no la tiene ni la pide. La
 *     autenticación es la sesión que entrega el servidor, y las escrituras viajan con
 *     el token CSRF que entrega la propia API.
 *  2. No existe aquí ninguna función que acepte una ruta arbitraria hacia otro sistema.
 *     Las funciones son las del portal, una por una.
 */

interface Kit {
  codigo: string
  nombre: string
  categoria: string
  resumen: string
  que_hace: string
  requisitos: string[]
  incluye: string[]
  licencia: string | null
  aprovisonable: boolean
  motivo_no_aprovisonable: string | null
}

interface Plan {
  codigo: string
  nombre: string
  descripcion: string
  precio_texto: string
  precio_es_marcador: boolean
  kits: Array<{ codigo: string; nombre: string; aprovisonable: boolean }>
}

export const usePortalApi = () => {
  const configuracion = useRuntimeConfig()
  const base = String(configuracion.apiBase)

  /** Rutas del catálogo, sin sesión: es lo que se ve antes de registrarse. */
  const catalogo = () =>
    $fetch<{ kits: Kit[] }>(`${base}/api/v1/catalogo/kits`)

  const planes = () =>
    $fetch<{ planes: Plan[]; nota: string }>(`${base}/api/v1/catalogo/planes`)

  const solicitudes = () =>
    $fetch<{ solicitudes: unknown[] }>(`${base}/api/v1/solicitudes`, {
      headers: { Accept: 'application/json' },
    })

  const solicitud = (id: number | string) =>
    $fetch<{ solicitud: Record<string, unknown> }>(`${base}/api/v1/solicitudes/${id}`, {
      headers: { Accept: 'application/json' },
    })

  return { catalogo, planes, solicitudes, solicitud }
}