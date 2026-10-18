/**
 * Configuración de Nuxt para el Portal de Onboarding de Hefesto.
 *
 * Decisión importante: este frontend NO habla nunca con Coolify, ni tiene la credencial
 * para hablar con él. Habla con la API de Laravel, y Laravel es el único que habla con
 * Coolify. La credencial de la plataforma de despliegue no sale del backend bajo
 * ninguna circunstancia, y este archivo es parte de la prueba: la configuración
 * pública no contiene ningún secreto.
 */

export default defineNuxtConfig({
  // Renderizado en el servidor: el catálogo y el estado de una solicitud deben verse
  // completos antes de que se ejecute JavaScript en el navegador.
  ssr: true,

  compatibilityDate: '2025-01-01',

  devtools: { enabled: false },

  runtimeConfig: {
    // Variable de entorno del servidor. Es la única forma de apuntar a la API, y
    // nunca se expone al navegador.
    apiBase: process.env.NUXT_API_BASE || 'http://127.0.0.1:8000',

    public: {
      nombrePortal: 'Portal de Onboarding de Hefesto',
      avisoAprobacion:
        'Una persona de Hefesto revisa y aprueba cada preparación antes de que se toque algún servidor.',
    },
  },

  app: {
    head: {
      htmlAttrs: { lang: 'es-CL' },
      title: 'Portal de Onboarding de Hefesto',
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        {
          name: 'description',
          content: 'Contrata y sigue la preparación de tu infraestructura con Hefesto Studio.',
        },
      ],
    },
  },

  nitro: {
    // La API de Laravel y este frontend comparten una red interna en producción.
    // En desarrollo se llama por el nombre del servicio.
    compressPublicAssets: true,
  },
})