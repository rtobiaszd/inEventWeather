<template>
  <div v-if="hasAlerts" class="card smart-alerts-card">
    <div class="card-header">
      <h3>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
          <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        Alertas Inteligentes
      </h3>
      <div class="smart-alerts-badges">
        <span v-if="highRiskCount > 0" class="badge badge-danger">{{ highRiskCount }} alto risco</span>
        <span v-if="mediumRiskCount > 0" class="badge badge-warning">{{ mediumRiskCount }} risco médio</span>
        <button class="btn btn-ghost btn-sm" @click="$emit('refresh')" :disabled="loading">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
          </svg>
          Atualizar
        </button>
      </div>
    </div>

    <div class="smart-alerts-list">
      <div
        v-for="alert in alerts"
        :key="alert.event_id"
        :class="['smart-alert-item', `alert-risk-${alert.risk?.status?.toLowerCase() || 'unknown'}`]"
      >
        <div class="smart-alert-left">
          <span class="smart-alert-status-icon">{{ riskIcon(alert.risk?.status) }}</span>
          <div class="smart-alert-info">
            <RouterLink :to="`/events/${alert.event_id}`" class="smart-alert-name">
              {{ alert.event_name }}
            </RouterLink>
            <div class="smart-alert-meta">
              <span>{{ formatDate(alert.event_date) }} {{ alert.event_time?.slice(0,5) }}</span>
              <span class="smart-alert-dot">•</span>
              <span>{{ alert.city }}, {{ alert.country }}</span>
              <span v-if="alert.venue" class="smart-alert-dot">•</span>
              <span v-if="alert.venue">{{ alert.venue }}</span>
              <span class="smart-alert-dot">•</span>
              <span :class="['badge', alert.type === 'outdoor' ? 'badge-info' : 'badge-neutral', 'badge-xs']">
                {{ alert.type === 'outdoor' ? 'Outdoor' : 'Indoor' }}
              </span>
            </div>
            <div v-if="alert.risk?.alerts?.length" class="smart-alert-reasons">
              <span
                v-for="(a, i) in alert.risk.alerts.slice(0, 2)"
                :key="i"
                :class="['smart-alert-reason', `reason-${a.severity}`]"
              >
                {{ alertIcon(a.type) }} {{ a.message }}
              </span>
              <span v-if="alert.risk.alerts.length > 2" class="smart-alert-more">
                +{{ alert.risk.alerts.length - 2 }} alertas
              </span>
            </div>
          </div>
        </div>
        <div class="smart-alert-right">
          <div class="smart-alert-score">
            <span class="smart-alert-score-value" :style="{ color: riskColor(alert.risk?.status) }">
              {{ alert.risk?.score || '—' }}
            </span>
            <span class="smart-alert-score-label">risco</span>
          </div>
          <div class="smart-alert-actions">
            <RouterLink :to="`/events/${alert.event_id}/edit`" class="btn btn-sm btn-ghost" title="Editar">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </RouterLink>
          </div>
        </div>
      </div>
    </div>

    <div v-if="loading" class="smart-alerts-loading">
      <span class="spinner" style="width:20px;height:20px;border-width:2px;" />
      <span class="text-sm text-muted">Analisando eventos...</span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  alerts:        { type: Array, default: () => [] },
  loading:       { type: Boolean, default: false },
  highRiskCount: { type: Number, default: 0 },
  mediumRiskCount: { type: Number, default: 0 },
  hasAlerts:     { type: Boolean, default: false },
})

defineEmits(['refresh'])

function formatDate(d) {
  if (!d) return ''
  const [y, m, day] = d.split('-')
  return `${day}/${m}/${y}`
}

function riskIcon(status) {
  if (status === 'HIGH_RISK') return '🔴'
  if (status === 'MEDIUM_RISK') return '🟡'
  return '🟢'
}

function riskColor(status) {
  if (status === 'HIGH_RISK') return '#EF4444'
  if (status === 'MEDIUM_RISK') return '#F59E0B'
  return '#22C55E'
}

function alertIcon(type) {
  return { heat: '🌡', cold: '🧊', wind: '💨', rain: '🌧', air: '🌫', humidity: '💧' }[type] ?? '⚠️'
}
</script>

<style scoped>
.smart-alerts-card {
  border-left: 3px solid #EF4444;
}

.smart-alerts-badges {
  display: flex;
  align-items: center;
  gap: 8px;
}

.smart-alerts-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.smart-alert-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 14px;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
  transition: background 100ms, border-color 100ms;
}

.smart-alert-item:hover {
  border-color: var(--color-border-strong);
}

.alert-risk-high_risk {
  background: #FEF2F2;
  border-color: #FECACA;
}

.alert-risk-medium_risk {
  background: #FFFBEB;
  border-color: #FDE68A;
}

.smart-alert-left {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  flex: 1;
  min-width: 0;
}

.smart-alert-status-icon {
  font-size: 18px;
  line-height: 1;
  flex-shrink: 0;
  margin-top: 1px;
}

.smart-alert-info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.smart-alert-name {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text);
  text-decoration: none;
}

.smart-alert-name:hover {
  color: var(--color-primary);
}

.smart-alert-meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 4px;
  font-size: 11.5px;
  color: var(--color-text-secondary);
}

.smart-alert-dot {
  color: var(--color-text-muted);
}

.smart-alert-reasons {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: 4px;
}

.smart-alert-reason {
  font-size: 11px;
  padding: 2px 6px;
  border-radius: 4px;
  background: var(--color-bg);
  white-space: nowrap;
}

.reason-danger {
  background: #FEE2E2;
  color: #991B1B;
}

.reason-warning {
  background: #FEF3C7;
  color: #92400E;
}

.smart-alert-more {
  font-size: 11px;
  color: var(--color-text-muted);
}

.smart-alert-right {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
}

.smart-alert-score {
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 44px;
}

.smart-alert-score-value {
  font-size: 18px;
  font-weight: 700;
  line-height: 1;
}

.smart-alert-score-label {
  font-size: 9px;
  color: var(--color-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.smart-alert-actions {
  display: flex;
  gap: 4px;
}

.smart-alerts-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 24px;
}

@media (max-width: 700px) {
  .smart-alert-item {
    flex-direction: column;
    align-items: flex-start;
  }

  .smart-alert-right {
    width: 100%;
    justify-content: space-between;
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid var(--color-border);
  }
}
</style>
