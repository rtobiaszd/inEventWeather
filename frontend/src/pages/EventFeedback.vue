<template>
  <div class="ef-wrapper">
    <div class="ef-backdrop">
      <div class="ef-container">
        <div v-if="loading" class="ef-loading">
          <span class="spinner" style="width:32px;height:32px;border-width:3px" />
          <p>Carregando...</p>
        </div>

        <div v-else-if="error" class="ef-error">
          <span class="ef-error-icon">🔗</span>
          <h3>Link inválido</h3>
          <p>Este link de feedback não é válido ou expirou.</p>
        </div>

        <!-- Already submitted -->
        <div v-else-if="alreadySubmitted" class="ef-card ef-done">
          <span class="ef-done-icon">✅</span>
          <h2>Feedback já enviado!</h2>
          <p>Você já avaliou este evento. Agradecemos sua participação!</p>
        </div>

        <!-- Thank you -->
        <div v-else-if="submitted" class="ef-card ef-done">
          <span class="ef-done-icon">🎉</span>
          <h2>Obrigado pelo feedback!</h2>
          <p>Sua opinião é muito importante para melhorarmos nossos eventos.</p>
        </div>

        <!-- Feedback form -->
        <template v-else-if="data">
          <div class="ef-card">
            <div class="ef-header">
              <span class="ef-brand">⛅ inEvent</span>
              <span class="ef-event-name">{{ data.event.name }}</span>
            </div>

            <div class="ef-body">
              <h1 class="ef-title">Como foi sua experiência?</h1>
              <p class="ef-subtitle">Sua opinião nos ajuda a criar eventos melhores.</p>

              <!-- Registration info -->
              <div class="ef-reg-info">
                <div class="ef-reg-avatar">{{ initials }}</div>
                <div>
                  <strong>{{ data.registration.name }}</strong>
                  <span class="text-sm text-muted">{{ data.registration.company || data.registration.email }}</span>
                </div>
              </div>

              <!-- NPS Question -->
              <div class="ef-nps-section">
                <label class="ef-question">
                  Em uma escala de 0 a 10, o quanto você recomendaria este evento a um amigo ou colega?
                </label>
                <div class="ef-nps-grid">
                  <button
                    v-for="score in 11"
                    :key="score - 1"
                    :class="['ef-nps-btn', npsClass(score - 1)]"
                    :title="npsLabel(score - 1)"
                    @click="npsScore = score - 1"
                  >
                    {{ score - 1 }}
                  </button>
                </div>
                <div v-if="npsScore !== null" class="ef-nps-label">
                  {{ npsLabel(npsScore) }}
                </div>
                <div v-else class="ef-nps-hint">Clique em um número para avaliar</div>
              </div>

              <!-- Comment -->
              <div class="ef-comment-section">
                <label class="ef-question">Quer deixar um comentário? (opcional)</label>
                <textarea
                  v-model="comment"
                  class="ef-textarea"
                  rows="4"
                  placeholder="Conte-nos o que você achou do evento, palestras, organização..."
                  maxlength="2000"
                ></textarea>
                <span class="ef-char-count">{{ comment.length }}/2000</span>
              </div>

              <!-- Error -->
              <div v-if="submitError" class="alert alert-danger">{{ submitError }}</div>

              <!-- Submit -->
              <button
                class="ef-submit"
                :disabled="npsScore === null || submitting"
                @click="doSubmit"
              >
                {{ submitting ? 'Enviando...' : 'Enviar Feedback' }}
              </button>
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { feedbackApi } from '../services/api.js'

const route = useRoute()

const data = ref(null)
const loading = ref(true)
const error = ref(false)
const alreadySubmitted = ref(false)
const submitted = ref(false)
const npsScore = ref(null)
const comment = ref('')
const submitting = ref(false)
const submitError = ref(null)

const initials = computed(() => {
  if (!data.value?.registration?.name) return '?'
  const parts = data.value.registration.name.trim().split(/\s+/)
  if (parts.length >= 2) return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
  return parts[0][0].toUpperCase()
})

function npsClass(score) {
  if (score >= 9) return 'ef-nps-promoter'
  if (score >= 7) return 'ef-nps-passive'
  return 'ef-nps-detractor'
}

function npsLabel(score) {
  if (score >= 9) return '🌟 Promotor — Você amou!'
  if (score >= 7) return '👍 Passivo — Foi bom'
  return '👎 Detrator — Precisa melhorar'
}

async function doSubmit() {
  if (npsScore.value === null) return
  submitting.value = true
  submitError.value = null
  try {
    await feedbackApi.submit(route.params.id, route.params.token, {
      nps_score: npsScore.value,
      comment: comment.value,
    })
    submitted.value = true
  } catch (e) {
    submitError.value = e.message
  } finally {
    submitting.value = false
  }
}

onMounted(async () => {
  try {
    const res = await feedbackApi.form(route.params.id, route.params.token)
    data.value = res.data
    if (res.data.already_submitted) {
      alreadySubmitted.value = true
    }
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.ef-wrapper {
  min-height: 100vh;
  font-family: var(--font, 'Inter', system-ui, sans-serif);
  background: #0f172a;
  color: #e2e8f0;
}

.ef-backdrop {
  min-height: 100vh;
  padding: 40px 20px;
  background: linear-gradient(135deg, #0f172a 0%, #1a2744 50%, #0f172a 100%);
}

.ef-container {
  max-width: 520px;
  margin: 0 auto;
}

.ef-loading,
.ef-error {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 24px;
  gap: 12px;
  color: #94a3b8;
}

.ef-error-icon { font-size: 48px; }
.ef-error h3 { color: #f1f5f9; font-size: 20px; }
.ef-error p { color: #94a3b8; font-size: 14px; }

.ef-card {
  background: #ffffff;
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 20px 60px rgba(0,0,0,.4);
}

.ef-done {
  text-align: center;
  padding: 60px 24px;
}

.ef-done-icon { font-size: 56px; display: block; margin-bottom: 16px; }
.ef-done h2 { font-size: 24px; color: #0f172a; margin-bottom: 8px; }
.ef-done p { font-size: 14px; color: #64748b; }

.ef-header {
  background: #0f172a;
  padding: 14px 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.ef-brand {
  font-weight: 800;
  font-size: 14px;
  color: #f8fafc;
}

.ef-event-name {
  font-size: 12px;
  color: #94a3b8;
}

.ef-body {
  padding: 28px 24px 24px;
}

.ef-title {
  font-size: 22px;
  font-weight: 800;
  color: #0f172a;
  margin-bottom: 4px;
}

.ef-subtitle {
  font-size: 14px;
  color: #64748b;
  margin-bottom: 20px;
}

.ef-reg-info {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  background: #f8fafc;
  border-radius: 12px;
  margin-bottom: 24px;
}

.ef-reg-avatar {
  width: 40px;
  height: 40px;
  border-radius: 999px;
  background: linear-gradient(135deg, #2563eb, #7c3aed);
  color: #fff;
  font-size: 14px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.ef-reg-info strong {
  display: block;
  font-size: 14px;
  color: #0f172a;
}

.ef-nps-section {
  margin-bottom: 24px;
}

.ef-question {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: #0f172a;
  margin-bottom: 14px;
  line-height: 1.5;
}

.ef-nps-grid {
  display: flex;
  gap: 4px;
  justify-content: center;
}

.ef-nps-btn {
  width: 36px;
  height: 44px;
  border-radius: 8px;
  border: 2px solid #e2e8f0;
  background: #f8fafc;
  color: #475569;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 150ms;
  display: flex;
  align-items: center;
  justify-content: center;
}

.ef-nps-btn:hover {
  transform: scale(1.1);
  border-color: #94a3b8;
}

.ef-nps-promoter {
  background: #22c55e;
  border-color: #22c55e;
  color: #fff;
}

.ef-nps-passive {
  background: #f59e0b;
  border-color: #f59e0b;
  color: #fff;
}

.ef-nps-detractor {
  background: #ef4444;
  border-color: #ef4444;
  color: #fff;
}

.ef-nps-label {
  text-align: center;
  font-size: 14px;
  font-weight: 600;
  margin-top: 10px;
  color: #0f172a;
}

.ef-nps-hint {
  text-align: center;
  font-size: 12px;
  color: #94a3b8;
  margin-top: 10px;
}

.ef-comment-section {
  margin-bottom: 20px;
}

.ef-textarea {
  width: 100%;
  padding: 12px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  font-size: 14px;
  font-family: var(--font);
  color: #0f172a;
  background: #f8fafc;
  resize: vertical;
  outline: none;
  transition: border-color 150ms;
}

.ef-textarea:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59,130,246,.15);
}

.ef-textarea::placeholder {
  color: #94a3b8;
}

.ef-char-count {
  display: block;
  text-align: right;
  font-size: 11px;
  color: #94a3b8;
  margin-top: 4px;
}

.ef-submit {
  width: 100%;
  padding: 14px;
  background: #3b82f6;
  color: #fff;
  border: none;
  border-radius: 12px;
  font-size: 15px;
  font-weight: 700;
  cursor: pointer;
  transition: background 150ms;
}

.ef-submit:hover:not(:disabled) {
  background: #2563eb;
}

.ef-submit:disabled {
  opacity: .5;
  cursor: default;
}

@media (max-width: 480px) {
  .ef-backdrop { padding: 20px 12px; }
  .ef-body { padding: 20px 16px; }
  .ef-nps-btn { width: 30px; height: 38px; font-size: 11px; }
}
</style>
