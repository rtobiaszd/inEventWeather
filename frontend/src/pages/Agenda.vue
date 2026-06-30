<template>
  <div class="stack">
    <div class="page-header">
      <div>
        <h2>Agenda Inteligente</h2>
        <p>{{ events.length }} evento(s)</p>
      </div>
      <div class="flex-gap">
        <button v-if="riskAlerts.length" class="btn btn-ghost btn-sm" @click="riskFilter = riskFilter === 'all' ? 'HIGH_RISK' : 'all'">
          <span v-if="riskFilter === 'HIGH_RISK'" class="badge badge-danger badge-xs" style="margin-right:4px">Filtrando</span>
          Mostrar {{ riskFilter === 'all' ? 'apenas risco alto' : 'todos' }}
        </button>
        <RouterLink v-if="can('events', 'create')" to="/events/create" class="btn btn-primary">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Novo Evento
        </RouterLink>
      </div>
    </div>

    <div class="agenda-layout">
      <div class="card agenda-calendar-card">
        <div class="agenda-nav">
          <button class="btn btn-ghost btn-sm" @click="prevMonth">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
          </button>
          <span class="agenda-month-label">{{ monthLabel }}</span>
          <button class="btn btn-ghost btn-sm" @click="nextMonth">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
          </button>
          <button class="btn btn-ghost btn-sm" @click="today" style="margin-left:8px">Hoje</button>

          <div class="agenda-month-risk" v-if="monthRiskCounts.high > 0 || monthRiskCounts.medium > 0">
            <span v-if="monthRiskCounts.high > 0" class="agenda-risk-badge agenda-risk-high">
              {{ monthRiskCounts.high }} alto
            </span>
            <span v-if="monthRiskCounts.medium > 0" class="agenda-risk-badge agenda-risk-medium">
              {{ monthRiskCounts.medium }} médio
            </span>
          </div>
        </div>

        <div class="agenda-grid">
          <div v-for="day in dayHeaders" :key="day" class="agenda-day-header">{{ day }}</div>
          <div
            v-for="(day, i) in calendarDays"
            :key="i"
            :class="[
              'agenda-day',
              { 'agenda-day-other': day.otherMonth, 'agenda-day-today': day.today, 'agenda-day-selected': selectedDate === day.date }
            ]"
            @click="selectDate(day.date)"
          >
            <span class="agenda-day-num">{{ day.num }}</span>
            <div v-if="day.events.length" class="agenda-day-dots">
              <div v-if="day.worstRisk === 'HIGH_RISK'" class="agenda-risk-line agenda-risk-high"></div>
              <div v-else-if="day.worstRisk === 'MEDIUM_RISK'" class="agenda-risk-line agenda-risk-medium"></div>
              <div v-else class="agenda-risk-line agenda-risk-low"></div>
              <span
                v-for="e in day.events.slice(0, 4)"
                :key="e.id"
                :title="e.name"
                :class="['agenda-dot', riskDotClass(getEventRisk(e.id))]"
              />
              <span v-if="day.events.length > 4" class="agenda-dot-more">+{{ day.events.length - 4 }}</span>
            </div>
          </div>
        </div>

        <div class="agenda-legend">
          <span class="agenda-legend-item"><span class="agenda-dot agenda-dot-high"></span> Alto risco</span>
          <span class="agenda-legend-item"><span class="agenda-dot agenda-dot-medium"></span> Médio risco</span>
          <span class="agenda-legend-item"><span class="agenda-dot agenda-dot-low"></span> Baixo risco</span>
          <span class="agenda-legend-item"><span class="agenda-dot agenda-dot-unknown"></span> Sem dados</span>
        </div>
      </div>

      <div class="card agenda-detail-card">
        <div class="card-header">
          <h3>{{ selectedDate ? formatSelectedDate(selectedDate) : 'Selecione uma data' }}</h3>
          <div v-if="selectedDate && selectedDateRisk" class="flex-gap">
            <span v-if="selectedDateRisk.high" class="badge badge-danger">{{ selectedDateRisk.high }} em risco alto</span>
            <span v-if="selectedDateRisk.medium" class="badge badge-warning">{{ selectedDateRisk.medium }} em atenção</span>
          </div>
        </div>

        <LoadingState v-if="loading" message="Carregando agenda..." />
        <ErrorMessage v-else-if="error" :message="error" @retry="load" />

        <template v-else-if="!selectedDate">
          <div class="agenda-empty-hint">
            <span class="empty-icon">📅</span>
            <p>Clique em uma data para ver os eventos e a previsão do tempo</p>
          </div>
        </template>

        <template v-else-if="dayEvents.length === 0">
          <div class="agenda-empty-hint">
            <span class="empty-icon">✅</span>
            <p>Nenhum evento nesta data</p>
          </div>
        </template>

        <div v-else class="agenda-event-list">
          <div v-for="event in dayEvents" :key="event.id" class="agenda-event-item" :class="{ 'agenda-event-risk-high': getEventRisk(event.id)?.status === 'HIGH_RISK' }" @click="goDetail(event)">
            <div class="agenda-event-risk-indicator">
              <span :class="['agenda-risk-badge-dot', riskDotClass(getEventRisk(event.id))]"></span>
            </div>
            <div class="agenda-event-body">
              <strong>{{ event.name }}</strong>
              <span class="agenda-event-meta">
                {{ event.city }} • {{ event.event_time ? event.event_time.slice(0, 5) : '—' }}
                <span :class="['badge', event.type === 'outdoor' ? 'badge-info' : 'badge-neutral', 'badge-xs']">{{ event.type === 'outdoor' ? '🌤' : '🏛' }}</span>
              </span>
              <div class="agenda-event-tags">
                <span :class="['badge', 'badge-xs', statusBadgeClass(event.status)]">{{ statusLabel(event.status) }}</span>
                <span v-if="getEventRisk(event.id)" :style="{ color: riskColor(getEventRisk(event.id)?.status), fontWeight:600, fontSize:'11px' }">
                  {{ riskLabel(getEventRisk(event.id)?.status) }}
                </span>
                <span v-if="getEventRisk(event.id)?.temperature !== undefined" class="text-xs text-muted">
                  🌡 {{ getEventRisk(event.id).temperature }}°C
                </span>
                <span v-if="getEventRisk(event.id)?.rain_probability !== undefined && getEventRisk(event.id).rain_probability > 0" class="text-xs text-muted">
                  🌧 {{ getEventRisk(event.id).rain_probability }}%
                </span>
                <span v-if="event.budget" class="text-xs text-muted">💰 {{ formatBRL(event.budget) }}</span>
              </div>
            </div>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="agenda-event-chevron"><polyline points="9 18 15 12 9 6"/></svg>
          </div>

          <div v-if="dayRiskAlerts.length" class="agenda-day-risk-summary">
            <div v-for="alert in dayRiskAlerts" :key="alert.event_id" :class="['agenda-alert-line', `agenda-alert-${alert.severity}`]">
              <span>{{ alertIcon(alert.type) }}</span>
              <span>{{ alert.message }}</span>
            </div>
          </div>

          <div v-if="selectedDateWeather" class="agenda-day-weather">
            <div class="agenda-day-weather-header">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>
              Clima no período
            </div>
            <div class="agenda-day-weather-grid">
              <div class="agenda-weather-item">
                <span class="agenda-w-label">Temp</span>
                <span class="agenda-w-value">{{ selectedDateWeather.avgTemp }}°C</span>
              </div>
              <div class="agenda-weather-item">
                <span class="agenda-w-label">Máx</span>
                <span class="agenda-w-value">{{ selectedDateWeather.maxTemp }}°C</span>
              </div>
              <div class="agenda-weather-item">
                <span class="agenda-w-label">Chuva</span>
                <span class="agenda-w-value">{{ selectedDateWeather.avgRain }}%</span>
              </div>
              <div class="agenda-weather-item">
                <span class="agenda-w-label">Vento</span>
                <span class="agenda-w-value">{{ selectedDateWeather.avgWind }} km/h</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { eventsApi, weatherApi } from '../services/api.js'
import { useAuth } from '../composables/useAuth.js'
import LoadingState from '../components/LoadingState.vue'
import ErrorMessage from '../components/ErrorMessage.vue'

const router = useRouter()
const { can } = useAuth()

const events = ref([])
const riskAlerts = ref([])
const loading = ref(false)
const error = ref(null)
const currentMonth = ref(new Date())
const selectedDate = ref(null)
const riskFilter = ref('all')

const cityForecastCache = ref({})

const dayHeaders = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']

const monthLabel = computed(() => {
  return currentMonth.value.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })
})

const riskData = computed(() => {
  const map = {}
  for (const a of riskAlerts.value) {
    const eid = a.event_id
    if (!eid) continue
    const r = a.risk
    map[eid] = {
      status: r?.status || 'unknown',
      score: r?.score || 0,
      temperature: a.event_forecast?.temperature ?? a.weather?.temperature,
      weather_main: a.event_forecast?.weather_main || a.weather?.weather_main || '',
      icon: a.event_forecast?.icon || a.weather?.icon || '',
      rain_probability: a.event_forecast?.rain_probability,
      wind_speed: a.event_forecast?.wind_speed,
      alerts: r?.alerts || [],
    }
  }
  return map
})

function getEventRisk(eventId) {
  return riskData.value[eventId] || null
}

const riskByDate = computed(() => {
  const dateMap = {}
  for (const a of riskAlerts.value) {
    const d = a.event_date
    if (!d) continue
    const r = a.risk
    const alertsList = r?.alerts || []
    if (!dateMap[d]) dateMap[d] = { high: 0, medium: 0, low: 0, unknown: 0, alerts: [] }
    if (r?.status === 'HIGH_RISK') { dateMap[d].high++ }
    else if (r?.status === 'MEDIUM_RISK') { dateMap[d].medium++ }
    else if (r?.status === 'LOW_RISK') { dateMap[d].low++ }
    else { dateMap[d].unknown++ }
    for (const al of alertsList) {
      dateMap[d].alerts.push({ ...al, event_id: a.event_id })
    }
  }
  return dateMap
})

const monthRiskCounts = computed(() => {
  const counts = { high: 0, medium: 0 }
  const month = currentMonth.value.getMonth()
  const year = currentMonth.value.getFullYear()
  for (const a of riskAlerts.value) {
    if (!a.event_date) continue
    const d = new Date(a.event_date + 'T12:00:00')
    if (d.getMonth() === month && d.getFullYear() === year) {
      if (a.risk?.status === 'HIGH_RISK') counts.high++
      else if (a.risk?.status === 'MEDIUM_RISK') counts.medium++
    }
  }
  return counts
})

const calendarDays = computed(() => {
  const year = currentMonth.value.getFullYear()
  const month = currentMonth.value.getMonth()
  const firstDay = new Date(year, month, 1)
  const lastDay = new Date(year, month + 1, 0)
  const startPad = firstDay.getDay()
  const daysInMonth = lastDay.getDate()
  const daysInPrev = new Date(year, month, 0).getDate()

  const todayStr = new Date().toISOString().slice(0, 10)
  const days = []

  for (let i = startPad - 1; i >= 0; i--) {
    const d = daysInPrev - i
    const date = `${year}-${String(month).padStart(2, '0')}-${String(d).padStart(2, '0')}`
    days.push(createDay(date, year, month - 1, d, true, todayStr))
  }

  for (let d = 1; d <= daysInMonth; d++) {
    const date = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`
    days.push(createDay(date, year, month, d, false, todayStr))
  }

  const remaining = 42 - days.length
  for (let d = 1; d <= remaining; d++) {
    const date = `${year}-${String(month + 2).padStart(2, '0')}-${String(d).padStart(2, '0')}`
    days.push(createDay(date, year, month + 1, d, true, todayStr))
  }

  return days
})

function createDay(date, year, month, num, otherMonth, todayStr) {
  const dayEvents = eventsByDate.value[date] || []
  let worstRisk = null
  const riskOrder = ['HIGH_RISK', 'MEDIUM_RISK', 'LOW_RISK', 'unknown']
  for (const e of dayEvents) {
    const r = getEventRisk(e.id)
    if (r && riskOrder.indexOf(r.status) < (riskOrder.indexOf(worstRisk) ?? 99)) {
      worstRisk = r.status
    }
  }
  return {
    num,
    date,
    otherMonth,
    today: date === todayStr,
    events: dayEvents,
    worstRisk: worstRisk || (dayEvents.length > 0 ? 'unknown' : null),
  }
}

const eventsByDate = computed(() => {
  const map = {}
  for (const event of filteredEvents.value) {
    const date = event.event_date
    if (!date) continue
    if (!map[date]) map[date] = []
    map[date].push(event)
  }
  return map
})

const filteredEvents = computed(() => {
  if (riskFilter.value === 'all') return events.value
  return events.value.filter(e => {
    const r = getEventRisk(e.id)
    return r && r.status === riskFilter.value
  })
})

const dayEvents = computed(() => {
  if (!selectedDate.value) return []
  return eventsByDate.value[selectedDate.value] || []
})

const selectedDateRisk = computed(() => {
  if (!selectedDate.value) return null
  return riskByDate.value[selectedDate.value] || null
})

const dayRiskAlerts = computed(() => {
  if (!selectedDate.value) return []
  const riskDate = riskByDate.value[selectedDate.value]
  if (!riskDate) return []
  return riskDate.alerts.slice(0, 5)
})

const selectedDateWeather = computed(() => {
  if (!selectedDate.value || dayEvents.value.length === 0) return null
  const eventsOnDay = dayEvents.value
  let totalTemp = 0, totalRain = 0, totalWind = 0, maxTemp = -Infinity, count = 0
  for (const e of eventsOnDay) {
    const r = getEventRisk(e.id)
    if (!r) continue
    if (r.temperature !== undefined) {
      totalTemp += r.temperature
      maxTemp = Math.max(maxTemp, r.temperature)
      count++
    }
    if (r.rain_probability !== undefined) totalRain += r.rain_probability
    if (r.wind_speed !== undefined) totalWind += r.wind_speed
  }
  if (count === 0) return null
  return {
    avgTemp: Math.round(totalTemp / count * 10) / 10,
    maxTemp: Math.round(maxTemp * 10) / 10,
    avgRain: Math.round(totalRain / count),
    avgWind: Math.round(totalWind / count * 10) / 10,
  }
})

function selectDate(date) {
  selectedDate.value = date
}

function formatSelectedDate(date) {
  return new Date(date + 'T12:00:00').toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long' })
}

function prevMonth() {
  currentMonth.value = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth() - 1, 1)
}

function nextMonth() {
  currentMonth.value = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth() + 1, 1)
}

function today() {
  currentMonth.value = new Date()
  selectedDate.value = new Date().toISOString().slice(0, 10)
}

function riskColor(status) {
  if (status === 'HIGH_RISK') return '#EF4444'
  if (status === 'MEDIUM_RISK') return '#F59E0B'
  if (status === 'LOW_RISK') return '#22C55E'
  return '#94a3b8'
}

function riskLabel(status) {
  if (status === 'HIGH_RISK') return 'Risco Alto'
  if (status === 'MEDIUM_RISK') return 'Risco Médio'
  if (status === 'LOW_RISK') return 'Risco Baixo'
  return ''
}

function riskDotClass(risk) {
  if (!risk || risk.status === 'unknown') return 'agenda-dot-unknown'
  if (risk.status === 'HIGH_RISK') return 'agenda-dot-high'
  if (risk.status === 'MEDIUM_RISK') return 'agenda-dot-medium'
  return 'agenda-dot-low'
}

function statusLabel(status) {
  return { planned: 'Planejado', confirmed: 'Confirmado', in_progress: 'Andamento', completed: 'Realizado', cancelled: 'Cancelado' }[status] || status || '—'
}

function statusBadgeClass(status) {
  return { planned: 'badge-neutral', confirmed: 'badge-success', in_progress: 'badge-info', completed: 'badge-neutral', cancelled: 'badge-danger' }[status] || 'badge-neutral'
}

function alertIcon(type) {
  return { heat: '🌡', cold: '🧊', wind: '💨', rain: '🌧', air: '🌫', humidity: '💧' }[type] ?? '⚠️'
}

function formatBRL(n) {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n || 0)
}

function goDetail(event) {
  router.push({ name: 'events.detail', params: { id: event.id } })
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const [evRes, alertRes] = await Promise.all([
      eventsApi.list(),
      eventsApi.riskAlerts().catch(() => ({ data: [] })),
    ])
    events.value = evRes.data ?? []
    riskAlerts.value = alertRes.data ?? []
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  load()
  selectedDate.value = new Date().toISOString().slice(0, 10)
})
</script>

<style scoped>
.agenda-layout {
  display: grid;
  grid-template-columns: 1fr 380px;
  gap: 16px;
  align-items: start;
}

.agenda-nav {
  display: flex;
  align-items: center;
  gap: 2px;
}

.agenda-month-label {
  font-size: 15px;
  font-weight: 600;
  text-transform: capitalize;
  margin: 0 4px;
  min-width: 150px;
  text-align: center;
}

.agenda-month-risk {
  display: flex;
  gap: 4px;
  margin-left: auto;
}

.agenda-risk-badge {
  font-size: 10px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 9999px;
  color: #fff;
}

.agenda-risk-high { background: #EF4444; }
.agenda-risk-medium { background: #F59E0B; }

.agenda-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 1px;
  background: var(--color-border);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  overflow: hidden;
}

.agenda-day-header {
  background: var(--color-surface);
  text-align: center;
  font-size: 11px;
  font-weight: 600;
  color: var(--color-text-secondary);
  padding: 8px 0;
  text-transform: uppercase;
}

.agenda-day {
  background: #fff;
  min-height: 72px;
  padding: 4px;
  cursor: pointer;
  transition: background 100ms;
  display: flex;
  flex-direction: column;
  gap: 2px;
  position: relative;
}

.agenda-day:hover {
  background: #f8fafc;
}

.agenda-day-other {
  background: #fafafa;
}

.agenda-day-other .agenda-day-num {
  color: #cbd5e1;
}

.agenda-day-today .agenda-day-num {
  background: var(--color-primary);
  color: #fff;
  border-radius: 50%;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.agenda-day-selected {
  background: #eff6ff !important;
  outline: 2px solid var(--color-primary);
  outline-offset: -2px;
}

.agenda-day-num {
  font-size: 12px;
  font-weight: 500;
  color: var(--color-text);
  line-height: 1;
}

.agenda-risk-line {
  height: 3px;
  border-radius: 2px;
  margin-bottom: 2px;
}

.agenda-risk-high { background: #EF4444; }
.agenda-risk-medium { background: #F59E0B; }
.agenda-risk-low { background: #22C55E; }

.agenda-day-dots {
  display: flex;
  flex-wrap: wrap;
  gap: 2px;
  margin-top: auto;
}

.agenda-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.agenda-dot-high { background: #EF4444; }
.agenda-dot-medium { background: #F59E0B; }
.agenda-dot-low { background: #22C55E; }
.agenda-dot-unknown { background: #cbd5e1; }

.agenda-dot-more {
  font-size: 9px;
  color: var(--color-text-secondary);
  line-height: 1;
}

.agenda-legend {
  display: flex;
  gap: 12px;
  padding: 8px 4px 0;
  flex-wrap: wrap;
}

.agenda-legend-item {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 10px;
  color: var(--color-text-secondary);
}

.agenda-detail-card {
  position: sticky;
  top: 16px;
}

.agenda-empty-hint {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 48px 24px;
  text-align: center;
  color: var(--color-text-secondary);
  font-size: 14px;
}

.agenda-event-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.agenda-event-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
  cursor: pointer;
  transition: background 100ms, border-color 100ms, box-shadow 100ms;
}

.agenda-event-item:hover {
  background: #f8fafc;
  border-color: var(--color-primary);
}

.agenda-event-risk-high {
  border-left: 3px solid #EF4444;
  background: #fef2f2;
}

.agenda-event-risk-high:hover {
  background: #fee2e2;
  border-color: #EF4444;
}

.agenda-event-risk-indicator {
  flex-shrink: 0;
  width: 12px;
  display: flex;
  justify-content: center;
}

.agenda-risk-badge-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
}

.agenda-event-body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.agenda-event-body strong {
  font-size: 13px;
}

.agenda-event-meta {
  font-size: 11px;
  color: var(--color-text-secondary);
}

.agenda-event-tags {
  display: flex;
  gap: 6px;
  margin-top: 2px;
  flex-wrap: wrap;
  align-items: center;
}

.agenda-event-chevron {
  flex-shrink: 0;
  color: var(--color-text-secondary);
}

.agenda-day-risk-summary {
  margin-top: 12px;
  padding: 10px 12px;
  background: #fefce8;
  border: 1px solid #fde68a;
  border-radius: var(--radius-md);
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.agenda-alert-line {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #92400e;
  line-height: 1.3;
}

.agenda-alert-danger { color: #991b1b; }
.agenda-alert-warning { color: #92400e; }

.agenda-day-weather {
  margin-top: 12px;
  padding: 12px 14px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
}

.agenda-day-weather-header {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-secondary);
  margin-bottom: 10px;
}

.agenda-day-weather-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
}

.agenda-weather-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
}

.agenda-w-label {
  font-size: 10px;
  color: var(--color-text-secondary);
  text-transform: uppercase;
}

.agenda-w-value {
  font-size: 14px;
  font-weight: 700;
  color: var(--color-text);
}

@media (max-width: 900px) {
  .agenda-layout {
    grid-template-columns: 1fr;
  }

  .agenda-detail-card {
    position: static;
  }

  .agenda-month-risk {
    display: none;
  }

  .agenda-day-weather-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
