<template>
  <RouterView v-if="isPublicPage" />
  <div v-else class="app-layout">
    <Sidebar :is-open="isMobileMenuOpen" @close="closeMobileMenu" />
    <div class="main-wrapper">
      <Header :is-menu-open="isMobileMenuOpen" @toggle-menu="toggleMobileMenu" />
      <main class="main-content">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import Sidebar from './components/Sidebar.vue'
import Header from './components/Header.vue'

const route = useRoute()
const isPublicPage = computed(() => !!route.meta.public)
const isMobileMenuOpen = ref(false)

function toggleMobileMenu() {
  isMobileMenuOpen.value = !isMobileMenuOpen.value
}

function closeMobileMenu() {
  isMobileMenuOpen.value = false
}

watch(() => route.fullPath, closeMobileMenu)
</script>
