<template>
  <div class="fav-card">
    <div class="fav-card-header">
      <div>
        <div class="fav-card-city">{{ city.city }}</div>
        <div class="fav-card-country">{{ city.country }} • Adicionado {{ formatDate(city.created_at) }}</div>
      </div>
      <button class="btn btn-danger btn-icon btn-sm" :title="`Remover ${city.city}`" @click="$emit('remove', city)">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <div v-if="loading" class="fav-weather-loading">
      <div class="spinner" style="width:20px;height:20px;border-width:2px;" />
      <span class="text-sm text-muted">Carregando clima...</span>
    </div>

    <div v-else-if="weather" class="fav-weather">
      <img
        :src="`https://openweathermap.org/img/wn/${weather.current.icon}@2x.png`"
        :alt="weather.current.weather_description"
        class="fav-weather-icon"
      />
      <div>
        <div class="fav-weather-temp">{{ weather.current.temperature }}°C</div>
        <div class="fav-weather-desc">{{ weather.current.weather_description }}</div>
      </div>
      <RiskBadge :status="weather.risk.status" />
    </div>

    <div v-else-if="weatherError" class="text-sm" style="color:var(--color-danger)">
      {{ weatherError }}
    </div>

    <!-- Eventos da cidade -->
    <div class="fav-events">
      <div class="fav-events-title">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Próximos eventos
      </div>
      <div v-if="!city.events || city.events.length === 0" class="fav-events-empty">
        Sem eventos cadastrados nesta cidade
      </div>
      <div v-else class="fav-events-list">
        <div v-for="ev in city.events.slice(0, 3)" :key="ev.id" class="fav-event-row">
          <span class="fav-event-name">{{ ev.name }}</span>
          <span class="fav-event-meta">
            {{ formatEventDate(ev.event_date) }}
            <span :class="['badge', 'badge-xs', ev.type === 'outdoor' ? 'badge-info' : 'badge-neutral']">
              {{ ev.type === 'outdoor' ? '🌤' : '🏛' }}
            </span>
          </span>
        </div>
        <div v-if="city.events.length > 3" class="fav-events-more">
          +{{ city.events.length - 3 }} evento(s)
        </div>
      </div>
    </div>

    <RouterLink :to="`/weather?city=${encodeURIComponent(city.city)}&country=${city.country}`" class="btn btn-secondary btn-sm">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>
      Ver clima
    </RouterLink>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { weatherApi } from '../services/api.js'
import RiskBadge from './RiskBadge.vue'

const props = defineProps({
  city: { type: Object, required: true },
})

defineEmits(['remove'])

const weather      = ref(null)
const loading      = ref(false)
const weatherError = ref(null)

onMounted(async () => {
  loading.value = true
  try {
    const res = await weatherApi.search(props.city.city, props.city.country)
    weather.value = res.data
  } catch (e) {
    weatherError.value = e.message
  } finally {
    loading.value = false
  }
})

function formatDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' })
}

function formatEventDate(d) {
  if (!d) return ''
  const [y, m, day] = d.split('-')
  return `${day}/${m}/${y}`
}
</script>

<style scoped>
.fav-weather {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 0;
}

.fav-weather-icon { width: 44px; height: 44px; }
.fav-weather-temp { font-size: 22px; font-weight: 700; }
.fav-weather-desc { font-size: 12px; color: var(--color-text-secondary); text-transform: capitalize; }
.fav-weather-loading { display: flex; align-items: center; gap: 8px; padding: 8px 0; }

.fav-events {
  border-top: 1px solid var(--color-border);
  padding-top: 10px;
  margin-top: 4px;
  margin-bottom: 10px;
}

.fav-events-title {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-text-secondary);
  margin-bottom: 6px;
}

.fav-events-empty {
  font-size: 12px;
  color: var(--color-text-secondary);
  font-style: italic;
}

.fav-events-list { display: flex; flex-direction: column; gap: 4px; }

.fav-event-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
  gap: 8px;
}

.fav-event-name {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  flex: 1;
  min-width: 0;
}

.fav-event-meta {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
  color: var(--color-text-secondary);
}

.fav-events-more {
  font-size: 11px;
  color: var(--color-text-secondary);
  margin-top: 2px;
}

.badge-xs {
  font-size: 10px;
  padding: 1px 5px;
  border-radius: 4px;
}
</style>
