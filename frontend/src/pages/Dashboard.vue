<template>
  <div class="stack">
    <!-- Search -->
    <div class="card">
      <div class="search-bar">
        <div class="search-input-group">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>
          <input
            v-model="city"
            type="text"
            placeholder="Cidade (ex: São Paulo)"
            @keyup.enter="searchWeather"
          />
        </div>
        <button class="btn btn-primary" :disabled="loading" @click="searchWeather">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          Buscar
        </button>
      </div>
    </div>

    <!-- Loading -->
    <LoadingState v-if="loading" message="Consultando clima..." />

    <!-- Error -->
    <ErrorMessage v-else-if="error" :message="error" @retry="searchWeather" />

    <template v-else-if="weather">
      <!-- Cidade + botão favoritar -->
      <div class="dash-city-row">
        <span class="dash-city-label">{{ searchedCity }}</span>
        <button
          class="btn btn-sm"
          :class="favorited ? 'btn-secondary' : 'btn-ghost'"
          :disabled="favLoading"
          @click="toggleFavorite"
        >
          {{ favorited ? '♥ Favoritada' : '♡ Favoritar' }}
        </button>
      </div>

      <!-- Main weather + metrics row -->
      <div class="grid-2">
        <WeatherCard :data="weather" />

        <div class="stack-sm">
          <!-- Risk card -->
          <div class="card">
            <div class="card-header">
              <h3>Análise de Risco</h3>
              <RiskBadge :status="weather.risk.status" />
            </div>
            <div>
              <div class="flex-between mb-16">
                <span class="text-sm text-muted">Score de risco</span>
                <span class="font-bold" :style="{ color: riskColor }">{{ weather.risk.score }}/100</span>
              </div>
              <div class="risk-bar">
                <div class="risk-bar-fill" :style="{ width: weather.risk.score + '%', background: riskColor }" />
              </div>
              <p class="text-sm text-muted mt-8">{{ weather.risk.recommendation }}</p>
            </div>
          </div>

          <!-- Metric quick cards -->
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
              <div class="metric-card-value" style="font-size:18px;">{{ aqiLabel }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Alerts -->
      <div v-if="weather.risk.alerts.length > 0" class="card">
        <div class="card-header"><h3>Alertas Climáticos</h3></div>
        <div class="stack-sm">
          <div
            v-for="(alert, i) in weather.risk.alerts"
            :key="i"
            :class="['alert', `alert-${alert.severity}`]"
          >
            <span>{{ alertIcon(alert.type) }}</span>
            <span>{{ alert.message }}</span>
          </div>
        </div>
      </div>

      <!-- Mini forecast -->
      <div class="card">
        <div class="card-header">
          <h3>Próximas Horas</h3>
          <RouterLink to="/weather" class="btn btn-ghost btn-sm">Ver tudo</RouterLink>
        </div>
        <LoadingState v-if="forecastLoading" message="Carregando previsão..." />
        <div v-else-if="forecast.length" class="forecast-grid">
          <ForecastCard v-for="item in forecast.slice(0,8)" :key="item.datetime" :item="item" />
        </div>
      </div>
    </template>

    <!-- Recent events -->
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
import { ref, computed, watch, onMounted } from 'vue'
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
const forecast= ref([])
const loading = ref(false)
const forecastLoading = ref(false)
const error   = ref(null)
const recentEvents  = ref([])
const eventsLoading = ref(false)

const favorited  = ref(false)
const favLoading = ref(false)

// Debounce: busca ao digitar (600ms)
let debounceTimer = null
watch(city, (val) => {
  clearTimeout(debounceTimer)
  if (!val.trim() || val.trim().length < 2) return
  debounceTimer = setTimeout(() => searchWeather(), 600)
})

const aqiLabels = { 1: 'Boa', 2: 'Razoável', 3: 'Moderada', 4: 'Ruim', 5: 'Muito Ruim' }
const aqiLabel  = computed(() => aqiLabels[weather.value?.aqi] ?? '—')

const riskColor = computed(() => {
  const s = weather.value?.risk?.status
  if (s === 'HIGH_RISK')   return '#EF4444'
  if (s === 'MEDIUM_RISK') return '#F59E0B'
  return '#22C55E'
})

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
    // silently ignore
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
    recentEvents.value = (res.data ?? []).slice(0, 5)
  } catch {
    // silently ignore
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
</style>
