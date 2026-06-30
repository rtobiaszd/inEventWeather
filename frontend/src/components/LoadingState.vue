<template>
  <div class="loading-state">
    <div class="spinner" />
    <p>{{ message }}</p>
  </div>
</template>

<script setup>
defineProps({
  message: { type: String, default: 'Carregando...' },
})
</script>
