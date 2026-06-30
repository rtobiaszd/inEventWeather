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

    <div v-else-if="error" class="text-sm" style="color:var(--color-danger)">
      {{ error }}
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

const weather = ref(null)
const loading = ref(false)
const error   = ref(null)

onMounted(async () => {
  loading.value = true
  try {
    const res = await weatherApi.search(props.city.city, props.city.country)
    weather.value = res.data
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
})

function formatDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' })
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
</style>
