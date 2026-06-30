<template>
  <header class="header">
    <div class="header-title">
      <h1>{{ title }}</h1>
      <p>{{ subtitle }}</p>
    </div>
    <div class="header-actions">
      <span class="text-sm text-muted">{{ dateStr }}</span>
    </div>
  </header>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

const pages = {
  dashboard:       { title: 'Dashboard',        sub: 'Visão geral do clima e eventos' },
  weather:         { title: 'Clima',            sub: 'Pesquise o clima de qualquer cidade' },
  events:          { title: 'Eventos',          sub: 'Gerencie seus eventos' },
  'events.create': { title: 'Novo Evento',      sub: 'Cadastre um novo evento' },
  'events.edit':   { title: 'Editar Evento',    sub: 'Atualize os dados do evento' },
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
