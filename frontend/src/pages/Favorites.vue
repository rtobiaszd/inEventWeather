<template>
  <div class="stack">
    <div class="page-header">
      <div>
        <h2>Cidades Favoritas</h2>
        <p>{{ favorites.length }} cidade(s) salva(s)</p>
      </div>
    </div>

    <!-- Add favorite -->
    <div class="card">
      <div class="card-header"><h3>Adicionar Cidade</h3></div>
      <div class="search-bar">
        <div class="search-input-group">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          <input v-model="newCity" type="text" placeholder="Nome da cidade" @keyup.enter="addFavorite" />
        </div>
        <button class="btn btn-primary" :disabled="adding" @click="addFavorite">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          {{ adding ? 'Adicionando...' : 'Adicionar' }}
        </button>
      </div>
      <div v-if="addError" class="alert alert-danger mt-8">{{ addError }}</div>
    </div>

    <LoadingState v-if="loading" message="Carregando favoritos..." />
    <ErrorMessage v-else-if="error" :message="error" @retry="load" />

    <div v-else-if="favorites.length === 0" class="empty-state">
      <span class="empty-icon">❤️</span>
      <h3>Nenhuma cidade favorita</h3>
      <p>Adicione cidades acima para acompanhar o clima rapidamente.</p>
    </div>

    <div v-else class="fav-grid">
      <FavoriteCityCard
        v-for="fav in favorites"
        :key="fav.id"
        :city="fav"
        @remove="removeFavorite"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { favoritesApi } from '../services/api.js'
import FavoriteCityCard from '../components/FavoriteCityCard.vue'
import LoadingState     from '../components/LoadingState.vue'
import ErrorMessage     from '../components/ErrorMessage.vue'

const favorites  = ref([])
const loading    = ref(false)
const error      = ref(null)
const newCity    = ref('')
const adding     = ref(false)
const addError   = ref(null)

async function load() {
  loading.value = true
  error.value   = null
  try {
    const res = await favoritesApi.list()
    favorites.value = res.data ?? []
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function addFavorite() {
  if (!newCity.value.trim()) return
  adding.value  = true
  addError.value = null
  try {
    const res = await favoritesApi.add({ city: newCity.value.trim(), country: 'BR' })
    favorites.value.push(res.data)
    newCity.value = ''
  } catch (e) {
    addError.value = e.message
  } finally {
    adding.value = false
  }
}

async function removeFavorite(fav) {
  try {
    await favoritesApi.remove(fav.id)
    favorites.value = favorites.value.filter(f => f.id !== fav.id)
  } catch (e) {
    alert(e.message)
  }
}

onMounted(load)
</script>
