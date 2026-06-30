<template>
  <div class="stack">
    <!-- Search form -->
    <div class="card">
      <div class="card-header"><h3>Pesquisar Clima</h3></div>
      <div class="search-bar">
        <div class="search-input-group">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>
          <input v-model="city" type="text" placeholder="Nome da cidade" @keyup.enter="search" />
        </div>
        <div class="search-input-group" style="max-width:110px;">
          <input v-model="country" type="text" placeholder="País (BR)" maxlength="2" style="padding-left:12px;" />
        </div>
        <button class="btn btn-primary" :disabled="loading" @click="search">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          {{ loading ? 'Buscando...' : 'Buscar' }}
        </button>
      </div>
    </div>

    <LoadingState v-if="loading" message="Consultando API de clima..." />
    <ErrorMessage v-else-if="error" :message="error" @retry="search" />

    <template v-else-if="weather">
      <!-- Current weather -->
      <WeatherCard :data="weather" />

      <!-- Risk analysis -->
      <div class="card">
        <div class="card-header">
          <h3>Análise de Risco para Eventos</h3>
          <RiskBadge :status="weather.risk.status" :score="weather.risk.score" :show-bar="true" />
        </div>
        <div class="grid-2">
          <div>
            <p class="text-sm font-medium mb-16">Recomendação</p>
            <p class="text-sm text-muted" style="line-height:1.7">{{ weather.risk.recommendation }}</p>
            <div v-if="weather.risk.alerts.length === 0" class="mt-16">
              <span class="badge badge-success">✓ Sem alertas ativos</span>
            </div>
          </div>
          <div>
            <p class="text-sm font-medium mb-16">Métricas de Risco</p>
            <div class="stack-sm">
              <div class="flex-between">
                <span class="text-sm text-muted">Chuva (próx. 12h)</span>
                <span class="font-medium">{{ weather.risk.summary.rain_probability }}%</span>
              </div>
              <div class="flex-between">
                <span class="text-sm text-muted">Vento</span>
                <span class="font-medium">{{ weather.risk.summary.wind_speed_kmh }} km/h</span>
              </div>
              <div class="flex-between">
                <span class="text-sm text-muted">Qualidade do Ar (AQI)</span>
                <span class="font-medium">{{ weather.risk.summary.aqi }} — {{ aqiLabel }}</span>
              </div>
              <div class="flex-between">
                <span class="text-sm text-muted">Score Total</span>
                <span class="font-bold" :style="{color: riskColor}">{{ weather.risk.score }}/100</span>
              </div>
            </div>
          </div>
        </div>

        <div v-if="weather.risk.alerts.length" class="mt-16 stack-sm">
          <div v-for="(a, i) in weather.risk.alerts" :key="i" :class="['alert', `alert-${a.severity}`]">
            {{ alertIcon(a.type) }} {{ a.message }}
          </div>
        </div>
      </div>

      <!-- Air quality -->
      <div v-if="airQuality" class="card">
        <div class="card-header">
          <h3>Qualidade do Ar</h3>
          <span :class="['badge', aqiBadgeClass]">AQI {{ airQuality.aqi }} — {{ airQuality.aqi_label }}</span>
        </div>
        <div class="metric-grid">
          <div class="metric-card" v-for="(val, key) in airQuality.components" :key="key">
            <div class="metric-card-label">{{ key.toUpperCase() }}</div>
            <div class="metric-card-value" style="font-size:18px;">{{ val.toFixed(1) }}</div>
            <div class="text-sm text-muted">μg/m³</div>
          </div>
        </div>
      </div>

      <!-- Forecast -->
      <div class="card">
        <div class="card-header"><h3>Previsão — Próximos 5 dias</h3></div>
        <LoadingState v-if="forecastLoading" message="Carregando previsão..." />
        <div v-else-if="forecast.length" class="forecast-grid">
          <ForecastCard v-for="item in forecast" :key="item.datetime" :item="item" />
        </div>
      </div>
    </template>

    <!-- Default empty -->
    <div v-else class="empty-state">
      <span class="empty-icon">🔍</span>
      <h3>Pesquise uma cidade</h3>
      <p>Digite o nome de uma cidade acima para consultar as condições climáticas e análise de risco.</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { weatherApi } from '../services/api.js'
import WeatherCard  from '../components/WeatherCard.vue'
import ForecastCard from '../components/ForecastCard.vue'
import RiskBadge    from '../components/RiskBadge.vue'
import LoadingState from '../components/LoadingState.vue'
import ErrorMessage from '../components/ErrorMessage.vue'

const route = useRoute()

const city    = ref(route.query.city  || '')
const country = ref(route.query.country || 'BR')

const weather        = ref(null)
const forecast       = ref([])
const airQuality     = ref(null)
const loading        = ref(false)
const forecastLoading= ref(false)
const error          = ref(null)

const aqiLabels = { 1: 'Boa', 2: 'Razoável', 3: 'Moderada', 4: 'Ruim', 5: 'Muito Ruim' }
const aqiLabel  = computed(() => aqiLabels[weather.value?.aqi] ?? '—')
const aqiBadgeClass = computed(() => {
  const a = airQuality.value?.aqi
  if (a >= 4) return 'badge-danger'
  if (a >= 3) return 'badge-warning'
  return 'badge-success'
})

const riskColor = computed(() => {
  const s = weather.value?.risk?.status
  return s === 'HIGH_RISK' ? '#EF4444' : s === 'MEDIUM_RISK' ? '#F59E0B' : '#22C55E'
})

function alertIcon(type) {
  return { heat: '🌡', cold: '🧊', wind: '💨', rain: '🌧', air: '🌫', humidity: '💧' }[type] ?? '⚠️'
}

async function search() {
  if (!city.value.trim()) return
  loading.value  = true
  error.value    = null
  weather.value  = null
  forecast.value = []
  airQuality.value = null

  try {
    const res = await weatherApi.search(city.value.trim(), country.value || 'BR')
    weather.value = res.data
    loadForecast()
    loadAirQuality()
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function loadForecast() {
  forecastLoading.value = true
  try {
    const res = await weatherApi.forecast(city.value, country.value || 'BR')
    forecast.value = res.data.forecast
  } catch {
    // silently ignore
  } finally {
    forecastLoading.value = false
  }
}

async function loadAirQuality() {
  try {
    const res = await weatherApi.airQuality(city.value, country.value || 'BR')
    airQuality.value = res.data
  } catch {
    // silently ignore
  }
}

onMounted(() => {
  if (city.value) search()
})
</script>
