<template>
  <div class="stack">
    <div class="page-header">
      <div>
        <h2>Inscrições</h2>
        <p v-if="event">{{ event.name }} — {{ event.city }}</p>
      </div>
      <div class="flex-gap">
        <RouterLink :to="`/events/${route.params.id}/checkin`" class="btn btn-primary btn-sm">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          Central de Check-in
        </RouterLink>
        <RouterLink :to="`/events/${route.params.id}`" class="btn btn-ghost btn-sm">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
          Voltar
        </RouterLink>
      </div>
    </div>

    <LoadingState v-if="loading" message="Carregando..." />
    <ErrorMessage v-else-if="error" :message="error" @retry="load" />

    <template v-else-if="event">
      <RegistrationManager :event-id="event.id" />

      <!-- Public link -->
      <div class="card">
        <div class="card-header"><h3>🔗 Link Público do Evento</h3></div>
        <div class="reg-share-link">
          <input :value="publicUrl" class="form-control" readonly @focus="$event.target.select()" />
          <button class="btn btn-primary" @click="copyLink">
            {{ copied ? 'Copiado!' : 'Copiar Link' }}
          </button>
        </div>
        <p class="text-sm text-muted mt-8">
          Compartilhe este link para que convidados possam ver as informações do evento e se inscrever.
        </p>
      </div>

      <!-- Feedback Results -->
      <div class="card">
        <div class="card-header">
          <h3>📊 Feedback dos Participantes</h3>
          <button v-if="!feedbackLoaded && !feedbackLoading" class="btn btn-ghost btn-sm" @click="loadFeedback">
            Carregar
          </button>
        </div>

        <LoadingState v-if="feedbackLoading" message="Carregando feedback..." />

        <div v-else-if="feedbackError" class="card-body">
          <p class="text-sm text-muted">Erro ao carregar feedback.</p>
        </div>

        <template v-else-if="feedbackLoaded">
          <div v-if="feedbackSummary.total_responses === 0" class="card-body">
            <p class="text-sm text-muted">Nenhum feedback recebido ainda.</p>
          </div>

          <template v-else>
            <!-- NPS Score -->
            <div class="fb-summary">
              <div class="fb-metric">
                <span class="fb-metric-num">{{ feedbackSummary.nps_score ?? '—' }}</span>
                <span class="fb-metric-label">NPS</span>
              </div>
              <div class="fb-metric">
                <span class="fb-metric-num">{{ feedbackSummary.average_nps ?? '—' }}</span>
                <span class="fb-metric-label">Média</span>
              </div>
              <div class="fb-metric">
                <span class="fb-metric-num">{{ feedbackSummary.total_responses }}</span>
                <span class="fb-metric-label">Respostas</span>
              </div>
              <div class="fb-metric">
                <span class="fb-metric-num">{{ feedbackSummary.response_rate }}%</span>
                <span class="fb-metric-label">Taxa</span>
              </div>
            </div>

            <!-- Distribution Bar -->
            <div class="fb-dist">
              <div class="fb-dist-bar">
                <div
                  v-if="feedbackSummary.detractors > 0"
                  class="fb-dist-segment fb-dist-detractor"
                  :style="{ width: pct(feedbackSummary.detractors) + '%' }"
                  :title="feedbackSummary.detractors + ' detratores'"
                />
                <div
                  v-if="feedbackSummary.passives > 0"
                  class="fb-dist-segment fb-dist-passive"
                  :style="{ width: pct(feedbackSummary.passives) + '%' }"
                  :title="feedbackSummary.passives + ' passivos'"
                />
                <div
                  v-if="feedbackSummary.promoters > 0"
                  class="fb-dist-segment fb-dist-promoter"
                  :style="{ width: pct(feedbackSummary.promoters) + '%' }"
                  :title="feedbackSummary.promoters + ' promotores'"
                />
              </div>
              <div class="fb-dist-labels">
                <span>😞 Detratores (0-6): {{ feedbackSummary.detractors }}</span>
                <span>😐 Passivos (7-8): {{ feedbackSummary.passives }}</span>
                <span>😍 Promotores (9-10): {{ feedbackSummary.promoters }}</span>
              </div>
            </div>

            <!-- Latest Comments -->
            <div v-if="feedbackComments.length > 0" class="fb-comments">
              <h4 style="font-size:13px;font-weight:600;margin-bottom:8px;padding:0 16px">Comentários Recentes</h4>
              <div v-for="c in feedbackComments" :key="c.submitted_at" class="fb-comment">
                <div class="fb-comment-header">
                  <span :class="['badge', npsBadge(c.nps_score), 'badge-xs']">{{ c.nps_score }}/10</span>
                  <span class="text-sm text-muted">{{ formatTime(c.submitted_at) }}</span>
                </div>
                <p class="fb-comment-text">{{ c.comment }}</p>
              </div>
            </div>
          </template>
        </template>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { eventsApi, feedbackApi } from '../services/api.js'
import { useToast } from '../composables/useToast.js'
import RegistrationManager from '../components/RegistrationManager.vue'
import LoadingState from '../components/LoadingState.vue'
import ErrorMessage from '../components/ErrorMessage.vue'

const route = useRoute()
const { show: showToast } = useToast()

const event = ref(null)
const loading = ref(true)
const error = ref(null)
const copied = ref(false)

const feedbackLoading = ref(false)
const feedbackLoaded = ref(false)
const feedbackError = ref(false)
const feedbackSummary = ref({})
const feedbackComments = ref([])

const publicUrl = computed(() => {
  if (!route.params.id) return ''
  return `${window.location.origin}/e/${route.params.id}`
})

async function load() {
  loading.value = true
  error.value = null
  try {
    const res = await eventsApi.get(route.params.id)
    event.value = res.data
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function copyLink() {
  try {
    await navigator.clipboard.writeText(publicUrl.value)
    copied.value = true
    showToast('Link copiado!', 'success')
    setTimeout(() => { copied.value = false }, 2000)
  } catch {
    showToast('Erro ao copiar link', 'error')
  }
}

function pct(n) {
  const total = feedbackSummary.value.total_responses || 1
  return (n / total) * 100
}

function formatTime(dt) {
  if (!dt) return ''
  return new Date(dt).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}

function npsBadge(score) {
  if (score >= 9) return 'badge-success'
  if (score >= 7) return 'badge-warning'
  return 'badge-danger'
}

async function loadFeedback() {
  feedbackLoading.value = true
  feedbackError.value = false
  try {
    const res = await feedbackApi.results(route.params.id)
    feedbackSummary.value = res.data.summary
    feedbackComments.value = res.data.comments
    feedbackLoaded.value = true
  } catch {
    feedbackError.value = true
  } finally {
    feedbackLoading.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.reg-share-link {
  display: flex;
  gap: 8px;
}

.reg-share-link .form-control {
  flex: 1;
  font-size: 13px;
  font-family: monospace;
}
</style>
