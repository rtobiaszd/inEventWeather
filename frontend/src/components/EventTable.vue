<template>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Nome</th>
          <th>Cidade</th>
          <th>Data</th>
          <th>Horário</th>
          <th>Tipo</th>
          <th>Público</th>
          <th>Clima no Evento</th>
          <th v-if="showActions">Ações</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="events.length === 0">
          <td :colspan="showActions ? 8 : 7">
            <div class="empty-state" style="padding:32px">
              <span class="empty-icon">📅</span>
              <h3>Nenhum evento cadastrado</h3>
              <p>Crie seu primeiro evento para começar</p>
            </div>
          </td>
        </tr>
        <tr v-for="event in events" :key="event.id">
          <td>
            <span class="font-medium">{{ event.name }}</span>
          </td>
          <td class="td-muted">{{ event.city }}, {{ event.country }}</td>
          <td class="td-muted">{{ formatDate(event.event_date) }}</td>
          <td class="td-muted">{{ formatTime(event.event_time) }}</td>
          <td>
            <span :class="['badge', event.type === 'outdoor' ? 'badge-info' : 'badge-neutral']">
              {{ event.type === 'outdoor' ? '🌤 Outdoor' : '🏛 Indoor' }}
            </span>
          </td>
          <td class="td-muted">{{ formatAudience(event.expected_audience) }}</td>
          <td class="td-forecast">
            <div v-if="forecasts[event.id]" class="forecast-chip">
              <img
                v-if="forecasts[event.id].icon"
                :src="`https://openweathermap.org/img/wn/${forecasts[event.id].icon}.png`"
                width="28" height="28"
                :alt="forecasts[event.id].condition"
                class="forecast-icon"
              />
              <div class="forecast-nums">
                <span class="forecast-temp">{{ Math.round(forecasts[event.id].temperature) }}°C</span>
                <span class="forecast-rain">💧 {{ forecasts[event.id].rain_probability }}%</span>
                <span class="forecast-ts" :title="forecasts[event.id].fetchedAt">
                  {{ timeAgo(forecasts[event.id].fetchedAt) }}
                </span>
              </div>
              <span
                :class="['badge', 'badge-xs', forecasts[event.id].type === 'forecast' ? 'badge-info' : 'badge-neutral']"
                :title="forecasts[event.id].note"
              >
                {{ forecasts[event.id].type === 'forecast' ? 'Real' : 'Média' }}
              </span>
              <button
                class="btn-refresh"
                :disabled="loadingFc[event.id]"
                :class="{ spinning: loadingFc[event.id] }"
                @click="loadForecast(event)"
                title="Atualizar previsão"
              >
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <polyline points="23 4 23 10 17 10"/>
                  <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                </svg>
              </button>
            </div>
            <span v-else-if="loadingFc[event.id]" class="td-muted forecast-loading">
              <span class="spinner-xs"></span> buscando...
            </span>
            <button
              v-else
              class="btn btn-ghost btn-sm forecast-btn"
              @click="loadForecast(event)"
              title="Ver previsão do clima para o dia do evento"
            >
              🌤 Ver clima
            </button>
          </td>
          <td v-if="showActions">
            <div class="flex-gap">
              <button class="btn btn-ghost btn-sm" @click="$emit('edit', event)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Editar
              </button>
              <button class="btn btn-danger btn-sm" @click="$emit('delete', event)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                Excluir
              </button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { reactive, watch } from 'vue'
import { eventsApi } from '../services/api.js'

const props = defineProps({
  events:      { type: Array, default: () => [] },
  showActions: { type: Boolean, default: true },
})

defineEmits(['edit', 'delete'])

const forecasts = reactive({})
const loadingFc = reactive({})

const CACHE_KEY = (id) => `ew_forecast_${id}`

function restoreFromCache(ids) {
  for (const id of ids) {
    try {
      const raw = localStorage.getItem(CACHE_KEY(id))
      if (raw) forecasts[id] = JSON.parse(raw)
    } catch { /* ignore corrupted entries */ }
  }
}

// restore cache whenever the events list is populated (async load from parent)
watch(() => props.events, (list) => {
  restoreFromCache(list.map(e => e.id))
}, { immediate: true })

async function loadForecast(event) {
  loadingFc[event.id] = true
  try {
    const res = await eventsApi.get(event.id)
    if (res.data?.event_forecast) {
      const data = { ...res.data.event_forecast, fetchedAt: new Date().toISOString() }
      forecasts[event.id] = data
      localStorage.setItem(CACHE_KEY(event.id), JSON.stringify(data))
    }
  } catch {
    // silently fail — button stays for retry
  } finally {
    loadingFc[event.id] = false
  }
}

function timeAgo(iso) {
  if (!iso) return ''
  const diff = Math.floor((Date.now() - new Date(iso)) / 1000)
  if (diff < 60)  return 'agora'
  if (diff < 3600) return `há ${Math.floor(diff / 60)}min`
  if (diff < 86400) return `há ${Math.floor(diff / 3600)}h`
  return `há ${Math.floor(diff / 86400)}d`
}

function formatDate(d) {
  if (!d) return '—'
  const [y, m, day] = d.split('-')
  return `${day}/${m}/${y}`
}

function formatTime(t) {
  if (!t) return '—'
  return t.slice(0, 5)
}

function formatAudience(n) {
  if (!n) return '—'
  return new Intl.NumberFormat('pt-BR').format(n)
}
</script>

<style scoped>
.td-forecast { white-space: nowrap; }

.forecast-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.forecast-icon { display: block; flex-shrink: 0; }

.forecast-nums {
  display: flex;
  flex-direction: column;
  line-height: 1.2;
}

.forecast-temp {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text);
}

.forecast-rain {
  font-size: 11px;
  color: var(--color-text-secondary);
}

.badge-xs {
  font-size: 10px;
  padding: 1px 5px;
  border-radius: 4px;
}

.forecast-loading {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
}

.spinner-xs {
  display: inline-block;
  width: 10px;
  height: 10px;
  border: 2px solid var(--color-border);
  border-top-color: var(--color-primary);
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }

.forecast-btn { font-size: 12px; }

.forecast-ts {
  font-size: 10px;
  color: var(--color-text-secondary);
  opacity: 0.7;
}

.btn-refresh {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  padding: 0;
  background: none;
  border: 1px solid var(--color-border);
  border-radius: 4px;
  cursor: pointer;
  color: var(--color-text-secondary);
  flex-shrink: 0;
  transition: color 0.15s, border-color 0.15s;
}

.btn-refresh:hover:not(:disabled) {
  color: var(--color-primary);
  border-color: var(--color-primary);
}

.btn-refresh:disabled { opacity: 0.4; cursor: default; }

.btn-refresh.spinning svg {
  animation: spin 0.7s linear infinite;
}
</style>
