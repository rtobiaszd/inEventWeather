<template>
  <div class="page-container">
    <div class="page-header">
      <div>
        <h1 class="page-title">Relatórios</h1>
        <p class="page-subtitle">Análise consolidada do portfólio de eventos</p>
      </div>
      <div class="page-actions">
        <a :href="exportUrl" class="btn btn-secondary btn-sm" @click.prevent="exportCsv">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Exportar CSV
        </a>
      </div>
    </div>

    <!-- Filters -->
    <div class="filter-bar">
      <div class="filter-group">
        <label>De</label>
        <input v-model="filters.date_from" type="date" class="form-control" @change="loadAll" />
      </div>
      <div class="filter-group">
        <label>Até</label>
        <input v-model="filters.date_to" type="date" class="form-control" @change="loadAll" />
      </div>
      <div class="filter-group">
        <label>Status</label>
        <select v-model="filters.status" class="form-control" @change="loadAll">
          <option value="">Todos</option>
          <option value="planned">Planejados</option>
          <option value="confirmed">Confirmados</option>
          <option value="in_progress">Em andamento</option>
          <option value="completed">Realizados</option>
          <option value="cancelled">Cancelados</option>
        </select>
      </div>
      <div class="filter-group">
        <label>Cidade</label>
        <input v-model="filters.city" type="text" class="form-control" placeholder="Filtrar cidade" @change="loadAll" />
      </div>
      <div class="filter-group">
        <label>Tipo</label>
        <select v-model="filters.type" class="form-control" @change="loadAll">
          <option value="">Todos</option>
          <option value="outdoor">Outdoor</option>
          <option value="indoor">Indoor</option>
        </select>
      </div>
    </div>

    <!-- Summary Cards -->
    <LoadingState v-if="loading.summary" message="Carregando resumo..." />
    <div v-else class="metric-grid">
      <div class="metric-card">
        <div class="metric-label">Total Eventos</div>
        <div class="metric-value">{{ summary.total_events }}</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">Orçamento Total</div>
        <div class="metric-value">{{ formatBRL(summary.total_budget) }}</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">Receita Total</div>
        <div class="metric-value">{{ formatBRL(summary.total_revenue) }}</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">Lucro Total</div>
        <div class="metric-value" :class="summary.total_profit >= 0 ? 'text-success' : 'text-danger'">{{ formatBRL(summary.total_profit) }}</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">ROI Médio</div>
        <div class="metric-value" :class="summary.avg_roi >= 0 ? 'text-success' : 'text-danger'">{{ summary.avg_roi }}%</div>
      </div>
    </div>

    <!-- Row: Status Donut + Monthly Trends -->
    <div class="report-grid-2col" v-if="!loading.summary">
      <!-- Status Distribution -->
      <section class="card">
        <div class="card-header"><h3>Distribuição por Status</h3></div>
        <div class="card-body" style="display:flex;align-items:center;gap:24px;flex-wrap:wrap;">
          <div class="donut-wrapper">
            <svg width="140" height="140" viewBox="0 0 42 42">
              <circle cx="21" cy="21" r="15.9" fill="none" stroke="#1E293B" stroke-width="3" />
              <template v-for="(seg, i) in statusSegments" :key="i">
                <circle cx="21" cy="21" r="15.9" fill="none" :stroke="seg.color" stroke-width="3" :stroke-dasharray="`${seg.pct} ${100 - seg.pct}`" :stroke-dashoffset="seg.offset" transform="rotate(-90 21 21)" style="transition:stroke-dasharray .6s ease" />
              </template>
              <text x="21" y="21" text-anchor="middle" dy=".35em" fill="#0F172A" font-size="6" font-weight="700">{{ summary.total_events }}</text>
            </svg>
          </div>
          <div class="legend-list">
            <div v-for="s in statusList" :key="s.key" class="legend-item">
              <span class="legend-dot" :style="{ background: s.color }"></span>
              <span class="legend-label">{{ s.label }}</span>
              <span class="legend-value">{{ s.count }}</span>
            </div>
          </div>
        </div>
      </section>

      <!-- Monthly Trends -->
      <section class="card">
        <div class="card-header">
          <h3>Tendências Mensais</h3>
          <select v-model.number="trendMonths" class="form-control form-control-sm" style="width:auto;" @change="loadTrends">
            <option :value="6">6 meses</option>
            <option :value="12">12 meses</option>
            <option :value="24">24 meses</option>
          </select>
        </div>
        <LoadingState v-if="loading.trends" message="Carregando tendências..." />
        <div v-else-if="trends.length === 0" class="card-body"><p class="empty-text">Nenhum evento no período.</p></div>
        <div v-else class="card-body">
          <div class="bar-chart" style="height:140px;display:flex;align-items:flex-end;gap:3px;padding:0 4px;">
            <div v-for="(t, i) in trends" :key="i" style="flex:1;display:flex;flex-direction:column;align-items:center;height:100%;justify-content:flex-end;">
              <div :title="`${formatBRL(t.revenue)}`" style="width:100%;background:var(--color-primary);border-radius:3px 3px 0 0;transition:height .6s ease;" :style="{ height: barHeight(t.revenue, maxRevenue) + '%' }"></div>
              <div :title="`${formatBRL(t.budget)}`" style="width:100%;background:#475569;border-radius:0;transition:height .6s ease;" :style="{ height: barHeight(t.budget, maxRevenue) + '%' }"></div>
            </div>
          </div>
          <div class="bar-labels" style="display:flex;gap:3px;margin-top:4px;">
            <div v-for="(t, i) in trends" :key="i" style="flex:1;text-align:center;font-size:9px;color:#64748B;overflow:hidden;text-overflow:ellipsis;">{{ t.month.slice(5) }}</div>
          </div>
          <div class="chart-legend" style="display:flex;gap:16px;justify-content:center;margin-top:8px;font-size:11px;color:#94A3B8;">
            <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:var(--color-primary);margin-right:4px;"></span> Receita</span>
            <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#475569;margin-right:4px;"></span> Orçamento</span>
          </div>
          <div class="trend-stats" style="display:flex;gap:12px;margin-top:10px;flex-wrap:wrap;justify-content:center;">
            <span class="badge badge-info">Eventos: {{ trendTotals.events }}</span>
            <span class="badge badge-success">Receita: {{ formatBRL(trendTotals.revenue) }}</span>
            <span class="badge badge-neutral">Média/mês: {{ formatBRL(trendTotals.avgRevenue) }}</span>
          </div>
        </div>
      </section>
    </div>

    <!-- Row: City Performance + Type Performance -->
    <div class="report-grid-2col" v-if="!loading.cities">
      <!-- City Performance -->
      <section class="card">
        <div class="card-header"><h3>Performance por Cidade</h3></div>
        <LoadingState v-if="loading.cities" message="Carregando..." />
        <div v-else-if="cities.length === 0" class="card-body"><p class="empty-text">Nenhum dado disponível.</p></div>
        <div v-else class="card-body" style="padding:0;">
          <table class="data-table">
            <thead>
              <tr>
                <th>Cidade</th>
                <th style="text-align:right;">Eventos</th>
                <th style="text-align:right;">Receita</th>
                <th style="text-align:right;">Lucro</th>
                <th style="text-align:right;">ROI</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in cities" :key="c.city">
                <td><strong>{{ c.city }}</strong></td>
                <td style="text-align:right;">{{ c.events }}</td>
                <td style="text-align:right;">{{ formatBRL(c.total_revenue) }}</td>
                <td style="text-align:right;" :class="c.total_profit >= 0 ? 'text-success' : 'text-danger'">{{ formatBRL(c.total_profit) }}</td>
                <td style="text-align:right;" :class="c.avg_roi >= 0 ? 'text-success' : 'text-danger'">{{ c.avg_roi }}%</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Type Performance -->
      <section class="card">
        <div class="card-header"><h3>Performance por Tipo</h3></div>
        <LoadingState v-if="loading.types" message="Carregando..." />
        <div v-else-if="types.length === 0" class="card-body"><p class="empty-text">Nenhum dado disponível.</p></div>
        <div v-else class="card-body" style="padding:0;">
          <table class="data-table">
            <thead>
              <tr>
                <th>Tipo</th>
                <th style="text-align:right;">Eventos</th>
                <th style="text-align:right;">Receita</th>
                <th style="text-align:right;">Lucro</th>
                <th style="text-align:right;">ROI</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="t in types" :key="t.type">
                <td><strong>{{ t.type }}</strong></td>
                <td style="text-align:right;">{{ t.events }}</td>
                <td style="text-align:right;">{{ formatBRL(t.total_revenue) }}</td>
                <td style="text-align:right;" :class="t.total_profit >= 0 ? 'text-success' : 'text-danger'">{{ formatBRL(t.total_profit) }}</td>
                <td style="text-align:right;" :class="t.avg_roi >= 0 ? 'text-success' : 'text-danger'">{{ t.avg_roi }}%</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <!-- Status Breakdown Table -->
    <section class="card" v-if="!loading.summary">
      <div class="card-header"><h3>Detalhamento por Status</h3></div>
      <div class="card-body" style="padding:0;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Status</th>
              <th style="text-align:right;">Eventos</th>
              <th style="text-align:right;">%</th>
              <th style="text-align:right;">Orçamento</th>
              <th style="text-align:right;">Receita</th>
              <th style="text-align:right;">Lucro</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in statusBreakdown" :key="s.key" :class="s.key === 'cancelled' ? 'row-danger' : ''">
              <td><span class="badge" :class="s.badgeClass">{{ s.label }}</span></td>
              <td style="text-align:right;">{{ s.count }}</td>
              <td style="text-align:right;">{{ s.pct }}%</td>
              <td style="text-align:right;">{{ formatBRL(s.budget) }}</td>
              <td style="text-align:right;">{{ formatBRL(s.revenue) }}</td>
              <td style="text-align:right;" :class="s.profit >= 0 ? 'text-success' : 'text-danger'">{{ formatBRL(s.profit) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { http } from '../services/api.js'
import LoadingState from '../components/LoadingState.vue'

const filters = reactive({
  date_from: '',
  date_to: '',
  status: '',
  city: '',
  type: '',
})
const loading = reactive({ summary: false, trends: false, cities: false, types: false })
const summary = reactive({ total_events: 0, total_budget: 0, total_revenue: 0, total_profit: 0, avg_roi: 0 })
const byStatus = reactive({})
const byType = reactive({ outdoor: 0, indoor: 0 })
const trends = ref([])
const trendMonths = ref(12)
const cities = ref([])
const types = ref([])

const statusColors = {
  planned: '#3B82F6',
  confirmed: '#22C55E',
  in_progress: '#F59E0B',
  completed: '#6366F1',
  cancelled: '#EF4444',
}
const statusLabels = {
  planned: '📋 Planejados',
  confirmed: '✅ Confirmados',
  in_progress: '▶ Em andamento',
  completed: '🏁 Realizados',
  cancelled: '❌ Cancelados',
}
const statusBadge = {
  planned: 'badge badge-neutral',
  confirmed: 'badge badge-success',
  in_progress: 'badge badge-info',
  completed: 'badge badge-neutral',
  cancelled: 'badge badge-danger',
}
const statusOrder = ['planned', 'confirmed', 'in_progress', 'completed', 'cancelled']

const statusList = computed(() =>
  statusOrder.map(key => ({
    key,
    label: statusLabels[key],
    color: statusColors[key],
    count: byStatus[key]?.count ?? 0,
  }))
)

const statusSegments = computed(() => {
  const total = statusList.value.reduce((s, i) => s + i.count, 0) || 1
  let offset = 0
  return statusList.value.filter(i => i.count > 0).map(i => {
    const pct = (i.count / total) * 100
    const seg = { pct, offset, color: i.color }
    offset -= pct
    return seg
  })
})

const statusBreakdown = computed(() =>
  statusOrder.map(key => {
    const s = byStatus[key]
    const total = summary.total_events || 1
    return {
      key,
      label: statusLabels[key],
      badgeClass: statusBadge[key],
      count: s?.count ?? 0,
      pct: total > 0 ? Math.round(((s?.count ?? 0) / total) * 100) : 0,
      budget: s?.budget ?? 0,
      revenue: s?.revenue ?? 0,
      profit: (s?.revenue ?? 0) - (s?.budget ?? 0),
    }
  })
)

const maxRevenue = computed(() => Math.max(...trends.value.map(t => t.revenue || t.budget || 0), 1))

const trendTotals = computed(() => {
  const total = trends.value.reduce((acc, t) => ({ events: acc.events + t.events, revenue: acc.revenue + t.revenue }), { events: 0, revenue: 0 })
  return { ...total, avgRevenue: trends.value.length > 0 ? total.revenue / trends.value.length : 0 }
})

const exportUrl = computed(() => {
  const params = new URLSearchParams()
  if (filters.date_from) params.set('date_from', filters.date_from)
  if (filters.date_to) params.set('date_to', filters.date_to)
  if (filters.status) params.set('status', filters.status)
  if (filters.city) params.set('city', filters.city)
  if (filters.type) params.set('type', filters.type)
  return `${import.meta.env.VITE_API_URL}/api/reports/export/csv?${params.toString()}`
})

function barHeight(val, max) {
  if (max <= 0) return 0
  return Math.max((val / max) * 100, 2)
}

function formatBRL(n) {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n || 0)
}

function getToken() {
  return localStorage.getItem('ew_token') || ''
}

function buildParams() {
  const params = new URLSearchParams()
  if (filters.date_from) params.set('date_from', filters.date_from)
  if (filters.date_to) params.set('date_to', filters.date_to)
  if (filters.status) params.set('status', filters.status)
  if (filters.city) params.set('city', filters.city)
  if (filters.type) params.set('type', filters.type)
  return params.toString()
}

async function loadSummary() {
  loading.summary = true
  try {
    const res = await http.get(`/api/reports/summary?${buildParams()}`)
    Object.assign(summary, res.data.data.summary)
    Object.assign(byStatus, res.data.data.by_status)
    Object.assign(byType, res.data.data.by_type)
  } catch { /* ignore */ }
  finally { loading.summary = false }
}

async function loadTrends() {
  loading.trends = true
  try {
    const res = await http.get(`/api/reports/financial-trends?months=${trendMonths.value}&${buildParams()}`)
    trends.value = res.data.data.trends
  } catch { trends.value = [] }
  finally { loading.trends = false }
}

async function loadCities() {
  loading.cities = true
  try {
    const res = await http.get(`/api/reports/city-performance?${buildParams()}`)
    cities.value = res.data.data.cities
  } catch { cities.value = [] }
  finally { loading.cities = false }
}

async function loadTypes() {
  loading.types = true
  try {
    const res = await http.get(`/api/reports/type-performance?${buildParams()}`)
    types.value = res.data.data.types
  } catch { types.value = [] }
  finally { loading.types = false }
}

async function loadAll() {
  loadSummary()
  loadTrends()
  loadCities()
  loadTypes()
}

function exportCsv() {
  window.open(exportUrl.value, '_blank')
}

onMounted(loadAll)
</script>

<style scoped>
.page-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 24px;
  color: #0f172a;
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 20px;
  gap: 16px;
  flex-wrap: wrap;
}

.page-title {
  font-size: 22px;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 4px;
}

.page-subtitle {
  font-size: 13px;
  color: #475569;
  margin: 0;
}

.page-actions {
  display: flex;
  gap: 8px;
  flex-shrink: 0;
}

.filter-bar {
  display: flex;
  gap: 12px;
  margin-bottom: 24px;
  flex-wrap: wrap;
  align-items: flex-end;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.filter-group label {
  font-size: 11px;
  font-weight: 700;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.filter-group .form-control {
  min-width: 120px;
  background: #ffffff;
  color: #0f172a;
  border: 1px solid #cbd5e1;
}

.metric-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
  gap: 12px;
  margin-bottom: 20px;
}

.metric-card,
.card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: var(--radius-md);
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

.metric-card {
  padding: 16px;
}

.metric-label {
  font-size: 11px;
  font-weight: 700;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 6px;
}

.metric-value {
  font-size: 22px;
  font-weight: 800;
  color: #0f172a;
}

.report-grid-2col {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 20px;
}

.card {
  overflow: hidden;
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  border-bottom: 1px solid #e2e8f0;
  background: #ffffff;
}

.card-header h3 {
  font-size: 15px;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
}

.card-body {
  padding: 16px;
}

.donut-wrapper {
  flex-shrink: 0;
}

.donut-wrapper text {
  fill: #0f172a;
}

.legend-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex: 1;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
}

.legend-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}

.legend-label {
  color: #334155;
  flex: 1;
}

.legend-value {
  color: #0f172a;
  font-weight: 700;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12.5px;
}

.data-table th {
  padding: 10px 12px;
  text-align: left;
  font-size: 10px;
  font-weight: 800;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
}

.data-table td {
  padding: 10px 12px;
  border-bottom: 1px solid #e2e8f0;
  color: #334155;
}

.data-table td strong {
  color: #0f172a;
}

.data-table tr:last-child td {
  border-bottom: none;
}

.data-table tbody tr:hover {
  background: #f8fafc;
}

.row-danger td {
  background: #fef2f2;
}

.text-success {
  color: #16a34a !important;
}

.text-danger {
  color: #dc2626 !important;
}

.empty-text {
  color: #475569;
  text-align: center;
  padding: 32px 16px;
  margin: 0;
}

.chart-legend {
  display: flex;
  gap: 16px;
  justify-content: center;
  font-size: 11px;
  color: #334155 !important;
}

.badge {
  color: #0f172a;
}

.badge-success {
  background: #dcfce7;
  color: #166534;
}

.badge-danger {
  background: #fee2e2;
  color: #991b1b;
}

.badge-info {
  background: #dbeafe;
  color: #1e40af;
}

.badge-neutral {
  background: #f1f5f9;
  color: #334155;
}

@media (max-width: 860px) {
  .report-grid-2col {
    grid-template-columns: 1fr;
  }

  .filter-bar {
    flex-direction: column;
  }

  .filter-group,
  .filter-group .form-control {
    min-width: auto;
    width: 100%;
  }
}
</style>
