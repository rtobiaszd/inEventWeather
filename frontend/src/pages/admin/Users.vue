<template>
  <div class="stack">
    <div class="page-header">
      <div>
        <h2>Usuários</h2>
        <p>{{ users.length }} usuário(s) cadastrado(s)</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Novo Usuário
      </button>
    </div>

    <LoadingState v-if="loading" message="Carregando usuários..." />
    <ErrorMessage v-else-if="error" :message="error" @retry="load" />

    <div v-else class="card">
      <div v-if="users.length === 0" class="empty-state" style="padding:40px 0;">
        <span class="empty-icon">👤</span>
        <h3>Nenhum usuário</h3>
      </div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>Usuário</th>
            <th>Login</th>
            <th>Perfil</th>
            <th>Status</th>
            <th>Criado em</th>
            <th style="width:120px;">Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="u in users" :key="u.id">
            <td>
              <div class="user-cell">
                <div class="user-avatar" :class="`avatar-${u.role}`">{{ initial(u) }}</div>
                <span>{{ u.name }}</span>
              </div>
            </td>
            <td class="text-muted">{{ u.username }}</td>
            <td><span :class="['badge', roleBadge(u.role)]">{{ roleLabel(u.role) }}</span></td>
            <td>
              <span :class="['badge', u.is_active ? 'badge-success' : 'badge-danger']">
                {{ u.is_active ? 'Ativo' : 'Inativo' }}
              </span>
            </td>
            <td class="text-muted">{{ formatDate(u.created_at) }}</td>
            <td>
              <div class="flex-gap" style="gap:6px;">
                <button class="btn btn-ghost btn-icon btn-sm" title="Editar" @click="openEdit(u)">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button class="btn btn-ghost btn-icon btn-sm" title="Permissões" @click="openPerms(u)">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </button>
                <button
                  class="btn btn-danger btn-icon btn-sm"
                  title="Excluir"
                  :disabled="u.id === currentUser?.id"
                  @click="confirmDelete(u)"
                >
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal: Criar/Editar usuário -->
    <div v-if="showForm" class="modal-overlay" @click.self="showForm = false">
      <div class="modal-box">
        <h3>{{ editTarget ? 'Editar Usuário' : 'Novo Usuário' }}</h3>
        <form @submit.prevent="submitForm" class="form-section" style="margin-top:16px;">
          <div class="form-group">
            <label class="form-label">Nome completo <span class="required">*</span></label>
            <input v-model="form.name" type="text" class="form-control" required />
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Login <span class="required">*</span></label>
              <input v-model="form.username" type="text" class="form-control" required :disabled="!!editTarget" />
            </div>
            <div class="form-group">
              <label class="form-label">
                {{ editTarget ? 'Nova senha' : 'Senha' }}
                <span v-if="!editTarget" class="required">*</span>
              </label>
              <input v-model="form.password" type="password" class="form-control" :required="!editTarget" placeholder="mínimo 6 caracteres" />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Perfil <span class="required">*</span></label>
              <select v-model="form.role" class="form-control" required>
                <option value="admin">Administrador</option>
                <option value="editor">Editor</option>
                <option value="viewer">Visualizador</option>
              </select>
            </div>
            <div v-if="editTarget" class="form-group">
              <label class="form-label">Status</label>
              <select v-model="form.is_active" class="form-control">
                <option :value="true">Ativo</option>
                <option :value="false">Inativo</option>
              </select>
            </div>
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

    <!-- Modal: Permissões -->
    <div v-if="showPerms" class="modal-overlay" @click.self="showPerms = false">
      <div class="modal-box modal-box-wide">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
          <h3>Permissões — {{ permsTarget?.name }}</h3>
          <span :class="['badge', roleBadge(permsTarget?.role)]">{{ roleLabel(permsTarget?.role) }}</span>
        </div>

        <p class="text-sm text-muted" style="margin-bottom:16px;">
          Permissões personalizadas substituem as do perfil.
          <button class="btn btn-ghost btn-sm" style="margin-left:8px;" @click="resetPerms">Resetar para padrão do perfil</button>
        </p>

        <div class="perms-grid">
          <div class="perms-header-row">
            <span>Módulo</span>
            <span>Visualizar</span>
            <span>Criar</span>
            <span>Editar</span>
            <span>Excluir/Gerenciar</span>
          </div>
          <div v-for="mod in MODULES" :key="mod.key" class="perms-row">
            <span class="perms-mod-name">{{ mod.label }}</span>
            <template v-for="action in ['view','create','edit','delete']" :key="action">
              <label v-if="mod.actions.includes(action)" class="perms-check">
                <input
                  type="checkbox"
                  v-model="permsForm[mod.key][action]"
                  :disabled="mod.key === 'users' && action !== 'delete'"
                />
              </label>
              <span v-else class="perms-na">—</span>
            </template>
          </div>
        </div>

        <div v-if="permsError" class="alert alert-danger" style="margin-top:12px;">{{ permsError }}</div>

        <div class="flex-gap" style="justify-content:flex-end;margin-top:16px;">
          <button class="btn btn-secondary" @click="showPerms = false">Cancelar</button>
          <button class="btn btn-primary" :disabled="permsSaving" @click="savePerms">
            {{ permsSaving ? 'Salvando...' : 'Salvar Permissões' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal: Confirmar exclusão -->
    <div v-if="deleteTarget" class="modal-overlay" @click.self="deleteTarget = null">
      <div class="modal-box">
        <h3>Excluir usuário?</h3>
        <p>Tem certeza que deseja excluir <strong>{{ deleteTarget.name }}</strong>? Todos os tokens de acesso serão revogados.</p>
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
import { ref, reactive, onMounted } from 'vue'
import { usersApi } from '../../services/api.js'
import { useAuth, ROLE_DEFAULTS } from '../../composables/useAuth.js'
import LoadingState from '../../components/LoadingState.vue'
import ErrorMessage from '../../components/ErrorMessage.vue'

const { user: currentUser } = useAuth()

const MODULES = [
  { key: 'events',    label: 'Eventos',    actions: ['view','create','edit','delete'] },
  { key: 'weather',   label: 'Clima',      actions: ['view'] },
  { key: 'favorites', label: 'Favoritos',  actions: ['view','delete'] },
  { key: 'history',   label: 'Histórico',  actions: ['view'] },
  { key: 'users',     label: 'Usuários',   actions: ['delete'] },
]

const users   = ref([])
const loading = ref(false)
const error   = ref(null)

// Form criar/editar
const showForm  = ref(false)
const editTarget= ref(null)
const form      = reactive({ name: '', username: '', password: '', role: 'viewer', is_active: true })
const formError = ref(null)
const formSaving= ref(false)

// Permissões
const showPerms   = ref(false)
const permsTarget = ref(null)
const permsForm   = reactive({})
const permsError  = ref(null)
const permsSaving = ref(false)

// Delete
const deleteTarget = ref(null)
const deleting     = ref(false)

async function load() {
  loading.value = true
  error.value   = null
  try {
    const res = await usersApi.list()
    users.value = res.data ?? []
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editTarget.value = null
  Object.assign(form, { name: '', username: '', password: '', role: 'viewer', is_active: true })
  formError.value = null
  showForm.value  = true
}

function openEdit(u) {
  editTarget.value = u
  Object.assign(form, { name: u.name, username: u.username, password: '', role: u.role, is_active: u.is_active })
  formError.value = null
  showForm.value  = true
}

async function submitForm() {
  formSaving.value = true
  formError.value  = null
  try {
    const payload = { name: form.name, role: form.role }
    if (!editTarget.value) {
      payload.username = form.username
      payload.password = form.password
      const res = await usersApi.create(payload)
      users.value.push(res.data)
    } else {
      if (form.password) payload.password = form.password
      payload.is_active = form.is_active
      const res = await usersApi.update(editTarget.value.id, payload)
      const idx = users.value.findIndex(u => u.id === editTarget.value.id)
      if (idx !== -1) users.value[idx] = res.data
    }
    showForm.value = false
  } catch (e) {
    formError.value = e.message
  } finally {
    formSaving.value = false
  }
}

function openPerms(u) {
  permsTarget.value = u
  permsError.value  = null
  // Preenche permsForm a partir das permissões salvas ou defaults do role
  const src = u.permissions ?? ROLE_DEFAULTS[u.role] ?? ROLE_DEFAULTS.viewer
  MODULES.forEach(mod => {
    permsForm[mod.key] = {}
    mod.actions.forEach(a => {
      permsForm[mod.key][a] = src[mod.key]?.[a] === true
    })
  })
  showPerms.value = true
}

function resetPerms() {
  const defaults = ROLE_DEFAULTS[permsTarget.value.role] ?? ROLE_DEFAULTS.viewer
  MODULES.forEach(mod => {
    mod.actions.forEach(a => {
      permsForm[mod.key][a] = defaults[mod.key]?.[a] === true
    })
  })
}

async function savePerms() {
  permsSaving.value = true
  permsError.value  = null
  try {
    const payload = {}
    MODULES.forEach(mod => {
      payload[mod.key] = {}
      mod.actions.forEach(a => {
        payload[mod.key][a] = permsForm[mod.key][a]
      })
    })
    await usersApi.updatePermissions(permsTarget.value.id, payload)
    const idx = users.value.findIndex(u => u.id === permsTarget.value.id)
    if (idx !== -1) users.value[idx].permissions = payload
    showPerms.value = false
  } catch (e) {
    permsError.value = e.message
  } finally {
    permsSaving.value = false
  }
}

function confirmDelete(u) {
  deleteTarget.value = u
}

async function doDelete() {
  deleting.value = true
  try {
    await usersApi.remove(deleteTarget.value.id)
    users.value = users.value.filter(u => u.id !== deleteTarget.value.id)
    deleteTarget.value = null
  } catch (e) {
    alert(e.message)
  } finally {
    deleting.value = false
  }
}

function initial(u) {
  return (u.name || u.username || '?').charAt(0).toUpperCase()
}

function roleLabel(role) {
  return { admin: 'Administrador', editor: 'Editor', viewer: 'Visualizador' }[role] ?? role
}

function roleBadge(role) {
  return { admin: 'badge-purple', editor: 'badge-info', viewer: 'badge-neutral' }[role] ?? 'badge-neutral'
}

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' })
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

.data-table tbody tr:hover { background: rgba(255,255,255,.02); }

.user-cell {
  display: flex;
  align-items: center;
  gap: 8px;
}

.user-avatar {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 700;
  color: white;
  flex-shrink: 0;
}

.avatar-admin  { background: #8B5CF6; }
.avatar-editor { background: #3B82F6; }
.avatar-viewer { background: #6B7280; }

.badge-purple  { background: rgba(139,92,246,.15); color: #A78BFA; border: 1px solid rgba(139,92,246,.3); }
.badge-neutral { background: rgba(107,114,128,.15); color: #9CA3AF; border: 1px solid rgba(107,114,128,.3); }

/* Permissões grid */
.perms-grid {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  overflow: hidden;
}

.perms-header-row,
.perms-row {
  display: grid;
  grid-template-columns: 140px 1fr 1fr 1fr 1fr;
  gap: 0;
}

.perms-header-row {
  background: rgba(255,255,255,.04);
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .04em;
  color: var(--color-text-secondary);
}

.perms-header-row span,
.perms-row > * {
  padding: 8px 12px;
  display: flex;
  align-items: center;
  border-bottom: 1px solid var(--color-border);
}

.perms-row:last-child > * { border-bottom: none; }

.perms-mod-name {
  font-size: 13px;
  font-weight: 500;
  background: rgba(255,255,255,.02);
  border-right: 1px solid var(--color-border);
}

.perms-check { justify-content: center; }
.perms-na { justify-content: center; color: var(--color-text-secondary); font-size: 12px; }

.modal-box-wide { max-width: 600px; }

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
  max-width: 480px;
  width: 100%;
  box-shadow: var(--shadow-lg);
  max-height: 90vh;
  overflow-y: auto;
}

.modal-box h3 { font-size: 17px; font-weight: 700; margin-bottom: 4px; }
.modal-box p  { font-size: 13.5px; color: var(--color-text-secondary); line-height: 1.6; }
</style>
