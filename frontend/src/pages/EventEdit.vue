<template>
  <div class="stack">
    <div class="page-header">
      <div>
        <h2>Editar Evento</h2>
        <p>Atualize os dados do evento</p>
      </div>
      <div class="flex-gap">
        <RouterLink v-if="eventId" :to="`/e/${eventId}`" class="btn btn-ghost btn-sm" target="_blank">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          Visualizar
        </RouterLink>
        <RouterLink to="/events" class="btn btn-secondary">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
          Voltar
        </RouterLink>
      </div>
    </div>

    <LoadingState v-if="loading" message="Carregando evento..." />
    <ErrorMessage v-else-if="loadError" :message="loadError" :retry="false" />

    <div v-else class="card">
      <form @submit.prevent="submit" class="form-section" novalidate>
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

        <div class="form-row-3">
          <div class="form-group">
            <label class="form-label">Data <span class="required">*</span></label>
            <input v-model="form.event_date" type="date" class="form-control" required />
          </div>
          <div class="form-group">
            <label class="form-label">Horário <span class="required">*</span></label>
            <input v-model="form.event_time" type="time" class="form-control" required />
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select v-model="form.status" class="form-control">
              <option value="planned">Planejado</option>
              <option value="confirmed">Confirmado</option>
              <option value="in_progress">Em andamento</option>
              <option value="completed">Realizado</option>
              <option value="cancelled">Cancelado</option>
            </select>
          </div>
        </div>

        <SmartDatePicker v-model="form.event_date" :city="form.city" :country="form.country" :type="form.type" />

        <div class="form-group">
          <label class="form-label">Descrição</label>
          <textarea v-model="form.description" class="form-control" />
        </div>

        <details class="form-details">
          <summary class="form-details-summary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
            Mais detalhes
          </summary>
          <div class="form-details-body">
            <div class="form-row-3">
              <div class="form-group">
                <label class="form-label">Data Término</label>
                <input v-model="form.end_date" type="date" class="form-control" />
              </div>
              <div class="form-group">
                <label class="form-label">Horário Término</label>
                <input v-model="form.end_time" type="time" class="form-control" />
              </div>
              <div class="form-group">
                <label class="form-label">Banner URL</label>
                <input v-model="form.banner_url" type="url" class="form-control" placeholder="https://..." />
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Local (Venue)</label>
                <input v-model="form.venue" type="text" class="form-control" />
              </div>
              <div class="form-group">
                <label class="form-label">Organizador</label>
                <input v-model="form.organizer" type="text" class="form-control" />
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Contato</label>
                <input v-model="form.organizer_contact" type="text" class="form-control" />
              </div>
              <div class="form-group">
                <label class="form-label">Tags</label>
                <input v-model="form.tags" type="text" class="form-control" placeholder="música, ao ar livre, família" />
              </div>
            </div>
          </div>
        </details>

        <details class="form-details">
          <summary class="form-details-summary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
            Financeiro
          </summary>
          <div class="form-details-body">
            <div class="form-row-3">
              <div class="form-group">
                <label class="form-label">Orçamento (R$)</label>
                <input v-model.number="form.budget" type="number" class="form-control" min="0" step="0.01" />
              </div>
              <div class="form-group">
                <label class="form-label">Receita (R$)</label>
                <input v-model.number="form.revenue" type="number" class="form-control" min="0" step="0.01" />
              </div>
              <div class="form-group">
                <label class="form-label">Ingresso (R$)</label>
                <input v-model.number="form.ticket_price" type="number" class="form-control" min="0" step="0.01" />
              </div>
            </div>
          </div>
        </details>

        <details class="form-details">
          <summary class="form-details-summary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
            Coordenadas (mapa)
          </summary>
          <div class="form-details-body">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Latitude</label>
                <input v-model.number="form.latitude" type="number" class="form-control" step="0.000001" min="-90" max="90" placeholder="-23.550520" />
              </div>
              <div class="form-group">
                <label class="form-label">Longitude</label>
                <input v-model.number="form.longitude" type="number" class="form-control" step="0.000001" min="-180" max="180" placeholder="-46.633308" />
              </div>
            </div>
          </div>
        </details>

        <details class="form-details">
          <summary class="form-details-summary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
            Anotações internas
          </summary>
          <div class="form-details-body">
            <div class="form-group">
              <textarea v-model="form.notes" class="form-control" placeholder="Observações..." rows="3" />
            </div>
          </div>
        </details>

        <WeatherPreview
          :city="form.city"
          :country="form.country"
          :event-date="form.event_date"
          :event-time="form.event_time"
          :is-outdoor="form.type === 'outdoor'"
        />

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
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { eventsApi, eventTypesApi } from '../services/api.js'
import WeatherPreview from '../components/WeatherPreview.vue'
import SmartDatePicker from '../components/SmartDatePicker.vue'
import LoadingState from '../components/LoadingState.vue'
import ErrorMessage from '../components/ErrorMessage.vue'

const eventTypes = ref([])

const route  = useRoute()
const router = useRouter()

const eventId = computed(() => route.params.id)

const form = ref({
  name: '', city: '', country: 'BR',
  event_date: '', event_time: '',
  type: 'outdoor', expected_audience: 0, description: '',
  latitude: null, longitude: null,
  status: 'planned',
  budget: null, revenue: null, ticket_price: null,
  organizer: '', organizer_contact: '', venue: '',
  end_date: '', end_time: '', banner_url: '',
  tags: '', notes: '',
})

const loading    = ref(true)
const loadError  = ref(null)
const submitting = ref(false)
const errorMsg   = ref(null)

onMounted(async () => {
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
      latitude:          ev.latitude,
      longitude:         ev.longitude,
      status:            ev.status ?? 'planned',
      budget:            ev.budget, revenue: ev.revenue,
      ticket_price:      ev.ticket_price,
      organizer:         ev.organizer ?? '',
      organizer_contact: ev.organizer_contact ?? '',
      venue:             ev.venue ?? '',
      end_date:          ev.end_date ?? '',
      end_time:          ev.end_time?.slice(0, 5),
      banner_url:        ev.banner_url ?? '',
      tags:              ev.tags ?? '',
      notes:             ev.notes ?? '',
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

<style scoped>
.form-details {
  margin-top: 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  overflow: hidden;
}

.form-details-summary {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 14px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 500;
  background: var(--color-surface);
  user-select: none;
}

.form-details-summary svg {
  transition: transform 0.2s;
  flex-shrink: 0;
}

details[open] .form-details-summary svg {
  transform: rotate(90deg);
}

.form-details-body {
  padding: 12px 14px 4px;
  border-top: 1px solid var(--color-border);
  background: #fff;
}
</style>
