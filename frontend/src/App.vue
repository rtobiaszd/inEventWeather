<template>
  <RouterView v-if="isPublicPage" />
  <div v-else class="app-layout">
    <Sidebar />
    <div class="main-wrapper">
      <Header />
      <main class="main-content">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import Sidebar from './components/Sidebar.vue'
import Header from './components/Header.vue'

const route = useRoute()
const isPublicPage = computed(() => !!route.meta.public)
</script>
