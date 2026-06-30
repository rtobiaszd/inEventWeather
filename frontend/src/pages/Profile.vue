<template>
  <div class="stack">
    <!-- Page header -->
    <div class="page-header">
      <div>
        <h2>Meu Perfil</h2>
      </div>
      <router-link to="/dashboard" class="btn btn-ghost btn-sm">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        Voltar
      </router-link>
    </div>

    <LoadingState v-if="saving && !userData" message="Carregando perfil..." />
    <ErrorMessage v-else-if="loadError" :message="loadError" @retry="loadProfile" />

    <template v-if="userData">
      <!-- Identity Card -->
      <div class="card profile-identity">
        <div class="profile-avatar-row">
          <div class="profile-avatar" :class="`avatar-${userData.role}`">
            {{ userInitial }}
          </div>
          <div class="profile-identity-text">
            <h3>{{ userData.name }}</h3>
            <span class="text-muted">@{{ userData.username }}</span>
          </div>
          <span :class="['badge', roleBadge]">{{ roleLabel }}</span>
          <span :class="['badge', userData.is_active ? 'badge-success' : 'badge-danger']" style="margin-left:4px">
            {{ userData.is_active ? 'Ativo' : 'Inativo' }}
          </span>
        </div>
        <div class="profile-meta">
          <span>Membro desde {{ formatDate(userData.created_at) }}</span>
        </div>
      </div>

      <div class="grid-2">
        <!-- Edit Name -->
        <div class="card">
          <div class="card-header"><h3>Informações</h3></div>
          <form @submit.prevent="saveName" class="form-section">
            <div class="form-group">
              <label class="form-label">Nome completo</label>
              <input v-model="nameForm.name" type="text" class="form-control" required minlength="2" />
            </div>
            <div class="form-group">
              <label class="form-label">Usuário</label>
              <input :value="userData.username" type="text" class="form-control" disabled style="opacity:0.6" />
              <span class="form-hint">O nome de usuário não pode ser alterado</span>
            </div>
            <div v-if="nameError" class="alert alert-danger">{{ nameError }}</div>
            <div class="flex-end">
              <button type="submit" class="btn btn-primary btn-sm" :disabled="nameSaving">
                {{ nameSaving ? 'Salvando...' : 'Salvar' }}
              </button>
            </div>
          </form>
        </div>

        <!-- Change Password -->
        <div class="card">
          <div class="card-header"><h3>Alterar Senha</h3></div>
          <form @submit.prevent="savePassword" class="form-section">
            <div class="form-group">
              <label class="form-label">Nova senha</label>
              <input v-model="passForm.password" type="password" class="form-control" placeholder="mínimo 6 caracteres" minlength="6" required />
            </div>
            <div class="form-group">
              <label class="form-label">Confirmar senha</label>
              <input v-model="passForm.confirm" type="password" class="form-control" placeholder="repita a senha" required />
            </div>
            <div v-if="passError" class="alert alert-danger">{{ passError }}</div>
            <div v-if="passSuccess" class="alert alert-success">{{ passSuccess }}</div>
            <div class="flex-end">
              <button type="submit" class="btn btn-primary btn-sm" :disabled="passSaving">
                {{ passSaving ? 'Salvando...' : 'Alterar Senha' }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Permissions Card -->
      <div class="card">
        <div class="card-header">
          <h3>Minhas Permissões</h3>
          <span :class="['badge', roleBadge]" style="font-size:11px">{{ roleLabel }}</span>
        </div>
        <p class="text-sm text-muted" style="margin-bottom:16px;">
          Estas são as permissões associadas ao seu perfil. <strong v-if="userData.permissions">Permissões personalizadas estão ativas.</strong>
        </p>
        <div class="perms-grid">
          <div class="perms-header-row">
            <span>Módulo</span>
            <span>Visualizar</span>
            <span>Criar</span>
            <span>Editar</span>
            <span>Excluir</span>
          </div>
          <div v-for="mod in permissionModules" :key="mod.key" class="perms-row">
            <span class="perms-mod-name">{{ mod.label }}</span>
            <span v-for="action in ['view','create','edit','delete']" :key="action" class="perms-cell">
              <span v-if="resolvePerm(mod.key, action)" class="perms-check-yes">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
              </span>
              <span v-else class="perms-check-no">—</span>
            </span>
          </div>
        </div>

        <div v-if="!userData.permissions" class="profile-perm-note">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
          Permissões baseadas no perfil <strong>{{ roleLabel }}</strong>. Um administrador pode personalizá-las.
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { authApi } from '../services/api.js'
import { useAuth, ROLE_DEFAULTS } from '../composables/useAuth.js'
import LoadingState from '../components/LoadingState.vue'
import ErrorMessage from '../components/ErrorMessage.vue'

const { user } = useAuth()

const userData = ref(null)
const loadError = ref(null)

const nameForm = reactive({ name: '' })
const nameSaving = ref(false)
const nameError = ref(null)

const passForm = reactive({ password: '', confirm: '' })
const passSaving = ref(false)
const passError = ref(null)
const passSuccess = ref(null)

const permissionModules = [
  { key: 'events',    label: 'Eventos' },
  { key: 'weather',   label: 'Clima' },
  { key: 'favorites', label: 'Favoritos' },
  { key: 'history',   label: 'Histórico' },
  { key: 'users',     label: 'Usuários' },
]

function resolvePerm(module, action) {
  const u = userData.value
  if (!u) return false
  if (module === 'users') return u.role === 'admin' && action === 'delete'
  const perms = u.permissions ?? ROLE_DEFAULTS[u.role] ?? ROLE_DEFAULTS.viewer
  return perms[module]?.[action] === true
}

const userInitial = computed(() => {
  const name = userData.value?.name || userData.value?.username || '?'
  return name.charAt(0).toUpperCase()
})

const roleLabel = computed(() => {
  const map = { admin: 'Administrador', editor: 'Editor', viewer: 'Visualizador' }
  return map[userData.value?.role] || 'Usuário'
})

const roleBadge = computed(() => {
  const map = { admin: 'badge-purple', editor: 'badge-info', viewer: 'badge-neutral' }
  return map[userData.value?.role] || 'badge-neutral'
})

async function loadProfile() {
  loadError.value = null
  try {
    const res = await authApi.me()
    userData.value = res.data
    nameForm.name = res.data.name || ''
  } catch (e) {
    loadError.value = e.message
  }
}

async function saveName() {
  if (!nameForm.name.trim()) return
  nameSaving.value = true
  nameError.value = null
  try {
    const res = await authApi.updateProfile({ name: nameForm.name.trim() })
    userData.value = res.data
    // Sync localStorage
    const stored = JSON.parse(localStorage.getItem('ew_user') || '{}')
    stored.name = res.data.name
    localStorage.setItem('ew_user', JSON.stringify(stored))
    user.value.name = res.data.name
  } catch (e) {
    nameError.value = e.message
  } finally {
    nameSaving.value = false
  }
}

async function savePassword() {
  if (passForm.password !== passForm.confirm) {
    passError.value = 'As senhas não coincidem.'
    return
  }
  passSaving.value = true
  passError.value = null
  passSuccess.value = null
  try {
    await authApi.updateProfile({ password: passForm.password })
    passForm.password = ''
    passForm.confirm = ''
    passSuccess.value = 'Senha alterada com sucesso!'
    setTimeout(() => { passSuccess.value = null }, 3000)
  } catch (e) {
    passError.value = e.message
  } finally {
    passSaving.value = false
  }
}

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' })
}

onMounted(loadProfile)
</script>

<style scoped>
.profile-identity {
  background: linear-gradient(135deg, #1e3a5f 0%, #1a365d 100%);
  color: #fff;
  border: none;
}

.profile-avatar-row {
  display: flex;
  align-items: center;
  gap: 16px;
}

.profile-avatar {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  font-weight: 700;
  color: #fff;
  flex-shrink: 0;
}

.avatar-admin  { background: #8B5CF6; }
.avatar-editor { background: #3B82F6; }
.avatar-viewer { background: #6B7280; }

.profile-identity-text h3 {
  font-size: 18px;
  color: #fff;
  margin: 0;
}

.profile-identity-text .text-muted {
  color: rgba(255,255,255,0.6);
  font-size: 13px;
}

.profile-meta {
  margin-top: 12px;
  font-size: 12px;
  color: rgba(255,255,255,0.5);
}

.profile-meta span {
  margin-right: 16px;
}

.form-hint {
  font-size: 11px;
  color: var(--color-text-muted);
  margin-top: 4px;
  display: block;
}

.flex-end {
  display: flex;
  justify-content: flex-end;
  margin-top: 12px;
}

/* Permissions */
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

.perms-cell { justify-content: center; }

.perms-check-yes {
  color: #22C55E;
  display: flex;
  align-items: center;
}

.perms-check-no {
  color: var(--color-text-secondary);
  font-size: 12px;
}

.profile-perm-note {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 12px;
  font-size: 12px;
  color: var(--color-text-secondary);
  padding: 8px 12px;
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: var(--radius-md);
}

.badge-purple { background: rgba(139,92,246,.15); color: #A78BFA; border: 1px solid rgba(139,92,246,.3); }
</style>
