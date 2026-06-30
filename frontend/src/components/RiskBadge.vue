<template>
  <div class="risk-badge-wrapper">
    <span :class="['badge', badgeClass]">{{ label }}</span>
    <div v-if="showBar" class="risk-bar mt-4" style="width:80px;">
      <div class="risk-bar-fill" :style="{ width: score + '%', background: barColor }" />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: { type: String, default: 'LOW_RISK' },
  score:  { type: Number, default: 0 },
  showBar:{ type: Boolean, default: false },
})

const config = {
  LOW_RISK:    { label: 'Baixo Risco',  cls: 'badge-success', color: '#22C55E' },
  MEDIUM_RISK: { label: 'Risco Médio',  cls: 'badge-warning', color: '#F59E0B' },
  HIGH_RISK:   { label: 'Alto Risco',   cls: 'badge-danger',  color: '#EF4444' },
}

const badgeClass = computed(() => config[props.status]?.cls ?? 'badge-neutral')
const label      = computed(() => config[props.status]?.label ?? props.status)
const barColor   = computed(() => config[props.status]?.color ?? '#94A3B8')
</script>
