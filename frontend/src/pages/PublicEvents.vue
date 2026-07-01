<template>
  <div class="pl-wrapper">
    <!-- Nav -->
    <nav class="pl-nav">
      <div class="pl-nav-inner">
        <span class="pl-logo">⛅ inEvent</span>
        <div class="pl-nav-links">
          <template v-if="isLoggedIn">
            <router-link to="/dashboard" class="btn btn-primary btn-sm">Dashboard</router-link>
          </template>
          <router-link v-else to="/login" class="btn btn-primary btn-sm">Entrar</router-link>
        </div>
      </div>
    </nav>

    <!-- Hero -->
    <section class="pl-hero">
      <div class="pl-hero-content">
        <h1 class="pl-hero-title">Descubra eventos incríveis</h1>
        <p class="pl-hero-sub">Encontre os melhores eventos perto de você, com previsão do tempo e muito mais.</p>
        <div class="pl-search">
          <svg class="pl-search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input
            v-model="searchQuery"
            type="text"
            class="pl-search-input"
            placeholder="Buscar por nome, cidade, organizador..."
            @input="debouncedSearch"
          />
        </div>
      </div>
    </section>

    <!-- Filters -->
    <section class="pl-filters">
      <div class="pl-filters-inner">
        <div class="pl-filter-group">
          <label class="pl-filter-label">Cidade</label>
          <select v-model="filterCity" class="pl-filter-select" @change="fetchEvents(1)">
            <option value="">Todas as cidades</option>
            <option v-for="c in cities" :key="c" :value="c">{{ c }}</option>
          </select>
        </div>
        <div class="pl-filter-group">
          <label class="pl-filter-label">Tipo</label>
          <select v-model="filterType" class="pl-filter-select" @change="fetchEvents(1)">
            <option value="">Todos os tipos</option>
            <option value="outdoor">Ao ar livre</option>
            <option value="indoor">Indoor</option>
            <option value="conference">Conferência</option>
            <option value="workshop">Workshop</option>
            <option value="networking">Networking</option>
          </select>
        </div>
        <div class="pl-filter-group">
          <label class="pl-filter-label">Período</label>
          <select v-model="filterPeriod" class="pl-filter-select" @change="fetchEvents(1)">
            <option value="upcoming">Próximos</option>
            <option value="past">Realizados</option>
            <option value="all">Todos</option>
          </select>
        </div>
        <div class="pl-filter-group pl-filter-count">
          <span class="pl-result-count">{{ meta.total }} evento{{ meta.total !== 1 ? 's' : '' }}</span>
        </div>
      </div>
    </section>

    <!-- Loading -->
    <section v-if="loading" class="pl-section">
      <div class="pl-loading">
        <span class="spinner" style="width:32px;height:32px;border-width:3px" />
        <p>Carregando eventos...</p>
      </div>
    </section>

    <!-- Empty -->
    <section v-else-if="events.length === 0" class="pl-section">
      <div class="pl-empty">
        <span class="pl-empty-icon">📭</span>
        <h3>Nenhum evento encontrado</h3>
        <p>Tente alterar os filtros ou buscar por outro termo.</p>
      </div>
    </section>

    <!-- Event Grid -->
    <section v-else class="pl-section">
      <div class="pl-grid">
        <article
          v-for="event in events"
          :key="event.id"
          class="pl-card"
          @click="goToEvent(event.id)"
        >
          <div class="pl-card-banner" :class="bannerClass(event)">
            <span :class="['badge', event.type === 'outdoor' ? 'badge-info' : 'badge-neutral']">
              {{ event.type === 'outdoor' ? '🌤 Ao ar livre' : '🏛 ' + (event.type || 'Indoor') }}
            </span>
          </div>
          <div class="pl-card-body">
            <h3 class="pl-card-title">{{ event.name }}</h3>
            <div class="pl-card-meta">
              <span class="pl-card-date">📅 {{ formatDate(event.event_date) }}{{ event.event_time ? ' às ' + event.event_time.slice(0, 5) : '' }}</span>
              <span class="pl-card-city">📍 {{ event.city }}{{ event.venue ? ' — ' + event.venue : '' }}</span>
            </div>
            <p v-if="event.description" class="pl-card-desc">{{ truncate(event.description, 120) }}</p>
            <div class="pl-card-footer">
              <span class="pl-card-org" v-if="event.organizer">🎫 {{ event.organizer }}</span>
              <span class="pl-card-regs">{{ event.registrations_count || 0 }} inscrito{{ event.registrations_count !== 1 ? 's' : '' }}</span>
            </div>
          </div>
        </article>
      </div>

      <!-- Pagination -->
      <div v-if="meta.last_page > 1" class="pl-pagination">
        <button
          class="btn btn-ghost btn-sm"
          :disabled="meta.current_page <= 1"
          @click="fetchEvents(meta.current_page - 1)"
        >Anterior</button>
        <span class="pl-page-info">Página {{ meta.current_page }} de {{ meta.last_page }}</span>
        <button
          class="btn btn-ghost btn-sm"
          :disabled="meta.current_page >= meta.last_page"
          @click="fetchEvents(meta.current_page + 1)"
        >Próxima</button>
      </div>
    </section>

    <!-- Footer -->
    <footer class="pl-footer">
      <p>⛅ inEvent — Gestão Inteligente de Eventos</p>
    </footer>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { publicApi } from '../services/api.js'

const router = useRouter()

const isLoggedIn = computed(() => !!localStorage.getItem('ew_token'))

const events = ref([])
const loading = ref(true)
const meta = ref({ total: 0, current_page: 1, last_page: 1 })

const searchQuery = ref('')
const filterCity = ref('')
const filterType = ref('')
const filterPeriod = ref('upcoming')
const cities = ref([])

let debounceTimer = null

function debouncedSearch() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => fetchEvents(1), 300)
}

function truncate(text, len) {
  if (!text) return ''
  return text.length > len ? text.slice(0, len) + '…' : text
}

function formatDate(d) {
  if (!d) return '—'
  return new Date(d + 'T12:00:00').toLocaleDateString('pt-BR', { day: 'numeric', month: 'short', year: 'numeric' })
}

function bannerClass(event) {
  if (event.type === 'outdoor') return 'pl-banner-outdoor'
  if (event.type === 'conference') return 'pl-banner-conf'
  if (event.type === 'workshop') return 'pl-banner-workshop'
  return 'pl-banner-default'
}

function goToEvent(id) {
  router.push(`/e/${id}`)
}

async function fetchEvents(page = 1) {
  loading.value = true
  try {
    const params = { page, per_page: 12, order: filterPeriod.value }
    if (searchQuery.value) params.search = searchQuery.value
    if (filterCity.value) params.city = filterCity.value
    if (filterType.value) params.type = filterType.value

    const res = await publicApi.list(params)
    events.value = res.data
    meta.value = res.meta

    // Extract unique cities for filter
    if (!filterCity.value) {
      const unique = [...new Set(events.value.map(e => e.city).filter(Boolean))]
      cities.value = unique.sort()
    }
  } catch {
    events.value = []
  } finally {
    loading.value = false
  }
}

onMounted(() => fetchEvents())
</script>

<style scoped>
.pl-wrapper {
  min-height: 100vh;
  font-family: var(--font, 'Inter', system-ui, sans-serif);
  background: #0f172a;
  color: #e2e8f0;
}

/* Nav */
.pl-nav {
  position: sticky;
  top: 0;
  z-index: 50;
  background: rgba(15, 23, 42, .85);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid rgba(255,255,255,.06);
}

.pl-nav-inner {
  max-width: 1200px;
  margin: 0 auto;
  padding: 12px 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.pl-logo {
  font-size: 18px;
  font-weight: 800;
  color: #f8fafc;
  letter-spacing: -.3px;
}

.pl-nav-links {
  display: flex;
  align-items: center;
  gap: 8px;
}

.pl-nav-links :deep(.btn-primary) {
  background: #3b82f6;
  color: #fff;
  padding: 8px 18px;
  font-size: 13px;
  font-weight: 600;
  border-radius: 10px;
}

/* Hero */
.pl-hero {
  padding: 64px 24px 48px;
  text-align: center;
  background: linear-gradient(180deg, #0f172a 0%, #1a2744 50%, #0f172a 100%);
}

.pl-hero-content {
  max-width: 640px;
  margin: 0 auto;
}

.pl-hero-title {
  font-size: 40px;
  font-weight: 800;
  color: #f8fafc;
  margin-bottom: 12px;
  line-height: 1.15;
  letter-spacing: -.5px;
}

.pl-hero-sub {
  font-size: 16px;
  color: #94a3b8;
  margin-bottom: 28px;
  line-height: 1.6;
}

.pl-search {
  position: relative;
  max-width: 480px;
  margin: 0 auto;
}

.pl-search-icon {
  position: absolute;
  left: 16px;
  top: 50%;
  transform: translateY(-50%);
  color: #64748b;
  pointer-events: none;
}

.pl-search-input {
  width: 100%;
  padding: 14px 16px 14px 48px;
  background: rgba(255,255,255,.08);
  border: 1px solid rgba(255,255,255,.12);
  border-radius: 14px;
  color: #f1f5f9;
  font-size: 15px;
  outline: none;
  transition: border-color 200ms, box-shadow 200ms;
}

.pl-search-input::placeholder {
  color: #64748b;
}

.pl-search-input:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59,130,246,.2);
}

/* Filters */
.pl-filters {
  padding: 0 24px;
  margin-bottom: 32px;
}

.pl-filters-inner {
  max-width: 1200px;
  margin: 0 auto;
  display: flex;
  gap: 16px;
  align-items: flex-end;
  flex-wrap: wrap;
}

.pl-filter-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 160px;
}

.pl-filter-label {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .05em;
  color: #64748b;
}

.pl-filter-select {
  padding: 10px 12px;
  background: rgba(255,255,255,.06);
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 10px;
  color: #e2e8f0;
  font-size: 13px;
  outline: none;
  cursor: pointer;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 10px center;
  padding-right: 32px;
}

.pl-filter-select:focus {
  border-color: #3b82f6;
}

.pl-filter-select option {
  background: #1e293b;
  color: #e2e8f0;
}

.pl-result-count {
  font-size: 13px;
  color: #64748b;
  white-space: nowrap;
  padding-bottom: 10px;
}

/* Loading & Empty */
.pl-section {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 24px 48px;
}

.pl-loading,
.pl-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 24px;
  gap: 12px;
  color: #94a3b8;
}

.pl-empty-icon { font-size: 48px; }
.pl-empty h3 { color: #e2e8f0; font-size: 18px; }
.pl-empty p { font-size: 14px; color: #64748b; }

/* Grid */
.pl-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 20px;
}

.pl-card {
  background: rgba(255,255,255,.04);
  border: 1px solid rgba(255,255,255,.08);
  border-radius: 16px;
  overflow: hidden;
  cursor: pointer;
  transition: all 250ms;
}

.pl-card:hover {
  background: rgba(255,255,255,.07);
  border-color: rgba(255,255,255,.15);
  transform: translateY(-2px);
  box-shadow: 0 12px 32px rgba(0,0,0,.3);
}

.pl-card-banner {
  height: 120px;
  display: flex;
  align-items: flex-start;
  justify-content: flex-end;
  padding: 12px;
  background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
}

.pl-banner-outdoor {
  background: linear-gradient(135deg, #0f3d3a 0%, #1a5a50 100%);
}

.pl-banner-conf {
  background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
}

.pl-banner-workshop {
  background: linear-gradient(135deg, #3b0f2a 0%, #6b1d45 100%);
}

.pl-card-banner :deep(.badge) {
  background: rgba(0,0,0,.4);
  backdrop-filter: blur(4px);
  color: #e2e8f0;
  border: none;
  font-size: 11px;
}

.pl-card-body {
  padding: 16px 20px 20px;
}

.pl-card-title {
  font-size: 17px;
  font-weight: 700;
  color: #f1f5f9;
  margin-bottom: 8px;
  line-height: 1.3;
}

.pl-card-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-bottom: 10px;
}

.pl-card-date,
.pl-card-city {
  font-size: 13px;
  color: #94a3b8;
}

.pl-card-desc {
  font-size: 13px;
  color: #64748b;
  line-height: 1.5;
  margin-bottom: 12px;
}

.pl-card-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 12px;
  border-top: 1px solid rgba(255,255,255,.06);
}

.pl-card-org {
  font-size: 12px;
  color: #64748b;
}

.pl-card-regs {
  font-size: 12px;
  font-weight: 600;
  color: #3b82f6;
}

/* Pagination */
.pl-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 32px 0 16px;
}

.pl-pagination :deep(.btn-ghost) {
  color: #94a3b8;
  padding: 8px 18px;
  font-size: 13px;
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 10px;
  background: transparent;
}

.pl-pagination :deep(.btn-ghost:hover:not(:disabled)) {
  background: rgba(255,255,255,.06);
  color: #e2e8f0;
}

.pl-pagination :deep(.btn-ghost:disabled) {
  opacity: .3;
  cursor: default;
}

.pl-page-info {
  font-size: 13px;
  color: #64748b;
}

/* Footer */
.pl-footer {
  text-align: center;
  padding: 32px 24px;
  border-top: 1px solid rgba(255,255,255,.06);
}

.pl-footer p {
  font-size: 13px;
  color: #475569;
}

/* Responsive */
@media (max-width: 768px) {
  .pl-hero { padding: 40px 20px 32px; }
  .pl-hero-title { font-size: 28px; }
  .pl-hero-sub { font-size: 14px; }
  .pl-search-input { padding: 12px 14px 12px 44px; font-size: 14px; }
  .pl-grid { grid-template-columns: 1fr; }
  .pl-filters-inner { flex-direction: column; }
  .pl-filter-group { min-width: 100%; }
}
</style>
