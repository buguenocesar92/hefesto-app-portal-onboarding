<script setup lang="ts">
/**
 * Raíz del portal.
 *
 * El aviso sobre la aprobación humana está en la pantalla de inicio y no escondido en
 * una sección de términos: es la diferencia entre "el sistema hace esto solo" y "el
 * sistema hace esto y una persona lo autoriza".
 */
const configuracion = useRuntimeConfig()

// El acceso lo resuelve Authentik en el servidor. El frontend solo lleva a la persona
// hasta la ruta de entrada: no conoce credenciales ni token alguno.
const urlAcceso = computed(() => `${String(configuracion.apiBase)}/auth/entrar`)

useHead({
  titleTemplate: (titulo) => (titulo ? `${titulo} · Hefesto` : 'Portal de Onboarding de Hefesto'),
})
</script>

<template>
  <div class="portal">
    <header class="portal__cabecera">
      <NuxtLink to="/" class="portal__marca">Hefesto Studio</NuxtLink>
      <nav class="portal__navegacion">
        <NuxtLink to="/">Catálogo</NuxtLink>
        <NuxtLink to="/seguimiento">Seguimiento</NuxtLink>
        <a class="portal__acceso" :href="urlAcceso">Ingresar</a>
      </nav>
    </header>

    <main class="portal__contenido">
      <NuxtPage />
    </main>

    <footer class="portal__pie">
      <p>{{ configuracion.public.nombrePortal }}</p>
      <p class="portal__aviso">{{ configuracion.public.avisoAprobacion }}</p>
    </footer>
  </div>
</template>

<style>
:root {
  color-scheme: light dark;
  --borde: #d8d8d8;
  --fondo: #fbfbfa;
  --texto: #1d1d1b;
  --acento: #14532d;
}

.portal {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  font-family: ui-sans-serif, system-ui, "Segoe UI", sans-serif;
  color: var(--texto);
  background: var(--fondo);
}

.portal__cabecera {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--borde);
}

.portal__marca {
  font-weight: 700;
  color: var(--acento);
  text-decoration: none;
}

.portal__navegacion {
  display: flex;
  gap: 1rem;
}

.portal__contenido {
  flex: 1;
  width: min(60rem, 100%);
  margin: 0 auto;
  padding: 2rem 1.5rem;
}

.portal__pie {
  padding: 1.5rem;
  border-top: 1px solid var(--borde);
  font-size: 0.875rem;
  color: #6b6b6b;
}

.portal__aviso {
  max-width: 44rem;
}
</style>