<template>
  <div class="stack">
    <div class="page-header">
      <div>
        <h2>Agenda</h2>
        <p>{{ events.length }} evento(s)</p>
      </div>
      <RouterLink v-if="can('events', 'create')" to="/events/create" class="btn btn-primary">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Novo Evento
      </RouterLink>
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
              <span
                v-for="e in day.events.slice(0, 3)"
                :key="e.id"
                :title="e.name"
                :class="['agenda-dot', statusDotClass(e.status)]"
              />
              <span v-if="day.events.length > 3" class="agenda-dot-more">+{{ day.events.length - 3 }}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="card agenda-detail-card">
        <div class="card-header">
          <h3>{{ selectedDate ? formatSelectedDate(selectedDate) : 'Selecione uma data' }}</h3>
        </div>

        <div v-if="!selectedDate" class="agenda-empty-hint">
          <span class="empty-icon">📅</span>
          <p>Clique em uma data para ver os eventos</p>
        </div>

        <div v-else-if="dayEvents.length === 0" class="agenda-empty-hint">
          <span class="empty-icon">✅</span>
          <p>Nenhum evento nesta data</p>
        </div>

        <div v-else class="agenda-event-list">
          <div v-for="event in dayEvents" :key="event.id" class="agenda-event-item" @click="goEdit(event)">
            <div class="agenda-event-time">
              {{ event.event_time ? event.event_time.slice(0, 5) : '—' }}
            </div>
            <div class="agenda-event-body">
              <strong>{{ event.name }}</strong>
              <span class="agenda-event-meta">{{ event.city }} • <span :class="['badge', event.type === 'outdoor' ? 'badge-info' : 'badge-neutral', 'badge-xs']">{{ event.type }}</span></span>
              <div class="agenda-event-tags">
                <span :class="['badge', 'badge-xs', statusBadgeClass(event.status)]">{{ statusLabel(event.status) }}</span>
                <span v-if="event.budget" class="text-xs text-muted">💰 {{ formatBRL(event.budget) }}</span>
                <span v-if="event.expected_audience" class="text-xs text-muted">👥 {{ event.expected_audience }}</span>
              </div>
            </div>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="agenda-event-chevron"><polyline points="9 18 15 12 9 6"/></svg>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { eventsApi } from '../services/api.js'
import { useAuth } from '../composables/useAuth.js'

const router = useRouter()
const { can } = useAuth()

const events = ref([])
const loading = ref(false)
const error = ref(null)
const currentMonth = ref(new Date())
const selectedDate = ref(null)

const dayHeaders = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']

const monthLabel = computed(() => {
  return currentMonth.value.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })
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
    const dateObj = new Date(year, month - 1, d)
    days.push({ num: d, date, otherMonth: true, today: date === todayStr, events: eventsByDate.value[date] || [] })
  }

  for (let d = 1; d <= daysInMonth; d++) {
    const date = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`
    days.push({ num: d, date, otherMonth: false, today: date === todayStr, events: eventsByDate.value[date] || [] })
  }

  const remaining = 42 - days.length
  for (let d = 1; d <= remaining; d++) {
    const date = `${year}-${String(month + 2).padStart(2, '0')}-${String(d).padStart(2, '0')}`
    days.push({ num: d, date, otherMonth: true, today: false, events: eventsByDate.value[date] || [] })
  }

  return days
})

const eventsByDate = computed(() => {
  const map = {}
  for (const event of events.value) {
    const date = event.event_date
    if (!date) continue
    if (!map[date]) map[date] = []
    map[date].push(event)
  }
  return map
})

const dayEvents = computed(() => {
  if (!selectedDate.value) return []
  return eventsByDate.value[selectedDate.value] || []
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

function statusLabel(status) {
  return { planned: 'Planejado', confirmed: 'Confirmado', in_progress: 'Andamento', completed: 'Realizado', cancelled: 'Cancelado' }[status] || status || '—'
}

function statusDotClass(status) {
  return { planned: 'dot-neutral', confirmed: 'dot-success', in_progress: 'dot-info', completed: 'dot-neutral', cancelled: 'dot-danger' }[status] || 'dot-neutral'
}

function statusBadgeClass(status) {
  return { planned: 'badge-neutral', confirmed: 'badge-success', in_progress: 'badge-info', completed: 'badge-neutral', cancelled: 'badge-danger' }[status] || 'badge-neutral'
}

function formatBRL(n) {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n || 0)
}

function goEdit(event) {
  router.push({ name: 'events.edit', params: { id: event.id } })
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const res = await eventsApi.list()
    events.value = res.data ?? []
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
  grid-template-columns: 1fr 360px;
  gap: 16px;
  align-items: start;
}

.agenda-nav {
  display: flex;
  align-items: center;
  margin-bottom: 16px;
}

.agenda-month-label {
  font-size: 15px;
  font-weight: 600;
  text-transform: capitalize;
  margin: 0 8px;
  min-width: 160px;
  text-align: center;
}

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

.dot-neutral { background: #94a3b8; }
.dot-success { background: #22c55e; }
.dot-info    { background: #3b82f6; }
.dot-danger  { background: #ef4444; }

.agenda-dot-more {
  font-size: 9px;
  color: var(--color-text-secondary);
  line-height: 1;
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
  gap: 12px;
  padding: 10px 12px;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
  cursor: pointer;
  transition: background 100ms, border-color 100ms;
}

.agenda-event-item:hover {
  background: #f8fafc;
  border-color: var(--color-primary);
}

.agenda-event-time {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-secondary);
  white-space: nowrap;
  min-width: 40px;
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
}

.agenda-event-chevron {
  flex-shrink: 0;
  color: var(--color-text-secondary);
}

@media (max-width: 900px) {
  .agenda-layout {
    grid-template-columns: 1fr;
  }

  .agenda-detail-card {
    position: static;
  }
}
</style>
