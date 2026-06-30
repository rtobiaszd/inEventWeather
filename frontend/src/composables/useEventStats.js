import { computed } from 'vue'

export function useEventStats(events) {
  const stats = computed(() => {
    const list = events.value
    const total = list.length
    const planned = list.filter(e => e.status === 'planned').length
    const confirmed = list.filter(e => e.status === 'confirmed').length
    const in_progress = list.filter(e => e.status === 'in_progress').length
    const completed = list.filter(e => e.status === 'completed').length
    const cancelled = list.filter(e => e.status === 'cancelled').length
    const outdoor = list.filter(e => e.type === 'outdoor').length
    const indoor = list.filter(e => e.type === 'indoor').length

    const revenue = list.reduce((s, e) => s + (Number(e.revenue) || 0), 0)
    const tickets = list.filter(e => Number(e.ticket_price) > 0)
    const avgTicket = tickets.length
      ? tickets.reduce((s, e) => s + Number(e.ticket_price), 0) / tickets.length
      : 0

    const participants = list.reduce((s, e) => s + (Number(e.registrations_count) || 0), 0)

    return { total, planned, confirmed, in_progress, completed, cancelled, outdoor, indoor, revenue, avgTicket, participants }
  })

  const statusBars = computed(() => {
    const total = stats.value.total || 1
    return [
      { key: 'planned',     label: '📋 Planejados',    count: stats.value.planned,     pct: (stats.value.planned / total) * 100,     color: '#94a3b8' },
      { key: 'confirmed',   label: '✅ Confirmados',   count: stats.value.confirmed,   pct: (stats.value.confirmed / total) * 100,   color: '#22c55e' },
      { key: 'in_progress', label: '▶ Em andamento',   count: stats.value.in_progress, pct: (stats.value.in_progress / total) * 100, color: '#3b82f6' },
      { key: 'completed',   label: '🏁 Realizados',    count: stats.value.completed,   pct: (stats.value.completed / total) * 100,   color: '#64748b' },
      { key: 'cancelled',   label: '❌ Cancelados',    count: stats.value.cancelled,   pct: (stats.value.cancelled / total) * 100,   color: '#ef4444' },
    ]
  })

  const ringSegments = computed(() => {
    const circumference = 314.16
    let currentAngle = 0
    return statusBars.value.filter(s => s.count > 0).map(s => {
      const angle = (s.pct / 100) * 360
      const offset = circumference - (angle / 360) * circumference
      const seg = { ...s, offset, rotate: currentAngle - 90 }
      currentAngle += angle
      return seg
    })
  })

  return { stats, statusBars, ringSegments }
}
