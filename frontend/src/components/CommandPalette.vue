<template>
  <Teleport to="body">
    <div v-if="open" class="cp-overlay" @click.self="close" @keydown="onKeydown">
      <div class="cp-modal" ref="modalRef">
        <div class="cp-input-wrap">
          <svg class="cp-search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input
            ref="inputRef"
            v-model="query"
            class="cp-input"
            placeholder="Buscar eventos, páginas ou comandos..."
            autocomplete="off"
            spellcheck="false"
          />
          <kbd class="cp-esc-hint">ESC</kbd>
        </div>

        <div v-if="filteredPages.length || filteredEvents.length || filteredCommands.length" class="cp-results">
          <div v-if="filteredPages.length" class="cp-group">
            <div class="cp-group-label">Páginas</div>
            <div
              v-for="(item, i) in filteredPages"
              :key="'p'+i"
              class="cp-item"
              :class="{ 'cp-item-active': activeIdx === pageOffset + i }"
              @click="go(item)"
              @mouseenter="activeIdx = pageOffset + i"
            >
              <span class="cp-item-icon" v-html="item.icon"></span>
              <span class="cp-item-label">{{ item.label }}</span>
              <span class="cp-item-path">{{ item.path }}</span>
            </div>
          </div>

          <div v-if="filteredEvents.length" class="cp-group">
            <div class="cp-group-label">Eventos</div>
            <div
              v-for="(item, i) in filteredEvents"
              :key="'e'+i"
              class="cp-item"
              :class="{ 'cp-item-active': activeIdx === eventOffset + i }"
              @click="go(item)"
              @mouseenter="activeIdx = eventOffset + i"
            >
              <span class="cp-item-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              </span>
              <span class="cp-item-label">{{ item.label }}</span>
              <span class="cp-item-path">{{ item.city }}</span>
            </div>
          </div>

          <div v-if="filteredCommands.length" class="cp-group">
            <div class="cp-group-label">Comandos</div>
            <div
              v-for="(item, i) in filteredCommands"
              :key="'c'+i"
              class="cp-item"
              :class="{ 'cp-item-active': activeIdx === cmdOffset + i }"
              @click="go(item)"
              @mouseenter="activeIdx = cmdOffset + i"
            >
              <span class="cp-item-icon" v-html="item.icon"></span>
              <span class="cp-item-label">{{ item.label }}</span>
              <span class="cp-item-path"></span>
            </div>
          </div>
        </div>

        <div v-else-if="query && query.length >= 1" class="cp-empty">
          <p>Nenhum resultado para <strong>"{{ query }}"</strong></p>
        </div>

        <div v-else class=cp-hint>
          <p>Digite para buscar eventos, páginas ou comandos<br><span class="text-xs text-muted">Use as setas para navegar e Enter para selecionar</span></p>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { eventsApi } from '../services/api.js'

let eventsCache = null

const router = useRouter()
const open = ref(false)
const query = ref('')
const activeIdx = ref(0)
const inputRef = ref(null)
const modalRef = ref(null)

const user = JSON.parse(localStorage.getItem('ew_user') || 'null')
const isAdmin = user?.role === 'admin'

const pages = [
  { label: 'Dashboard',    path: '/dashboard',     icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>' },
  { label: 'Eventos',      path: '/events',          icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>' },
  { label: 'Mapa',         path: '/events/map',       icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>' },
  { label: 'Agenda',       path: '/events/agenda',    icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>' },
  { label: 'Clima',        path: '/weather',          icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/></svg>' },
  { label: 'Favoritos',    path: '/favorites',        icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>' },
  { label: 'Histórico',    path: '/history',          icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>' },
  { label: 'Melhor Data',  path: '/events/best-dates', icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>' },
  { label: 'Intelig. Financeira', path: '/events/financial', icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>' },
  { label: 'Meu Perfil',   path: '/profile',          icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>' },
]

const adminPages = [
  { label: 'Usuários',     path: '/admin/users',      icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>' },
  { label: 'Tipos de Evento', path: '/admin/event-types', icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>' },
]

const commands = [
  { label: 'Criar Evento',  action: 'create-event', icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>' },
  { label: 'Ir para Dashboard', action: 'go-dashboard', icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>' },
]

const allPages = computed(() => isAdmin ? [...pages, ...adminPages] : pages)

const events = ref([])

const filteredPages = computed(() => {
  if (!query.value) return allPages.value
  const q = query.value.toLowerCase()
  return allPages.value.filter(p => p.label.toLowerCase().includes(q))
})

const filteredEvents = computed(() => {
  if (!query.value) return []
  const q = query.value.toLowerCase()
  return events.value.filter(e => e.name.toLowerCase().includes(q) || (e.city && e.city.toLowerCase().includes(q)))
})

const filteredCommands = computed(() => {
  if (!query.value) return []
  const q = query.value.toLowerCase()
  return commands.value.filter(c => c.label.toLowerCase().includes(q))
})

const pageOffset = computed(() => 0)
const eventOffset = computed(() => filteredPages.value.length)
const cmdOffset = computed(() => filteredPages.value.length + filteredEvents.value.length)

const totalItems = computed(() => filteredPages.value.length + filteredEvents.value.length + filteredCommands.value.length)

function clamp(v, min, max) {
  if (v < min) return max
  if (v > max) return min
  return v
}

function onKeydown(e) {
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    activeIdx.value = clamp(activeIdx.value + 1, 0, totalItems.value - 1)
    scrollIntoView()
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    activeIdx.value = clamp(activeIdx.value - 1, 0, totalItems.value - 1)
    scrollIntoView()
  } else if (e.key === 'Enter') {
    e.preventDefault()
    executeActive()
  }
}

function scrollIntoView() {
  nextTick(() => {
    const items = modalRef.value?.querySelectorAll('.cp-item')
    if (items && items[activeIdx.value]) {
      items[activeIdx.value].scrollIntoView({ block: 'nearest' })
    }
  })
}

function getItem(idx) {
  if (idx < filteredPages.value.length) {
    return { type: 'page', item: filteredPages.value[idx] }
  }
  idx -= filteredPages.value.length
  if (idx < filteredEvents.value.length) {
    return { type: 'event', item: filteredEvents.value[idx] }
  }
  idx -= filteredEvents.value.length
  return { type: 'command', item: filteredCommands.value[idx] }
}

function executeActive() {
  const result = getItem(activeIdx.value)
  if (result) go(result.item, result.type)
}

function go(item, type) {
  if (item.action === 'create-event') {
    router.push('/events/create')
  } else if (item.action === 'go-dashboard') {
    router.push('/dashboard')
  } else if (type === 'event' || item.id) {
    router.push(`/events/${item.id}`)
  } else if (item.path) {
    router.push(item.path)
  }
  close()
}

function close() {
  open.value = false
  query.value = ''
  activeIdx.value = 0
}

async function openPalette() {
  open.value = true
  activeIdx.value = 0
  await nextTick()
  inputRef.value?.focus()
  if (!eventsCache) {
    try {
      const data = await eventsApi.list()
      eventsCache = Array.isArray(data) ? data : (data.data || [])
    } catch {
      eventsCache = []
    }
  }
  events.value = eventsCache
}

function onGlobalKeydown(e) {
  if (e.key === 'k' && (e.ctrlKey || e.metaKey)) {
    e.preventDefault()
    if (open.value) { close(); return }
    openPalette()
    return
  }
  if (e.key === '/' && !open.value) {
    const tag = e.target?.tagName
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || e.target?.isContentEditable) return
    e.preventDefault()
    openPalette()
    return
  }
  if (e.key === 'Escape' && open.value) {
    close()
  }
}

onMounted(() => {
  window.addEventListener('keydown', onGlobalKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', onGlobalKeydown)
})
</script>

<style scoped>
.cp-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: rgba(15, 23, 42, .55);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding-top: 12vh;
}

.cp-modal {
  width: 560px;
  max-width: 90vw;
  max-height: 60vh;
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 25px 60px rgba(0,0,0,.25);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  animation: cp-slide 160ms ease-out;
}

@keyframes cp-slide {
  from { opacity: 0; transform: translateY(-12px) scale(.98); }
  to   { opacity: 1; transform: translateY(0) scale(1); }
}

.cp-input-wrap {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 16px;
  border-bottom: 1px solid var(--color-border);
}

.cp-search-icon {
  flex-shrink: 0;
  color: var(--color-text-muted);
}

.cp-input {
  flex: 1;
  border: none;
  outline: none;
  background: transparent;
  font-size: 15px;
  color: var(--color-text);
}

.cp-input::placeholder { color: var(--color-text-muted); }

.cp-esc-hint {
  font-size: 10px;
  font-weight: 600;
  letter-spacing: .5px;
  padding: 2px 6px;
  border-radius: 4px;
  background: var(--color-bg);
  color: var(--color-text-muted);
  border: 1px solid var(--color-border);
  flex-shrink: 0;
}

.cp-results {
  overflow-y: auto;
  padding: 6px 0;
}

.cp-group-label {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: .5px;
  text-transform: uppercase;
  color: var(--color-text-muted);
  padding: 8px 16px 4px;
}

.cp-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 16px;
  cursor: pointer;
  transition: background var(--transition);
}

.cp-item.cp-item-active { background: var(--color-primary-xlight); }

.cp-item-icon {
  flex-shrink: 0;
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-text-muted);
}

.cp-item-active .cp-item-icon { color: var(--color-primary); }

.cp-item-label {
  flex: 1;
  font-size: 14px;
  font-weight: 500;
  color: var(--color-text);
}

.cp-item-path {
  font-size: 12px;
  color: var(--color-text-muted);
  white-space: nowrap;
}

.cp-item-active .cp-item-path { color: var(--color-primary); }

.cp-empty {
  text-align: center;
  padding: 32px 16px;
  font-size: 14px;
  color: var(--color-text-secondary);
}

.cp-hint {
  text-align: center;
  padding: 32px 16px;
  font-size: 14px;
  color: var(--color-text-secondary);
  line-height: 1.6;
}
</style>
