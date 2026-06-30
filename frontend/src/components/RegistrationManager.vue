<template>
  <div class="card">
    <div class="card-header">
      <h3>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:6px"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Participantes
        <span v-if="stats.total > 0" class="text-sm text-muted" style="font-weight:400;margin-left:4px">— {{ stats.confirmed }} confirmados · {{ stats.checked_in }} check-ins</span>
      </h3>
      <button class="btn btn-primary btn-sm" @click="openAdd">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Adicionar
      </button>
    </div>

    <div v-if="stats.total > 0" class="rm-stats">
      <div class="rm-stat"><span class="rm-stat-num">{{ stats.total }}</span> Total</div>
      <div class="rm-stat"><span class="rm-stat-num">{{ stats.confirmed }}</span> Confirmados</div>
      <div class="rm-stat rm-stat-ok"><span class="rm-stat-num">{{ stats.checked_in }}</span> Check-in</div>
      <div class="rm-stat rm-stat-warn"><span class="rm-stat-num">{{ stats.waitlist }}</span> Espera</div>
      <div class="rm-stat rm-stat-muted"><span class="rm-stat-num">{{ stats.cancelled }}</span> Cancelados</div>
    </div>

    <LoadingState v-if="loading" message="Carregando participantes..." />
    <ErrorMessage v-else-if="loadError" :message="loadError" @retry="load" />

    <template v-else>
      <div v-if="showAddForm" class="rm-form">
        <h4>Adicionar Participante</h4>
        <div class="rm-form-row">
          <div class="rm-field">
            <label>Nome *</label>
            <input v-model="form.name" type="text" class="form-control" placeholder="Nome completo" @keyup.enter="submitAdd" />
          </div>
          <div class="rm-field">
            <label>Email *</label>
            <input v-model="form.email" type="email" class="form-control" placeholder="email@exemplo.com" @keyup.enter="submitAdd" />
          </div>
        </div>
        <div class="rm-form-row">
          <div class="rm-field">
            <label>Telefone</label>
            <input v-model="form.phone" type="text" class="form-control" placeholder="(11) 99999-9999" />
          </div>
          <div class="rm-field">
            <label>Empresa</label>
            <input v-model="form.company" type="text" class="form-control" placeholder="Empresa Ltda" />
          </div>
        </div>
        <div v-if="formError" class="alert alert-danger" style="margin:6px 0">{{ formError }}</div>
        <div class="flex-gap">
          <button class="btn btn-primary btn-sm" :disabled="formSaving" @click="submitAdd">
            {{ formSaving ? 'Adicionando...' : 'Adicionar' }}
          </button>
          <button class="btn btn-ghost btn-sm" @click="showAddForm = false">Cancelar</button>
        </div>
      </div>

      <div v-if="!registrations.length" class="rm-empty">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--color-text-muted)"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        <p>Nenhum participante cadastrado.</p>
        <p class="text-sm text-muted">Adicione participantes para gerenciar inscrições e fazer check-in.</p>
      </div>

      <div v-else class="rm-table-wrap">
        <table class="rm-table">
          <thead>
            <tr>
              <th>Nome</th>
              <th>Email</th>
              <th>Telefone</th>
              <th>Empresa</th>
              <th>Status</th>
              <th>Check-in</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in registrations" :key="r.id" :class="{ 'rm-row-checked': r.checked_in_at }">
              <td class="td-semibold">{{ r.name }}</td>
              <td class="td-muted">{{ r.email }}</td>
              <td class="td-muted">{{ r.phone || '—' }}</td>
              <td class="td-muted">{{ r.company || '—' }}</td>
              <td><span :class="statusBadge(r.status)">{{ statusLabel(r.status) }}</span></td>
              <td>
                <span v-if="r.checked_in_at" class="rm-checkin-done" :title="formatDateTime(r.checked_in_at)">✓ {{ formatTime(r.checked_in_at) }}</span>
                <span v-else class="rm-checkin-pending">Pendente</span>
              </td>
              <td class="rm-actions">
                <button
                  v-if="!r.checked_in_at"
                  class="btn btn-ghost btn-xs"
                  :disabled="checkingId === r.id"
                  @click="doCheckIn(r)"
                  title="Fazer check-in"
                >
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                </button>
                <button
                  v-else
                  class="btn btn-ghost btn-xs"
                  :disabled="checkingId === r.id"
                  @click="undoCheckIn(r)"
                  title="Desfazer check-in"
                >
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
                <button class="btn btn-ghost btn-xs" @click="confirmRemove(r)" title="Remover">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <div v-if="deleteTarget" class="modal-overlay" @click.self="deleteTarget = null">
      <div class="modal-box">
        <h3>Remover participante?</h3>
        <p>Tem certeza que deseja remover <strong>{{ deleteTarget.name }}</strong>?</p>
        <div class="flex-gap" style="justify-content:flex-end;margin-top:20px;">
          <button class="btn btn-secondary btn-sm" @click="deleteTarget = null">Cancelar</button>
          <button class="btn btn-danger btn-sm" :disabled="deleting" @click="doRemove">
            {{ deleting ? 'Removendo...' : 'Remover' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { registrationApi } from '../services/api.js'
import { useToast } from '../composables/useToast.js'
import LoadingState from './LoadingState.vue'
import ErrorMessage from './ErrorMessage.vue'

const props = defineProps({
  eventId: { type: [Number, String], required: true },
})

const { show: showToast } = useToast()

const registrations = ref([])
const loading = ref(true)
const loadError = ref(null)
const showAddForm = ref(false)
const form = ref({ name: '', email: '', phone: '', company: '' })
const formSaving = ref(false)
const formError = ref(null)
const checkingId = ref(null)
const deleteTarget = ref(null)
const deleting = ref(false)

const stats = computed(() => {
  const total = registrations.value.length
  let confirmed = 0, waitlist = 0, cancelled = 0, checked_in = 0
  for (const r of registrations.value) {
    if (r.status === 'confirmed') confirmed++
    else if (r.status === 'waitlist') waitlist++
    else if (r.status === 'cancelled') cancelled++
    if (r.checked_in_at) checked_in++
  }
  return { total, confirmed, waitlist, cancelled, checked_in }
})

function statusLabel(s) {
  return { registered: 'Registrado', confirmed: 'Confirmado', waitlist: 'Espera', cancelled: 'Cancelado', checked_in: 'Check-in' }[s] || s
}

function statusBadge(s) {
  if (s === 'cancelled') return 'badge badge-danger badge-xs'
  if (s === 'waitlist') return 'badge badge-warning badge-xs'
  if (s === 'confirmed') return 'badge badge-success badge-xs'
  return 'badge badge-neutral badge-xs'
}

function formatTime(dt) {
  if (!dt) return ''
  return new Date(dt).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
}

function formatDateTime(dt) {
  if (!dt) return ''
  return new Date(dt).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}

async function load() {
  loading.value = true
  loadError.value = null
  try {
    const res = await registrationApi.list(props.eventId)
    registrations.value = res.data?.registrations ?? []
  } catch (e) {
    loadError.value = e.message
  } finally {
    loading.value = false
  }
}

function openAdd() {
  showAddForm.value = true
  form.value = { name: '', email: '', phone: '', company: '' }
  formError.value = null
}

async function submitAdd() {
  if (!form.value.name?.trim() || !form.value.email?.trim()) {
    formError.value = 'Nome e email são obrigatórios.'
    return
  }
  formSaving.value = true
  formError.value = null
  try {
    const res = await registrationApi.create(props.eventId, form.value)
    registrations.value.unshift(res.data?.registration)
    showAddForm.value = false
    showToast('Participante adicionado!', 'success')
  } catch (e) {
    formError.value = e.message
  } finally {
    formSaving.value = false
  }
}

async function doCheckIn(r) {
  checkingId.value = r.id
  try {
    await registrationApi.checkIn(props.eventId, r.id)
    r.checked_in_at = new Date().toISOString()
    r.status = 'confirmed'
    showToast('Check-in realizado!', 'success')
  } catch (e) {
    showToast(e.message, 'error')
  } finally {
    checkingId.value = null
  }
}

async function undoCheckIn(r) {
  checkingId.value = r.id
  try {
    await registrationApi.undoCheckIn(props.eventId, r.id)
    r.checked_in_at = null
    showToast('Check-in desfeito.', 'info')
  } catch (e) {
    showToast(e.message, 'error')
  } finally {
    checkingId.value = null
  }
}

function confirmRemove(r) {
  deleteTarget.value = r
}

async function doRemove() {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await registrationApi.remove(props.eventId, deleteTarget.value.id)
    registrations.value = registrations.value.filter(r => r.id !== deleteTarget.value.id)
    showToast('Participante removido.', 'info')
    deleteTarget.value = null
  } catch (e) {
    showToast(e.message, 'error')
  } finally {
    deleting.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.rm-stats {
  display: flex;
  gap: 16px;
  padding: 12px 16px;
  border-bottom: 1px solid var(--color-border);
  flex-wrap: wrap;
}
.rm-stat {
  font-size: 12px;
  color: var(--color-text-secondary);
  display: flex;
  align-items: center;
  gap: 4px;
}
.rm-stat-num {
  font-size: 18px;
  font-weight: 700;
  color: var(--color-text);
}
.rm-stat-ok .rm-stat-num { color: var(--color-success); }
.rm-stat-warn .rm-stat-num { color: var(--color-warning); }
.rm-stat-muted .rm-stat-num { color: var(--color-text-muted); }

.rm-form {
  padding: 16px;
  border-bottom: 1px solid var(--color-border);
  background: var(--color-bg);
}
.rm-form h4 {
  font-size: 13px;
  font-weight: 600;
  margin-bottom: 10px;
}
.rm-form-row {
  display: flex;
  gap: 10px;
  margin-bottom: 8px;
}
.rm-field {
  flex: 1;
  min-width: 0;
}
.rm-field label {
  display: block;
  font-size: 11px;
  font-weight: 600;
  color: var(--color-text-secondary);
  margin-bottom: 3px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.rm-table-wrap {
  overflow-x: auto;
}
.rm-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.rm-table th {
  text-align: left;
  padding: 10px 12px;
  font-size: 11px;
  font-weight: 600;
  color: var(--color-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  border-bottom: 1px solid var(--color-border);
  background: var(--color-bg);
  white-space: nowrap;
}
.rm-table td {
  padding: 8px 12px;
  border-bottom: 1px solid var(--color-border);
  vertical-align: middle;
}
.rm-row-checked {
  background: #F0FDF4;
}
.rm-actions {
  white-space: nowrap;
  text-align: right;
}

.rm-checkin-done {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-success);
}
.rm-checkin-pending {
  font-size: 12px;
  color: var(--color-text-muted);
}

.rm-empty {
  text-align: center;
  padding: 40px 20px;
}
.rm-empty p {
  margin: 4px 0;
  font-size: 13px;
  color: var(--color-text-secondary);
}
</style>
