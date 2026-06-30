<template>
  <div class="stack">
    <div class="page-header">
      <div>
        <h2>Recomendador de Data Ideal</h2>
        <p>Encontre a melhor data para seu evento baseado nas condições climáticas</p>
      </div>
    </div>

    <!-- Search bar -->
    <div class="card">
      <div class="search-bar">
        <div class="search-input-group">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>
          <input v-model="city" type="text" placeholder="Cidade (ex: São Paulo)" @keyup.enter="search" />
        </div>
        <button class="btn btn-primary" :disabled="loading" @click="search">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          {{ loading ? 'Analisando...' : 'Analisar Datas' }}
        </button>
      </div>
    </div>

    <!-- Favorites quick access -->
    <div v-if="favorites.length > 0" class="fav-quick-row">
      <span class="fav-quick-label">Cidades salvas:</span>
      <div class="fav-quick-list">
        <button
          v-for="fav in favorites"
          :key="fav.id"
          class="btn btn-sm"
          :class="city === fav.city ? 'btn-primary' : 'btn-ghost'"
          @click="switchCity(fav.city)"
        >
          {{ fav.city }}
        </button>
      </div>
    </div>

    <LoadingState v-if="loading" message="Analisando previsão para os próximos 14 dias..." />
    <ErrorMessage v-else-if="error" :message="error" @retry="search" />

    <template v-else-if="result">
      <!-- Custom Priority Weights -->
      <div class="card priority-card">
        <div class="card-header" style="cursor:pointer;user-select:none" @click="showPriorities = !showPriorities">
          <h3>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            Personalizar Prioridades
            <span class="priority-chips">
              <span v-for="p in priorityFactors" :key="p.key" class="priority-chip" :class="{ 'priority-chip-active': weights[p.key] > 1.2, 'priority-chip-low': weights[p.key] < 0.6 }">
                {{ p.icon }} {{ p.label }}
              </span>
            </span>
          </h3>
          <div class="flex-gap">
            <button v-if="showPriorities" class="btn btn-sm btn-ghost" @click.stop="resetWeights">Restaurar</button>
            <span class="text-sm text-muted">{{ showPriorities ? 'Ocultar' : 'Ajustar' }}</span>
          </div>
        </div>
        <div v-if="showPriorities" class="priority-body">
          <p class="text-sm text-muted priority-help">Ajuste a importância de cada fator climático. As datas são reordenadas automaticamente.</p>
          <div v-for="p in priorityFactors" :key="p.key" class="priority-row">
            <div class="priority-info">
              <span class="priority-icon">{{ p.icon }}</span>
              <div>
                <span class="priority-label">{{ p.label }}</span>
                <span class="priority-desc">{{ p.description }}</span>
              </div>
            </div>
            <div class="priority-slider-group">
              <span class="priority-slider-min">{{ p.minLabel }}</span>
              <div class="priority-slider-track">
                <input type="range" :min="0.1" :max="3" step="0.1" v-model.number="weights[p.key]" class="priority-slider" />
                <div class="priority-slider-fill" :style="{ width: ((weights[p.key] - 0.1) / 2.9 * 100) + '%' }"></div>
              </div>
              <span class="priority-slider-max">{{ p.maxLabel }}</span>
              <span class="priority-slider-value">{{ weights[p.key].toFixed(1) }}x</span>
            </div>
          </div>
          <div v-if="sortedDates.length" class="priority-preview">
            <span class="text-sm text-muted">Prévia: </span>
            <span class="priority-preview-item" v-for="(d, i) in sortedDates.slice(0, 5)" :key="d.date">
              <span class="badge" :class="statusBadgeClass(d.personalizedStatus)" style="font-size:10px;">#{{ i + 1 }} {{ formatDay(d.date) }}</span>
            </span>
          </div>
        </div>
      </div>

      <!-- Best date highlight -->
      <div v-if="sortedDates.length" class="card best-date-highlight">
        <div class="bhd-content">
          <div class="bhd-left">
            <div class="bhd-trophy">🏆</div>
            <div>
              <span class="bhd-label">Melhor data recomendada</span>
              <span class="bhd-date">{{ formatDateFull(sortedDates[0].date) }} — {{ capitalize(sortedDates[0].weekday) }}</span>
              <div class="bhd-stats">
                <span>🌡 {{ sortedDates[0].avg_temperature }}°C</span>
                <span>🌧 {{ sortedDates[0].max_rain }}% chuva</span>
                <span>💨 {{ sortedDates[0].max_wind }} km/h</span>
                <span>💧 {{ sortedDates[0].avg_humidity }}%</span>
              </div>
            </div>
          </div>
          <div class="bhd-right">
            <span :class="['badge', statusBadgeClass(sortedDates[0].personalizedStatus)]" class="bhd-badge" style="font-size:13px;padding:6px 14px">
              {{ statusLabel(sortedDates[0].personalizedStatus) }} · {{ 100 - sortedDates[0].personalizedScore }} pts
            </span>
            <button class="btn btn-sm btn-primary" @click="createEvent(sortedDates[0])">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Criar Evento
            </button>
          </div>
        </div>
      </div>

      <!-- Weather Timeline Chart -->
      <div v-if="chartData.length > 1" class="card">
        <div class="card-header">
          <h3>Tendência Climática — Próximos 14 Dias</h3>
          <span class="text-sm text-muted">{{ result.city_name }}</span>
        </div>
        <div class="timeline-chart-wrap">
          <svg
            :viewBox="`0 0 ${chartWidth} ${chartHeight}`"
            class="timeline-chart"
            preserveAspectRatio="xMidYMid meet"
            role="img"
            aria-label="Gráfico de tendência de temperatura e chuva"
          >
            <!-- Grid lines -->
            <line v-for="g in gridLines" :key="'g-'+g.y" :x1="padLeft" :y1="g.y" :x2="chartWidth - padRight" :y2="g.y" stroke="#E2E8F0" stroke-width="1" />

            <!-- Y-axis labels -->
            <text v-for="g in gridLines" :key="'tl-'+g.y" :x="padLeft - 6" :y="g.y + 4" text-anchor="end" font-size="9" fill="#94A3B8">{{ g.label }}°</text>

            <!-- Risk zone backgrounds -->
            <rect v-for="(z, zi) in riskZones" :key="'z-'+zi" :x="z.x" :y="0" :width="z.w" :height="chartHeight" :fill="z.color" opacity="0.06" />

            <!-- Rain bars -->
            <rect
              v-for="(d, i) in chartData"
              :key="'r-'+i"
              :x="barX(i)"
              :y="rainY(d.max_rain)"
              :width="barWidth"
              :height="rainBarHeight(d.max_rain)"
              :fill="d.status === 'AVOID' ? '#EF4444' : d.status === 'CAUTION' ? '#F59E0B' : d.status === 'FAVORABLE' ? '#3B82F6' : '#22C55E'"
              opacity="0.35"
              rx="2"
            />

            <!-- Temperature polyline -->
            <polyline
              :points="tempPoints"
              fill="none"
              stroke="#EF4444"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            />

            <!-- Temperature dots -->
            <circle
              v-for="(d, i) in chartData"
              :key="'td-'+i"
              :cx="tempX(i)"
              :cy="tempY(d.avg_temperature)"
              :r="d.date === sortedDates[0]?.date ? 4 : 3"
              fill="#EF4444"
              stroke="#fff"
              stroke-width="1.5"
            />

            <!-- Best date marker -->
            <circle
              v-if="sortedDates.length"
              :cx="tempX(0)"
              :cy="tempY(sortedDates[0].avg_temperature)"
              r="6"
              fill="none"
              stroke="#22C55E"
              stroke-width="2"
              stroke-dasharray="3,2"
            />

            <!-- X-axis labels -->
            <text
              v-for="(d, i) in chartLabels"
              :key="'xl-'+i"
              :x="tempX(i)"
              :y="chartHeight - 4"
              text-anchor="middle"
              font-size="8"
              fill="#94A3B8"
            >{{ d }}</text>
          </svg>
        </div>
        <div class="timeline-legend">
          <span class="tl-legend-item"><span class="tl-dot tl-dot-line"></span> Temperatura (°C)</span>
          <span class="tl-legend-item"><span class="tl-dot tl-dot-bar"></span> Chuva (%)</span>
          <span class="tl-legend-item"><span class="tl-dot tl-dot-best"></span> Melhor data</span>
        </div>
      </div>

      <!-- All dates grid -->
      <div class="card">
        <div class="card-header">
          <h3>Datas para {{ result.city_name }}</h3>
          <span class="text-sm text-muted">{{ sortedDates.length }} dias analisados</span>
        </div>

        <div class="best-dates-grid">
          <div
            v-for="(d, idx) in sortedDates"
            :key="d.date"
            :class="['best-date-card', `best-date-${d.personalizedStatus.toLowerCase()}`]"
          >
            <div class="best-date-card-header">
              <span class="best-date-day">{{ formatDay(d.date) }}</span>
              <span class="best-date-weekday">{{ capitalize(d.weekday) }}</span>
            </div>
            <div class="best-date-card-score">
              <div class="best-date-score-ring">
                <svg viewBox="0 0 36 36" width="52" height="52">
                  <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#E2E8F0" stroke-width="3"/>
                  <path
                    d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                    fill="none"
                    :stroke="scoreColor(d.personalizedScore)"
                    stroke-width="3"
                    :stroke-dasharray="`${(100 - d.personalizedScore) / 100 * 100}, 100`"
                    stroke-linecap="round"
                    class="score-ring-fill"
                  />
                  <text x="18" y="20" text-anchor="middle" font-size="9.5" font-weight="700" fill="#1E293B">{{ 100 - d.personalizedScore }}</text>
                  <text x="18" y="26" text-anchor="middle" font-size="3.8" fill="#64748B">pts</text>
                </svg>
              </div>
            </div>
            <div class="best-date-card-stats">
              <div class="bds-row">
                <span class="bds-label">🌡 Temperatura</span>
                <span class="bds-value">{{ d.avg_temperature }}°C</span>
              </div>
              <div class="bds-row">
                <span class="bds-label">🌧 Chuva máx</span>
                <span class="bds-value">{{ d.max_rain }}%</span>
              </div>
              <div class="bds-row">
                <span class="bds-label">💨 Vento máx</span>
                <span class="bds-value">{{ d.max_wind }} km/h</span>
              </div>
              <div class="bds-row">
                <span class="bds-label">💧 Umidade</span>
                <span class="bds-value">{{ d.avg_humidity }}%</span>
              </div>
              <div class="bds-row">
                <span class="bds-label">Condição</span>
                <span class="bds-value text-sm">{{ capitalize(d.conditions) }}</span>
              </div>
            </div>
            <span :class="['badge', statusBadgeClass(d.personalizedStatus), 'best-date-badge']">
              {{ statusLabel(d.personalizedStatus) }}
            </span>
            <button class="btn btn-sm btn-create-from-card" @click="createEvent(d)">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Criar Evento
            </button>
          </div>
        </div>
      </div>

      <!-- Score explanation -->
      <div class="card score-explanation">
        <div class="card-header"><h3>Como o score é calculado</h3></div>
        <div class="score-explain-grid">
          <div class="score-explain-item">
            <span class="score-explain-ring" style="background:#22C55E"></span>
            <span><strong>Ideal</strong> (0-20) — Condições ótimas para eventos outdoor</span>
          </div>
          <div class="score-explain-item">
            <span class="score-explain-ring" style="background:#3B82F6"></span>
            <span><strong>Favorável</strong> (21-50) — Boas condições, pequenos cuidados</span>
          </div>
          <div class="score-explain-item">
            <span class="score-explain-ring" style="background:#F59E0B"></span>
            <span><strong>Cautela</strong> (51-70) — Risco moderado, tenha plano B</span>
          </div>
          <div class="score-explain-item">
            <span class="score-explain-ring" style="background:#EF4444"></span>
            <span><strong>Evitar</strong> (71-100) — Condições desfavoráveis, prefira indoor</span>
          </div>
        </div>
      </div>
    </template>

    <!-- Empty state -->
    <div v-else class="empty-state best-dates-empty">
      <span class="empty-icon">🔮</span>
      <h3>Descubra a melhor data para seu evento</h3>
      <p>Digite o nome de uma cidade acima para analisar o clima dos próximos 14 dias.</p>
      <div class="best-dates-empty-examples">
        <span>Cidades populares:</span>
        <button v-for="ex in examples" :key="ex" class="btn btn-ghost btn-sm" @click="switchCity(ex)">{{ ex }}</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { weatherApi, favoritesApi } from '../services/api.js'
import ErrorMessage from '../components/ErrorMessage.vue'

const route = useRoute()
const router = useRouter()

const city = ref(route.query.city || '')
const result = ref(null)
const loading = ref(false)
const error = ref(null)
const favorites = ref([])
const showPriorities = ref(false)

const examples = ['São Paulo', 'Rio de Janeiro', 'Belo Horizonte', 'Salvador', 'Curitiba']

const labels = { rain: 'Chuva', wind: 'Vento', temperature: 'Temperatura', humidity: 'Umidade' }

const priorityFactors = [
  { key: 'rain', icon: '🌧', label: 'Chuva', description: 'Evitar dias chuvosos', minLabel: 'Ignorar', maxLabel: 'Crucial' },
  { key: 'wind', icon: '💨', label: 'Vento', description: 'Evitar dias ventosos', minLabel: 'Ignorar', maxLabel: 'Crucial' },
  { key: 'temperature', icon: '🌡', label: 'Temperatura', description: 'Preferir clima ameno', minLabel: 'Ignorar', maxLabel: 'Crucial' },
  { key: 'humidity', icon: '💧', label: 'Umidade', description: 'Evitar dias abafados', minLabel: 'Ignorar', maxLabel: 'Crucial' },
]

const defaultWeights = { rain: 1, wind: 1, temperature: 1, humidity: 1 }
const weights = ref({ ...defaultWeights })

function resetWeights() {
  weights.value = { ...defaultWeights }
}

const sortedDates = computed(() => {
  const dates = result.value?.dates || []
  if (!dates.length) return []

  return dates.map(d => {
    const score =
      (scoreRain(d.max_rain) * weights.value.rain) +
      (scoreWind(d.max_wind) * weights.value.wind) +
      (scoreTemperature(d.avg_temperature) * weights.value.temperature) +
      (scoreHumidity(d.avg_humidity) * weights.value.humidity)

    const personalizedScore = Math.min(100, Math.round(score))
    const personalizedStatus = personalizedScore <= 20 ? 'IDEAL' : personalizedScore <= 50 ? 'FAVORABLE' : personalizedScore <= 70 ? 'CAUTION' : 'AVOID'

    return { ...d, personalizedScore, personalizedStatus }
  }).sort((a, b) => a.personalizedScore - b.personalizedScore)
})

// Chart config
const chartWidth = 700
const chartHeight = 180
const padLeft = 30
const padRight = 16
const padTop = 12
const padBottom = 20

const chartData = computed(() => sortedDates.value)

const tempRange = computed(() => {
  const temps = chartData.value.map(d => d.avg_temperature)
  if (!temps.length) return { min: 15, max: 35 }
  const min = Math.floor(Math.min(...temps)) - 2
  const max = Math.ceil(Math.max(...temps)) + 2
  return { min, max: Math.max(max, min + 6) }
})

const gridLines = computed(() => {
  const lines = []
  const step = Math.max(1, Math.round((tempRange.value.max - tempRange.value.min) / 4))
  for (let t = tempRange.value.min; t <= tempRange.value.max; t += step) {
    lines.push({ y: tempY(t), label: t })
  }
  return lines
})

const riskZones = computed(() => {
  const zones = []
  const total = chartData.value.length
  if (!total) return zones
  const dataW = chartWidth - padLeft - padRight
  const stepW = dataW / total
  let start = -1
  let currentStatus = ''
  for (let i = 0; i < total; i++) {
    const d = chartData.value[i]
    if (d.personalizedStatus !== currentStatus) {
      if (start >= 0) {
        zones.push({ x: padLeft + start * stepW, w: (i - start) * stepW, color: zoneColor(currentStatus) })
      }
      start = i
      currentStatus = d.personalizedStatus
    }
  }
  if (start >= 0) {
    zones.push({ x: padLeft + start * stepW, w: (total - start) * stepW, color: zoneColor(currentStatus) })
  }
  return zones
})

const chartLabels = computed(() => {
  return chartData.value.map(d => {
    const [, m, day] = d.date.split('-')
    return `${day}/${m}`
  })
})

function zoneColor(status) {
  return { IDEAL: '#22C55E', FAVORABLE: '#3B82F6', CAUTION: '#F59E0B', AVOID: '#EF4444' }[status] || '#94A3B8'
}

function tempX(i) {
  const total = chartData.value.length
  if (total <= 1) return padLeft + (chartWidth - padLeft - padRight) / 2
  return padLeft + (i / (total - 1)) * (chartWidth - padLeft - padRight)
}

function tempY(temp) {
  const r = tempRange.value
  const dataH = chartHeight - padTop - padBottom
  if (r.max === r.min) return padTop + dataH / 2
  return padTop + ((r.max - temp) / (r.max - r.min)) * dataH
}

const tempPoints = computed(() => {
  return chartData.value.map((d, i) => `${tempX(i)},${tempY(d.avg_temperature)}`).join(' ')
})

function barX(i) {
  const total = chartData.value.length
  const dataW = chartWidth - padLeft - padRight
  const stepW = dataW / total
  return padLeft + i * stepW + (stepW - barWidth.value) / 2
}

const barWidth = computed(() => {
  const total = chartData.value.length
  if (total <= 1) return 16
  const dataW = chartWidth - padLeft - padRight
  const stepW = dataW / total
  return Math.max(4, Math.min(12, stepW * 0.5))
})

function rainY(rain) {
  const dataH = chartHeight - padTop - padBottom
  return padTop + dataH - (rain / 100) * dataH * 0.7
}

function rainBarHeight(rain) {
  const dataH = chartHeight - padTop - padBottom
  return (rain / 100) * dataH * 0.7
}

// Score functions (same logic as backend for consistency)
function scoreRain(maxRain) {
  if (maxRain >= 80) return 40
  if (maxRain >= 60) return 30
  if (maxRain >= 40) return 20
  if (maxRain >= 20) return 10
  return 0
}

function scoreWind(maxWind) {
  if (maxWind > 60) return 30
  if (maxWind > 35) return 25
  if (maxWind > 20) return 15
  if (maxWind > 12) return 8
  return 0
}

function scoreTemperature(avgTemp) {
  if (avgTemp > 38 || avgTemp < 5) return 35
  if (avgTemp > 32 || avgTemp < 10) return 25
  if (avgTemp > 28 || avgTemp < 15) return 15
  if (avgTemp > 25) return 5
  return 0
}

function scoreHumidity(avgHumidity) {
  if (avgHumidity > 90) return 15
  if (avgHumidity > 80) return 10
  if (avgHumidity > 70) return 5
  return 0
}

async function search() {
  if (!city.value.trim()) return
  loading.value = true
  error.value = null
  result.value = null
  try {
    const res = await weatherApi.bestDates(city.value.trim(), 'BR')
    result.value = res.data
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function switchCity(name) {
  city.value = name
  search()
}

function createEvent(dateObj) {
  router.push({
    name: 'events.create',
    query: { city: city.value, date: dateObj.date, type: 'outdoor' },
  })
}

function formatDateFull(date) {
  if (!date) return ''
  const [y, m, d] = date.split('-')
  const months = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez']
  return `${parseInt(d)} de ${months[parseInt(m) - 1]}`
}

function formatDay(date) {
  if (!date) return ''
  const [y, m, d] = date.split('-')
  return `${parseInt(d)}/${m}`
}

function capitalize(str) {
  if (!str) return ''
  return str.charAt(0).toUpperCase() + str.slice(1)
}

function statusLabel(status) {
  return { IDEAL: 'Ideal', FAVORABLE: 'Favorável', CAUTION: 'Cautela', AVOID: 'Evitar' }[status] || status
}

function statusBadgeClass(status) {
  return { IDEAL: 'badge-success', FAVORABLE: 'badge-info', CAUTION: 'badge-warning', AVOID: 'badge-danger' }[status] || 'badge-neutral'
}

function scoreColor(score) {
  if (score <= 20) return '#22C55E'
  if (score <= 50) return '#3B82F6'
  if (score <= 70) return '#F59E0B'
  return '#EF4444'
}

onMounted(async () => {
  if (city.value) search()
  try {
    const res = await favoritesApi.list()
    favorites.value = res.data ?? []
  } catch { /* silent */ }
})
</script>

<style scoped>
/* Priority / Preferences */
.priority-card {
  border-left: 3px solid #8B5CF6;
}

.priority-chips {
  display: inline-flex;
  gap: 4px;
  margin-left: 8px;
  flex-wrap: wrap;
}

.priority-chip {
  font-size: 10px;
  padding: 1px 6px;
  border-radius: 9999px;
  background: var(--color-bg);
  border: 1px solid var(--color-border);
  color: var(--color-text-secondary);
  transition: all 150ms;
}

.priority-chip-active {
  background: #FEF3C7;
  border-color: #F59E0B;
  color: #92400E;
}

.priority-chip-low {
  opacity: 0.5;
}

.priority-body {
  padding: 0 16px 16px;
  border-top: 1px solid var(--color-border);
}

.priority-help {
  margin: 12px 0;
}

.priority-row {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 10px;
  flex-wrap: wrap;
}

.priority-info {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 180px;
  flex: 1;
}

.priority-icon {
  font-size: 18px;
  flex-shrink: 0;
}

.priority-label {
  font-size: 13px;
  font-weight: 600;
  display: block;
}

.priority-desc {
  font-size: 11px;
  color: var(--color-text-secondary);
  display: block;
}

.priority-slider-group {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.priority-slider-min,
.priority-slider-max {
  font-size: 10px;
  color: var(--color-text-muted);
  white-space: nowrap;
  min-width: 44px;
}

.priority-slider-max {
  text-align: right;
}

.priority-slider-track {
  position: relative;
  width: 100px;
  height: 6px;
  background: var(--color-border);
  border-radius: 9999px;
}

.priority-slider {
  position: relative;
  width: 100%;
  height: 100%;
  -webkit-appearance: none;
  appearance: none;
  background: transparent;
  cursor: pointer;
  z-index: 2;
  margin: 0;
}

.priority-slider::-webkit-slider-thumb {
  -webkit-appearance: none;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: #8B5CF6;
  border: 2px solid #fff;
  box-shadow: 0 1px 4px rgba(0,0,0,0.2);
  cursor: pointer;
}

.priority-slider-fill {
  position: absolute;
  left: 0;
  top: 0;
  height: 100%;
  background: #8B5CF6;
  border-radius: 9999px;
  pointer-events: none;
}

.priority-slider-value {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-secondary);
  min-width: 30px;
  text-align: right;
}

.priority-preview {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 8px;
  padding-top: 10px;
  border-top: 1px solid var(--color-border);
  flex-wrap: wrap;
}

.priority-preview-item {
  display: inline-flex;
}

/* Best date highlight */
.best-date-highlight {
  background: linear-gradient(135deg, #065F46 0%, #047857 100%);
  color: #fff;
  border: none;
}

.bhd-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.bhd-left {
  display: flex;
  align-items: center;
  gap: 16px;
  flex: 1;
  min-width: 200px;
}

.bhd-trophy {
  font-size: 40px;
  flex-shrink: 0;
}

.bhd-label {
  display: block;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  opacity: 0.75;
  margin-bottom: 2px;
}

.bhd-date {
  display: block;
  font-size: 20px;
  font-weight: 700;
}

.bhd-stats {
  display: flex;
  gap: 16px;
  margin-top: 6px;
  font-size: 13px;
  opacity: 0.85;
  flex-wrap: wrap;
}

.bhd-right {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.bhd-badge {
  flex-shrink: 0;
}

/* Favorites quick access */
.fav-quick-row {
  display: flex;
  align-items: center;
  gap: 8px;
  overflow-x: auto;
  padding-bottom: 2px;
}

.fav-quick-label {
  font-size: 12px;
  color: var(--color-text-secondary);
  white-space: nowrap;
  font-weight: 500;
}

.fav-quick-list {
  display: flex;
  gap: 6px;
  flex-wrap: nowrap;
}

/* Timeline chart */
.timeline-chart-wrap {
  width: 100%;
  overflow-x: auto;
}

.timeline-chart {
  width: 100%;
  min-width: 400px;
  height: auto;
  display: block;
}

.timeline-legend {
  display: flex;
  gap: 16px;
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid var(--color-border);
  flex-wrap: wrap;
}

.tl-legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  color: var(--color-text-secondary);
}

.tl-dot {
  display: inline-block;
  width: 14px;
  height: 3px;
  border-radius: 2px;
  flex-shrink: 0;
}

.tl-dot-line { background: #EF4444; height: 3px; }
.tl-dot-bar { background: #3B82F6; height: 8px; width: 8px; border-radius: 2px; opacity: 0.5; }
.tl-dot-best { border: 2px dashed #22C55E; height: 8px; width: 8px; border-radius: 50%; background: transparent; }

/* Date cards grid */
.best-dates-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 12px;
}

.best-date-card {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  transition: box-shadow 150ms, transform 150ms;
  position: relative;
}

.best-date-card:hover {
  box-shadow: var(--shadow-md);
  transform: translateY(-2px);
}

.best-date-ideal {
  border-color: #BBF7D0;
  background: #F0FDF4;
}

.best-date-favorable {
  border-color: #BFDBFE;
  background: #EFF6FF;
}

.best-date-caution {
  border-color: #FDE68A;
  background: #FFFBEB;
}

.best-date-avoid {
  border-color: #FECACA;
  background: #FEF2F2;
}

.best-date-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.best-date-day {
  font-size: 15px;
  font-weight: 700;
}

.best-date-weekday {
  font-size: 10.5px;
  color: var(--color-text-secondary);
  text-transform: capitalize;
}

.best-date-card-score {
  display: flex;
  justify-content: center;
  padding: 2px 0;
}

.best-date-score-ring {
  display: flex;
  align-items: center;
  justify-content: center;
}

.best-date-card-stats {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.bds-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 11.5px;
}

.bds-label {
  color: var(--color-text-secondary);
}

.bds-value {
  font-weight: 600;
}

.best-date-badge {
  align-self: center;
  font-size: 10.5px;
}

.btn-create-from-card {
  width: 100%;
  justify-content: center;
  font-size: 11.5px;
  padding: 6px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transition: background 150ms, border-color 150ms;
  color: var(--color-text);
  font-weight: 600;
}

.btn-create-from-card:hover {
  border-color: var(--color-primary);
  color: var(--color-primary);
  background: var(--color-primary-light);
}

/* Score explanation */
.score-explanation {
  background: var(--color-bg);
}

.score-explain-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 8px;
}

.score-explain-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: var(--color-text-secondary);
  line-height: 1.4;
}

.score-explain-ring {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  flex-shrink: 0;
}

/* Empty state */
.best-dates-empty {
  text-align: center;
}

.best-dates-empty-examples {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 6px;
  margin-top: 16px;
  font-size: 13px;
  color: var(--color-text-secondary);
}

.best-dates-empty-examples .btn {
  font-size: 12px;
}

@keyframes ringFill {
  from { stroke-dashoffset: 100; }
  to { stroke-dashoffset: 0; }
}

.score-ring-fill {
  animation: ringFill 0.8s ease-out forwards;
}

@media (max-width: 700px) {
  .bhd-content {
    flex-direction: column;
    text-align: center;
  }

  .bhd-left {
    flex-direction: column;
    text-align: center;
  }

  .bhd-right {
    flex-direction: row;
  }

  .best-dates-grid {
    grid-template-columns: 1fr 1fr;
  }

  .priority-row {
    flex-direction: column;
    align-items: stretch;
  }

  .priority-slider-group {
    justify-content: stretch;
  }

  .priority-slider-track {
    flex: 1;
  }

  .score-explain-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 480px) {
  .best-dates-grid {
    grid-template-columns: 1fr;
  }
}
</style>
