<template>
  <div class="stack">
    <div class="page-header">
      <div>
        <h2>Novo Evento</h2>
        <p>Preencha os dados do evento abaixo</p>
      </div>
      <RouterLink to="/events" class="btn btn-secondary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        Voltar
      </RouterLink>
    </div>

    <div class="card">
      <form @submit.prevent="submit" class="form-section">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Nome do Evento <span class="required">*</span></label>
            <input v-model="form.name" type="text" class="form-control" placeholder="Ex: Festival de Verão" required />
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
            <input v-model="form.city" type="text" class="form-control" placeholder="Ex: São Paulo" required />
          </div>
          <div class="form-group">
            <label class="form-label">País <span class="required">*</span></label>
            <input v-model="form.country" type="text" class="form-control" placeholder="BR" maxlength="2" required />
          </div>
          <div class="form-group">
            <label class="form-label">Público Estimado</label>
            <input v-model.number="form.expected_audience" type="number" class="form-control" placeholder="0" min="0" />
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

        <div class="form-group">
          <label class="form-label">Descrição</label>
          <textarea v-model="form.description" class="form-control" placeholder="Descreva o evento..." />
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
                <input v-model="form.venue" type="text" class="form-control" placeholder="Ex: Parque Ibirapuera" />
              </div>
              <div class="form-group">
                <label class="form-label">Organizador</label>
                <input v-model="form.organizer" type="text" class="form-control" placeholder="Ex: Produtora ABC" />
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Contato</label>
                <input v-model="form.organizer_contact" type="text" class="form-control" placeholder="Telefone / e-mail" />
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
                <input v-model.number="form.budget" type="number" class="form-control" min="0" step="0.01" placeholder="0,00" />
              </div>
              <div class="form-group">
                <label class="form-label">Receita (R$)</label>
                <input v-model.number="form.revenue" type="number" class="form-control" min="0" step="0.01" placeholder="0,00" />
              </div>
              <div class="form-group">
                <label class="form-label">Ingresso (R$)</label>
                <input v-model.number="form.ticket_price" type="number" class="form-control" min="0" step="0.01" placeholder="0,00" />
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

        <div v-if="errorMsg" class="alert alert-danger">{{ errorMsg }}</div>

        <div class="flex-gap">
          <button type="submit" class="btn btn-primary" :disabled="submitting">
            {{ submitting ? 'Salvando...' : 'Criar Evento' }}
          </button>
          <RouterLink to="/events" class="btn btn-secondary">Cancelar</RouterLink>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { eventsApi, favoritesApi, eventTypesApi } from '../services/api.js'

const router = useRouter()

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

const eventTypes = ref([])

onMounted(async () => {
  try {
    const res = await eventTypesApi.list()
    eventTypes.value = res.data ?? []
    if (eventTypes.value.length && !eventTypes.value.find(t => t.slug === form.value.type)) {
      form.value.type = eventTypes.value[0].slug
    }
  } catch {
    eventTypes.value = [
      { slug: 'outdoor', name: 'Outdoor', icon: '🌤' },
      { slug: 'indoor',  name: 'Indoor',  icon: '🏛' },
    ]
  }
})

const submitting = ref(false)
const errorMsg   = ref(null)

async function submit() {
  submitting.value = true
  errorMsg.value   = null
  try {
    await eventsApi.create(form.value)
    try {
      await favoritesApi.add({ city: form.value.city, country: form.value.country || 'BR' })
    } catch { /* silent */ }
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
