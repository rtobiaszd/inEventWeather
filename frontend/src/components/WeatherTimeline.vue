<template>
  <div v-if="filtered.length" class="wt-container">
    <div class="wt-strip">
      <button
        v-for="(entry, i) in filtered"
        :key="entry.timestamp ?? i"
        :class="['wt-block', { 'wt-block-active': i === activeIndex }]"
        :title="entry.weather_description"
      >
        <span class="wt-time">{{ formatTime(entry.datetime) }}</span>
        <img v-if="entry.icon"
          :src="`https://openweathermap.org/img/wn/${entry.icon}.png`"
          width="28" height="28" :alt="entry.weather_main" />
        <span class="wt-temp">{{ Math.round(entry.temperature) }}°</span>
        <div class="wt-rain-bar-track">
          <div class="wt-rain-bar-fill" :style="{ width: entry.rain_probability + '%', background: rainColor(entry.rain_probability) }" />
        </div>
        <span class="wt-rain-pct">{{ entry.rain_probability }}%</span>
        <div v-if="i === activeIndex" class="wt-indicator">▼</div>
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  forecast:   { type: Array, default: () => [] },
  eventDate:  { type: String, default: '' },
  eventTime:  { type: String, default: '' },
})

const filtered = computed(() => {
  if (!props.eventDate || !props.forecast.length) return []
  return props.forecast.filter(e => {
    const datePart = e.datetime?.slice(0, 10)
    return datePart === props.eventDate
  })
})

const activeIndex = computed(() => {
  if (!filtered.value.length) return -1
  const targetStr = props.eventTime || '12:00'
  const target = targetStr.slice(0, 5)
  let closest = 0
  let minDiff = Infinity
  for (let i = 0; i < filtered.value.length; i++) {
    const time = (filtered.value[i].datetime ?? '').slice(11, 16)
    const diff = Math.abs(timeToMinutes(time) - timeToMinutes(target))
    if (diff < minDiff) {
      minDiff = diff
      closest = i
    }
  }
  return closest
})

function timeToMinutes(t) {
  if (!t || !t.includes(':')) return 0
  const [h, m] = t.split(':').map(Number)
  return h * 60 + m
}

function formatTime(dt) {
  if (!dt) return ''
  return dt.slice(11, 16)
}

function rainColor(pct) {
  if (pct < 20) return 'var(--color-success)'
  if (pct < 50) return 'var(--color-warning)'
  if (pct < 80) return '#F97316'
  return 'var(--color-danger)'
}
</script>

<style scoped>
.wt-container {
  margin-top: 10px;
  overflow-x: auto;
  padding-bottom: 4px;
}

.wt-strip {
  display: flex;
  gap: 6px;
}

.wt-block {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 3px;
  min-width: 62px;
  padding: 8px 6px 6px;
  border: 1.5px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  cursor: default;
  transition: border-color 150ms, box-shadow 150ms;
  text-align: center;
  font-family: var(--font);
  position: relative;
}

.wt-block:hover {
  border-color: var(--color-primary);
  box-shadow: 0 0 0 2px var(--color-primary-light);
}

.wt-block-active {
  border-color: var(--color-primary);
  background: var(--color-primary-light);
  box-shadow: 0 0 0 2px var(--color-primary-light);
}

.wt-time {
  font-size: 10px;
  font-weight: 600;
  color: var(--color-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.wt-temp {
  font-size: 14px;
  font-weight: 700;
  color: var(--color-text);
  line-height: 1.1;
}

.wt-rain-bar-track {
  width: 100%;
  height: 4px;
  background: var(--color-border);
  border-radius: 2px;
  overflow: hidden;
}

.wt-rain-bar-fill {
  height: 100%;
  border-radius: 2px;
  transition: width 300ms;
}

.wt-rain-pct {
  font-size: 9px;
  font-weight: 600;
  color: var(--color-text-secondary);
}

.wt-indicator {
  position: absolute;
  bottom: -14px;
  font-size: 10px;
  color: var(--color-primary);
  line-height: 1;
}
</style>
