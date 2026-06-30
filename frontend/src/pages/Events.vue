<template>
  <div class="stack">
    <div class="page-header">
      <div>
        <h2>Eventos</h2>
        <p>{{ events.length }} evento(s) cadastrado(s)</p>
      </div>
      <RouterLink to="/events/create" class="btn btn-primary">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Novo Evento
      </RouterLink>
    </div>

    <LoadingState v-if="loading" message="Carregando eventos..." />
    <ErrorMessage v-else-if="error" :message="error" @retry="load" />
    <EventTable v-else :events="events" @edit="goEdit" @delete="confirmDelete" />

    <!-- Delete confirmation modal -->
    <div v-if="deleteTarget" class="modal-overlay" @click.self="deleteTarget = null">
      <div class="modal-box">
        <h3>Excluir evento?</h3>
        <p>Tem certeza que deseja excluir o evento <strong>{{ deleteTarget.name }}</strong>?<br/>Esta ação não pode ser desfeita.</p>
        <div class="flex-gap" style="justify-content:flex-end;margin-top:20px;">
          <button class="btn btn-secondary" @click="deleteTarget = null">Cancelar</button>
          <button class="btn btn-danger" :disabled="deleting" @click="doDelete">
            {{ deleting ? 'Excluindo...' : 'Excluir' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { eventsApi } from '../services/api.js'
import EventTable  from '../components/EventTable.vue'
import LoadingState from '../components/LoadingState.vue'
import ErrorMessage from '../components/ErrorMessage.vue'

const router  = useRouter()
const events  = ref([])
const loading = ref(false)
const error   = ref(null)
const deleteTarget = ref(null)
const deleting     = ref(false)

async function load() {
  loading.value = true
  error.value   = null
  try {
    const res = await eventsApi.list()
    events.value = res.data ?? []
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function goEdit(event) {
  router.push({ name: 'events.edit', params: { id: event.id } })
}

function confirmDelete(event) {
  deleteTarget.value = event
}

async function doDelete() {
  deleting.value = true
  try {
    await eventsApi.remove(deleteTarget.value.id)
    events.value = events.value.filter(e => e.id !== deleteTarget.value.id)
    deleteTarget.value = null
  } catch (e) {
    alert(e.message)
  } finally {
    deleting.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 200;
}

.modal-box {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 28px;
  max-width: 420px;
  width: 90%;
  box-shadow: var(--shadow-lg);
}

.modal-box h3 { font-size: 17px; font-weight: 700; margin-bottom: 10px; }
.modal-box p  { font-size: 13.5px; color: var(--color-text-secondary); line-height: 1.6; }
</style>
