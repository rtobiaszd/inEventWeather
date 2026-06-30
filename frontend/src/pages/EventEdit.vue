<template>
  <div class="stack">
    <div class="page-header">
      <div>
        <h2>Editar Evento</h2>
        <p>Atualize os dados do evento</p>
      </div>
      <RouterLink to="/events" class="btn btn-secondary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        Voltar
      </RouterLink>
    </div>

    <LoadingState v-if="loading" message="Carregando evento..." />
    <ErrorMessage v-else-if="loadError" :message="loadError" :retry="false" />

    <div v-else class="card">
      <form @submit.prevent="submit" class="form-section">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Nome do Evento <span class="required">*</span></label>
            <input v-model="form.name" type="text" class="form-control" required />
          </div>
          <div class="form-group">
            <label class="form-label">Tipo <span class="required">*</span></label>
            <select v-model="form.type" class="form-control" required>
              <option v-for="t in eventTypes" :key="t.slug" :value="t.slug">
                {{ t.icon }} {{ t.name }}
              </option>
            </select>
          </div>
        </div>

        <div class="form-row-3">
          <div class="form-group">
            <label class="form-label">Cidade <span class="required">*</span></label>
            <input v-model="form.city" type="text" class="form-control" required />
          </div>
          <div class="form-group">
            <label class="form-label">País <span class="required">*</span></label>
            <input v-model="form.country" type="text" class="form-control" maxlength="2" required />
          </div>
          <div class="form-group">
            <label class="form-label">Público Estimado</label>
            <input v-model.number="form.expected_audience" type="number" class="form-control" min="0" />
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Data <span class="required">*</span></label>
            <input v-model="form.event_date" type="date" class="form-control" required />
          </div>
          <div class="form-group">
            <label class="form-label">Horário <span class="required">*</span></label>
            <input v-model="form.event_time" type="time" class="form-control" required />
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Descrição</label>
          <textarea v-model="form.description" class="form-control" />
        </div>

        <div v-if="errorMsg" class="alert alert-danger">{{ errorMsg }}</div>

        <div class="flex-gap">
          <button type="submit" class="btn btn-primary" :disabled="submitting">
            {{ submitting ? 'Salvando...' : 'Salvar Alterações' }}
          </button>
          <RouterLink to="/events" class="btn btn-secondary">Cancelar</RouterLink>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { eventsApi, eventTypesApi } from '../services/api.js'
import LoadingState from '../components/LoadingState.vue'
import ErrorMessage from '../components/ErrorMessage.vue'

const eventTypes = ref([])

const route  = useRoute()
const router = useRouter()

const form = ref({
  name: '', city: '', country: 'BR',
  event_date: '', event_time: '',
  type: 'outdoor', expected_audience: 0, description: '',
})

const loading    = ref(true)
const loadError  = ref(null)
const submitting = ref(false)
const errorMsg   = ref(null)

onMounted(async () => {
  // Carrega tipos e evento em paralelo
  try {
    const typesRes = await eventTypesApi.list()
    eventTypes.value = typesRes.data ?? []
  } catch {
    eventTypes.value = [
      { slug: 'outdoor', name: 'Outdoor', icon: '🌤' },
      { slug: 'indoor',  name: 'Indoor',  icon: '🏛' },
    ]
  }

  try {
    const res = await eventsApi.get(route.params.id)
    const ev  = res.data
    form.value = {
      name:              ev.name,
      city:              ev.city,
      country:           ev.country,
      event_date:        ev.event_date,
      event_time:        ev.event_time?.slice(0, 5),
      type:              ev.type,
      expected_audience: ev.expected_audience,
      description:       ev.description ?? '',
    }
  } catch (e) {
    loadError.value = e.message
  } finally {
    loading.value = false
  }
})

async function submit() {
  submitting.value = true
  errorMsg.value   = null
  try {
    await eventsApi.update(route.params.id, form.value)
    router.push({ name: 'events' })
  } catch (e) {
    errorMsg.value = e.message
  } finally {
    submitting.value = false
  }
}
</script>
