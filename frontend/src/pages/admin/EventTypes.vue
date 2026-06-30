<template>
  <div class="stack">
    <div class="page-header">
      <div>
        <h2>Tipos de Evento</h2>
        <p>{{ types.length }} tipo(s) configurado(s)</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Novo Tipo
      </button>
    </div>

    <LoadingState v-if="loading" message="Carregando tipos..." />
    <ErrorMessage v-else-if="error" :message="error" @retry="load" />

    <div v-else class="card">
      <div v-if="types.length === 0" class="empty-state" style="padding:40px 0;">
        <span class="empty-icon">🏷</span>
        <h3>Nenhum tipo cadastrado</h3>
      </div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>Tipo</th>
            <th>Slug</th>
            <th>Descrição</th>
            <th>Status</th>
            <th style="width:100px;">Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="t in types" :key="t.id">
            <td>
              <div class="type-cell">
                <span class="type-icon">{{ t.icon }}</span>
                <span class="font-medium">{{ t.name }}</span>
                <span v-if="t.is_system" class="badge badge-neutral" style="font-size:10px;">sistema</span>
              </div>
            </td>
            <td><code class="slug-code">{{ t.slug }}</code></td>
            <td class="text-muted">{{ t.description || '—' }}</td>
            <td>
              <span :class="['badge', t.is_active ? 'badge-success' : 'badge-danger']">
                {{ t.is_active ? 'Ativo' : 'Inativo' }}
              </span>
            </td>
            <td>
              <div class="flex-gap" style="gap:6px;">
                <button class="btn btn-ghost btn-icon btn-sm" title="Editar" @click="openEdit(t)">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button
                  class="btn btn-danger btn-icon btn-sm"
                  title="Excluir"
                  :disabled="t.is_system"
                  @click="confirmDelete(t)"
                >
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Preview dos tipos ativos -->
    <div v-if="activeTypes.length" class="card">
      <div class="card-header"><h3>Preview — seletor de tipo de evento</h3></div>
      <div class="type-preview-row">
        <button
          v-for="t in [{ slug: 'all', name: 'Todos', icon: '📋' }, ...activeTypes]"
          :key="t.slug"
          :class="['btn', previewSelected === t.slug ? 'btn-primary' : 'btn-secondary']"
          @click="previewSelected = t.slug"
        >
          {{ t.icon }} {{ t.name }}
        </button>
      </div>
    </div>

    <!-- Modal: Criar/Editar -->
    <div v-if="showForm" class="modal-overlay" @click.self="showForm = false">
      <div class="modal-box">
        <h3>{{ editTarget ? 'Editar Tipo' : 'Novo Tipo de Evento' }}</h3>
        <form @submit.prevent="submitForm" class="form-section" style="margin-top:16px;">
          <div class="form-row">
            <div class="form-group" style="max-width:80px;">
              <label class="form-label">Ícone</label>
              <input v-model="form.icon" type="text" class="form-control" maxlength="4" placeholder="🎪" style="font-size:20px;text-align:center;" />
            </div>
            <div class="form-group">
              <label class="form-label">Nome <span class="required">*</span></label>
              <input v-model="form.name" type="text" class="form-control" required placeholder="Ex: Híbrido" />
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Descrição</label>
            <input v-model="form.description" type="text" class="form-control" placeholder="Descreva brevemente este tipo de evento" />
          </div>
          <div v-if="editTarget" class="form-group">
            <label class="form-label">Status</label>
            <select v-model="form.is_active" class="form-control">
              <option :value="true">Ativo</option>
              <option :value="false">Inativo</option>
            </select>
          </div>
          <div v-if="formError" class="alert alert-danger">{{ formError }}</div>
          <div class="flex-gap" style="justify-content:flex-end;margin-top:8px;">
            <button type="button" class="btn btn-secondary" @click="showForm = false">Cancelar</button>
            <button type="submit" class="btn btn-primary" :disabled="formSaving">
              {{ formSaving ? 'Salvando...' : (editTarget ? 'Salvar' : 'Criar') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal: Confirmar exclusão -->
    <div v-if="deleteTarget" class="modal-overlay" @click.self="deleteTarget = null">
      <div class="modal-box">
        <h3>Excluir tipo?</h3>
        <p>Tem certeza que deseja excluir o tipo <strong>{{ deleteTarget.name }}</strong>? Eventos com este tipo não serão afetados.</p>
        <div class="flex-gap" style="justify-content:flex-end;margin-top:20px;">
          <button class="btn btn-secondary" @click="deleteTarget = null">Cancelar</button>
          <button class="btn btn-danger" :disabled="deleting" @click="doDelete">
            {{ deleting ? 'Excluindo...' : 'Excluir' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { eventTypesApi } from '../../services/api.js'
import LoadingState from '../../components/LoadingState.vue'
import ErrorMessage from '../../components/ErrorMessage.vue'

const types   = ref([])
const loading = ref(false)
const error   = ref(null)

const previewSelected = ref('all')

const activeTypes = computed(() => types.value.filter(t => t.is_active))

const showForm   = ref(false)
const editTarget = ref(null)
const form       = reactive({ name: '', icon: '📅', description: '', is_active: true })
const formError  = ref(null)
const formSaving = ref(false)

const deleteTarget = ref(null)
const deleting     = ref(false)

async function load() {
  loading.value = true
  error.value   = null
  try {
    const res = await eventTypesApi.list()
    types.value = res.data ?? []
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editTarget.value = null
  Object.assign(form, { name: '', icon: '📅', description: '', is_active: true })
  formError.value = null
  showForm.value  = true
}

function openEdit(t) {
  editTarget.value = t
  Object.assign(form, { name: t.name, icon: t.icon, description: t.description || '', is_active: t.is_active })
  formError.value = null
  showForm.value  = true
}

async function submitForm() {
  formSaving.value = true
  formError.value  = null
  try {
    if (!editTarget.value) {
      const res = await eventTypesApi.create({ name: form.name, icon: form.icon, description: form.description })
      types.value.push(res.data)
    } else {
      const res = await eventTypesApi.update(editTarget.value.id, {
        name: form.name, icon: form.icon, description: form.description, is_active: form.is_active,
      })
      const idx = types.value.findIndex(t => t.id === editTarget.value.id)
      if (idx !== -1) types.value[idx] = res.data
    }
    showForm.value = false
  } catch (e) {
    formError.value = e.message
  } finally {
    formSaving.value = false
  }
}

function confirmDelete(t) {
  deleteTarget.value = t
}

async function doDelete() {
  deleting.value = true
  try {
    await eventTypesApi.remove(deleteTarget.value.id)
    types.value = types.value.filter(t => t.id !== deleteTarget.value.id)
    deleteTarget.value = null
  } catch (e) {
    alert(e.message)
  } finally {
    deleting.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.data-table th {
  text-align: left;
  padding: 8px 12px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .04em;
  color: var(--color-text-secondary);
  border-bottom: 1px solid var(--color-border);
}

.data-table td {
  padding: 10px 12px;
  border-bottom: 1px solid var(--color-border);
  vertical-align: middle;
}

.data-table tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover   { background: rgba(255,255,255,.02); }

.type-cell { display: flex; align-items: center; gap: 8px; }
.type-icon { font-size: 18px; }

.slug-code {
  font-family: monospace;
  font-size: 12px;
  background: rgba(255,255,255,.06);
  padding: 2px 6px;
  border-radius: 4px;
  color: var(--color-text-secondary);
}

.type-preview-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  padding: 4px 0;
}

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 200;
  padding: 16px;
}

.modal-box {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 28px;
  max-width: 440px;
  width: 100%;
  box-shadow: var(--shadow-lg);
}

.modal-box h3 { font-size: 17px; font-weight: 700; margin-bottom: 4px; }
.modal-box p  { font-size: 13.5px; color: var(--color-text-secondary); line-height: 1.6; }
</style>
