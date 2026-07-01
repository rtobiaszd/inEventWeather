<template>
  <div class="stack">
    <div class="page-header">
      <div>
        <h2>{{ event?.name || 'Carregando...' }}</h2>
        <p v-if="event">{{ event.city }}, {{ event.country }} • {{ formatDate(event.event_date) }} {{ event.event_time?.slice(0, 5) }}</p>
      </div>
      <div class="flex-gap">
        <button class="btn btn-ghost btn-sm" @click="shareEvent" :disabled="sharing">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
          {{ sharing ? 'Copiado!' : 'Compartilhar' }}
        </button>
        <button v-if="event" class="btn btn-ghost btn-sm" :disabled="duplicating" @click="duplicateEvent">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
          {{ duplicating ? 'Duplicando...' : 'Duplicar' }}
        </button>
        <RouterLink v-if="event" :to="`/events/${event.id}/edit`" class="btn btn-secondary btn-sm">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          Editar
        </RouterLink>
        <RouterLink to="/events" class="btn btn-ghost btn-sm">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
          Voltar
        </RouterLink>
      </div>
    </div>

    <LoadingState v-if="loading" message="Carregando evento..." />
    <ErrorMessage v-else-if="error" :message="error" />

    <template v-else-if="event">
      <!-- Top grid: Info + Weather -->
      <div class="grid-2">
        <!-- Event Details Card -->
        <div class="card">
          <div class="card-header"><h3>Informações do Evento</h3></div>
          <div class="detail-grid">
            <div class="detail-field">
              <span class="detail-label">Tipo</span>
              <span class="detail-value">
                <span :class="['badge', event.type === 'outdoor' ? 'badge-info' : 'badge-neutral']">
                  {{ event.type === 'outdoor' ? '🌤 Outdoor' : '🏛 Indoor' }}
                </span>
              </span>
            </div>
            <div class="detail-field">
              <span class="detail-label">Status</span>
              <span class="detail-value">
                <span :class="statusBadgeClass(event.status)">{{ statusLabel(event.status) }}</span>
              </span>
            </div>
            <div class="detail-field">
              <span class="detail-label">Data</span>
              <span class="detail-value">{{ formatDate(event.event_date) }} {{ event.event_time?.slice(0, 5) }}</span>
            </div>
            <div class="detail-field" v-if="event.end_date">
              <span class="detail-label">Término</span>
              <span class="detail-value">{{ formatDate(event.end_date) }} {{ event.end_time?.slice(0, 5) }}</span>
            </div>
            <div class="detail-field">
              <span class="detail-label">Cidade</span>
              <span class="detail-value">{{ event.city }}, {{ event.country }}</span>
            </div>
            <div class="detail-field" v-if="event.venue">
              <span class="detail-label">Local</span>
              <span class="detail-value">{{ event.venue }}</span>
            </div>
            <div class="detail-field" v-if="event.expected_audience">
              <span class="detail-label">Público</span>
              <span class="detail-value">{{ formatNum(event.expected_audience) }} pessoas</span>
            </div>
            <div class="detail-field" v-if="event.organizer">
              <span class="detail-label">Organizador</span>
              <span class="detail-value">{{ event.organizer }}</span>
            </div>
            <div class="detail-field" v-if="event.budget">
              <span class="detail-label">Orçamento</span>
              <span class="detail-value detail-value-finance">{{ formatBRL(event.budget) }}</span>
            </div>
            <div class="detail-field" v-if="event.revenue">
              <span class="detail-label">Receita</span>
              <span class="detail-value detail-value-finance">{{ formatBRL(event.revenue) }}</span>
            </div>
            <div class="detail-field" v-if="event.ticket_price">
              <span class="detail-label">Ingresso</span>
              <span class="detail-value detail-value-finance">{{ formatBRL(event.ticket_price) }}</span>
            </div>
            <div class="detail-field" v-if="event.organizer_contact">
              <span class="detail-label">Contato</span>
              <span class="detail-value">{{ event.organizer_contact }}</span>
            </div>
            <div class="detail-field" v-if="event.tags" style="grid-column: 1 / -1">
              <span class="detail-label">Tags</span>
              <span class="detail-value">
                <span v-for="tag in tagList" :key="tag" class="badge badge-neutral badge-xs" style="margin-right:4px">{{ tag.trim() }}</span>
              </span>
            </div>
          </div>
          <div v-if="event.description" class="detail-description">
            <span class="detail-label">Descrição</span>
            <p>{{ event.description }}</p>
          </div>
          <div v-if="event.notes" class="detail-description detail-notes">
            <span class="detail-label">Anotações Internas</span>
            <p>{{ event.notes }}</p>
          </div>
        </div>

        <!-- Weather + Risk Card -->
        <div class="stack-sm">
          <div v-if="event.weather" class="card">
            <div class="card-header">
              <h3>🌤 Clima Agora em {{ event.city }}</h3>
            </div>
            <div class="weather-snapshot">
              <img
                v-if="event.weather.icon"
                :src="`https://openweathermap.org/img/wn/${event.weather.icon}@2x.png`"
                width="64" height="64"
                :alt="event.weather.weather_description"
              />
              <div>
                <div class="ws-temp">{{ event.weather.temperature }}<span class="ws-unit">°C</span></div>
                <div class="ws-desc">{{ event.weather.weather_description }}</div>
              </div>
            </div>
          </div>

          <div v-if="event.risk" class="card">
            <div class="card-header">
              <h3>Análise de Risco</h3>
              <RiskBadge :status="event.risk.status" />
            </div>
            <div>
              <div class="flex-between mb-16">
                <span class="text-sm text-muted">Score</span>
                <span class="font-bold" :style="{ color: riskColor }">{{ event.risk.score }}/100</span>
              </div>
              <div class="risk-bar">
                <div class="risk-bar-fill" :style="{ width: event.risk.score + '%', background: riskColor }" />
              </div>
              <p class="text-sm text-muted mt-8">{{ event.risk.recommendation }}</p>
            </div>
          </div>

          <div v-if="event.event_forecast" class="card">
            <div class="card-header">
              <h3>📊 Previsão para o Dia</h3>
              <span :class="['badge', event.event_forecast.type === 'forecast' ? 'badge-info' : 'badge-neutral', 'badge-xs']">
                {{ event.event_forecast.type === 'forecast' ? 'Dados Reais' : 'Estimativa' }}
              </span>
            </div>
            <div class="forecast-snapshot">
              <div class="fs-item">
                <span class="fs-label">Temperatura</span>
                <span class="fs-value">{{ event.event_forecast.temperature }}°C</span>
              </div>
              <div class="fs-item">
                <span class="fs-label">Sensação</span>
                <span class="fs-value">{{ event.event_forecast.feels_like }}°C</span>
              </div>
              <div class="fs-item">
                <span class="fs-label">Chuva</span>
                <span class="fs-value">{{ event.event_forecast.rain_probability }}%</span>
              </div>
              <div class="fs-item">
                <span class="fs-label">Vento</span>
                <span class="fs-value">{{ event.event_forecast.wind_speed }} km/h</span>
              </div>
              <div class="fs-item">
                <span class="fs-label">Umidade</span>
                <span class="fs-value">{{ event.event_forecast.humidity }}%</span>
              </div>
              <div class="fs-item">
                <span class="fs-label">Condição</span>
                <span class="fs-value">{{ event.event_forecast.weather_main }}</span>
              </div>
            </div>
            <p class="text-xs text-muted mt-8" style="font-style:italic">{{ event.event_forecast.note }}</p>
          </div>

          <div v-if="forecastList.length" class="card">
            <div class="card-header">
              <h3>⏳ Evolução Horária</h3>
            </div>
            <WeatherTimeline
              :forecast="forecastList"
              :event-date="event.event_date"
              :event-time="event.event_time?.slice(0, 5)"
            />
          </div>

          <!-- Quick actions -->
          <div class="card">
            <div class="card-header"><h3>Ações Rápidas</h3></div>
            <div class="quick-actions">
              <RouterLink :to="`/events/${event.id}/edit`" class="btn btn-secondary">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Editar Evento
              </RouterLink>
              <RouterLink :to="`/events/${event.id}/registrations`" class="btn btn-ghost">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                Inscrições
              </RouterLink>
              <RouterLink :to="`/events/map`" class="btn btn-ghost">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>
                Ver no Mapa
              </RouterLink>
              <button class="btn btn-ghost" @click="shareEvent" :disabled="sharing">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                {{ sharing ? 'Link Copiado!' : 'Compartilhar Link' }}
              </button>
              <button class="btn btn-ghost" @click="copyPublicLink">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                Link Público
              </button>
              <button class="btn btn-danger" @click="confirmDeleteEvent">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                Excluir
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Palestrantes -->
      <div class="card">
        <div class="card-header">
          <h3>🎤 Palestrantes</h3>
          <RouterLink :to="`/events/${event.id}/speakers`" class="btn btn-ghost btn-sm">
            Gerenciar Palestrantes
          </RouterLink>
        </div>
      </div>

      <!-- Participants / Inscrições -->
      <RegistrationManager :event-id="event.id" />

      <!-- Sessions / Programação -->
      <SessionManager :event-id="event.id" />

      <!-- Production / Tarefas -->
      <ProductionManager :event-id="event.id" />
    </template>

    <!-- Delete modal -->
    <div v-if="deleting" class="modal-overlay" @click.self="deleting = false">
      <div class="modal-box">
        <h3>Excluir evento?</h3>
        <p>Tem certeza que deseja excluir <strong>{{ event?.name }}</strong>? Esta ação não pode ser desfeita.</p>
        <div class="flex-gap" style="justify-content:flex-end;margin-top:20px;">
          <button class="btn btn-secondary" @click="deleting = false">Cancelar</button>
          <button class="btn btn-danger" :disabled="deleteLoading" @click="doDelete">
            {{ deleteLoading ? 'Excluindo...' : 'Excluir' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, defineAsyncComponent } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { eventsApi, weatherApi } from '../services/api.js'
import { useAuth } from '../composables/useAuth.js'
import { useToast } from '../composables/useToast.js'
import LoadingState from '../components/LoadingState.vue'
import ErrorMessage from '../components/ErrorMessage.vue'
import RiskBadge from '../components/RiskBadge.vue'
import WeatherTimeline from '../components/WeatherTimeline.vue'

const ProductionManager = defineAsyncComponent(() => import('../components/ProductionManager.vue'))
const RegistrationManager = defineAsyncComponent(() => import('../components/RegistrationManager.vue'))
import SessionManager from '../components/SessionManager.vue'

const route = useRoute()
const router = useRouter()
const { can } = useAuth()
const { show: showToast } = useToast()

const event = ref(null)
const loading = ref(true)
const error = ref(null)
const sharing = ref(false)
const duplicating = ref(false)
const deleting = ref(false)
const deleteLoading = ref(false)
const forecastList = ref([])

const riskColor = computed(() => {
  const s = event.value?.risk?.status
  if (s === 'HIGH_RISK') return '#EF4444'
  if (s === 'MEDIUM_RISK') return '#F59E0B'
  return '#22C55E'
})

const tagList = computed(() => {
  if (!event.value?.tags) return []
  return event.value.tags.split(',').map(t => t.trim()).filter(Boolean)
})

function formatDate(d) {
  if (!d) return '—'
  return new Date(d + 'T12:00:00').toLocaleDateString('pt-BR')
}

function formatNum(n) {
  return new Intl.NumberFormat('pt-BR').format(n || 0)
}

function formatBRL(n) {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n || 0)
}

function statusLabel(status) {
  return { planned: '📋 Planejado', confirmed: '✅ Confirmado', in_progress: '▶ Em andamento', completed: '🏁 Realizado', cancelled: '❌ Cancelado' }[status] || status || '—'
}

function statusBadgeClass(status) {
  return { planned: 'badge badge-neutral', confirmed: 'badge badge-success', in_progress: 'badge badge-info', completed: 'badge badge-neutral', cancelled: 'badge badge-danger' }[status] || 'badge badge-neutral'
}

async function duplicateEvent() {
  if (!event.value) return
  duplicating.value = true
  try {
    const res = await eventsApi.duplicate(event.value.id)
    showToast(`Evento duplicado com sucesso!`, 'success')
    router.push({ name: 'events.edit', params: { id: res.data.id } })
  } catch (e) {
    showToast(e.message, 'error')
  } finally {
    duplicating.value = false
  }
}

async function shareEvent() {
  sharing.value = true
  try {
    await navigator.clipboard.writeText(window.location.href)
    showToast('Link copiado para a área de transferência!', 'success', 2000)
  } catch {
    showToast('Erro ao copiar link', 'error')
  } finally {
    setTimeout(() => { sharing.value = false }, 1500)
  }
}

async function copyPublicLink() {
  if (!event.value) return
  const url = `${window.location.origin}/e/${event.value.id}`
  try {
    await navigator.clipboard.writeText(url)
    showToast('Link público copiado! Compartilhe com seus convidados.', 'success')
  } catch {
    showToast('Erro ao copiar link', 'error')
  }
}

function confirmDeleteEvent() {
  deleting.value = true
}

async function doDelete() {
  deleteLoading.value = true
  try {
    await eventsApi.remove(event.value.id)
    showToast('Evento excluído com sucesso', 'success')
    router.push({ name: 'events' })
  } catch (e) {
    showToast(e.message, 'error')
  } finally {
    deleteLoading.value = false
    deleting.value = false
  }
}

onMounted(async () => {
  try {
    const res = await eventsApi.get(route.params.id)
    event.value = res.data

    if (event.value?.city) {
      try {
        const fRes = await weatherApi.forecast(event.value.city, event.value.country)
        forecastList.value = fRes.data?.forecast ?? []
      } catch { /* silent */ }
    }
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.detail-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px 24px;
}

.detail-field {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.detail-label {
  font-size: 11px;
  font-weight: 600;
  color: var(--color-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.detail-value {
  font-size: 14px;
  color: var(--color-text);
}

.detail-value-finance {
  font-weight: 600;
  color: #059669;
}

.detail-description {
  margin-top: 16px;
  padding-top: 12px;
  border-top: 1px solid var(--color-border);
}

.detail-description p {
  font-size: 13px;
  color: var(--color-text-secondary);
  line-height: 1.6;
  margin: 4px 0 0;
}

.detail-notes {
  background: #fffbeb;
  border-top-color: #fde68a;
  padding: 12px;
  border-radius: var(--radius-md);
}

.detail-notes .detail-label {
  color: #92400e;
}

.weather-snapshot {
  display: flex;
  align-items: center;
  gap: 12px;
}

.ws-temp {
  font-size: 36px;
  font-weight: 700;
  color: var(--color-text);
  line-height: 1;
}

.ws-unit {
  font-size: 18px;
  font-weight: 400;
  color: var(--color-text-secondary);
}

.ws-desc {
  font-size: 13px;
  color: var(--color-text-secondary);
  text-transform: capitalize;
}

.forecast-snapshot {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px 16px;
}

.fs-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 6px 0;
  border-bottom: 1px solid var(--color-border);
}

.fs-label {
  font-size: 12px;
  color: var(--color-text-secondary);
}

.fs-value {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text);
}

.quick-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.quick-actions .btn {
  flex: 1;
  min-width: 120px;
  justify-content: center;
}

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

@media (max-width: 700px) {
  .detail-grid {
    grid-template-columns: 1fr;
  }

  .quick-actions .btn {
    flex: none;
    width: 100%;
  }
}
</style>
