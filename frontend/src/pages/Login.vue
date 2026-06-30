<template>
  <div class="login-page">
    <div class="login-card">
      <div class="login-logo">
        <div class="login-logo-icon">⛅</div>
        <h1>Event Weather</h1>
        <p>Dashboard de Clima para Eventos</p>
      </div>

      <!-- Tabs -->
      <div class="login-tabs">
        <button class="login-tab" :class="{ active: mode === 'login' }" @click="switchMode('login')">
          Entrar
        </button>
        <button class="login-tab" :class="{ active: mode === 'register' }" @click="switchMode('register')">
          Criar conta
        </button>
      </div>

      <!-- Formulário de Login -->
      <form v-if="mode === 'login'" @submit.prevent="submitLogin" class="login-form">
        <div class="form-group">
          <label class="form-label">Usuário</label>
          <div class="search-input-group">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <input v-model="loginForm.username" type="text" class="form-control"
              placeholder="admin" autocomplete="username" required :disabled="loading" />
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Senha</label>
          <div class="search-input-group">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <input v-model="loginForm.password" type="password" class="form-control"
              placeholder="••••••••" autocomplete="current-password" required :disabled="loading" />
          </div>
        </div>

        <div v-if="error" class="alert alert-danger">{{ error }}</div>

        <button type="submit" class="btn btn-primary login-btn" :disabled="loading">
          <svg v-if="!loading" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
          <div v-else class="spinner" style="width:16px;height:16px;border-width:2px;" />
          {{ loading ? 'Entrando...' : 'Entrar' }}
        </button>

      </form>

      <!-- Formulário de Cadastro -->
      <form v-else @submit.prevent="submitRegister" class="login-form">
        <div class="form-group">
          <label class="form-label">Nome completo</label>
          <div class="search-input-group">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <input v-model="regForm.name" type="text" class="form-control"
              placeholder="Maria Silva" autocomplete="name" required :disabled="loading" />
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Usuário</label>
          <div class="search-input-group">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
            <input v-model="regForm.username" type="text" class="form-control"
              placeholder="maria_silva" autocomplete="username" required :disabled="loading"
              pattern="[a-zA-Z0-9_]+" title="Somente letras, números e _" />
          </div>
          <span class="form-hint">Apenas letras, números e _</span>
        </div>

        <div class="form-group">
          <label class="form-label">Senha</label>
          <div class="search-input-group">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <input v-model="regForm.password" type="password" class="form-control"
              placeholder="mínimo 6 caracteres" autocomplete="new-password"
              minlength="6" required :disabled="loading" />
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Confirmar senha</label>
          <div class="search-input-group">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            <input v-model="regForm.confirm" type="password" class="form-control"
              placeholder="repita a senha" autocomplete="new-password" required :disabled="loading" />
          </div>
        </div>

        <div v-if="error" class="alert alert-danger">{{ error }}</div>

        <button type="submit" class="btn btn-primary login-btn" :disabled="loading">
          <svg v-if="!loading" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
          <div v-else class="spinner" style="width:16px;height:16px;border-width:2px;" />
          {{ loading ? 'Criando conta...' : 'Criar conta' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth.js'

const router              = useRouter()
const { login, register } = useAuth()

const mode    = ref('login')
const loading = ref(false)
const error   = ref(null)

const loginForm = reactive({ username: '', password: '' })
const regForm   = reactive({ name: '', username: '', password: '', confirm: '' })

function switchMode(m) {
  mode.value  = m
  error.value = null
}

async function submitLogin() {
  loading.value = true
  error.value   = null
  try {
    await login(loginForm.username, loginForm.password)
    router.push({ name: 'dashboard' })
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function submitRegister() {
  if (regForm.password !== regForm.confirm) {
    error.value = 'As senhas não coincidem.'
    return
  }
  loading.value = true
  error.value   = null
  try {
    await register(regForm.name, regForm.username, regForm.password)
    router.push({ name: 'dashboard' })
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #0F172A 0%, #1E3A5F 50%, #1E293B 100%);
  padding: 20px;
}

.login-card {
  background: var(--color-surface);
  border-radius: var(--radius-xl);
  padding: 36px 40px 40px;
  width: 100%;
  max-width: 420px;
  box-shadow: var(--shadow-lg);
}

.login-logo {
  text-align: center;
  margin-bottom: 24px;
}

.login-logo-icon {
  width: 52px;
  height: 52px;
  background: var(--color-primary);
  border-radius: var(--radius-lg);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 26px;
  margin: 0 auto 12px;
  box-shadow: 0 4px 14px rgba(37,99,235,.35);
}

.login-logo h1 {
  font-size: 21px;
  font-weight: 700;
  color: var(--color-text);
}

.login-logo p {
  font-size: 13px;
  color: var(--color-text-secondary);
  margin-top: 3px;
}

.login-tabs {
  display: flex;
  background: var(--color-bg);
  border-radius: var(--radius-md);
  padding: 3px;
  margin-bottom: 24px;
  gap: 2px;
}

.login-tab {
  flex: 1;
  padding: 8px 0;
  border: none;
  background: none;
  border-radius: calc(var(--radius-md) - 2px);
  font-size: 13.5px;
  font-weight: 500;
  color: var(--color-text-secondary);
  cursor: pointer;
  transition: background 150ms, color 150ms;
}

.login-tab.active {
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: 0 1px 4px rgba(0,0,0,.18);
}

.login-tab:hover:not(.active) {
  color: var(--color-text);
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.form-hint {
  font-size: 11px;
  color: var(--color-text-muted);
  margin-top: 4px;
  display: block;
}

.login-btn {
  width: 100%;
  justify-content: center;
  padding: 11px;
  font-size: 14px;
  margin-top: 2px;
}

.login-hint {
  text-align: center;
  font-size: 12px;
  color: var(--color-text-muted);
  padding-top: 14px;
  border-top: 1px solid var(--color-border);
}

.login-hint code {
  background: var(--color-bg);
  border: 1px solid var(--color-border);
  border-radius: 4px;
  padding: 1px 6px;
  font-size: 12px;
  color: var(--color-primary);
  margin: 0 2px;
}
</style>
