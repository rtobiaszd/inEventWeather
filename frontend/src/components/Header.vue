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
</script>
