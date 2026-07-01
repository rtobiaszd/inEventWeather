<template>
  <div class="cic-wrapper">
    <div class="page-header">
      <div>
        <h2>Central de Check-in</h2>
        <p v-if="event">{{ event.name }} — {{ event.city }}</p>
      </div>
      <div class="flex-gap">
        <button class="btn btn-ghost btn-sm" @click="showEventInfo = !showEventInfo">
          {{ showEventInfo ? 'Esconder' : 'Info do Evento' }}
        </button>
        <RouterLink :to="`/events/${route.params.id}/registrations`" class="btn btn-ghost btn-sm">Voltar</RouterLink>
      </div>
    </div>

    <LoadingState v-if="loading" message="Carregando..." />
    <ErrorMessage v-else-if="error" :message="error" @retry="load" />

    <template v-else-if="event">
      <!-- Event Info Toggle -->
      <div v-if="showEventInfo" class="card" style="margin-bottom:16px">
        <div class="card-header"><h3>📋 {{ event.name }}</h3></div>
        <div class="card-body">
          <p>{{ formatDate(event.event_date) }} {{ event.event_time?.slice(0,5) }} — {{ event.city }}{{ event.venue ? ', ' + event.venue : '' }}</p>
          <p class="text-sm text-muted">{{ event.description }}</p>
        </div>
      </div>

      <!-- Stats Bar -->
      <div class="cic-stats">
        <div class="cic-stat">
          <span class="cic-stat-num">{{ stats.total }}</span>
          Inscritos
        </div>
        <div class="cic-stat">
          <span class="cic-stat-num cic-stat-ok">{{ stats.confirmed }}</span>
          Confirmados
        </div>
        <div class="cic-stat">
          <span class="cic-stat-num cic-stat-checkin">{{ stats.checked_in }}</span>
          Check-in
        </div>
        <div class="cic-stat">
          <span class="cic-stat-num cic-stat-pct">{{ checkinPct }}%</span>
          Taxa
        </div>
      </div>

      <!-- Check-in Form -->
      <div class="cic-checkin-card">
        <div class="cic-checkin-header">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
          <span>Fazer Check-in</span>
        </div>

        <div class="cic-token-input-row">
          <input
            v-model="tokenInput"
            type="text"
            class="form-control cic-token-input"
            placeholder="Digite ou cole o token da credencial..."
            maxlength="32"
            @keyup.enter="doTokenCheckIn"
          />
          <button class="btn btn-primary" :disabled="!tokenInput || tokenChecking" @click="doTokenCheckIn">
            {{ tokenChecking ? 'Verificando...' : 'Check-in' }}
          </button>
        </div>

        <div v-if="checkinResult" :class="['cic-result', checkinResult.success ? 'cic-result-ok' : 'cic-result-err']">
          <span v-if="checkinResult.success" class="cic-result-icon">✅</span>
          <span v-else class="cic-result-icon">❌</span>
          <div>
            <strong>{{ checkinResult.success ? 'Check-in realizado!' : 'Erro' }}</strong>
            <p>{{ checkinResult.message }}</p>
            <p v-if="checkinResult.registration?.name" class="text-sm">{{ checkinResult.registration.name }}{{ checkinResult.registration.company ? ' — ' + checkinResult.registration.company : '' }}</p>
          </div>
        </div>

        <div class="cic-hint">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
          <span>O token está no QR code da credencial do participante.</span>
        </div>
      </div>

      <!-- Recent Check-ins -->
      <div class="card">
        <div class="card-header"><h3>⏱ Check-ins Recentes</h3></div>
        <div v-if="recentCheckins.length === 0" class="cic-empty">
          <p>Nenhum check-in realizado ainda.</p>
        </div>
        <div v-else class="cic-recent-list">
          <div v-for="r in recentCheckins" :key="r.id" class="cic-recent-item">
            <div class="cic-recent-avatar">{{ r.name.charAt(0) }}</div>
            <div class="cic-recent-info">
              <strong>{{ r.name }}</strong>
              <span class="text-sm text-muted">{{ r.company || '—' }} · {{ formatTime(r.checked_in_at) }}</span>
            </div>
            <span class="badge badge-success badge-xs">✓</span>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { eventsApi, registrationApi, badgeApi } from '../services/api.js'
import { useToast } from '../composables/useToast.js'
import LoadingState from '../components/LoadingState.vue'
import ErrorMessage from '../components/ErrorMessage.vue'

const route = useRoute()
const { show: showToast } = useToast()

const event = ref(null)
const loading = ref(true)
const error = ref(null)
const showEventInfo = ref(false)
const tokenInput = ref('')
const tokenChecking = ref(false)
const checkinResult = ref(null)
const registrations = ref([])
const recentCheckins = ref([])

const stats = computed(() => {
  const list = registrations.value
  return {
    total: list.length,
    confirmed: list.filter(r => r.status === 'confirmed').length,
    checked_in: list.filter(r => r.checked_in_at).length,
  }
})

const checkinPct = computed(() => {
  const c = stats.value.confirmed
  if (c === 0) return 0
  return Math.round((stats.value.checked_in / c) * 100)
})

function formatDate(d) {
  if (!d) return '—'
  return new Date(d + 'T12:00:00').toLocaleDateString('pt-BR', { day: 'numeric', month: 'long' })
}

function formatTime(dt) {
  if (!dt) return ''
  return new Date(dt).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
}

async function doTokenCheckIn() {
  const token = tokenInput.value.trim()
  if (!token || token.length !== 32) {
    showToast('Token inválido. Deve ter 32 caracteres.', 'error')
    return
  }

  tokenChecking.value = true
  checkinResult.value = null

  try {
    const res = await badgeApi.checkInByToken(route.params.id, token)
    checkinResult.value = { success: true, ...res.data }
    recentCheckins.value.unshift(res.data.registration)
    await loadStats()
    tokenInput.value = ''
    showToast(`Check-in: ${res.data.registration.name}`, 'success')
  } catch (e) {
    checkinResult.value = { success: false, message: e.message }
    showToast(e.message, 'error')
  } finally {
    tokenChecking.value = false
  }
}

async function loadStats() {
  try {
    const res = await registrationApi.list(route.params.id)
    registrations.value = res.data?.registrations ?? []
  } catch {
    // Silent
  }
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const [eventRes, regRes] = await Promise.all([
      eventsApi.get(route.params.id),
      registrationApi.list(route.params.id),
    ])
    event.value = eventRes.data
    registrations.value = regRes.data?.registrations ?? []
    // Pre-populate recent checkins
    recentCheckins.value = registrations.value
      .filter(r => r.checked_in_at)
      .sort((a, b) => new Date(b.checked_in_at) - new Date(a.checked_in_at))
      .slice(0, 20)
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.cic-stats {
  display: flex;
  gap: 16px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}

.cic-stat {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 12px;
  color: var(--color-text-secondary);
}

.cic-stat-num {
  font-size: 28px;
  font-weight: 800;
  color: var(--color-text);
  line-height: 1;
}

.cic-stat-ok { color: var(--color-success); }
.cic-stat-checkin { color: var(--color-primary); }
.cic-stat-pct { color: var(--color-info); }

.cic-checkin-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 20px;
  margin-bottom: 20px;
}

.cic-checkin-header {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 16px;
  font-weight: 700;
  margin-bottom: 16px;
  color: var(--color-text);
}

.cic-token-input-row {
  display: flex;
  gap: 8px;
}

.cic-token-input {
  flex: 1;
  font-family: monospace;
  font-size: 14px;
  letter-spacing: 1px;
}

.cic-token-input::placeholder {
  letter-spacing: 0;
  font-family: var(--font);
}

.cic-result {
  display: flex;
  gap: 12px;
  padding: 14px 16px;
  border-radius: var(--radius-md);
  margin-top: 14px;
  align-items: flex-start;
}

.cic-result-ok {
  background: var(--color-success-light);
  border: 1px solid var(--color-success);
}

.cic-result-err {
  background: var(--color-danger-light);
  border: 1px solid var(--color-danger);
}

.cic-result-icon {
  font-size: 24px;
  flex-shrink: 0;
}

.cic-result strong {
  font-size: 14px;
  display: block;
  margin-bottom: 2px;
}

.cic-result p {
  font-size: 13px;
  color: var(--color-text-secondary);
  margin: 0;
}

.cic-hint {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--color-text-muted);
  margin-top: 12px;
}

.cic-empty {
  text-align: center;
  padding: 24px;
  color: var(--color-text-muted);
  font-size: 13px;
}

.cic-recent-list {
  display: flex;
  flex-direction: column;
}

.cic-recent-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 16px;
  border-bottom: 1px solid var(--color-border);
}

.cic-recent-item:last-child {
  border-bottom: none;
}

.cic-recent-avatar {
  width: 36px;
  height: 36px;
  border-radius: 999px;
  background: var(--color-primary);
  color: #fff;
  font-size: 14px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.cic-recent-info {
  flex: 1;
  min-width: 0;
}

.cic-recent-info strong {
  font-size: 14px;
  display: block;
}
</style>
