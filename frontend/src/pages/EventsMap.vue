<template>
  <div class="events-map-page">
    <div class="events-map-toolbar">
      <div class="events-map-search">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input
          ref="searchInput"
          v-model="searchQuery"
          type="text"
          placeholder="Buscar endereço ou cidade..."
          @input="onSearchInput"
          @keydown.escape="clearSearch"
        />
        <button v-if="searchQuery" class="events-map-search-clear" @click="clearSearch">&times;</button>
        <div v-if="searchResults.length" class="events-map-search-results">
          <button
            v-for="r in searchResults"
            :key="r.osm_id ?? r.lat + r.lon"
            class="events-map-search-item"
            @click="goToResult(r)"
          >
            <span class="events-map-search-item-name">{{ r.display_name }}</span>
          </button>
        </div>
      </div>
      <div class="events-map-stats">
        <template v-if="filterCity">
          <strong>{{ filteredEvents.length }}</strong> evento{{ filteredEvents.length !== 1 ? 's' : '' }} em <em>{{ filterCity }}</em>
          <button class="btn btn-ghost btn-xs" @click="clearCityFilter">Limpar filtro</button>
        </template>
        <template v-else>
          <strong>{{ filteredEvents.length }}</strong> evento{{ filteredEvents.length !== 1 ? 's' : '' }}
        </template>
      </div>
    </div>
    <div ref="mapContainer" class="events-map-container"></div>

    <div v-if="selectedEvent" class="events-map-popup-card">
      <button class="events-map-popup-close" @click="selectedEvent = null">&times;</button>
      <h4>{{ selectedEvent.name }}</h4>
      <div class="events-map-popup-info">
        <span>{{ formatDate(selectedEvent.event_date) }} às {{ selectedEvent.event_time }}</span>
        <span>{{ selectedEvent.city }}, {{ selectedEvent.country }}</span>
        <span v-if="selectedEvent.venue">📍 {{ selectedEvent.venue }}</span>
        <span v-if="selectedEvent.organizer">👤 {{ selectedEvent.organizer }}</span>
        <span v-if="selectedEvent.expected_audience">👥 {{ formatNum(selectedEvent.expected_audience) }} pessoas</span>
        <span v-if="selectedEvent.budget">💰 Orçamento: {{ formatBRL(selectedEvent.budget) }}</span>
      </div>
      <div class="events-map-popup-actions">
        <RouterLink :to="`/events/${selectedEvent.id}/edit`" class="btn btn-sm btn-ghost">Editar</RouterLink>
      </div>
    </div>

    <div v-if="showQuickForm" class="events-map-quick-overlay" @click.self="closeQuickForm">
      <div class="events-map-quick-card">
        <h4>Novo Evento</h4>
        <p class="events-map-quick-coords">
          {{ quickLat.toFixed(4) }}, {{ quickLng.toFixed(4) }}
        </p>
        <div class="events-map-quick-form">
          <input v-model="quickForm.name" type="text" placeholder="Nome do evento" class="form-control" />
          <div class="events-map-quick-row">
            <input v-model="quickForm.event_date" type="date" class="form-control" />
            <input v-model="quickForm.event_time" type="time" class="form-control" />
          </div>
          <div class="events-map-quick-row">
            <input v-model="quickForm.city" type="text" placeholder="Cidade" class="form-control" />
            <input v-model="quickForm.country" type="text" placeholder="BR" maxlength="2" class="form-control" style="max-width:60px" />
          </div>
          <div v-if="quickError" class="alert alert-danger">{{ quickError }}</div>
          <div class="events-map-quick-actions">
            <button class="btn btn-primary btn-sm" :disabled="quickSubmitting" @click="submitQuickEvent">
              {{ quickSubmitting ? 'Salvando...' : 'Criar' }}
            </button>
            <button class="btn btn-ghost btn-sm" @click="closeQuickForm">Cancelar</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import L from 'leaflet'
import { eventsApi, favoritesApi } from '../services/api.js'

const route = useRoute()
const router = useRouter()

const mapContainer = ref(null)
const searchInput = ref(null)
const events = ref([])
const selectedEvent = ref(null)

const searchQuery = ref(route.query.city || '')
const filterCity = ref(route.query.city || '')
const searchResults = ref([])
let searchTimer = null

const showQuickForm = ref(false)
const quickLat = ref(0)
const quickLng = ref(0)
const quickForm = ref({ name: '', city: '', country: 'BR', event_date: '', event_time: '' })
const quickSubmitting = ref(false)
const quickError = ref(null)

const filteredEvents = computed(() => {
  if (!filterCity.value) return events.value
  const q = filterCity.value.toLowerCase()
  return events.value.filter(e => e.city?.toLowerCase().includes(q))
})

function clearCityFilter() {
  filterCity.value = ''
  searchQuery.value = ''
  router.replace({ query: {} })
  renderMarkers()
}

let map = null
let markersLayer = null

function createEventIcon(isSelected = false) {
  const color = isSelected ? '#3B82F6' : '#6B7280'
  const size = isSelected ? 28 : 22
  return L.divIcon({
    className: 'evmap-marker',
    html: `<span style="width:${size}px;height:${size}px;background:${color};border:3px solid rgba(255,255,255,0.9);box-shadow:0 2px 8px rgba(0,0,0,0.3)"></span>`,
    iconSize: [size, size],
    iconAnchor: [size / 2, size / 2],
  })
}

function renderMarkers() {
  if (!markersLayer) return
  markersLayer.clearLayers()

  const bounds = []
  for (const event of filteredEvents.value) {
    const lat = Number(event.latitude)
    const lng = Number(event.longitude)
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) continue

    const isSelected = selectedEvent.value?.id === event.id
    const marker = L.marker([lat, lng], { icon: createEventIcon(isSelected) })

    const dateStr = event.event_date ? formatDate(event.event_date) : ''
    const timeStr = event.event_time || ''
    const typeLabel = event.type === 'outdoor' ? '🌤' : '🏛'
    const statusIcons = { planned: '📋', confirmed: '✅', in_progress: '▶️', completed: '🏁', cancelled: '❌' }
    const statusIcon = statusIcons[event.status] || ''
    marker.bindTooltip(`
      <div class="evmap-tooltip">
        <strong>${event.name}</strong>
        <span>${dateStr}${timeStr ? ' — ' + timeStr : ''}</span>
        <span class="evmap-tooltip-city">${event.city} ${typeLabel} ${statusIcon}</span>
        ${event.venue ? `<span class="evmap-tooltip-city">📍 ${event.venue}</span>` : ''}
      </div>
    `, { direction: 'top', offset: [0, -10] })

    marker.on('click', () => {
      selectedEvent.value = event
    })

    marker.addTo(markersLayer)
    bounds.push([lat, lng])
  }

  if (bounds.length > 0) {
    map.fitBounds(bounds, { padding: [50, 50], maxZoom: 14 })
  } else {
    map.setView([-23.55052, -46.633308], 10)
  }
}

function formatDate(date) {
  if (!date) return ''
  return new Date(date + 'T00:00:00').toLocaleDateString('pt-BR')
}

function formatNum(n) {
  return new Intl.NumberFormat('pt-BR').format(n)
}

function formatBRL(n) {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n)
}

async function loadEvents() {
  try {
    const res = await eventsApi.list()
    events.value = res.data ?? []
    renderMarkers()
  } catch {
    // silent
  }
}

function onSearchInput() {
  clearTimeout(searchTimer)
  searchResults.value = []
  const q = searchQuery.value.trim()
  if (q.length < 3) return
  searchTimer = setTimeout(() => searchAddress(q), 400)
}

async function searchAddress(q) {
  try {
    const res = await fetch(`https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(q)}&format=json&limit=5&accept-language=pt`)
    const data = await res.json()
    searchResults.value = data.map(r => ({
      lat: parseFloat(r.lat),
      lon: parseFloat(r.lon),
      display_name: r.display_name,
    }))
  } catch {
    // silent
  }
}

function goToResult(r) {
  searchQuery.value = ''
  searchResults.value = []
  map.setView([r.lat, r.lon], 15)
  L.circleMarker([r.lat, r.lon], {
    radius: 8,
    color: '#3B82F6',
    fillColor: '#3B82F6',
    fillOpacity: 0.3,
    weight: 2,
  }).addTo(markersLayer)
}

function clearSearch() {
  searchQuery.value = ''
  searchResults.value = []
}

function onMapDblClick(e) {
  quickLat.value = e.latlng.lat
  quickLng.value = e.latlng.lng
  quickForm.value = { name: '', city: '', country: 'BR', event_date: '', event_time: '' }
  quickError.value = null
  showQuickForm.value = true
}

function closeQuickForm() {
  showQuickForm.value = false
}

async function submitQuickEvent() {
  if (!quickForm.value.name || !quickForm.value.event_date || !quickForm.value.event_time) {
    quickError.value = 'Preencha nome, data e horário'
    return
  }

  quickSubmitting.value = true
  quickError.value = null

  try {
    const payload = {
      name: quickForm.value.name,
      city: quickForm.value.city || 'Desconhecida',
      country: quickForm.value.country || 'BR',
      event_date: quickForm.value.event_date,
      event_time: quickForm.value.event_time,
      type: 'outdoor',
      latitude: quickLat.value,
      longitude: quickLng.value,
    }
    await eventsApi.create(payload)
    try {
      await favoritesApi.add({ city: payload.city, country: payload.country })
    } catch { /* silent */ }
    showQuickForm.value = false
    await loadEvents()
  } catch (e) {
    quickError.value = e.message
  } finally {
    quickSubmitting.value = false
  }
}

onMounted(() => {
  if (!mapContainer.value) return

  map = L.map(mapContainer.value, {
    zoomControl: true,
    attributionControl: true,
  })

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18,
    attribution: '&copy; OpenStreetMap',
  }).addTo(map)

  markersLayer = L.layerGroup().addTo(map)

  map.on('dblclick', onMapDblClick)

  loadEvents()
})

onBeforeUnmount(() => {
  if (map) {
    map.remove()
    map = null
    markersLayer = null
  }
})
</script>

<style scoped>
.events-map-page {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 140px);
  gap: 0;
}

.events-map-toolbar {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 12px 0;
}

.events-map-search {
  position: relative;
  flex: 1;
  max-width: 420px;
  display: flex;
  align-items: center;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 0 10px;
  gap: 8px;
}

.events-map-search svg {
  flex-shrink: 0;
  color: var(--color-text-secondary);
}

.events-map-search input {
  flex: 1;
  border: none;
  background: transparent;
  padding: 8px 0;
  font-size: 13px;
  color: var(--color-text);
  outline: none;
}

.events-map-search-clear {
  background: none;
  border: none;
  color: var(--color-text-secondary);
  cursor: pointer;
  font-size: 18px;
  line-height: 1;
  padding: 2px;
}

.events-map-search-results {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  margin-top: 4px;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: var(--radius-md);
  box-shadow: 0 8px 24px rgba(0,0,0,0.12);
  z-index: 1000;
  max-height: 220px;
  overflow-y: auto;
}

.events-map-search-item {
  display: block;
  width: 100%;
  text-align: left;
  background: none;
  border: none;
  padding: 10px 12px;
  font-size: 12px;
  color: #1e293b;
  cursor: pointer;
  border-bottom: 1px solid #e2e8f0;
}

.events-map-search-item:last-child {
  border-bottom: none;
}

.events-map-search-item:hover {
  background: #f1f5f9;
}

.events-map-search-item-name {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.events-map-stats {
  font-size: 13px;
  color: var(--color-text-secondary);
  white-space: nowrap;
}

.events-map-container {
  flex: 1;
  min-height: 400px;
  border-radius: var(--radius-md);
  overflow: hidden;
  border: 1px solid var(--color-border);
}

.events-map-popup-card {
  position: absolute;
  bottom: 24px;
  left: 50%;
  transform: translateX(-50%);
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: var(--radius-md);
  padding: 16px 20px;
  box-shadow: 0 8px 24px rgba(0,0,0,0.15);
  z-index: 1000;
  min-width: 240px;
  max-width: 320px;
}

.events-map-popup-close {
  position: absolute;
  top: 8px;
  right: 12px;
  background: none;
  border: none;
  font-size: 20px;
  color: #94a3b8;
  cursor: pointer;
  line-height: 1;
}

.events-map-popup-card h4 {
  margin: 0 0 8px;
  font-size: 15px;
  color: #1e293b;
}

.events-map-popup-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 12px;
  color: #64748b;
  margin-bottom: 10px;
}

.events-map-popup-actions a {
  color: #3b82f6;
}

.events-map-popup-actions {
  display: flex;
  gap: 8px;
}

.events-map-quick-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}

.events-map-quick-card {
  background: #fff;
  border-radius: var(--radius-md);
  padding: 24px;
  width: 360px;
  max-width: 92vw;
  box-shadow: 0 16px 48px rgba(0,0,0,0.25);
}

.events-map-quick-card h4 {
  margin: 0 0 4px;
  font-size: 16px;
  color: #1e293b;
}

.events-map-quick-coords {
  font-size: 11px;
  color: #64748b;
  margin: 0 0 16px;
  font-family: monospace;
}

.events-map-quick-form {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.events-map-quick-row {
  display: flex;
  gap: 8px;
}

.events-map-quick-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  margin-top: 4px;
}

:global(.evmap-marker) {
  background: transparent !important;
  border: none !important;
}

:global(.evmap-marker span) {
  display: block;
  border-radius: 50%;
  transition: transform 150ms;
}

:global(.evmap-marker span:hover) {
  transform: scale(1.15);
}

:global(.leaflet-tooltip) {
  border: none;
  border-radius: var(--radius-md);
  background: rgba(15, 23, 42, 0.92);
  color: #fff;
  box-shadow: 0 4px 12px rgba(0,0,0,0.25);
  padding: 0;
  font-size: 12px;
  line-height: 1.4;
  max-width: 220px;
}

:global(.leaflet-tooltip-top:before) {
  border-top-color: rgba(15, 23, 42, 0.92);
}

:global(.evmap-tooltip) {
  padding: 8px 12px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

:global(.evmap-tooltip strong) {
  font-size: 13px;
  color: #fff;
}

:global(.evmap-tooltip span) {
  font-size: 11px;
  color: rgba(255,255,255,0.7);
}

:global(.evmap-tooltip .evmap-tooltip-city) {
  font-size: 10px;
  color: rgba(255,255,255,0.5);
  margin-top: 1px;
}

@media (max-width: 768px) {
  .events-map-page {
    height: calc(100vh - 100px);
  }

  .events-map-toolbar {
    flex-wrap: wrap;
    gap: 8px;
  }

  .events-map-search {
    max-width: none;
    order: 2;
    flex-basis: 100%;
  }

  .events-map-popup-card {
    bottom: 16px;
    left: 12px;
    right: 12px;
    transform: none;
    min-width: 0;
  }

  .events-map-quick-card {
    width: 100%;
    margin: 16px;
  }
}
</style>
