<template>
  <div class="error-message">
    <span class="error-icon">⚠️</span>
    <h3>{{ title }}</h3>
    <p>{{ message }}</p>
    <button v-if="retry" class="btn btn-primary mt-16" @click="$emit('retry')">
      Tentar novamente
    </button>
  </div>
</template>

<script setup>
defineProps({
  title:   { type: String, default: 'Erro ao carregar' },
  message: { type: String, required: true },
  retry:   { type: Boolean, default: true },
})

defineEmits(['retry'])
</script>
