<template>
  <div class="forecast-item">
    <span class="fi-time">{{ time }}</span>
    <img
      :src="`https://openweathermap.org/img/wn/${item.icon}@2x.png`"
      :alt="item.weather_description"
      class="fi-icon-img"
    />
    <span class="fi-temp">{{ item.temperature }}°C</span>
    <span class="fi-desc">{{ item.weather_description }}</span>
    <span v-if="item.rain_probability > 0" class="fi-rain">💧 {{ item.rain_probability }}%</span>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  item: { type: Object, required: true },
})

const time = computed(() => {
  const d = new Date(props.item.datetime)
  return d.toLocaleString('pt-BR', { weekday: 'short', hour: '2-digit', minute: '2-digit' })
})
</script>

<style scoped>
.fi-icon-img { width: 48px; height: 48px; }
</style>
