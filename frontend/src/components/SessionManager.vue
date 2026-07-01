<template>
  <div class="card">
    <div class="card-header">
      <h3>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:6px"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Programação
      </h3>
      <button class="btn btn-primary btn-sm" @click="openCreate" v-if="!showForm">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nova Sessão
      </button>
    </div>

    <LoadingState v-if="loading" message="Carregando programação..." />
    <ErrorMessage v-else-if="loadError" :message="loadError" @retry="load" />

    <template v-else>
      <!-- Inline create/edit form -->
      <div v-if="showForm" class="session-form">
        <h4>{{ editingId ? 'Editar Sessão' : 'Nova Sessão' }}</h4>
        <div class="form-row">
          <div class="form-group" style="flex:2">
            <label>Nome *</label>
            <input v-model="form.name" type="text" class="form-control" placeholder="Título da sessão" />
          </div>
          <div class="form-group" style="flex:1">
            <label>Tipo</label>
            <select v-model="form.type" class="form-control">
              <option value="talk">Palestra</option>
              <option value="workshop">Workshop</option>
              <option value="panel">Painel</option>
              <option value="keynote">Keynote</option>
              <option value="other">Outro</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Descrição</label>
          <textarea v-model="form.description" class="form-control" rows="2" placeholder="Descrição da sessão"></textarea>
        </div>
        <div class="form-row">
          <div class="form-group" style="flex:1">
            <label>Data *</label>
            <input v-model="form.date" type="date" class="form-control" />
          </div>
          <div class="form-group" style="flex:1">
            <label>Início *</label>
            <input v-model="form.start_time" type="time" class="form-control" />
          </div>
          <div class="form-group" style="flex:1">
            <label>Término *</label>
            <input v-model="form.end_time" type="time" class="form-control" />
          </div>
        </div>
        <div class="form-row">
          <div class="form-group" style="flex:1">
            <label>Sala / Local</label>
            <input v-model="form.room" type="text" class="form-control" placeholder="Ex: Auditório A" />
          </div>
          <div class="form-group" style="flex:1">
            <label>Capacidade</label>
            <input v-model="form.capacity" type="number" class="form-control" placeholder="0" min="0" />
          </div>
          <div class="form-group" style="flex:1">
            <label>Outdoor</label>
            <select v-model="form.outdoor_suitable" class="form-control">
              <option :value="true">Sim</option>
              <option :value="false">Não</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Palestrantes</label>
          <div v-if="form.speaker_ids.length" class="selected-speakers">
            <span v-for="sid in form.speaker_ids" :key="sid" class="selected-speaker-tag">
              {{ getSpeakerName(sid) }}
              <button type="button" class="tag-remove" @click="removeSpeakerFromSession(sid)">×</button>
            </span>
          </div>
          <div class="speaker-add-row">
            <input v-model="speakerSearchQuery" type="text" class="form-control" placeholder="Buscar palestrante..." @input="searchSpeakers(speakerSearchQuery)" @focus="showSpeakerSearch = true" />
            <button v-if="showSpeakerSearch && speakerSearchResults.length" type="button" class="btn btn-ghost btn-xs" @click="showSpeakerSearch = false">Fechar</button>
          </div>
          <div v-if="showSpeakerSearch && speakerSearchResults.length" class="speaker-search-results">
            <div
              v-for="s in speakerSearchResults"
              :key="s.id"
              class="speaker-search-item"
              @click="addSpeakerToSession(s)"
            >
              <strong>{{ s.name }}</strong>
              <span v-if="s.company"> — {{ s.company }}</span>
            </div>
          </div>
          <div class="form-group" style="margin-top:8px">
            <label>Ou use nome avulso (sem cadastro)</label>
            <input v-model="form.speaker_name" type="text" class="form-control" placeholder="Nome do palestrante" />
          </div>
        </div>
        <div class="form-group">
          <label>Bio do Palestrante</label>
          <textarea v-model="form.speaker_bio" class="form-control" rows="2" placeholder="Mini bio do palestrante"></textarea>
        </div>
        <div class="form-actions">
          <button class="btn btn-secondary" @click="cancelForm">Cancelar</button>
          <button class="btn btn-primary" :disabled="saving || !form.name || !form.date || !form.start_time || !form.end_time" @click="save">
            {{ saving ? 'Salvando...' : (editingId ? 'Atualizar' : 'Criar Sessão') }}
          </button>
        </div>
        <p v-if="formError" class="form-error">{{ formError }}</p>
      </div>

      <!-- Sessions list grouped by date -->
      <div v-if="groupedSessions.length === 0" class="session-empty">
        <span class="empty-icon">📋</span>
        <p>Nenhuma sessão cadastrada</p>
        <button class="btn btn-ghost btn-sm" @click="openCreate">Adicionar primeira sessão</button>
      </div>

      <div v-else class="session-list">
        <div v-for="group in groupedSessions" :key="group.date" class="session-date-group">
          <div class="session-date-label">{{ formatDate(group.date) }}</div>
          <div
            v-for="session in group.sessions"
            :key="session.id"
            class="session-item"
          >
            <div class="session-time">{{ session.start_time?.slice(0, 5) }} – {{ session.end_time?.slice(0, 5) }}</div>
            <div class="session-body">
              <div class="session-head">
                <strong>{{ session.name }}</strong>
                <span :class="['badge', 'badge-xs', typeBadge(session.type)]">{{ typeLabel(session.type) }}</span>
                <span v-if="session.status === 'cancelled'" class="badge badge-xs badge-danger">Cancelada</span>
              </div>
              <div class="session-meta">
                <span v-if="session.speakers?.length">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                  {{ session.speakers.map(s => s.name).join(', ') }}
                </span>
                <span v-else-if="session.speaker_name">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                  {{ session.speaker_name }}
                </span>
                <span v-if="session.room">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                  {{ session.room }}
                </span>
                <span v-if="session.capacity">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                  {{ session.capacity }}
                </span>
                <span v-if="session.outdoor_suitable === false" class="badge badge-xs badge-neutral">Indoor</span>
                <span v-if="session.weather_optimized_at" class="badge badge-xs badge-info">Otimizado</span>
              </div>
              <p v-if="session.description" class="session-desc">{{ session.description }}</p>
            </div>
            <div class="session-actions">
              <button class="btn btn-ghost btn-xs" @click="openEdit(session)" title="Editar">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </button>
              <button class="btn btn-ghost btn-xs" @click="confirmRemove(session)" title="Excluir">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Delete confirmation -->
    <div v-if="deleteTarget" class="modal-overlay" @click.self="deleteTarget = null">
      <div class="modal-box">
        <h3>Excluir sessão?</h3>
        <p>Tem certeza que deseja excluir <strong>{{ deleteTarget.name }}</strong>?</p>
        <div class="flex-gap" style="justify-content:flex-end;margin-top:20px;">
          <button class="btn btn-secondary" @click="deleteTarget = null">Cancelar</button>
          <button class="btn btn-danger" :disabled="deleting" @click="doRemove">
            {{ deleting ? 'Excluindo...' : 'Excluir' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { sessionsApi, speakersApi } from '../services/api.js'
import { useToast } from '../composables/useToast.js'
import LoadingState from './LoadingState.vue'
import ErrorMessage from './ErrorMessage.vue'

const props = defineProps({
  eventId: { type: [Number, String], required: true },
})

const { show: showToast } = useToast()

const sessions = ref([])
const loading = ref(true)
const loadError = ref(null)
const availableSpeakers = ref([])
const showSpeakerSearch = ref(false)
const speakerSearchQuery = ref('')
const speakerSearchResults = ref([])

const showForm = ref(false)
const editingId = ref(null)
const saving = ref(false)
const formError = ref(null)
const form = ref(emptyForm())

const deleteTarget = ref(null)
const deleting = ref(false)

function emptyForm() {
  return {
    name: '',
    description: '',
    speaker_name: '',
    speaker_bio: '',
    date: '',
    start_time: '',
    end_time: '',
    room: '',
    type: 'talk',
    capacity: null,
    status: 'scheduled',
    speaker_ids: [],
    outdoor_suitable: true,
  }
}

async function searchSpeakers(query) {
  if (!query.trim()) {
    speakerSearchResults.value = []
    return
  }
  try {
    const res = await speakersApi.list({ search: query })
    speakerSearchResults.value = res.data ?? []
  } catch {
    speakerSearchResults.value = []
  }
}

function addSpeakerToSession(speaker) {
  if (!form.value.speaker_ids.find(id => id === speaker.id)) {
    form.value.speaker_ids.push(speaker.id)
  }
  showSpeakerSearch.value = false
  speakerSearchQuery.value = ''
  speakerSearchResults.value = []
}

function removeSpeakerFromSession(speakerId) {
  form.value.speaker_ids = form.value.speaker_ids.filter(id => id !== speakerId)
}

const groupedSessions = computed(() => {
  const map = {}
  for (const s of sessions.value) {
    const key = s.date || ''
    if (!map[key]) map[key] = { date: key, sessions: [] }
    map[key].sessions.push(s)
  }
  return Object.values(map).sort((a, b) => a.date.localeCompare(b.date))
})

function typeLabel(t) {
  return { talk: 'Palestra', workshop: 'Workshop', panel: 'Painel', keynote: 'Keynote', other: 'Outro' }[t] || t
}

function typeBadge(t) {
  return { talk: 'badge-info', workshop: 'badge-neutral', panel: 'badge-success', keynote: 'badge-warning', other: '' }[t] || ''
}

function formatDate(d) {
  if (!d) return ''
  return new Date(d + 'T12:00:00').toLocaleDateString('pt-BR', { weekday: 'short', day: 'numeric', month: 'short' })
}

function openCreate() {
  editingId.value = null
  form.value = emptyForm()
  formError.value = null
  showForm.value = true
}

function openEdit(session) {
  editingId.value = session.id
  form.value = {
    name: session.name || '',
    description: session.description || '',
    speaker_name: session.speaker_name || '',
    speaker_bio: session.speaker_bio || '',
    date: session.date || '',
    start_time: session.start_time?.slice(0, 5) || '',
    end_time: session.end_time?.slice(0, 5) || '',
    room: session.room || '',
    type: session.type || 'talk',
    capacity: session.capacity,
    status: session.status || 'scheduled',
  }
  formError.value = null
  showForm.value = true
}

function cancelForm() {
  showForm.value = false
  editingId.value = null
  form.value = emptyForm()
  formError.value = null
}

function getSpeakerName(id) {
  const found = availableSpeakers.value.find(s => s.id === id)
  return found?.name || 'Carregando...'
}

async function loadSpeakers() {
  try {
    const res = await speakersApi.list({ event_id: props.eventId })
    availableSpeakers.value = res.data ?? []
  } catch {
    availableSpeakers.value = []
  }
}

async function save() {
  if (!form.value.name || !form.value.date || !form.value.start_time || !form.value.end_time) return
  saving.value = true
  formError.value = null
  try {
    const payload = { ...form.value }
    if (editingId.value) {
      await sessionsApi.update(props.eventId, editingId.value, payload)
      showToast('Sessão atualizada', 'success')
    } else {
      await sessionsApi.create(props.eventId, payload)
      showToast('Sessão criada', 'success')
    }
    cancelForm()
    await load()
  } catch (e) {
    formError.value = e.message
  } finally {
    saving.value = false
  }
}

function confirmRemove(session) {
  deleteTarget.value = session
}

async function doRemove() {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await sessionsApi.remove(props.eventId, deleteTarget.value.id)
    showToast('Sessão excluída', 'success')
    deleteTarget.value = null
    await load()
  } catch (e) {
    showToast(e.message, 'error')
  } finally {
    deleting.value = false
  }
}

async function load() {
  loading.value = true
  loadError.value = null
  try {
    const [sessionsRes, speakersRes] = await Promise.all([
      sessionsApi.list(props.eventId),
      speakersApi.list({ event_id: props.eventId }),
    ])
    sessions.value = sessionsRes.data ?? []
    availableSpeakers.value = speakersRes.data ?? []
  } catch (e) {
    loadError.value = e.message
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.session-form {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 20px;
  margin-bottom: 20px;
  background: var(--color-bg);
}

.session-form h4 {
  font-size: 15px;
  font-weight: 700;
  margin-bottom: 16px;
}

.form-row {
  display: flex;
  gap: 12px;
  margin-bottom: 12px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-bottom: 12px;
}

.form-group label {
  font-size: 11px;
  font-weight: 600;
  color: var(--color-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.form-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  margin-top: 16px;
}

.form-error {
  font-size: 12px;
  color: #EF4444;
  margin-top: 8px;
}

.session-empty {
  text-align: center;
  padding: 32px 16px;
  color: var(--color-text-secondary);
}

.session-empty .empty-icon {
  font-size: 28px;
  display: block;
  margin-bottom: 8px;
}

.session-empty p {
  font-size: 13px;
  margin-bottom: 12px;
}

.session-date-group {
  margin-bottom: 20px;
}

.session-date-label {
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-text-secondary);
  padding: 8px 0 6px;
  border-bottom: 1px solid var(--color-border);
  margin-bottom: 8px;
}

.session-item {
  display: flex;
  gap: 12px;
  padding: 12px;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
  margin-bottom: 6px;
  align-items: flex-start;
  transition: border-color 0.15s;
}

.session-item:hover {
  border-color: var(--color-primary);
}

.session-time {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-secondary);
  white-space: nowrap;
  min-width: 100px;
  padding-top: 2px;
}

.session-body {
  flex: 1;
  min-width: 0;
}

.session-head {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 4px;
}

.session-head strong {
  font-size: 14px;
}

.session-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  font-size: 12px;
  color: var(--color-text-secondary);
  margin-bottom: 4px;
}

.session-meta span {
  display: flex;
  align-items: center;
  gap: 4px;
}

.session-desc {
  font-size: 12px;
  color: var(--color-text-secondary);
  line-height: 1.5;
  margin: 4px 0 0;
}

.session-actions {
  display: flex;
  gap: 4px;
  flex-shrink: 0;
}

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 200;
}

.modal-box {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 28px;
  max-width: 420px;
  width: 90%;
  box-shadow: var(--shadow-lg);
}

.modal-box h3 { font-size: 17px; font-weight: 700; margin-bottom: 10px; }
.modal-box p  { font-size: 13.5px; color: var(--color-text-secondary); line-height: 1.6; }

.selected-speakers {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-bottom: 6px;
}

.selected-speaker-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 8px;
  font-size: 12px;
  font-weight: 600;
  background: var(--color-bg);
  border: 1px solid var(--color-border);
  border-radius: 9999px;
}

.tag-remove {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 14px;
  color: var(--color-text-secondary);
  padding: 0 2px;
  line-height: 1;
}

.tag-remove:hover {
  color: #EF4444;
}

.speaker-add-row {
  display: flex;
  gap: 6px;
  align-items: center;
}

.speaker-search-results {
  margin-top: 4px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  max-height: 160px;
  overflow-y: auto;
}

.speaker-search-item {
  padding: 8px 12px;
  font-size: 13px;
  cursor: pointer;
  transition: background 0.1s;
}

.speaker-search-item:hover {
  background: var(--color-bg);
}

@media (max-width: 600px) {
  .form-row {
    flex-direction: column;
    gap: 0;
  }
  .session-item {
    flex-direction: column;
  }
  .session-time {
    min-width: auto;
  }
}
</style>
