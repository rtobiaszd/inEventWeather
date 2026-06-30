import { ref, computed } from 'vue'
import { eventsApi } from '../services/api.js'

const _alerts = ref([])
const _loading = ref(false)
const _meta = ref({ high_risk: 0, medium_risk: 0, total: 0 })

export function useSmartAlerts() {
  const totalAlerts = computed(() => _meta.value.high_risk + _meta.value.medium_risk)
  const highRiskCount = computed(() => _meta.value.high_risk)
  const hasHighRisk = computed(() => _meta.value.high_risk > 0)
  const hasAlerts = computed(() => totalAlerts.value > 0)

  async function loadAlerts() {
    _loading.value = true
    try {
      const res = await eventsApi.riskAlerts()
      _alerts.value = res.data ?? []
      _meta.value = res.meta ?? { high_risk: 0, medium_risk: 0, total: 0 }
    } catch {
      _alerts.value = []
      _meta.value = { high_risk: 0, medium_risk: 0, total: 0 }
    } finally {
      _loading.value = false
    }
  }

  function clearAlerts() {
    _alerts.value = []
    _meta.value = { high_risk: 0, medium_risk: 0, total: 0 }
  }

  return {
    alerts: _alerts,
    loading: _loading,
    meta: _meta,
    totalAlerts,
    highRiskCount,
    hasHighRisk,
    hasAlerts,
    loadAlerts,
    clearAlerts,
  }
}
