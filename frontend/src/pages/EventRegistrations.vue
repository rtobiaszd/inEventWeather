<template>
  <div class="stack">
    <div class="page-header">
      <div>
        <h2>Inscrições</h2>
        <p v-if="event">{{ event.name }} — {{ event.city }}</p>
      </div>
      <div class="flex-gap">
        <RouterLink :to="`/events/${route.params.id}`" class="btn btn-ghost btn-sm">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
          Voltar
        </RouterLink>
      </div>
    </div>

    <LoadingState v-if="loading" message="Carregando..." />
    <ErrorMessage v-else-if="error" :message="error" @retry="load" />

    <template v-else-if="event">
      <RegistrationManager :event-id="event.id" />

      <!-- Public link -->
      <div class="card">
        <div class="card-header"><h3>🔗 Link Público do Evento</h3></div>
        <div class="reg-share-link">
          <input :value="publicUrl" class="form-control" readonly @focus="$event.target.select()" />
          <button class="btn btn-primary" @click="copyLink">
            {{ copied ? 'Copiado!' : 'Copiar Link' }}
          </button>
        </div>
        <p class="text-sm text-muted mt-8">
          Compartilhe este link para que convidados possam ver as informações do evento e se inscrever.
        </p>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { eventsApi } from '../services/api.js'
import { useToast } from '../composables/useToast.js'
import RegistrationManager from '../components/RegistrationManager.vue'
import LoadingState from '../components/LoadingState.vue'
import ErrorMessage from '../components/ErrorMessage.vue'

const route = useRoute()
const { show: showToast } = useToast()

const event = ref(null)
const loading = ref(true)
const error = ref(null)
const copied = ref(false)

const publicUrl = computed(() => {
  if (!route.params.id) return ''
  return `${window.location.origin}/e/${route.params.id}`
})

async function load() {
  loading.value = true
  error.value = null
  try {
    const res = await eventsApi.get(route.params.id)
    event.value = res.data
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function copyLink() {
  try {
    await navigator.clipboard.writeText(publicUrl.value)
    copied.value = true
    showToast('Link copiado!', 'success')
    setTimeout(() => { copied.value = false }, 2000)
  } catch {
    showToast('Erro ao copiar link', 'error')
  }
}

onMounted(load)
</script>

<style scoped>
.reg-share-link {
  display: flex;
  gap: 8px;
}

.reg-share-link .form-control {
  flex: 1;
  font-size: 13px;
  font-family: monospace;
}
</style>
