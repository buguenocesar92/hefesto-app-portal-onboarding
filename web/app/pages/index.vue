<script setup lang="ts">
/**
 * Catálogo y planes.
 *
 * El precio se muestra como lo que es: un marcador que mantiene Hefesto fuera del
 * portal. Y los kits que todavía no se pueden automatizar lo dicen, en vez de aparecer
 * igual que los demás.
 */
const { catalogo, planes } = usePortalApi()

const { data: catalogoRespuesta, error: errorCatalogo } = await useAsyncData('kits', () => catalogo())
const { data: planesRespuesta, error: errorPlanes } = await useAsyncData('planes', () => planes())

const kits = computed(() => catalogoRespuesta.value?.kits ?? [])
const listaPlanes = computed(() => planesRespuesta.value?.planes ?? [])
</script>

<template>
  <section>
    <h1>Prepara tu infraestructura con Hefesto</h1>
    <p class="intro">
      Elige lo que necesitas y nosotros lo dejamos andando en tu servidor. Antes de tocar
      nada, una persona de Hefesto revisa y aprueba la preparación.
    </p>

    <p v-if="errorCatalogo || errorPlanes" class="aviso aviso--rojo" role="alert">
      No pudimos cargar el catálogo en este momento. Inténtalo más tarde.
    </p>

    <section aria-labelledby="titulo-planes">
      <h2 id="titulo-planes">Planes</h2>
      <p v-if="planesRespuesta" class="nota">{{ planesRespuesta.nota }}</p>

      <ul class="rejilla">
        <li v-for="plan in listaPlanes" :key="plan.codigo" class="tarjeta">
          <h3>{{ plan.nombre }}</h3>
          <p>{{ plan.descripcion }}</p>
          <p class="precio" :class="{ 'precio--marcador': plan.precio_es_marcador }">
            {{ plan.precio_texto }}
          </p>
          <ul class="lista-kits">
            <li v-for="kit in plan.kits" :key="kit.codigo">
              {{ kit.nombre }}
              <span v-if="!kit.aprovisonable" class="etiqueta etiqueta--ambar">
                lo prepara Hefesto
              </span>
            </li>
          </ul>
        </li>
      </ul>
    </section>

    <section aria-labelledby="titulo-kits">
      <h2 id="titulo-kits">Los catorce kits</h2>
      <ul class="rejilla">
        <li v-for="kit in kits" :key="kit.codigo" class="tarjeta">
          <h3>{{ kit.nombre }}</h3>
          <p class="categoria">{{ kit.categoria }}</p>
          <p>{{ kit.resumen }}</p>

          <details>
            <summary>Qué incluye y qué necesita</summary>
            <p>{{ kit.que_hace }}</p>
            <h4>Requisitos</h4>
            <ul>
              <li v-for="requisito in kit.requisitos" :key="requisito">{{ requisito }}</li>
            </ul>
            <p v-if="kit.licencia" class="licencia">Licencia: {{ kit.licencia }}</p>
          </details>

          <p v-if="!kit.aprovisonable" class="etiqueta etiqueta--ambar">
            {{ kit.motivo_no_aprovisonable }}
          </p>
        </li>
      </ul>
    </section>
  </section>
</template>

<style scoped>
.intro {
  font-size: 1.125rem;
  max-width: 46rem;
}

.nota {
  color: #6b6b6b;
  font-size: 0.9rem;
}

.rejilla {
  list-style: none;
  padding: 0;
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(auto-fill, minmax(18rem, 1fr));
}

.tarjeta {
  border: 1px solid var(--borde);
  border-radius: 0.5rem;
  padding: 1rem;
}

.categoria {
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #6b6b6b;
  margin: 0.15rem 0 0.5rem;
}

.precio {
  font-weight: 600;
}

.precio--marcador {
  color: #8a5a00;
  font-weight: 500;
}

.etiqueta {
  display: inline-block;
  font-size: 0.8rem;
  padding: 0.1rem 0.45rem;
  border-radius: 0.25rem;
}

.etiqueta--ambar {
  background: #fff4d6;
  color: #7a5200;
}

.aviso {
  padding: 0.75rem 1rem;
  border-radius: 0.5rem;
}

.aviso--rojo {
  background: #fdecec;
  color: #8a1f1f;
}

.licencia {
  font-size: 0.85rem;
  color: #6b6b6b;
}
</style>