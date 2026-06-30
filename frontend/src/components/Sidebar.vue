<template>
  <nav class="sidebar">
    <div class="sidebar-logo">
      <div class="sidebar-logo-icon">⛅</div>
      <div class="sidebar-logo-text">
        <strong>Event Weather</strong>
        <span>Dashboard</span>
      </div>
    </div>

    <div class="sidebar-section">
      <p class="sidebar-section-label">Menu</p>
      <ul class="sidebar-nav">
        <li>
          <RouterLink to="/dashboard">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            Dashboard
          </RouterLink>
        </li>
        <li v-if="can('weather')">
          <RouterLink to="/weather">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>
            Clima
          </RouterLink>
        </li>
        <li v-if="can('events')">
          <RouterLink to="/events">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Eventos
          </RouterLink>
        </li>
        <li v-if="can('favorites')">
          <RouterLink to="/favorites">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            Favoritos
          </RouterLink>
        </li>
        <li v-if="can('history')">
          <RouterLink to="/history">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Histórico
          </RouterLink>
        </li>
      </ul>
    </div>

    <!-- Seção Admin -->
    <div v-if="isAdmin" class="sidebar-section">
      <p class="sidebar-section-label">Administração</p>
      <ul class="sidebar-nav">
        <li>
          <RouterLink to="/admin/users">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Usuários
          </RouterLink>
        </li>
        <li>
          <RouterLink to="/admin/event-types">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            Tipos de Evento
          </RouterLink>
        </li>
      </ul>
    </div>

    <div class="sidebar-footer">
      <div v-if="user" class="sidebar-user">
        <div class="sidebar-user-avatar" :class="`avatar-${user.role || 'viewer'}`">{{ userInitial }}</div>
        <div class="sidebar-user-info">
          <span class="sidebar-user-name">{{ user.name || user.username }}</span>
          <span class="sidebar-user-role">{{ roleLabel }}</span>
        </div>
        <button class="sidebar-logout-btn" title="Sair" @click="handleLogout">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </button>
      </div>
      <p v-else style="font-size:11px;color:#475569;text-align:center;">Event Weather v2.0</p>
    </div>
  </nav>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth.js'

const router               = useRouter()
const { user, logout, isAdmin, can } = useAuth()

const userInitial = computed(() => {
  const name = user.value?.name || user.value?.username || '?'
  return name.charAt(0).toUpperCase()
})

const roleLabel = computed(() => {
  const map = { admin: 'Administrador', editor: 'Editor', viewer: 'Visualizador' }
  return map[user.value?.role] || 'Usuário'
})

async function handleLogout() {
  await logout()
  router.push({ name: 'login' })
}
</script>

<style scoped>
.sidebar-user {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: var(--radius-md);
  background: rgba(255,255,255,.04);
}

.sidebar-user-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--color-primary);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 700;
  flex-shrink: 0;
}

.sidebar-user-avatar.avatar-admin  { background: #8B5CF6; }
.sidebar-user-avatar.avatar-editor { background: #3B82F6; }
.sidebar-user-avatar.avatar-viewer { background: #6B7280; }

.sidebar-user-info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.sidebar-user-name {
  font-size: 12.5px;
  font-weight: 600;
  color: #CBD5E1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sidebar-user-role {
  font-size: 10.5px;
  color: #475569;
}

.sidebar-logout-btn {
  background: none;
  border: none;
  color: #475569;
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
  display: flex;
  align-items: center;
  transition: color 150ms, background 150ms;
  flex-shrink: 0;
}

.sidebar-logout-btn:hover {
  color: #EF4444;
  background: rgba(239,68,68,.12);
}
</style>
