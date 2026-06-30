<template>
  <div v-if="visible" class="smart-date-picker">
    <div class="sdp-header">
      <div class="sdp-title-row">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <span>Datas recomendadas para <strong>{{ city }}</strong></span>
      </div>
      <button
        v-if="bestDate && modelValue !== bestDate.date"
        class="btn btn-sm btn-primary"
        @click="selectDate(bestDate.date)"
      >
        🏆 Usar melhor data
      </button>
    </div>

    <div v-if="loading" class="sdp-loading">
      <span class="spinner" style="width:16px;height:16px;border-width:2px;" />
      <span class="text-sm text-muted">Analisando clima...</span>
    </div>

    <ErrorMessage v-else-if="error" :message="error" :retry="false" />

    <div v-else-if="dates.length" class="sdp-strip">
      <button
        v-for="d in dates"
        :key="d.date"
        :class="['sdp-day', {
          'sdp-day-selected': modelValue === d.date,
          'sdp-day-best': d.date === bestDate?.date,
          'sdp-status-ideal': d.status === 'IDEAL',
          'sdp-status-favorable': d.status === 'FAVORABLE',
          'sdp-status-caution': d.status === 'CAUTION',
          'sdp-status-avoid': d.status === 'AVOID',
        }]"
        @click="selectDate(d.date)"
        :title="`${formatDateFull(d.date)} — ${statusLabel(d.status)}`"
      >
        <span class="sdp-day-weekday">{{ formatWeekday(d.date) }}</span>
        <span class="sdp-day-date">{{ formatDay(d.date) }}</span>
        <div class="sdp-day-score-ring">
          <svg viewBox="0 0 24 24" width="24" height="24">
            <circle cx="12" cy="12" r="10" fill="none" stroke="#E2E8F0" stroke-width="2.5"/>
            <circle
              cx="12" cy="12" r="10"
              fill="none"
              :stroke="scoreColor(d.score)"
              stroke-width="2.5"
              :stroke-dasharray="`${(100 - d.score) / 100 * 62.83}, 62.83`"
              stroke-linecap="round"
              transform="rotate(-90 12 12)"
            />
            <text x="12" y="15" text-anchor="middle" font-size="8" font-weight="700" fill="#1E293B">
              {{ 100 - d.score }}
            </text>
          </svg>
        </div>
        <span class="sdp-day-temp">{{ Math.round(d.avg_temperature) }}°</span>
        <span class="sdp-day-rain">💧{{ d.max_rain }}%</span>
        <span v-if="d.date === bestDate?.date" class="sdp-day-best-badge">🏆</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { weatherApi } from '../services/api.js'
import ErrorMessage from './ErrorMessage.vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  city:       { type: String, default: '' },
  country:    { type: String, default: 'BR' },
  type:       { type: String, default: 'outdoor' },
})

const emit = defineEmits(['update:modelValue'])

const dates = ref([])
const loading = ref(false)
const error = ref(null)

const visible = computed(() => {
  return props.type === 'outdoor' && props.city?.trim().length >= 2
})

const bestDate = computed(() => {
  return dates.value.length ? dates.value.reduce((a, b) => a.score < b.score ? a : b) : null
})

const statusLabel = (s) => ({ IDEAL: 'Ideal', FAVORABLE: 'Favorável', CAUTION: 'Cautela', AVOID: 'Evitar' }[s] || s)
const scoreColor = (s) => s <= 20 ? '#22C55E' : s <= 50 ? '#3B82F6' : s <= 70 ? '#F59E0B' : '#EF4444'

function formatWeekday(date) {
  if (!date) return ''
  return new Date(date + 'T12:00:00').toLocaleDateString('pt-BR', { weekday: 'short' }).slice(0, 3)
}

function formatDay(date) {
  if (!date) return ''
  const [y, m, d] = date.split('-')
  return `${d}/${m}`
}

function formatDateFull(date) {
  if (!date) return ''
  return new Date(date + 'T12:00:00').toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long' })
}

function selectDate(date) {
  emit('update:modelValue', date)
}

let debounceTimer = null

async function fetchDates() {
  if (!props.city?.trim() || props.type !== 'outdoor') {
    dates.value = []
    error.value = null
    return
  }

  loading.value = true
  error.value = null
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(async () => {
    try {
      const res = await weatherApi.bestDates(props.city.trim(), props.country)
      dates.value = res.data?.dates?.slice(0, 7) ?? []
    } catch (e) {
      dates.value = []
      error.value = e.message
    } finally {
      loading.value = false
    }
  }, 500)
}

watch(() => [props.city, props.country, props.type], fetchDates, { immediate: false })
</script>

<style scoped>
.smart-date-picker {
  margin-top: 12px;
  padding: 14px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-bg);
}

.sdp-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 10px;
  flex-wrap: wrap;
}

.sdp-title-row {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--color-text-secondary);
}

.sdp-title-row svg {
  flex-shrink: 0;
  color: var(--color-primary);
}

.sdp-loading {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 0;
}

.sdp-strip {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  padding-bottom: 4px;
}

.sdp-day {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  min-width: 72px;
  padding: 8px 6px;
  border: 1.5px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  cursor: pointer;
  transition: all 150ms;
  text-align: center;
  font-family: var(--font);
}

.sdp-day:hover {
  border-color: var(--color-primary);
  box-shadow: 0 0 0 2px var(--color-primary-light);
  transform: translateY(-1px);
}

.sdp-day-selected {
  border-color: var(--color-primary);
  background: var(--color-primary-light);
  box-shadow: 0 0 0 2px var(--color-primary-light);
}

.sdp-day-best {
  border-color: #059669;
  background: #F0FDF4;
}

.sdp-day-weekday {
  font-size: 10px;
  font-weight: 600;
  color: var(--color-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.sdp-day-date {
  font-size: 13px;
  font-weight: 700;
  color: var(--color-text);
  line-height: 1.2;
}

.sdp-day-score-ring {
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 2px 0;
}

.sdp-day-temp {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text);
}

.sdp-day-rain {
  font-size: 10px;
  color: var(--color-primary);
  font-weight: 500;
}

.sdp-day-best-badge {
  position: absolute;
  top: -6px;
  right: -6px;
  font-size: 14px;
  line-height: 1;
  filter: drop-shadow(0 1px 2px rgba(0,0,0,0.2));
}

.sdp-status-ideal { border-color: #BBF7D0; }
.sdp-status-favorable { border-color: #BFDBFE; }
.sdp-status-caution { border-color: #FDE68A; }
.sdp-status-avoid { border-color: #FECACA; }

.sdp-error {
  padding: 4px 0;
}
</style>
