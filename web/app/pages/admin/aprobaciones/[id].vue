<script setup lang="ts">
/**
 * Pantalla de aprobación para el personal de Hefesto.
 *
 * Lo importante es qué se muestra: la lista exacta de operaciones de Coolify que se van
 * a ejecutar, con su método, su ruta y la razón por la que están permitidas. La persona
 * que aprueba no aprueba "el onboarding": aprueba esas dos llamadas concretas.
 */
const ruta = useRoute()
const configuracion = useRuntimeConfig()
const id = String(ruta.params.id)

interface Operacion {
  clave: string
  etiqueta: string
  metodo: string
  ruta: string
  porque: string
  documentacion: string
}

const { data, error } = await useAsyncData(`previa-${id}`, () =>
  $fetch<{
    solicitud_id: number
    puede_ejecutar: boolean
    tiene_aprobacion: boolean
    operaciones: Operacion[]
    advertencia: string | null
  }>(`${configuracion.apiBase}/api/v1/aprobaciones/${id}/previa`, {
    headers: { Accept: 'application/json' },
  }),
)

const comentario = ref('')
const comentarios = ref<string[]>([])

async function aprobar() {
  // La escritura lleva el token CSRF que entrega la API; sin él, Laravel la rechaza.
  await $fetch(`/api/v1/aprobaciones/${id}`, {
    method: 'POST',
    headers: { Accept: 'application/json' },
    body: { operaciones: data.value?.operaciones.map((o) => o.clave) ?? [], comentario: comentario.value },
  }).catch(() => {
    comentarios.value.push('La API del backend no respondió. Revisa que esté levantada.')
  })
}
</script>

<template>
  <section>
    <h1>Revisar y aprobar</h1>

    <p v-if="error" class="aviso aviso--rojo" role="alert">
      No pudimos cargar la solicitud, o no tienes permiso para revisarla.
    </p>

    <template v-else-if="data">
      <p v-if="data.advertencia" class="aviso aviso--ambar" role="status">
        {{ data.advertencia }}
      </p>

      <h2>Qué se va a ejecutar en la plataforma de despliegue</h2>
      <table class="tabla">
        <caption class="oculto">
          Operaciones de Coolify autorizadas por esta aprobación
        </caption>
        <thead>
          <tr>
            <th scope="col">Operación</th>
            <th scope="col">Método</th>
            <th scope="col">Ruta</th>
            <th scope="col">Por qué está permitida</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="operacion in data.operaciones" :key="operacion.clave">
            <td>{{ operacion.etiqueta }}</td>
            <td><code>{{ operacion.metodo }}</code></td>
            <td><code>{{ operacion.ruta }}</code></td>
            <td>{{ operacion.porque }}</td>
          </tr>
        </tbody>
      </table>

      <p class="nota">
        Todo lo que no aparece en esta tabla no se ejecuta. En particular, nada borra,
        detiene ni reinicia: esas operaciones no están expuestas en el portal.
      </p>

      <h2><label for="comentario">Comentario para la bitácora</label></h2>
      <textarea id="comentario" v-model="comentario" rows="3" />

      <button type="button" class="boton" @click="aprobar">Aprobar y preparar</button>

      <ul v-if="comentores.length" class="aviso aviso--rojo">
        <li v-for="mensaje in comentarios" :key="mensaje">{{ mensaje }}</li>
      </ul>
    </template>
  </section>
</template>

<style scoped>
.tabla {
  width: 100%;
  border-collapse: collapse;
}

.tabla th,
.tabla td {
  text-align: left;
  padding: 0.5rem;
  border-bottom: 1px solid var(--borde);
  vertical-align: top;
}

.nota {
  color: #6b6b6b;
  max-width: 44rem;
}

.boton {
  margin-top: 1rem;
  padding: 0.6rem 1.1rem;
  border: 1px solid var(--acento);
  background: var(--acento);
  color: #fff;
  border-radius: 0.4rem;
  cursor: pointer;
}

textarea {
  width: 100%;
  max-width: 40rem;
  padding: 0.5rem;
  border: 1px solid var(--borde);
  border-radius: 0.4rem;
}

.aviso {
  padding: 0.75rem 1rem;
  border-radius: 0.5rem;
}

.aviso--ambar {
  background: #fff4d6;
  color: #7a5200;
}

.aviso--rojo {
  background: #fdecec;
  color: #8a1f1f;
}

.oculto {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
}
</style>