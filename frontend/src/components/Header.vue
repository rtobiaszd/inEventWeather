<template>
  <header class="header">
    <div class="header-left">
      <button
        type="button"
        class="mobile-menu-toggle"
        :aria-expanded="isMenuOpen"
        aria-controls="app-sidebar"
        aria-label="Abrir menu"
        @click="$emit('toggle-menu')"
      >
        <svg v-if="!isMenuOpen" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
          <line x1="3" y1="6" x2="21" y2="6" />
          <line x1="3" y1="12" x2="21" y2="12" />
          <line x1="3" y1="18" x2="21" y2="18" />
        </svg>
        <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
          <line x1="18" y1="6" x2="6" y2="18" />
          <line x1="6" y1="6" x2="18" y2="18" />
        </svg>
      </button>
      <div class="header-title">
      <h1>{{ title }}</h1>
      <p>{{ subtitle }}</p>
      </div>
    </div>
    <div class="header-actions">
      <button class="cmd-hint" @click="openCmd" title="Abrir busca rápida (Ctrl+K)">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <kbd>Ctrl+K</kbd>
      </button>
      <span class="text-sm text-muted">{{ dateStr }}</span>
    </div>
  </header>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

defineProps({
  isMenuOpen: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['toggle-menu'])

const route = useRoute()

const pages = {
  dashboard:       { title: 'Dashboard',        sub: 'Visão geral do clima e eventos' },
  weather:         { title: 'Clima',            sub: 'Pesquise o clima de qualquer cidade' },
  events:          { title: 'Eventos',          sub: 'Gerencie seus eventos' },
  'events.create': { title: 'Novo Evento',      sub: 'Cadastre um novo evento' },
  'events.edit':   { title: 'Editar Evento',    sub: 'Atualize os dados do evento' },
  'events.map':    { title: 'Mapa de Eventos',  sub: 'Visualize todos os eventos no mapa' },
  'events.best-dates': { title: 'Melhor Data', sub: 'Encontre a data ideal para seu evento' },
  favorites:       { title: 'Favoritos',        sub: 'Suas cidades favoritas' },
  history:         { title: 'Histórico',        sub: 'Consultas climáticas anteriores' },
}

const title    = computed(() => pages[route.name]?.title ?? 'Event Weather')
const subtitle = computed(() => pages[route.name]?.sub   ?? '')

const dateStr = computed(() => {
  return new Date().toLocaleDateString('pt-BR', {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
  })
})

function openCmd() {
  window.dispatchEvent(new KeyboardEvent('keydown', { key: 'k', ctrlKey: true, metaKey: true }))
}
</script>

<style scoped>
.cmd-hint {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text-muted);
  font-size: 12px;
  transition: all var(--transition);
}
.cmd-hint:hover {
  border-color: var(--color-border-strong);
  color: var(--color-text-secondary);
  box-shadow: var(--shadow-xs);
}
.cmd-hint kbd {
  font-family: var(--font);
  font-size: 10px;
  font-weight: 600;
  letter-spacing: .3px;
  padding: 1px 4px;
  border-radius: 3px;
  background: var(--color-bg);
  border: 1px solid var(--color-border);
}
</style>
