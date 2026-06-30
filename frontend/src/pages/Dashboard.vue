<template>
  <div class="stack">
    <!-- Overview Cards -->
    <div class="metric-grid" style="grid-template-columns:repeat(auto-fit, minmax(160px, 1fr))">
      <div class="metric-card">
        <div class="metric-card-label">📅 Total de Eventos</div>
        <div class="metric-card-value">{{ stats.total }}</div>
      </div>
      <div class="metric-card">
        <div class="metric-card-label">📋 Planejados</div>
        <div class="metric-card-value" style="color:var(--color-text-secondary)">{{ stats.planned }}</div>
      </div>
      <div class="metric-card">
        <div class="metric-card-label">✅ Confirmados</div>
        <div class="metric-card-value" style="color:#16a34a">{{ stats.confirmed }}</div>
      </div>
      <div class="metric-card">
        <div class="metric-card-label">🏁 Realizados</div>
        <div class="metric-card-value" style="color:var(--color-text-secondary)">{{ stats.completed }}</div>
      </div>
      <div class="metric-card">
        <div class="metric-card-label">▶ Em Andamento</div>
        <div class="metric-card-value" style="color:#2563eb">{{ stats.in_progress }}</div>
      </div>
      <div class="metric-card">
        <div class="metric-card-label">💰 Receita Total</div>
        <div class="metric-card-value" style="font-size:18px">{{ formatBRL(stats.revenue) }}</div>
      </div>
      <div class="metric-card">
        <div class="metric-card-label">🎫 Ticket Médio</div>
        <div class="metric-card-value" style="font-size:18px">{{ formatBRL(stats.avgTicket) }}</div>
      </div>
      <div class="metric-card">
        <div class="metric-card-label">🌤 Outdoor</div>
        <div class="metric-card-value">{{ stats.outdoor }}</div>
      </div>
    </div>

    <!-- Main grid: Weather + Risk -->
    <div class="grid-2">
      <!-- Search + Weather -->
      <div class="stack-sm">
        <div class="card">
          <div class="search-bar">
            <div class="search-input-group">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>
              <input v-model="city" type="text" placeholder="Cidade (ex: São Paulo)" @keyup.enter="searchWeather" />
            </div>
            <button class="btn btn-primary" :disabled="loading" @click="searchWeather">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              Buscar
            </button>
          </div>
        </div>

        <LoadingState v-if="loading" message="Consultando clima..." />
        <ErrorMessage v-else-if="error" :message="error" @retry="searchWeather" />

        <template v-if="weather">
          <div class="dash-city-row">
            <span class="dash-city-label">{{ searchedCity }}</span>
            <button class="btn btn-sm" :class="favorited ? 'btn-secondary' : 'btn-ghost'" :disabled="favLoading" @click="toggleFavorite">
              {{ favorited ? '♥ Favoritada' : '♡ Favoritar' }}
            </button>
          </div>

          <WeatherCard :data="weather" :events="dashboardEvents" />

          <div class="metric-grid" style="grid-template-columns:1fr 1fr;">
            <div class="metric-card">
              <div class="metric-card-label">🌡 Temperatura</div>
              <div class="metric-card-value">{{ weather.current.temperature }}<span class="metric-card-unit">°C</span></div>
            </div>
            <div class="metric-card">
              <div class="metric-card-label">💧 Umidade</div>
              <div class="metric-card-value">{{ weather.current.humidity }}<span class="metric-card-unit">%</span></div>
            </div>
            <div class="metric-card">
              <div class="metric-card-label">💨 Vento</div>
              <div class="metric-card-value">{{ weather.current.wind_speed }}<span class="metric-card-unit">km/h</span></div>
            </div>
            <div class="metric-card">
              <div class="metric-card-label">🌫 Qualidade Ar</div>
              <div class="metric-card-value" style="font-size:18px">{{ aqiLabel }}</div>
            </div>
          </div>

          <div v-if="weather.risk.alerts.length > 0" class="card">
            <div class="card-header"><h3>Alertas Climáticos</h3></div>
            <div class="stack-sm">
              <div v-for="(alert, i) in weather.risk.alerts" :key="i" :class="['alert', `alert-${alert.severity}`]">
                <span>{{ alertIcon(alert.type) }}</span>
                <span>{{ alert.message }}</span>
              </div>
            </div>
          </div>
        </template>
      </div>

      <!-- Right column: Risk + Forecast -->
      <div class="stack-sm">
        <div v-if="weather" class="card">
          <div class="card-header">
            <h3>Análise de Risco</h3>
            <RiskBadge :status="weather.risk.status" />
          </div>
          <div>
            <div class="flex-between mb-16">
              <span class="text-sm text-muted">Score</span>
              <span class="font-bold" :style="{ color: riskColor }">{{ weather.risk.score }}/100</span>
            </div>
            <div class="risk-bar">
              <div class="risk-bar-fill" :style="{ width: weather.risk.score + '%', background: riskColor }" />
            </div>
            <p class="text-sm text-muted mt-8">{{ weather.risk.recommendation }}</p>
          </div>
        </div>

        <div v-if="forecast.length" class="card">
          <div class="card-header">
            <h3>Próximas Horas</h3>
            <RouterLink to="/weather" class="btn btn-ghost btn-sm">Ver tudo</RouterLink>
          </div>
          <LoadingState v-if="forecastLoading" message="Carregando..." />
          <div v-else class="forecast-grid">
            <ForecastCard v-for="item in forecast.slice(0,8)" :key="item.datetime" :item="item" />
          </div>
        </div>
      </div>
    </div>

    <!-- Event Status Breakdown -->
    <div class="card">
      <div class="card-header">
        <h3>Eventos por Status</h3>
        <span class="text-sm text-muted">{{ stats.total }} no total</span>
      </div>
      <div class="status-grid">
        <div class="status-ring-wrap">
          <svg viewBox="0 0 120 120" class="status-ring">
            <circle cx="60" cy="60" r="50" fill="none" stroke="#f1f5f9" stroke-width="14" />
            <circle
              v-for="(s, i) in ringSegments"
              :key="s.key"
              cx="60" cy="60" r="50"
              fill="none"
              :stroke="s.color"
              stroke-width="14"
              stroke-dasharray="314.16"
              :stroke-dashoffset="s.offset"
              :transform="`rotate(${s.rotate} 60 60)`"
              stroke-linecap="round"
              style="transition: stroke-dashoffset 0.6s ease"
            />
            <text x="60" y="52" text-anchor="middle" class="ring-total" font-size="26" font-weight="700" fill="#1e293b">{{ stats.total }}</text>
            <text x="60" y="68" text-anchor="middle" font-size="9" fill="#64748b">eventos</text>
          </svg>
        </div>
        <div class="status-legend">
          <div v-for="s in statusBars" :key="s.key" class="legend-row">
            <span class="legend-dot" :style="{ background: s.color }"></span>
            <span class="legend-label">{{ s.label }}</span>
            <span class="legend-count">{{ s.count }}</span>
            <span class="legend-pct">{{ s.pct.toFixed(0) }}%</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Upcoming Events -->
    <div class="card">
      <div class="card-header">
        <h3>Próximos Eventos</h3>
        <RouterLink to="/events" class="btn btn-ghost btn-sm">Ver todos</RouterLink>
      </div>
      <LoadingState v-if="eventsLoading" message="Carregando eventos..." />
      <EventTable v-else :events="recentEvents" :show-actions="false" />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { weatherApi, eventsApi, favoritesApi } from '../services/api.js'
import WeatherCard   from '../components/WeatherCard.vue'
import ForecastCard  from '../components/ForecastCard.vue'
import RiskBadge     from '../components/RiskBadge.vue'
import EventTable    from '../components/EventTable.vue'
import LoadingState  from '../components/LoadingState.vue'
import ErrorMessage  from '../components/ErrorMessage.vue'

const city         = ref('São Paulo')
const searchedCity = ref('São Paulo')
const weather = ref(null)
const forecast = ref([])
const loading = ref(false)
const forecastLoading = ref(false)
const error   = ref(null)
const dashboardEvents = ref([])
const eventsLoading = ref(false)

const favorited  = ref(false)
const favLoading = ref(false)

const aqiLabels = { 1: 'Boa', 2: 'Razoável', 3: 'Moderada', 4: 'Ruim', 5: 'Muito Ruim' }
const aqiLabel  = computed(() => aqiLabels[weather.value?.aqi] ?? '—')

const riskColor = computed(() => {
  const s = weather.value?.risk?.status
  if (s === 'HIGH_RISK')   return '#EF4444'
  if (s === 'MEDIUM_RISK') return '#F59E0B'
  return '#22C55E'
})

const recentEvents = computed(() => dashboardEvents.value.slice(0, 5))

const stats = computed(() => {
  const events = dashboardEvents.value
  const total = events.length
  const planned = events.filter(e => e.status === 'planned').length
  const confirmed = events.filter(e => e.status === 'confirmed').length
  const in_progress = events.filter(e => e.status === 'in_progress').length
  const completed = events.filter(e => e.status === 'completed').length
  const cancelled = events.filter(e => e.status === 'cancelled').length
  const outdoor = events.filter(e => e.type === 'outdoor').length
  const indoor = events.filter(e => e.type === 'indoor').length

  const revenue = events.reduce((s, e) => s + (Number(e.revenue) || 0), 0)
  const tickets = events.filter(e => Number(e.ticket_price) > 0)
  const avgTicket = tickets.length ? tickets.reduce((s, e) => s + Number(e.ticket_price), 0) / tickets.length : 0

  return { total, planned, confirmed, in_progress, completed, cancelled, outdoor, indoor, revenue, avgTicket }
})

const statusBars = computed(() => {
  const total = stats.value.total || 1
  return [
    { key: 'planned',     label: '📋 Planejados',    count: stats.value.planned,     pct: (stats.value.planned / total) * 100,     color: '#94a3b8' },
    { key: 'confirmed',   label: '✅ Confirmados',   count: stats.value.confirmed,   pct: (stats.value.confirmed / total) * 100,   color: '#22c55e' },
    { key: 'in_progress', label: '▶ Em andamento',   count: stats.value.in_progress, pct: (stats.value.in_progress / total) * 100, color: '#3b82f6' },
    { key: 'completed',   label: '🏁 Realizados',    count: stats.value.completed,   pct: (stats.value.completed / total) * 100,   color: '#64748b' },
    { key: 'cancelled',   label: '❌ Cancelados',    count: stats.value.cancelled,   pct: (stats.value.cancelled / total) * 100,   color: '#ef4444' },
  ]
})

const ringSegments = computed(() => {
  const circumference = 314.16
  let currentAngle = 0
  return statusBars.value.filter(s => s.count > 0).map(s => {
    const angle = (s.pct / 100) * 360
    const offset = circumference - (angle / 360) * circumference
    const seg = {
      ...s,
      offset,
      rotate: currentAngle - 90,
    }
    currentAngle += angle
    return seg
  })
})

function formatBRL(n) {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n || 0)
}

function alertIcon(type) {
  return { heat: '🌡', cold: '🧊', wind: '💨', rain: '🌧', air: '🌫', humidity: '💧' }[type] ?? '⚠️'
}

async function searchWeather() {
  if (!city.value.trim()) return
  loading.value  = true
  error.value    = null
  weather.value  = null
  forecast.value = []
  favorited.value = false

  try {
    const res = await weatherApi.search(city.value.trim(), 'BR')
    weather.value  = res.data
    searchedCity.value = city.value.trim()
    loadForecast()
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function loadForecast() {
  forecastLoading.value = true
  try {
    const res = await weatherApi.forecast(city.value, 'BR')
    forecast.value = res.data.forecast
  } catch {
    // silent
  } finally {
    forecastLoading.value = false
  }
}

async function toggleFavorite() {
  if (favorited.value) return
  favLoading.value = true
  try {
    await favoritesApi.add({ city: searchedCity.value, country: 'BR' })
    favorited.value = true
  } catch (e) {
    if (e.message?.includes('já está')) favorited.value = true
  } finally {
    favLoading.value = false
  }
}

async function loadEvents() {
  eventsLoading.value = true
  try {
    const res = await eventsApi.list()
    dashboardEvents.value = res.data ?? []
  } catch {
    // silent
  } finally {
    eventsLoading.value = false
  }
}

onMounted(() => {
  searchWeather()
  loadEvents()
})
</script>

<style scoped>
.dash-city-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 2px 4px;
}

.dash-city-label {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text-secondary);
}

.status-grid {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 24px;
  align-items: center;
  padding: 12px 0 8px;
}

.status-ring-wrap {
  width: 140px;
  height: 140px;
}

.status-ring {
  width: 100%;
  height: 100%;
}

.status-legend {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.legend-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.legend-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}

.legend-label {
  flex: 1;
  font-size: 13px;
  color: var(--color-text);
}

.legend-count {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  min-width: 28px;
  text-align: right;
}

.legend-pct {
  font-size: 12px;
  color: var(--color-text-secondary);
  min-width: 36px;
  text-align: right;
}

@media (max-width: 500px) {
  .status-grid {
    grid-template-columns: 1fr;
    justify-items: center;
  }
}
</style>
