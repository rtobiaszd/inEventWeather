import { ref, computed } from 'vue'
import { eventsApi } from '../services/api.js'

const _insights = ref(null)
const _loading = ref(false)
const _error = ref(null)

export function useFinancialInsights() {
  const summary = computed(() => _insights.value?.summary ?? null)
  const events = computed(() => _insights.value?.events ?? [])
  const distribution = computed(() => _insights.value?.distribution ?? null)

  const hasData = computed(() => _insights.value?.summary?.total_events > 0)
  const capitalAtRiskFormatted = computed(() => {
    if (!summary.value) return 'R$ 0,00'
    return formatBRL(summary.value.capital_at_risk)
  })
  const totalProfitFormatted = computed(() => {
    if (!summary.value) return 'R$ 0,00'
    return formatBRL(summary.value.total_profit)
  })
  const totalBudgetFormatted = computed(() => {
    if (!summary.value) return 'R$ 0,00'
    return formatBRL(summary.value.total_budget)
  })
  const totalRevenueFormatted = computed(() => {
    if (!summary.value) return 'R$ 0,00'
    return formatBRL(summary.value.total_revenue)
  })

  async function loadInsights() {
    _loading.value = true
    _error.value = null
    try {
      const res = await eventsApi.financialInsights()
      _insights.value = res.data
    } catch (e) {
      _error.value = e.message
      _insights.value = null
    } finally {
      _loading.value = false
    }
  }

  function clearInsights() {
    _insights.value = null
    _error.value = null
  }

  return {
    insights: _insights,
    loading: _loading,
    error: _error,
    summary,
    events,
    distribution,
    hasData,
    capitalAtRiskFormatted,
    totalProfitFormatted,
    totalBudgetFormatted,
    totalRevenueFormatted,
    loadInsights,
    clearInsights,
  }
}

function formatBRL(n) {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n || 0)
}