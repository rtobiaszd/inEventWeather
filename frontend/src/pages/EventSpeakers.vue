<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h2>Palestrantes do Evento</h2>
        <p class="page-subtitle">{{ event?.name || 'Carregando...' }}</p>
      </div>
      <div class="flex-gap">
        <button class="btn btn-secondary btn-sm" @click="showLinkForm = true">
          Vincular Existente
        </button>
        <button class="btn btn-primary btn-sm" @click="openCreate">
          Novo Palestrante
        </button>
      </div>
    </div>

    <LoadingState v-if="loading" message="Carregando palestrantes..." />
    <ErrorMessage v-else-if="loadError" :message="loadError" @retry="load" />

    <template v-else>
      <div v-if="speakers.length === 0" class="empty-state">
        <span class="empty-icon">🎤</span>
        <p>Nenhum palestrante vinculado a este evento</p>
        <button class="btn btn-ghost btn-sm" @click="showLinkForm = true">Vincular palestrante existente</button>
      </div>

      <div v-else class="speaker-grid">
        <SpeakerCard
          v-for="speaker in speakers"
          :key="speaker.id"
          :speaker="speaker"
          :featured="speaker.pivot?.is_featured"
        >
          <template #actions>
            <button class="btn btn-ghost btn-xs" @click="toggleFeatured(speaker)" :title="speaker.pivot?.is_featured ? 'Remover destaque' : 'Destacar'">
              <svg v-if="speaker.pivot?.is_featured" width="14" height="14" viewBox="0 0 24 24" fill="#F59E0B" stroke="#F59E0B" stroke-width="1"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
              <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </button>
            <button class="btn btn-ghost btn-xs" @click="confirmUnlink(speaker)" title="Desvincular">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </template>
        </SpeakerCard>
      </div>
    </template>

    <div v-if="showLinkForm" class="modal-overlay" @click.self="showLinkForm = false">
      <div class="modal-box">
        <h3>Vincular Palestrante</h3>
        <div class="form-group">
          <label>Buscar palestrante</label>
          <input v-model="linkSearch" type="text" class="form-control" placeholder="Digite o nome..." @input="searchSpeakers" />
        </div>
        <div v-if="searchResults.length" class="search-results">
          <div
            v-for="s in searchResults"
            :key="s.id"
            class="search-result-item"
            :class="{ selected: selectedLinkId === s.id }"
            @click="selectedLinkId = s.id"
          >
            <strong>{{ s.name }}</strong>
            <span v-if="s.company"> — {{ s.company }}</span>
          </div>
        </div>
        <div class="form-actions">
          <button class="btn btn-secondary" @click="showLinkForm = false">Cancelar</button>
          <button class="btn btn-primary" :disabled="!selectedLinkId" @click="doLink">Vincular</button>
        </div>
      </div>
    </div>

    <div v-if="unlinkTarget" class="modal-overlay" @click.self="unlinkTarget = null">
      <div class="modal-box">
        <h3>Desvincular palestrante?</h3>
        <p>Remover <strong>{{ unlinkTarget.name }}</strong> deste evento?</p>
        <div class="flex-gap" style="justify-content:flex-end;margin-top:20px;">
          <button class="btn btn-secondary" @click="unlinkTarget = null">Cancelar</button>
          <button class="btn btn-danger" :disabled="unlinking" @click="doUnlink">
            {{ unlinking ? 'Removendo...' : 'Desvincular' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { speakersApi, eventsApi } from '../services/api.js'
import { useToast } from '../composables/useToast.js'
import SpeakerCard from '../components/SpeakerCard.vue'
import LoadingState from '../components/LoadingState.vue'
import ErrorMessage from '../components/ErrorMessage.vue'

const { show: showToast } = useToast()
const route = useRoute()
const eventId = Number(route.params.id)

const event = ref(null)
const speakers = ref([])
const loading = ref(true)
const loadError = ref(null)

const showLinkForm = ref(false)
const linkSearch = ref('')
const searchResults = ref([])
const selectedLinkId = ref(null)

const unlinkTarget = ref(null)
const unlinking = ref(false)

async function load() {
  loading.value = true
  loadError.value = null
  try {
    const [eventRes, speakersRes] = await Promise.all([
      eventsApi.get(eventId),
      speakersApi.list({ event_id: eventId }),
    ])
    event.value = eventRes.data
    speakers.value = speakersRes.data ?? []
  } catch (e) {
    loadError.value = e.message
  } finally {
    loading.value = false
  }
}

async function searchSpeakers() {
  if (!linkSearch.value.trim()) {
    searchResults.value = []
    return
  }
  try {
    const res = await speakersApi.list({ search: linkSearch.value })
    searchResults.value = (res.data ?? []).filter(
      s => !speakers.value.find(es => es.id === s.id)
    )
  } catch {
    searchResults.value = []
  }
}

async function doLink() {
  if (!selectedLinkId.value) return
  try {
    await speakersApi.linkToEvent(selectedLinkId.value, { event_id: eventId, is_featured: false })
    showToast('Palestrante vinculado', 'success')
    showLinkForm.value = false
    selectedLinkId.value = null
    linkSearch.value = ''
    searchResults.value = []
    await load()
  } catch (e) {
    showToast(e.message, 'error')
  }
}

function confirmUnlink(speaker) {
  unlinkTarget.value = speaker
}

async function doUnlink() {
  if (!unlinkTarget.value) return
  unlinking.value = true
  try {
    await speakersApi.unlinkFromEvent(unlinkTarget.value.id, eventId)
    showToast('Palestrante desvinculado', 'success')
    unlinkTarget.value = null
    await load()
  } catch (e) {
    showToast(e.message, 'error')
  } finally {
    unlinking.value = false
  }
}

async function toggleFeatured(speaker) {
  const isFeatured = !speaker.pivot?.is_featured
  try {
    await speakersApi.linkToEvent(speaker.id, { event_id: eventId, is_featured, sort_order: speaker.pivot?.sort_order ?? 0 })
    await load()
  } catch (e) {
    showToast(e.message, 'error')
  }
}

onMounted(load)
</script>

<style scoped>
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
}

.page-subtitle {
  font-size: 13px;
  color: var(--color-text-secondary);
  margin: 4px 0 0;
}

.speaker-grid {
  display: grid;
  gap: 12px;
}

.empty-state {
  text-align: center;
  padding: 48px 16px;
  color: var(--color-text-secondary);
}

.empty-icon {
  font-size: 36px;
  display: block;
  margin-bottom: 12px;
}

.search-results {
  margin-top: 12px;
  max-height: 200px;
  overflow-y: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}

.search-result-item {
  padding: 10px 14px;
  cursor: pointer;
  font-size: 13px;
  transition: background 0.1s;
}

.search-result-item:hover,
.search-result-item.selected {
  background: var(--color-bg);
}

.form-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  margin-top: 16px;
}
</style>
