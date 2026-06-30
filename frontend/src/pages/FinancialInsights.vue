<template>
  <div class="stack">
    <div class="page-header">
      <div>
        <h2>Inteligência Financeira</h2>
        <p>Análise de risco climático com impacto financeiro por evento</p>
      </div>
      <button class="btn btn-ghost btn-sm" :disabled="loading" @click="load">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
        </svg>
        Atualizar
      </button>
    </div>

    <LoadingState v-if="loading" message="Calculando inteligência financeira..." />
    <ErrorMessage v-else-if="error" :message="error" @retry="load" />

    <template v-else-if="summary">
      <!-- Summary cards -->
      <div class="fi-summary-grid">
        <div class="fi-card fi-card-accent">
          <span class="fi-card-label">Eventos</span>
          <span class="fi-card-value">{{ summary.total_events }}</span>
        </div>
        <div class="fi-card">
          <span class="fi-card-label">Orçamento Total</span>
          <span class="fi-card-value fi-value-blue">{{ totalBudgetFormatted }}</span>
        </div>
        <div class="fi-card">
          <span class="fi-card-label">Receita Total</span>
          <span class="fi-card-value fi-value-green">{{ totalRevenueFormatted }}</span>
        </div>
        <div class="fi-card" :class="summary.total_profit >= 0 ? 'fi-card-positive' : 'fi-card-negative'">
          <span class="fi-card-label">Lucro / Prejuízo</span>
          <span class="fi-card-value">{{ totalProfitFormatted }}</span>
        </div>
        <div class="fi-card">
          <span class="fi-card-label">ROI Médio</span>
          <span class="fi-card-value" :style="{ color: (summary.avg_roi || 0) >= 0 ? '#16a34a' : '#ef4444' }">
            {{ summary.avg_roi || 0 }}%
          </span>
        </div>
        <div class="fi-card fi-card-warning">
          <span class="fi-card-label">Capital em Risco</span>
          <span class="fi-card-value">{{ capitalAtRiskFormatted }}</span>
          <span v-if="summary.high_risk_financial > 0" class="fi-card-sub">
            {{ summary.high_risk_financial }} evento(s) em risco alto
          </span>
        </div>
      </div>

      <!-- Budget at risk chart -->
      <div v-if="distribution" class="card">
        <div class="card-header"><h3>Distribuição do Orçamento por Risco</h3></div>
        <div class="fi-risk-chart">
          <div class="fi-risk-row">
            <span class="fi-risk-label">Baixo Risco</span>
            <div class="fi-risk-bar-wrap">
              <div class="fi-risk-bar fi-risk-bar-low" :style="{ width: riskBudgetPct('LOW_RISK') + '%' }" />
            </div>
            <span class="fi-risk-value">{{ riskBudgetPct('LOW_RISK') }}%</span>
          </div>
          <div class="fi-risk-row">
            <span class="fi-risk-label">Médio Risco</span>
            <div class="fi-risk-bar-wrap">
              <div class="fi-risk-bar fi-risk-bar-medium" :style="{ width: riskBudgetPct('MEDIUM_RISK') + '%' }" />
            </div>
            <span class="fi-risk-value">{{ riskBudgetPct('MEDIUM_RISK') }}%</span>
          </div>
          <div class="fi-risk-row">
            <span class="fi-risk-label">Alto Risco</span>
            <div class="fi-risk-bar-wrap">
              <div class="fi-risk-bar fi-risk-bar-high" :style="{ width: riskBudgetPct('HIGH_RISK') + '%' }" />
            </div>
            <span class="fi-risk-value">{{ riskBudgetPct('HIGH_RISK') }}%</span>
          </div>
        </div>
      </div>

      <!-- Filter bar -->
      <div class="fi-filter-bar">
        <div class="fi-filter-group">
          <button
            v-for="f in riskFilters"
            :key="f.key"
            :class="['btn btn-sm', activeRiskFilter === f.key ? 'btn-primary' : 'btn-ghost']"
            @click="activeRiskFilter = f.key"
          >
            {{ f.label }}
          </button>
        </div>
        <button
          v-if="activeRiskFilter !== 'all'"
          class="btn btn-sm btn-ghost"
          @click="activeRiskFilter = 'all'"
        >
          Limpar filtros
        </button>
      </div>

      <!-- Table -->
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th class="th-sort" @click="sortBy = 'event_name'; sortDir = sortDir === 'asc' ? 'desc' : 'asc'">
                Evento {{ sortIcon('event_name') }}
              </th>
              <th class="th-sort" @click="sortBy = 'budget'; sortDir = sortDir === 'asc' ? 'desc' : 'asc'">
                Orçamento {{ sortIcon('budget') }}
              </th>
              <th class="th-sort" @click="sortBy = 'revenue'; sortDir = sortDir === 'asc' ? 'desc' : 'asc'">
                Receita {{ sortIcon('revenue') }}
              </th>
              <th class="th-sort" @click="sortBy = 'profit'; sortDir = sortDir === 'asc' ? 'desc' : 'asc'">
                Lucro {{ sortIcon('profit') }}
              </th>
              <th class="th-sort" @click="sortBy = 'roi'; sortDir = sortDir === 'asc' ? 'desc' : 'asc'">
                ROI {{ sortIcon('roi') }}
              </th>
              <th class="th-sort" @click="sortBy = 'risk_score'; sortDir = sortDir === 'asc' ? 'desc' : 'asc'">
                Risco {{ sortIcon('risk_score') }}
              </th>
              <th class="th-sort" @click="sortBy = 'financial_exposure'; sortDir = sortDir === 'asc' ? 'desc' : 'asc'">
                Exposição {{ sortIcon('financial_exposure') }}
              </th>
              <th>Receita Ajust.</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="sortedEvents.length === 0">
              <td colspan="9">
                <div class="empty-state" style="padding:32px">
                  <span class="empty-icon">📊</span>
                  <h3>Nenhum evento encontrado</h3>
                  <p>Tente alterar os filtros ou crie novos eventos.</p>
                </div>
              </td>
            </tr>
            <tr
              v-for="ev in sortedEvents"
              :key="ev.event_id"
              :class="['fi-row', `fi-row-${(ev.risk_status || 'unknown').toLowerCase()}`]"
            >
              <td>
                <RouterLink :to="`/events/${ev.event_id}`" class="fi-event-link">
                  {{ ev.event_name }}
                </RouterLink>
                <div class="fi-event-meta">{{ ev.city }} • {{ ev.type }}</div>
              </td>
              <td class="td-muted">{{ formatBRL(ev.budget) }}</td>
              <td class="td-muted">{{ formatBRL(ev.revenue) }}</td>
              <td :class="ev.profit >= 0 ? 'td-profit' : 'td-loss'">{{ formatBRL(ev.profit) }}</td>
              <td :class="ev.roi >= 0 ? 'td-profit' : 'td-loss'">{{ ev.roi }}%</td>
              <td>
                <span :class="['badge', riskBadgeClass(ev.risk_status)]">
                  {{ ev.risk_score }}/100
                </span>
              </td>
              <td class="td-muted">{{ formatBRL(ev.financial_exposure) }}</td>
              <td class="td-muted">{{ formatBRL(ev.adjusted_revenue) }}</td>
              <td>
                <div class="flex-gap">
                  <RouterLink :to="`/events/${ev.event_id}/edit`" class="btn btn-ghost btn-sm" title="Editar">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                      <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                  </RouterLink>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <div v-else class="empty-state">
      <span class="empty-icon">📊</span>
      <h3>Inteligência Financeira</h3>
      <p>Cadastre eventos com dados financeiros para ver a análise de impacto climático aqui.</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useFinancialInsights } from '../composables/useFinancialInsights.js'
import LoadingState from '../components/LoadingState.vue'
import ErrorMessage from '../components/ErrorMessage.vue'

const {
  summary, loading, error, hasData,
  distribution, events,
  capitalAtRiskFormatted, totalProfitFormatted,
  totalBudgetFormatted, totalRevenueFormatted,
  loadInsights,
} = useFinancialInsights()

const sortBy = ref('financial_exposure')
const sortDir = ref('desc')
const activeRiskFilter = ref('all')

const riskFilters = [
  { key: 'all',       label: 'Todos' },
  { key: 'HIGH_RISK', label: '🔴 Alto Risco' },
  { key: 'MEDIUM_RISK', label: '🟡 Médio Risco' },
  { key: 'LOW_RISK',  label: '🟢 Baixo Risco' },
]

const filteredEvents = computed(() => {
  let list = events.value
  if (activeRiskFilter.value !== 'all') {
    list = list.filter(e => e.risk_status === activeRiskFilter.value)
  }
  return list
})

const sortedEvents = computed(() => {
  const list = [...filteredEvents.value]
  const dir = sortDir.value === 'asc' ? 1 : -1
  list.sort((a, b) => {
    const va = a[sortBy.value] ?? 0
    const vb = b[sortBy.value] ?? 0
    if (typeof va === 'string') return va.localeCompare(vb) * dir
    return (va - vb) * dir
  })
  return list
})

function formatBRL(n) {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n || 0)
}

function riskBudgetPct(level) {
  if (!distribution.value?.budget_at_risk || !summary.value?.total_budget) return 0
  const total = summary.value.total_budget
  const val = distribution.value.budget_at_risk[level] || 0
  return total > 0 ? Math.round((val / total) * 100) : 0
}

function riskBadgeClass(status) {
  if (status === 'HIGH_RISK') return 'badge-danger'
  if (status === 'MEDIUM_RISK') return 'badge-warning'
  return 'badge-success'
}

function sortIcon(field) {
  if (sortBy.value !== field) return ''
  return sortDir.value === 'asc' ? '▲' : '▼'
}

async function load() {
  await loadInsights()
}

onMounted(load)
</script>

<style scoped>
.fi-summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 12px;
}

.fi-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  box-shadow: var(--shadow-xs);
}

.fi-card-accent {
  border-left: 3px solid var(--color-primary);
}

.fi-card-positive {
  border-left: 3px solid #16a34a;
}

.fi-card-negative {
  border-left: 3px solid #ef4444;
}

.fi-card-warning {
  border-left: 3px solid #f59e0b;
}

.fi-card-label {
  font-size: 11px;
  font-weight: 600;
  color: var(--color-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.fi-card-value {
  font-size: 22px;
  font-weight: 700;
  line-height: 1;
}

.fi-card-sub {
  font-size: 11px;
  color: var(--color-text-secondary);
}

.fi-value-blue { color: #2563eb; }
.fi-value-green { color: #16a34a; }

.fi-filter-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.fi-filter-group {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
}

/* Risk chart */
.fi-risk-chart {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.fi-risk-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.fi-risk-label {
  font-size: 12px;
  color: var(--color-text-secondary);
  min-width: 90px;
}

.fi-risk-bar-wrap {
  flex: 1;
  height: 10px;
  background: var(--color-bg);
  border-radius: var(--radius-full);
  overflow: hidden;
}

.fi-risk-bar {
  height: 100%;
  border-radius: var(--radius-full);
  transition: width 0.4s ease;
}

.fi-risk-bar-low    { background: #22c55e; }
.fi-risk-bar-medium { background: #f59e0b; }
.fi-risk-bar-high   { background: #ef4444; }

.fi-risk-value {
  font-size: 12px;
  font-weight: 600;
  min-width: 36px;
  text-align: right;
}

/* Table */
.th-sort {
  cursor: pointer;
  user-select: none;
}

.th-sort:hover {
  color: var(--color-primary);
}

.fi-event-link {
  color: var(--color-primary);
  text-decoration: none;
  font-weight: 600;
  font-size: 13px;
}

.fi-event-link:hover {
  text-decoration: underline;
}

.fi-event-meta {
  font-size: 11px;
  color: var(--color-text-secondary);
  margin-top: 2px;
}

.td-profit { color: #16a34a; font-weight: 600; }
.td-loss   { color: #ef4444; font-weight: 600; }

.fi-row-high_risk {
  background: #fef2f2;
}

.fi-row-high_risk:hover {
  background: #fee2e2 !important;
}

.fi-row-medium_risk {
  background: #fffbeb;
}

.fi-row-medium_risk:hover {
  background: #fef3c7 !important;
}
</style>
