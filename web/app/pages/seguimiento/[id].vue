<script setup lang="ts">
/**
 * Seguimiento de una solicitud.
 *
 * La pantalla responde "¿qué están haciendo con mi servidor?" con hechos: cada paso, su
 * hora y su resultado. Y cuando algo no se ejecutó, lo dice. Un estado que parece
 * completo y no aprovisionó nada es peor que uno que dice "todavía no".
 */
const ruta = useRoute()
const { solicitud } = usePortalApi()

const id = computed(() => String(ruta.params.id))

const { data, error, refresh } = await useAsyncData(
  `solicitud-${id.value}`,
  () => solicitud(id.value),
)

// Actualización periódica mientras la solicitud siga en movimiento.
const enCurso = computed(() => {
  const estado = data.value?.solicitud?.estado as string | undefined
  return estado === 'en_ejecucion' || estado === 'aprobada'
})

let temporizador: ReturnType<typeof setInterval> | undefined
onMounted(() => {
  temporizador = setInterval(() => {
    if (enCurso.value) refresh()
  }, 5000)
})
onBeforeUnmount(() => clearInterval(temporizador))

interface Paso {
  orden: number
  etiqueta: string
  estado: string
  estado_texto: string
  color: string
  mensaje: string | null
  iniciado: string | null
  terminado: string | null
}

const pasos = computed<Paso[]>(() => (data.value?.solicitud?.pasos as Paso[]) ?? [])
const entregas = computed<Array<{ tipo: string; estado: string; ubicacion: string }>>(
  () => (data.value?.solicitud?.entregas as never[]) ?? [],
)
</script>

<template>
  <section>
    <h1>Tu solicitud</h1>

    <p v-if="error" class="aviso aviso--rojo" role="alert">
      No pudimos encontrar esa solicitud. Si crees que es un error, escríbenos.
    </p>

    <template v-else-if="data">
      <p class="estado" :class="`estado--${data.solicitud.color}`">
        {{ data.solicitud.estado_texto }}
      </p>
      <p class="explicacion">{{ data.solicitud.explicacion }}</p>

      <h2>Avance</h2>
      <ol class="pasos">
        <li v-for="paso in pasos" :key="paso.orden" class="paso">
          <span class="paso__estado" :class="`paso__estado--${paso.color}`" aria-hidden="true" />
          <div>
            <strong>{{ paso.etiqueta }}</strong>
            <span class="paso__texto">{{ paso.estado_texto }}</span>
            <p v-if="paso.mensaje" class="paso__mensaje">{{ paso.mensaje }}</p>
          </div>
        </li>
      </ol>

      <h2>Credenciales</h2>
      <ul v-if="entregas.length" class="entregas">
        <li v-for="entrega in entregas" :key="entrega.tipo">
          {{ entrega.ubicacion }}
        </li>
      </ul>
      <p v-else>Todavía no hay credenciales que entregar.</p>
    </template>
  </section>
</template>

<style scoped>
.estado {
  font-size: 1.25rem;
  font-weight: 600;
}

.estado--verde { color: #14532d; }
.estado--azul { color: #1e3a8a; }
.estado--ambar { color: #8a5a00; }
.estado--rojo { color: #8a1f1f; }
.estado--gris { color: #6b6b6b; }

.explicacion {
  max-width: 44rem;
}

.pasos {
  list-style: none;
  padding: 0;
  display: grid;
  gap: 0.75rem;
}

.paso {
  display: grid;
  grid-template-columns: 1rem 1fr;
  gap: 0.75rem;
  align-items: start;
}

.paso__estado {
  width: 0.75rem;
  height: 0.75rem;
  border-radius: 50%;
  margin-top: 0.35rem;
  background: #b9b9b9;
}

.paso__estado--verde { background: #15803d; }
.paso__estado--azul { background: #1d4ed8; }
.paso__estado--ambar { background: #b45309; }
.paso__estado--rojo { background: #b91c1c; }

.paso__texto {
  margin-left: 0.5rem;
  color: #6b6b6b;
}

.paso__mensaje {
  margin: 0.25rem 0 0;
  font-size: 0.9rem;
}

.entregas {
  padding-left: 1.1rem;
}

.aviso {
  padding: 0.75rem 1rem;
  border-radius: 0.5rem;
}

.aviso--rojo {
  background: #fdecec;
  color: #8a1f1f;
}
</style>