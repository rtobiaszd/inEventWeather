<template>
  <div v-if="city" class="card weather-preview-card">
    <div class="card-header">
      <h3>
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/>
        </svg>
        Inteligência Climática
      </h3>
      <button v-if="hasData" class="btn btn-ghost btn-sm" @click="refresh" :disabled="loading">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
        </svg>
        Atualizar
      </button>
    </div>

    <div v-if="loading && !weather" class="weather-preview-loading">
      <span class="spinner" style="width:18px;height:18px;border-width:2px;"></span>
      <span class="text-sm text-muted">Analisando clima em {{ city }}...</span>
    </div>

    <template v-else-if="weather">
      <div class="wp-row">
        <div class="wp-current">
          <img v-if="weather.current.icon"
            :src="`https://openweathermap.org/img/wn/${weather.current.icon}@2x.png`"
            width="48" height="48" :alt="weather.current.weather_description" />
          <div>
            <div class="wp-temp">{{ weather.current.temperature }}<span class="text-sm text-muted">°C</span></div>
            <div class="wp-desc">{{ weather.current.weather_description }}</div>
          </div>
        </div>
        <div class="wp-metrics">
          <span title="Umidade">💧 {{ weather.current.humidity }}%</span>
          <span title="Vento">💨 {{ weather.current.wind_speed }} km/h</span>
          <span v-if="weather.aqi" title="Qualidade do Ar">🌫 {{ aqiLabel }}</span>
        </div>
        <RiskBadge v-if="weather.risk" :status="weather.risk.status" :score="weather.risk.score" :show-bar="true" />
      </div>

      <div v-if="hasEventDate && weather.risk" class="wp-forecast">
        <div class="wp-forecast-header">
          <span class="text-sm font-medium">Previsão para {{ formatDate(eventDate) }}</span>
        </div>

        <WeatherTimeline
          :forecast="weather.forecast ?? []"
          :event-date="eventDate"
          :event-time="eventTime"
        />

        <div class="risk-bar mt-8">
          <div class="risk-bar-fill" :style="{ width: weather.risk.score + '%', background: riskColor }" />
        </div>
        <p class="wp-recommendation">{{ weather.risk.recommendation }}</p>
      </div>

      <div v-if="isOutdoor" class="wp-better-dates">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
        </svg>
        <RouterLink :to="`/events/best-dates?city=${city}`">
          Ver datas recomendadas para {{ city }}
        </RouterLink>
      </div>
    </template>

    <div v-else-if="forecastLoading && weather" class="weather-preview-loading">
      <span class="spinner" style="width:14px;height:14px;border-width:2px;"></span>
      <span class="text-sm text-muted">Analisando data...</span>
    </div>

    <div v-else-if="!loading && !weather && !hasError" class="wp-hint">
      <span class="text-sm text-muted">Digite a cidade para ver a previsão do clima</span>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import { weatherApi } from '../services/api.js'
import RiskBadge from './RiskBadge.vue'
import WeatherTimeline from './WeatherTimeline.vue'

const props = defineProps({
  city:      { type: String, default: '' },
  country:   { type: String, default: 'BR' },
  eventDate: { type: String, default: '' },
  eventTime: { type: String, default: '' },
  isOutdoor: { type: Boolean, default: false },
})

const weather = ref(null)
const eventForecast = ref(null)
const loading = ref(false)
const forecastLoading = ref(false)
const hasError = ref(false)

const aqiLabels = { 1: 'Boa', 2: 'Razoável', 3: 'Moderada', 4: 'Ruim', 5: 'Muito Ruim' }
const aqiLabel = computed(() => aqiLabels[weather.value?.aqi] ?? '—')

const riskColor = computed(() => {
  const s = weather.value?.risk?.status
  if (s === 'HIGH_RISK') return '#EF4444'
  if (s === 'MEDIUM_RISK') return '#F59E0B'
  return '#22C55E'
})

const hasData = computed(() => !!weather.value)
const hasEventDate = computed(() => props.eventDate && props.eventDate.length === 10)

let abortController = null
let debounceTimer = null

function formatDate(date) {
  if (!date) return ''
  const [y, m, d] = date.split('-')
  return `${d}/${m}`
}

async function fetchWeather() {
  if (!props.city || props.city.trim().length < 2) return

  if (abortController) abortController.abort()
  abortController = new AbortController()

  loading.value = true
  hasError.value = false

  try {
    const res = await weatherApi.search(props.city.trim(), props.country, { signal: abortController.signal })
    weather.value = res.data
    if (hasEventDate.value) {
      loadEventForecast()
    }
  } catch (e) {
    if (e.name === 'AbortError') return
    hasError.value = true
    weather.value = null
  } finally {
    loading.value = false
  }
}

async function loadEventForecast() {
  if (!props.city || !hasEventDate.value) return
  forecastLoading.value = true
  try {
    const res = await weatherApi.forecast(props.city.trim(), props.country)
    const list = res.data?.forecast ?? []
    if (!list.length) return

    const eventTs = new Date(`${props.eventDate}T${props.eventTime || '12:00'}:00`).getTime() / 1000

    let closest = null
    let minDiff = Infinity
    for (const entry of list) {
      const diff = Math.abs((entry.timestamp) - eventTs)
      if (diff < minDiff) {
        minDiff = diff
        closest = entry
      }
    }

    if (closest) {
      eventForecast.value = {
        type: 'forecast',
        temperature: closest.temperature ?? 0,
        rain_probability: closest.rain_probability ?? 0,
        wind_speed: closest.wind_speed ?? 0,
        weather_main: closest.weather_main ?? '',
      }
    }
  } catch {
    // silent
  } finally {
    forecastLoading.value = false
  }
}

async function refresh() {
  weather.value = null
  eventForecast.value = null
  await fetchWeather()
}

watch(() => props.city, (val) => {
  clearTimeout(debounceTimer)
  if (!val || val.trim().length < 2) {
    weather.value = null
    eventForecast.value = null
    return
  }
  debounceTimer = setTimeout(fetchWeather, 500)
})

watch(() => props.eventDate, () => {
  if (weather.value && hasEventDate.value) {
    loadEventForecast()
  }
})

onBeforeUnmount(() => {
  if (abortController) abortController.abort()
  clearTimeout(debounceTimer)
})
</script>

<style scoped>
.weather-preview-card {
  border-left: 3px solid var(--color-primary);
  background: var(--color-primary-xlight);
}
.weather-preview-card .card-header {
  margin-bottom: 10px;
}
.weather-preview-card .card-header h3 {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
}

.weather-preview-loading {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 0;
}

.wp-row {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.wp-current {
  display: flex;
  align-items: center;
  gap: 6px;
}
.wp-temp {
  font-size: 22px;
  font-weight: 700;
  line-height: 1;
}
.wp-desc {
  font-size: 11px;
  color: var(--color-text-secondary);
  text-transform: capitalize;
}

.wp-metrics {
  display: flex;
  gap: 12px;
  font-size: 12px;
  color: var(--color-text-secondary);
  flex: 1;
}

.wp-forecast {
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid var(--color-border);
}
.wp-forecast-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
}
.wp-forecast-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 6px 16px;
}
.wp-fc-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
}
.wp-fc-label {
  color: var(--color-text-secondary);
}
.wp-fc-value {
  font-weight: 600;
}

.wp-recommendation {
  font-size: 12px;
  color: var(--color-text-secondary);
  margin-top: 8px;
  line-height: 1.5;
}

.wp-better-dates {
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid var(--color-border);
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
}
.wp-better-dates a {
  color: var(--color-primary);
  font-weight: 600;
  text-decoration: none;
}
.wp-better-dates a:hover {
  text-decoration: underline;
}

.wp-hint {
  padding: 4px 0;
}
</style>
