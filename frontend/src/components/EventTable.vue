<template>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Nome</th>
          <th>Cidade</th>
          <th>Data</th>
          <th>Tipo</th>
          <th>Status</th>
          <th>Clima</th>
          <th v-if="showActions">Ações</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="events.length === 0">
          <td :colspan="showActions ? 7 : 6">
            <div class="empty-state" style="padding:32px">
              <span class="empty-icon">📅</span>
              <h3>Nenhum evento cadastrado</h3>
              <p>Crie seu primeiro evento para começar</p>
            </div>
          </td>
        </tr>
        <template v-for="event in events" :key="event.id">
          <tr>
            <td>
              <RouterLink :to="`/events/${event.id}`" class="event-name-link">{{ event.name }}</RouterLink>
            </td>
            <td class="td-muted">{{ event.city }}, {{ event.country }}</td>
            <td class="td-muted">{{ formatDate(event.event_date) }}</td>
            <td>
              <span :class="['badge', event.type === 'outdoor' ? 'badge-info' : 'badge-neutral']">
                {{ event.type === 'outdoor' ? '🌤 Outdoor' : '🏛 Indoor' }}
              </span>
            </td>
            <td>
              <span :class="statusClass(event.status)">{{ statusLabel(event.status) }}</span>
            </td>
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
                </div>
                <button
                  class="btn-refresh"
                  :disabled="loadingFc[event.id]"
                  :class="{ spinning: loadingFc[event.id] }"
                  @click="loadForecast(event)"
                  title="Atualizar"
                >
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="23 4 23 10 17 10"/>
                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                  </svg>
                </button>
              </div>
              <span v-else-if="loadingFc[event.id]" class="td-muted forecast-loading">
                <span class="spinner-xs"></span>
              </span>
              <button
                v-else
                class="btn btn-ghost btn-sm forecast-btn"
                @click="loadForecast(event)"
              >
                🌤 Ver
              </button>
            </td>
            <td v-if="showActions">
              <div class="flex-gap">
                <button class="btn btn-ghost btn-sm" @click="$emit('edit', event)" title="Editar">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button class="btn btn-ghost btn-sm" @click="$emit('duplicate', event)" title="Duplicar">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                </button>
                <button class="btn btn-danger btn-sm" @click="$emit('delete', event)" title="Excluir">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                </button>
              </div>
            </td>
          </tr>
        </template>
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

defineEmits(['edit', 'delete', 'duplicate'])

const forecasts = reactive({})
const loadingFc = reactive({})

const CACHE_KEY = (id) => `ew_forecast_${id}`

function restoreFromCache(ids) {
  for (const id of ids) {
    try {
      const raw = localStorage.getItem(CACHE_KEY(id))
      if (raw) forecasts[id] = JSON.parse(raw)
    } catch { /* ignore */ }
  }
}

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
    // silent
  } finally {
    loadingFc[event.id] = false
  }
}

function formatDate(d) {
  if (!d) return '—'
  const [y, m, day] = d.split('-')
  return `${day}/${m}/${y}`
}

function statusLabel(status) {
  const labels = {
    planned: '📋 Planejado',
    confirmed: '✅ Confirmado',
    in_progress: '▶ Andamento',
    completed: '🏁 Realizado',
    cancelled: '❌ Cancelado',
  }
  return labels[status] || status || '—'
}

function statusClass(status) {
  const cls = {
    planned: 'badge badge-neutral',
    confirmed: 'badge badge-success',
    in_progress: 'badge badge-info',
    completed: 'badge badge-neutral',
    cancelled: 'badge badge-danger',
  }
  return cls[status] || 'badge badge-neutral'
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
  align-items: center;
  gap: 4px;
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

.event-name-link { color: var(--color-primary); text-decoration: none; font-weight: 600; }
.event-name-link:hover { text-decoration: underline; }

.btn-refresh:disabled { opacity: 0.4; cursor: default; }

.btn-refresh.spinning svg {
  animation: spin 0.7s linear infinite;
}
</style>
