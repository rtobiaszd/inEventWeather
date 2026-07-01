<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h2>Palestrantes</h2>
        <p class="page-subtitle">Gerencie todos os palestrantes do sistema</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">Novo Palestrante</button>
    </div>

    <div class="search-bar">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input v-model="search" type="text" class="search-input" placeholder="Buscar por nome, empresa, expertise..." @input="load" />
    </div>

    <LoadingState v-if="loading" message="Carregando palestrantes..." />
    <ErrorMessage v-else-if="loadError" :message="loadError" @retry="load" />

    <template v-else>
      <div v-if="speakers.length === 0" class="empty-state">
        <span class="empty-icon">🎤</span>
        <p>{{ search ? 'Nenhum palestrante encontrado para esta busca' : 'Nenhum palestrante cadastrado' }}</p>
        <button v-if="!search" class="btn btn-ghost" @click="openCreate">Adicionar primeiro palestrante</button>
      </div>

      <div v-else class="speaker-grid">
        <SpeakerCard
          v-for="speaker in speakers"
          :key="speaker.id"
          :speaker="speaker"
        >
          <template #actions>
            <button class="btn btn-ghost btn-xs" @click="openEdit(speaker)" title="Editar">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button class="btn btn-ghost btn-xs" @click="confirmRemove(speaker)" title="Excluir">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
            </button>
          </template>
        </SpeakerCard>
      </div>
    </template>

    <div v-if="showForm" class="modal-overlay" @click.self="showForm = false">
      <div class="modal-box modal-wide">
        <h3>{{ editingId ? 'Editar Palestrante' : 'Novo Palestrante' }}</h3>
        <div class="form-grid">
          <div class="form-group">
            <label>Nome *</label>
            <input v-model="form.name" type="text" class="form-control" placeholder="Nome completo" />
          </div>
          <div class="form-group">
            <label>Email</label>
            <input v-model="form.email" type="email" class="form-control" placeholder="email@exemplo.com" />
          </div>
          <div class="form-group">
            <label>Cargo</label>
            <input v-model="form.role_title" type="text" class="form-control" placeholder="Ex: CTO" />
          </div>
          <div class="form-group">
            <label>Empresa</label>
            <input v-model="form.company" type="text" class="form-control" placeholder="Ex: Google" />
          </div>
          <div class="form-group">
            <label>Foto (URL)</label>
            <input v-model="form.avatar_url" type="url" class="form-control" placeholder="https://..." />
          </div>
          <div class="form-group">
            <label>Expertise</label>
            <input v-model="form.expertise" type="text" class="form-control" placeholder="Ex: Inteligência Artificial, Produto" />
          </div>
          <div class="form-group form-group-full">
            <label>Bio</label>
            <textarea v-model="form.bio" class="form-control" rows="2" placeholder="Mini biografia"></textarea>
          </div>
          <div class="form-group">
            <label>LinkedIn</label>
            <input v-model="form.social_linkedin" type="url" class="form-control" placeholder="https://linkedin.com/in/..." />
          </div>
          <div class="form-group">
            <label>Twitter / X</label>
            <input v-model="form.social_twitter" type="url" class="form-control" placeholder="https://twitter.com/..." />
          </div>
          <div class="form-group">
            <label>Website</label>
            <input v-model="form.website" type="url" class="form-control" placeholder="https://..." />
          </div>
        </div>
        <div class="form-actions">
          <button class="btn btn-secondary" @click="cancelForm">Cancelar</button>
          <button class="btn btn-primary" :disabled="saving || !form.name" @click="save">
            {{ saving ? 'Salvando...' : (editingId ? 'Atualizar' : 'Criar') }}
          </button>
        </div>
        <p v-if="formError" class="form-error">{{ formError }}</p>
      </div>
    </div>

    <div v-if="deleteTarget" class="modal-overlay" @click.self="deleteTarget = null">
      <div class="modal-box">
        <h3>Excluir palestrante?</h3>
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
import { ref, onMounted } from 'vue'
import { speakersApi } from '../services/api.js'
import { useToast } from '../composables/useToast.js'
import SpeakerCard from '../components/SpeakerCard.vue'
import LoadingState from '../components/LoadingState.vue'
import ErrorMessage from '../components/ErrorMessage.vue'

const { show: showToast } = useToast()

const speakers = ref([])
const loading = ref(true)
const loadError = ref(null)
const search = ref('')

const showForm = ref(false)
const editingId = ref(null)
const saving = ref(false)
const formError = ref(null)
const form = ref(emptyForm())

const deleteTarget = ref(null)
const deleting = ref(false)

function emptyForm() {
  return {
    name: '', email: '', bio: '', avatar_url: '',
    company: '', role_title: '', expertise: '',
    social_linkedin: '', social_twitter: '', website: '',
  }
}

function openCreate() {
  editingId.value = null
  form.value = emptyForm()
  formError.value = null
  showForm.value = true
}

function openEdit(speaker) {
  editingId.value = speaker.id
  form.value = {
    name: speaker.name || '',
    email: speaker.email || '',
    bio: speaker.bio || '',
    avatar_url: speaker.avatar_url || '',
    company: speaker.company || '',
    role_title: speaker.role_title || '',
    expertise: speaker.expertise || '',
    social_linkedin: speaker.social_linkedin || '',
    social_twitter: speaker.social_twitter || '',
    website: speaker.website || '',
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

async function save() {
  if (!form.value.name) return
  saving.value = true
  formError.value = null
  try {
    if (editingId.value) {
      await speakersApi.update(editingId.value, form.value)
      showToast('Palestrante atualizado', 'success')
    } else {
      await speakersApi.create(form.value)
      showToast('Palestrante criado', 'success')
    }
    cancelForm()
    await load()
  } catch (e) {
    formError.value = e.message
  } finally {
    saving.value = false
  }
}

function confirmRemove(speaker) {
  deleteTarget.value = speaker
}

async function doRemove() {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await speakersApi.remove(deleteTarget.value.id)
    showToast('Palestrante excluído', 'success')
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
    const params = {}
    if (search.value) params.search = search.value
    const res = await speakersApi.list(params)
    speakers.value = res.data ?? []
  } catch (e) {
    loadError.value = e.message
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
}

.page-subtitle {
  font-size: 13px;
  color: var(--color-text-secondary);
  margin: 4px 0 0;
}

.search-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  margin-bottom: 20px;
  color: var(--color-text-secondary);
}

.search-input {
  border: none;
  background: none;
  outline: none;
  flex: 1;
  font-size: 13px;
  color: var(--color-text);
}

.speaker-grid {
  display: grid;
  gap: 12px;
}

.empty-state {
  text-align: center;
  padding: 48px 16px;
  color: var(--color-text-secondary);
}

.empty-icon {
  font-size: 36px;
  display: block;
  margin-bottom: 12px;
}

.modal-wide {
  max-width: 640px;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-top: 16px;
}

.form-group-full {
  grid-column: 1 / -1;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
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
  margin-top: 20px;
}

.form-error {
  font-size: 12px;
  color: #EF4444;
  margin-top: 8px;
}
</style>
