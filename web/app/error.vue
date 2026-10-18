/**
 * Página de error.
 *
 * Cuando algo falla, el cliente ve un mensaje honesto en español y el detalle queda en el
 * registro del servidor. Nunca se muestra un rastro de pila ni un identificador interno.
 */
defineProps<{ error: { statusCode?: number; statusMessage?: string; message?: string } }>()

const volver = () => clearError({ redirect: '/' })
</script>

<template>
  <section>
    <h1>Algo no salió bien</h1>
    <p v-if="error?.statusCode === 404">
      No encontramos lo que estabas buscando.
    </p>
    <p v-else>
      No pudimos completar la operación. Si el problema sigue, escríbenos y lo revisamos.
    </p>
    <button type="button" class="boton" @click="volver">Volver al inicio</button>
  </section>
</template>

<style scoped>
.boton {
  margin-top: 1rem;
  padding: 0.6rem 1.1rem;
  border: 1px solid var(--acento);
  background: var(--acento);
  color: #fff;
  border-radius: 0.4rem;
  cursor: pointer;
}
</style>