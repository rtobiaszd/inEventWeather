<template>
  <div class="card weather-main-card">
    <div class="card-header">
      <h3>{{ data.city }}, {{ data.country }}</h3>
      <span class="badge badge-info" style="background:rgba(255,255,255,.15);color:#fff;">Agora</span>
    </div>

    <div class="wc-body">
      <div class="wc-temp-section">
        <img
          v-if="data.current?.icon"
          :src="`https://openweathermap.org/img/wn/${data.current.icon}@2x.png`"
          :alt="data.current.weather_description"
          class="wc-icon"
        />
        <div>
          <div class="wc-temp">{{ data.current?.temperature }}<span class="wc-unit">°C</span></div>
          <div class="wc-desc">{{ data.current?.weather_description }}</div>
          <div class="wc-feels">Sensação: {{ data.current?.feels_like }}°C</div>
        </div>
      </div>

      <div class="wc-metrics">
        <div class="wc-metric">
          <span class="wc-metric-label">Umidade</span>
          <span class="wc-metric-value">{{ data.current?.humidity }}%</span>
        </div>
        <div class="wc-metric">
          <span class="wc-metric-label">Vento</span>
          <span class="wc-metric-value">{{ data.current?.wind_speed }} km/h</span>
        </div>
        <div class="wc-metric">
          <span class="wc-metric-label">Pressão</span>
          <span class="wc-metric-value">{{ data.current?.pressure }} hPa</span>
        </div>
        <div class="wc-metric">
          <span class="wc-metric-label">Visibilidade</span>
          <span class="wc-metric-value">{{ data.current?.visibility }} km</span>
        </div>
        <div class="wc-metric">
          <span class="wc-metric-label">Nuvens</span>
          <span class="wc-metric-value">{{ data.current?.clouds }}%</span>
        </div>
        <div class="wc-metric">
          <span class="wc-metric-label">Qualidade do Ar</span>
          <span class="wc-metric-value">{{ aqiLabel }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  data: { type: Object, required: true },
})

const aqiLabels = { 1: 'Boa', 2: 'Razoável', 3: 'Moderada', 4: 'Ruim', 5: 'Muito Ruim' }
const aqiLabel  = computed(() => aqiLabels[props.data.aqi] ?? '—')
</script>

<style scoped>
.wc-body { display: flex; gap: 24px; flex-wrap: wrap; }

.wc-temp-section {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
  min-width: 180px;
}

.wc-icon { width: 80px; height: 80px; filter: drop-shadow(0 2px 6px rgba(0,0,0,.2)); }

.wc-temp {
  font-size: 52px;
  font-weight: 700;
  color: white;
  line-height: 1;
}
.wc-unit { font-size: 24px; font-weight: 400; }
.wc-desc { font-size: 15px; color: rgba(255,255,255,.85); text-transform: capitalize; margin-top: 4px; }
.wc-feels { font-size: 13px; color: rgba(255,255,255,.65); margin-top: 4px; }

.wc-metrics {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 12px 20px;
  flex: 1;
  min-width: 220px;
}

.wc-metric { display: flex; flex-direction: column; gap: 2px; }
.wc-metric-label { font-size: 11px; color: rgba(255,255,255,.55); text-transform: uppercase; letter-spacing: .05em; }
.wc-metric-value { font-size: 15px; font-weight: 600; color: rgba(255,255,255,.9); }
</style>
