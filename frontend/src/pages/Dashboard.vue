<template>
  <div class="stack">
    <!-- Toast container -->
    <div class="toast-container">
      <div
        v-for="t in toasts"
        :key="t.id"
        :class="['toast', `toast-${t.type}`]"
        @click="dismiss(t.id)"
      >
        {{ t.message }}
      </div>
    </div>

    <!-- Page header with quick-create -->
    <div class="page-header">
      <div>
        <h2>Dashboard</h2>
        <p v-if="!eventsLoading && dashboardEvents.length > 0">{{ stats.total }} evento(s) cadastrado(s)</p>
        <p v-else-if="!eventsLoading">Bem-vindo ao inEvent</p>
      </div>
      <RouterLink v-if="can('events', 'create')" to="/events/create" class="btn btn-primary">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Criar Evento
      </RouterLink>
    </div>

    <!-- Overview Cards -->
    <div class="metric-grid overview-grid">
      <div v-if="eventsLoading" v-for="i in 8" :key="'s-'+i" class="metric-card skeleton-card">
        <div class="skeleton-line skeleton-label"></div>
        <div class="skeleton-line skeleton-value"></div>
      </div>
      <template v-else>
        <div class="metric-card metric-card-highlight">
          <div class="metric-card-label">📅 Eventos</div>
          <div class="metric-card-value">{{ animateNum(stats.total) }}</div>
        </div>
        <div class="metric-card">
          <div class="metric-card-label">📋 Planejados</div>
          <div class="metric-card-value metric-value-muted">{{ animateNum(stats.planned) }}</div>
        </div>
        <div class="metric-card metric-card-success">
          <div class="metric-card-label">✅ Confirmados</div>
          <div class="metric-card-value">{{ animateNum(stats.confirmed) }}</div>
        </div>
        <div class="metric-card metric-card-primary">
          <div class="metric-card-label">▶ Andamento</div>
          <div class="metric-card-value">{{ animateNum(stats.in_progress) }}</div>
        </div>
        <div class="metric-card">
          <div class="metric-card-label">🏁 Realizados</div>
          <div class="metric-card-value metric-value-muted">{{ animateNum(stats.completed) }}</div>
        </div>
        <div class="metric-card">
          <div class="metric-card-label">❌ Cancelados</div>
          <div class="metric-card-value metric-value-danger">{{ animateNum(stats.cancelled) }}</div>
        </div>
        <div class="metric-card metric-card-cta">
          <div class="metric-card-label">💰 Receita Total</div>
          <div class="metric-card-value metric-value-finance">{{ formatBRL(stats.revenue) }}</div>
          <div class="metric-card-cta-hint">Exportar relatório →</div>
        </div>
        <div class="metric-card metric-card-cta">
          <div class="metric-card-label">🎫 Ticket Médio</div>
          <div class="metric-card-value metric-value-finance">{{ formatBRL(stats.avgTicket) }}</div>
          <div class="metric-card-cta-hint">Ver detalhes →</div>
        </div>
      </template>
    </div>

    <!-- Empty state onboarding -->
    <div v-if="!eventsLoading && dashboardEvents.length === 0" class="card onboarding-card">
      <div class="onboarding-content">
        <span class="onboarding-icon">🚀</span>
        <h3>Você ainda não tem eventos</h3>
        <p>Crie seu primeiro evento em 3 passos:</p>
        <ol class="onboarding-steps">
          <li><strong>Cadastre</strong> o nome, cidade e data do evento</li>
          <li><strong>Veja</strong> a previsão do clima automaticamente</li>
          <li><strong>Analise</strong> os riscos e tome decisões inteligentes</li>
        </ol>
        <RouterLink to="/events/create" class="btn btn-primary">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Criar meu primeiro evento
        </RouterLink>
      </div>
    </div>

    <template v-if="dashboardEvents.length > 0">
      <!-- Main grid: Weather + Risk -->
      <div class="grid-2">
        <div class="stack-sm">
          <div class="card">
            <div class="search-bar">
              <div class="search-input-group">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>
                <input
                  ref="cityInput"
                  v-model="cityQuery"
                  type="text"
                  placeholder="Cidade (ex: São Paulo)"
                />
              </div>
              <button class="btn btn-primary" :disabled="weatherLoading" @click="searchWeather">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                {{ weatherLoading ? 'Analisando...' : 'Analisar Clima' }}
              </button>
            </div>
          </div>

          <LoadingState v-if="weatherLoading" message="Consultando clima..." />
          <ErrorMessage v-else-if="weatherError" :message="weatherError" @retry="searchWeather" />

          <template v-if="weather">
            <div class="dash-city-row">
              <span class="dash-city-label">{{ searchedCity }}</span>
              <button class="btn btn-sm" :class="favorited ? 'btn-secondary' : 'btn-ghost'" :disabled="favLoading" @click="toggleFavorite">
                {{ favorited ? '♥ Favoritada' : '♡ Favoritar' }}
              </button>
            </div>

            <WeatherCard :data="weather" :events="dashboardEvents" />

            <div class="metric-grid metric-grid-2col">
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
                <div class="metric-card-label">🌫 Qualidade do Ar</div>
                <div class="metric-card-value" style="font-size:18px">{{ aqiLabel }}</div>
              </div>
            </div>

            <div v-if="highRiskAlert" class="card card-alert-danger">
              <div class="alert-row">
                <span class="alert-icon">⚠️</span>
                <div>
                  <strong>Risco Climático Alto</strong>
                  <p class="text-sm">{{ weather.risk.recommendation }}</p>
                </div>
              </div>
            </div>

            <div v-else-if="weather.risk.alerts.length > 0" class="card">
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
            <svg viewBox="0 0 120 120" class="status-ring" role="img" aria-label="Gráfico de distribuição de eventos por status">
              <circle cx="60" cy="60" r="50" fill="none" stroke="#f1f5f9" stroke-width="14" />
              <g v-for="(s, i) in ringSegments" :key="s.key">
                <circle
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
              </g>
              <text x="60" y="52" text-anchor="middle" font-size="26" font-weight="700" fill="#1e293b">{{ stats.total }}</text>
              <text x="60" y="68" text-anchor="middle" font-size="9" fill="#64748b">eventos</text>
            </svg>
          </div>
          <div class="status-legend">
            <div v-for="s in statusBars" :key="s.key" class="legend-row">
              <span class="legend-dot" :style="{ background: s.color }"></span>
              <span class="legend-label">{{ s.label }}</span>
              <span class="legend-count">{{ animateNum(s.count) }}</span>
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
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import { weatherApi, eventsApi, favoritesApi } from '../services/api.js'
import { useAuth } from '../composables/useAuth.js'
import { useEventStats } from '../composables/useEventStats.js'
import { useToast } from '../composables/useToast.js'
import WeatherCard   from '../components/WeatherCard.vue'
import ForecastCard  from '../components/ForecastCard.vue'
import RiskBadge     from '../components/RiskBadge.vue'
import EventTable    from '../components/EventTable.vue'
import LoadingState  from '../components/LoadingState.vue'
import ErrorMessage  from '../components/ErrorMessage.vue'

const { can } = useAuth()
const { toasts, show: showToast, dismiss } = useToast()

const cityQuery = ref('São Paulo')
const searchedCity = ref('São Paulo')

let searchAbort = null
let debounceTimer = null

watch(cityQuery, (val) => {
  clearTimeout(debounceTimer)
  if (!val.trim() || val.trim().length < 2) return
  debounceTimer = setTimeout(() => searchWeather(), 600)
})

const weather = ref(null)
const forecast = ref([])
const weatherLoading = ref(false)
const forecastLoading = ref(false)
const weatherError = ref(null)
const dashboardEvents = ref([])
const eventsLoading = ref(false)

const favorited = ref(false)
const favLoading = ref(false)

const { stats, statusBars, ringSegments } = useEventStats(dashboardEvents)

const recentEvents = computed(() => dashboardEvents.value.slice(0, 5))

const highRiskAlert = computed(() =>
  weather.value?.risk?.status === 'HIGH_RISK'
)

const aqiLabels = { 1: 'Boa', 2: 'Razoável', 3: 'Moderada', 4: 'Ruim', 5: 'Muito Ruim' }
const aqiLabel = computed(() => aqiLabels[weather.value?.aqi] ?? '—')

const riskColor = computed(() => {
  const s = weather.value?.risk?.status
  if (s === 'HIGH_RISK') return '#EF4444'
  if (s === 'MEDIUM_RISK') return '#F59E0B'
  return '#22C55E'
})

const animatedValues = ref({})

function animateNum(target) {
  const key = `v_${target}`
  if (!animatedValues.value[key]) {
    animatedValues.value[key] = ref(0)
    animateValue(animatedValues.value[key], target)
  }
  return animatedValues.value[key]
}

function animateValue(refVal, target) {
  const start = performance.now()
  const duration = 600
  const from = 0

  function tick(now) {
    const elapsed = now - start
    const progress = Math.min(elapsed / duration, 1)
    const eased = 1 - Math.pow(1 - progress, 3)
    refVal.value = Math.round(from + (target - from) * eased)
    if (progress < 1) requestAnimationFrame(tick)
  }

  requestAnimationFrame(tick)
}

function formatBRL(n) {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n || 0)
}

function alertIcon(type) {
  return { heat: '🌡', cold: '🧊', wind: '💨', rain: '🌧', air: '🌫', humidity: '💧' }[type] ?? '⚠️'
}

async function searchWeather() {
  if (!cityQuery.value.trim()) return

  if (searchAbort) searchAbort.abort()
  searchAbort = new AbortController()

  weatherLoading.value = true
  weatherError.value = null
  weather.value = null
  forecast.value = []
  favorited.value = false

  try {
    const res = await weatherApi.search(cityQuery.value.trim(), 'BR', { signal: searchAbort.signal })
    weather.value = res.data
    searchedCity.value = cityQuery.value.trim()
    loadForecast()
  } catch (e) {
    if (e.name === 'AbortError') return
    weatherError.value = e.message
  } finally {
    weatherLoading.value = false
  }
}

async function loadForecast() {
  forecastLoading.value = true
  try {
    const res = await weatherApi.forecast(cityQuery.value, 'BR')
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
    showToast('Cidade favoritada com sucesso!', 'success')
  } catch (e) {
    if (e.message?.includes('já está')) {
      favorited.value = true
      showToast('Cidade já estava nos favoritos', 'info')
    }
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

onBeforeUnmount(() => {
  if (searchAbort) searchAbort.abort()
  clearTimeout(debounceTimer)
})
</script>

<style scoped>
.overview-grid {
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
}

.metric-grid-2col {
  grid-template-columns: 1fr 1fr;
}

.metric-card-highlight .metric-card-value {
  color: var(--color-primary);
  font-size: 28px;
}

.metric-card-success .metric-card-value {
  color: #16a34a;
}

.metric-card-primary .metric-card-value {
  color: #2563eb;
}

.metric-value-muted {
  color: var(--color-text-secondary) !important;
}

.metric-value-danger {
  color: #ef4444 !important;
}

.metric-value-finance {
  font-size: 20px !important;
  color: #059669 !important;
}

.metric-card-cta {
  cursor: pointer;
  position: relative;
}

.metric-card-cta:hover {
  border-color: var(--color-primary);
}

.metric-card-cta-hint {
  font-size: 11px;
  color: var(--color-primary);
  opacity: 0;
  transition: opacity 150ms;
  margin-top: 2px;
}

.metric-card-cta:hover .metric-card-cta-hint {
  opacity: 1;
}

.skeleton-card {
  pointer-events: none;
}

.skeleton-line {
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s ease-in-out infinite;
  border-radius: 4px;
}

.skeleton-label {
  width: 60%;
  height: 12px;
  margin-bottom: 10px;
}

.skeleton-value {
  width: 40%;
  height: 22px;
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.onboarding-card {
  background: linear-gradient(135deg, #1e3a5f 0%, #1a365d 100%);
  color: #fff;
  border: none;
}

.onboarding-content {
  text-align: center;
  padding: 32px 16px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}

.onboarding-icon {
  font-size: 48px;
}

.onboarding-content h3 {
  font-size: 20px;
  color: #fff;
  margin: 0;
}

.onboarding-content p {
  color: rgba(255,255,255,0.75);
}

.onboarding-steps {
  text-align: left;
  color: rgba(255,255,255,0.85);
  line-height: 2;
  font-size: 14px;
  margin: 8px 0 16px;
}

.onboarding-steps li strong {
  color: #60a5fa;
}

.onboarding-content .btn-primary {
  background: #60a5fa;
  border: none;
  color: #1e3a5f;
  font-weight: 700;
}

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

.card-alert-danger {
  background: #fef2f2;
  border-color: #fecaca;
}

.alert-row {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.alert-icon {
  font-size: 24px;
  flex-shrink: 0;
  line-height: 1;
}

/* Status chart */
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

/* Toast notifications */
.toast-container {
  position: fixed;
  top: 16px;
  right: 16px;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 8px;
  pointer-events: none;
}

.toast {
  pointer-events: auto;
  padding: 12px 20px;
  border-radius: var(--radius-md);
  font-size: 13px;
  font-weight: 500;
  box-shadow: 0 4px 16px rgba(0,0,0,0.15);
  cursor: pointer;
  animation: toastIn 250ms ease;
  max-width: 320px;
}

.toast-success {
  background: #16a34a;
  color: #fff;
}

.toast-info {
  background: #2563eb;
  color: #fff;
}

.toast-error {
  background: #ef4444;
  color: #fff;
}

@keyframes toastIn {
  from { opacity: 0; transform: translateX(40px); }
  to { opacity: 1; transform: translateX(0); }
}

@media (max-width: 600px) {
  .overview-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .metric-grid-2col {
    grid-template-columns: 1fr 1fr;
  }

  .status-grid {
    grid-template-columns: 1fr;
    justify-items: center;
  }
}
</style>
