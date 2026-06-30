<template>
  <div class="card weather-main-card weather-map-card">
    <div ref="mapElement" class="weather-map-background" aria-hidden="true"></div>
    <div class="weather-map-overlay"></div>

    <div class="weather-card-content">
      <div class="card-header">
        <h3>{{ data.city }}, {{ data.country }}</h3>
        <div class="weather-card-badges">
          <span class="badge badge-info weather-live-badge">Agora</span>
          <span class="badge weather-events-badge">{{ mappedEvents.length }} eventos no mapa</span>
        </div>
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
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import L from 'leaflet'

const props = defineProps({
  data: { type: Object, required: true },
  events: { type: Array, default: () => [] },
})

const aqiLabels = { 1: 'Boa', 2: 'Razoável', 3: 'Moderada', 4: 'Ruim', 5: 'Muito Ruim' }
const aqiLabel = computed(() => aqiLabels[props.data.aqi] ?? '—')

const mapElement = ref(null)
let map = null
let markersLayer = null

const centerCoordinates = computed(() => {
  const latitude = Number(props.data?.coordinates?.latitude)
  const longitude = Number(props.data?.coordinates?.longitude)
  if (Number.isFinite(latitude) && Number.isFinite(longitude)) {
    return [latitude, longitude]
  }
  return [-23.55052, -46.633308]
})

const mappedEvents = computed(() =>
  props.events
    .map((event) => {
      const latitude = Number(event.latitude)
      const longitude = Number(event.longitude)
      if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
        return null
      }

      return {
        id: event.id,
        name: event.name,
        city: event.city,
        date: event.event_date,
        coordinates: [latitude, longitude],
      }
    })
    .filter(Boolean)
)

function createEventIcon() {
  return L.divIcon({
    className: 'weather-event-marker',
    html: '<span></span>',
    iconSize: [18, 18],
    iconAnchor: [9, 9],
  })
}

function renderMarkers() {
  if (!map || !markersLayer) {
    return
  }

  markersLayer.clearLayers()

  const bounds = []
  for (const event of mappedEvents.value) {
    const marker = L.marker(event.coordinates, { icon: createEventIcon() })
    marker.bindTooltip(`${event.name} • ${event.city}`, {
      direction: 'top',
      offset: [0, -8],
      opacity: 0.92,
    })
    marker.addTo(markersLayer)
    bounds.push(event.coordinates)
  }

  if (bounds.length > 0) {
    map.fitBounds(bounds, { padding: [36, 36], maxZoom: 11 })
    return
  }

  map.setView(centerCoordinates.value, 10)
}

function initMap() {
  if (!mapElement.value || map) {
    return
  }

  map = L.map(mapElement.value, {
    zoomControl: false,
    attributionControl: false,
    dragging: false,
    scrollWheelZoom: false,
    doubleClickZoom: false,
    boxZoom: false,
    keyboard: false,
    tap: false,
  })

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18,
  }).addTo(map)

  markersLayer = L.layerGroup().addTo(map)
  renderMarkers()
}

onMounted(() => {
  initMap()
})

watch([mappedEvents, centerCoordinates], () => {
  renderMarkers()
}, { deep: true })

onBeforeUnmount(() => {
  if (map) {
    map.remove()
    map = null
    markersLayer = null
  }
})
</script>

<style scoped>
.weather-map-card {
  position: relative;
  overflow: hidden;
  min-height: 300px;
}

.weather-map-background,
.weather-map-overlay,
.weather-card-content {
  position: absolute;
  inset: 0;
}

.weather-map-background {
  z-index: 0;
  transform: scale(1.08);
  filter: saturate(0.8) blur(2px);
}

.weather-map-overlay {
  z-index: 1;
  background:
    linear-gradient(135deg, rgba(29, 78, 216, 0.78), rgba(15, 23, 42, 0.72)),
    radial-gradient(circle at top right, rgba(255,255,255,0.16), transparent 34%);
}

.weather-card-content {
  position: relative;
  z-index: 2;
  padding: 20px;
}

.weather-card-badges {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.weather-live-badge {
  background: rgba(255,255,255,.15);
  color: #fff;
}

.weather-events-badge {
  background: rgba(15, 23, 42, 0.28);
  color: rgba(255,255,255,.92);
  border: 1px solid rgba(255,255,255,.14);
}

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

:global(.weather-event-marker) {
  background: transparent;
  border: none;
}

:global(.weather-event-marker span) {
  display: block;
  width: 18px;
  height: 18px;
  border-radius: 9999px;
  background: rgba(255,255,255,0.92);
  border: 3px solid rgba(37, 99, 235, 0.6);
  box-shadow: 0 0 0 10px rgba(255,255,255,0.12);
}

:global(.leaflet-tooltip) {
  border: none;
  border-radius: 9999px;
  background: rgba(15, 23, 42, 0.82);
  color: #fff;
  box-shadow: none;
  padding: 6px 10px;
}

:global(.leaflet-tooltip-top:before) {
  border-top-color: rgba(15, 23, 42, 0.82);
}

@media (max-width: 768px) {
  .weather-card-content {
    padding: 16px;
  }

  .weather-card-badges {
    justify-content: flex-start;
  }

  .wc-metrics {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 480px) {
  .weather-map-card {
    min-height: 360px;
  }

  .wc-temp {
    font-size: 42px;
  }

  .wc-metrics {
    grid-template-columns: 1fr;
    gap: 10px;
  }
}
</style>
