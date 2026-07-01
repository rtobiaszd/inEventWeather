<template>
  <div class="ep-wrapper">
    <div class="ep-backdrop" :style="backdropStyle">
      <div class="ep-container">
        <!-- Loading / Error -->
        <div v-if="loading" class="ep-loading">
          <span class="spinner" style="width:32px;height:32px;border-width:3px" />
          <p>Carregando evento...</p>
        </div>
        <div v-else-if="error" class="ep-error">
          <span class="empty-icon">🔍</span>
          <h3>Evento não encontrado</h3>
          <p>O evento que você procura não existe ou foi removido.</p>
        </div>

        <template v-else-if="event">
          <!-- Header -->
          <div class="ep-header">
            <div class="ep-header-content">
              <h1 class="ep-title">{{ event.name }}</h1>
              <div class="ep-meta">
                <span>📅 {{ formatDate(event.event_date) }} {{ event.event_time?.slice(0, 5) }}</span>
                <span v-if="event.end_date"> até {{ formatDate(event.end_date) }} {{ event.end_time?.slice(0, 5) }}</span>
                <span>📍 {{ event.city }}{{ event.venue ? ` — ${event.venue}` : '' }}</span>
              </div>
              <div class="ep-badges">
                <span :class="['badge', event.type === 'outdoor' ? 'badge-info' : 'badge-neutral']">
                  {{ event.type === 'outdoor' ? '🌤 Ao ar livre' : '🏛 Indoor' }}
                </span>
                <span v-if="event.expected_audience" class="badge badge-neutral">👥 {{ formatNum(event.expected_audience) }} pessoas</span>
                <span v-if="event.organizer" class="badge badge-neutral">🎫 {{ event.organizer }}</span>
              </div>
              <p v-if="event.description" class="ep-description">{{ event.description }}</p>
            </div>
          </div>

          <!-- Weather + Risk -->
          <div v-if="event.weather" class="ep-weather-card">
            <div class="ep-weather-left">
              <img v-if="event.weather.icon"
                :src="`https://openweathermap.org/img/wn/${event.weather.icon}@2x.png`"
                width="56" height="56" :alt="event.weather.weather_description"
              />
              <div>
                <div class="ep-weather-temp">{{ event.weather.temperature }}<span class="ep-weather-unit">°C</span></div>
                <div class="ep-weather-desc">{{ event.weather.weather_description }}</div>
              </div>
            </div>
            <div class="ep-weather-right" v-if="event.risk">
              <div class="ep-risk-score" :style="{ color: riskColor }">{{ event.risk.score }}/100</div>
              <div class="ep-risk-bar-track">
                <div class="ep-risk-bar-fill" :style="{ width: event.risk.score + '%', background: riskColor }" />
              </div>
              <div class="ep-risk-status" :style="{ color: riskColor }">{{ riskLabel }}</div>
            </div>
          </div>

          <!-- Forecast -->
          <div v-if="event.event_forecast" class="ep-forecast-card">
            <h3>📊 Previsão para o dia</h3>
            <div class="ep-forecast-grid">
              <div class="ep-fc-item">
                <span class="ep-fc-label">Temperatura</span>
                <span class="ep-fc-value">{{ event.event_forecast.temperature }}°C</span>
              </div>
              <div class="ep-fc-item">
                <span class="ep-fc-label">Sensação</span>
                <span class="ep-fc-value">{{ event.event_forecast.feels_like }}°C</span>
              </div>
              <div class="ep-fc-item">
                <span class="ep-fc-label">Chuva</span>
                <span class="ep-fc-value">{{ event.event_forecast.rain_probability }}%</span>
              </div>
              <div class="ep-fc-item">
                <span class="ep-fc-label">Vento</span>
                <span class="ep-fc-value">{{ event.event_forecast.wind_speed }} km/h</span>
              </div>
            </div>
          </div>

          <!-- Speakers -->
          <div v-if="event.speakers?.length" class="ep-speakers-card">
            <h3>🎤 Palestrantes</h3>
            <div class="ep-speakers-grid">
              <div
                v-for="speaker in event.speakers"
                :key="speaker.id"
                class="ep-speaker-item"
                :class="{ 'ep-speaker-featured': speaker.pivot?.is_featured }"
              >
                <div class="ep-speaker-avatar">
                  <img v-if="speaker.avatar_url" :src="speaker.avatar_url" :alt="speaker.name" />
                  <span v-else class="ep-speaker-initials">{{ speaker.name.charAt(0) }}</span>
                </div>
                <div class="ep-speaker-info">
                  <strong>{{ speaker.name }}</strong>
                  <span v-if="speaker.company || speaker.role_title">
                    {{ [speaker.role_title, speaker.company].filter(Boolean).join(' — ') }}
                  </span>
                  <span v-if="speaker.expertise" class="ep-speaker-expertise">{{ speaker.expertise }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Sessions -->
          <div v-if="event.sessions?.length" class="ep-sessions-card">
            <h3>📋 Programação</h3>
            <div class="ep-sessions-list">
              <div v-for="session in event.sessions" :key="session.id" class="ep-session-item">
                <div class="ep-session-time">{{ session.start_time?.slice(0, 5) }}{{ session.end_time ? ' — ' + session.end_time.slice(0, 5) : '' }}</div>
                <div class="ep-session-info">
                  <strong>{{ session.title }}</strong>
                  <span v-if="session.speaker_name" class="ep-session-speaker">{{ session.speaker_name }}</span>
                  <span v-if="session.room" class="ep-session-room">{{ session.room }}</span>
                  <span v-if="session.outdoor_suitable" class="badge badge-info" style="font-size:11px">🌤 Área externa</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Registration form -->
          <div class="ep-register-card">
            <h3>📝 Inscreva-se</h3>
            <p v-if="registrationCount !== null" class="ep-register-count">
              {{ registrationCount }} pessoa(s) já inscrita(s)
            </p>

            <div v-if="registered" class="ep-registered-success">
              <span class="ep-success-icon">✅</span>
              <h4>Inscrição confirmada!</h4>
              <p>Você receberá as informações do evento em breve.</p>
            </div>

            <form v-else @submit.prevent="submitRegistration" class="ep-register-form">
              <div class="ep-form-row">
                <div class="ep-form-group">
                  <label>Nome <span class="required">*</span></label>
                  <input v-model="regForm.name" type="text" class="form-control" placeholder="Seu nome completo" required />
                </div>
                <div class="ep-form-group">
                  <label>Email <span class="required">*</span></label>
                  <input v-model="regForm.email" type="email" class="form-control" placeholder="seu@email.com" required />
                </div>
              </div>
              <div class="ep-form-row">
                <div class="ep-form-group">
                  <label>Telefone</label>
                  <input v-model="regForm.phone" type="tel" class="form-control" placeholder="(11) 99999-9999" />
                </div>
                <div class="ep-form-group">
                  <label>Empresa</label>
                  <input v-model="regForm.company" type="text" class="form-control" placeholder="Sua empresa" />
                </div>
              </div>
              <div v-if="regError" class="alert alert-danger">{{ regError }}</div>
              <button type="submit" class="btn btn-primary ep-submit" :disabled="regSubmitting">
                {{ regSubmitting ? 'Enviando...' : 'Confirmar Inscrição' }}
              </button>
            </form>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { publicApi } from '../services/api.js'

const route = useRoute()

const event = ref(null)
const loading = ref(true)
const error = ref(false)

const registrationCount = ref(null)
const registered = ref(false)
const regSubmitting = ref(false)
const regError = ref(null)
const regForm = ref({ name: '', email: '', phone: '', company: '' })

const riskColor = computed(() => {
  const s = event.value?.risk?.status
  if (s === 'HIGH_RISK') return '#EF4444'
  if (s === 'MEDIUM_RISK') return '#F59E0B'
  return '#22C55E'
})

const riskLabel = computed(() => {
  const s = event.value?.risk?.status
  if (s === 'HIGH_RISK') return 'Alto Risco ⚠️'
  if (s === 'MEDIUM_RISK') return 'Risco Moderado'
  return 'Condições Favoráveis ✅'
})

const backdropStyle = computed(() => {
  const s = event.value?.risk?.status
  if (s === 'HIGH_RISK') return { background: 'linear-gradient(135deg, #450a0a 0%, #7f1d1d 100%)' }
  if (s === 'MEDIUM_RISK') return { background: 'linear-gradient(135deg, #1c1917 0%, #44403c 100%)' }
  return { background: 'linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%)' }
})

function formatDate(d) {
  if (!d) return '—'
  return new Date(d + 'T12:00:00').toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
}

function formatNum(n) {
  return new Intl.NumberFormat('pt-BR').format(n || 0)
}

async function submitRegistration() {
  regSubmitting.value = true
  regError.value = null
  try {
    const res = await publicApi.register(route.params.id, regForm.value)
    registered.value = true
    registrationCount.value = (registrationCount.value || 0) + 1
  } catch (e) {
    regError.value = e.message
  } finally {
    regSubmitting.value = false
  }
}

onMounted(async () => {
  try {
    const res = await publicApi.event(route.params.id)
    event.value = res.data
    registrationCount.value = res.data.registration_count ?? null
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.ep-wrapper {
  min-height: 100vh;
  font-family: var(--font, 'Inter', system-ui, sans-serif);
}

.ep-backdrop {
  min-height: 100vh;
  padding: 40px 20px;
}

.ep-container {
  max-width: 720px;
  margin: 0 auto;
}

.ep-loading,
.ep-error {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 24px;
  color: #cbd5e1;
  gap: 12px;
}

.ep-error h3 { color: #f1f5f9; font-size: 20px; }
.ep-error p  { color: #94a3b8; font-size: 14px; }

.ep-header {
  text-align: center;
  margin-bottom: 24px;
  padding-bottom: 24px;
  border-bottom: 1px solid rgba(255,255,255,.1);
}

.ep-title {
  font-size: 32px;
  font-weight: 800;
  color: #f8fafc;
  margin: 0 0 12px;
  line-height: 1.2;
}

.ep-meta {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 16px;
  font-size: 14px;
  color: #94a3b8;
  margin-bottom: 12px;
}

.ep-badges {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 8px;
  margin-bottom: 12px;
}

.ep-badges :deep(.badge) {
  background: rgba(255,255,255,.1);
  color: #e2e8f0;
  border: none;
}

.ep-description {
  font-size: 15px;
  color: #cbd5e1;
  line-height: 1.7;
  max-width: 560px;
  margin: 12px auto 0;
}

.ep-weather-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: rgba(255,255,255,.06);
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 16px;
  padding: 20px 24px;
  margin-bottom: 16px;
  gap: 20px;
}

.ep-weather-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.ep-weather-temp {
  font-size: 32px;
  font-weight: 700;
  color: #f1f5f9;
  line-height: 1;
}

.ep-weather-unit {
  font-size: 16px;
  color: #94a3b8;
  font-weight: 400;
}

.ep-weather-desc {
  font-size: 13px;
  color: #94a3b8;
  text-transform: capitalize;
}

.ep-weather-right {
  text-align: right;
}

.ep-risk-score {
  font-size: 28px;
  font-weight: 700;
  line-height: 1;
}

.ep-risk-bar-track {
  width: 120px;
  height: 6px;
  background: rgba(255,255,255,.12);
  border-radius: 999px;
  overflow: hidden;
  margin: 6px 0 4px auto;
}

.ep-risk-bar-fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.6s ease;
}

.ep-risk-status {
  font-size: 12px;
  font-weight: 600;
}

.ep-forecast-card {
  background: rgba(255,255,255,.06);
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 16px;
  padding: 20px 24px;
  margin-bottom: 16px;
}

.ep-forecast-card h3 {
  font-size: 14px;
  font-weight: 600;
  color: #e2e8f0;
  margin: 0 0 12px;
}

.ep-forecast-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
}

.ep-fc-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.ep-fc-label {
  font-size: 11px;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.ep-fc-value {
  font-size: 16px;
  font-weight: 600;
  color: #f1f5f9;
}

.ep-register-card {
  background: rgba(255,255,255,.08);
  border: 1px solid rgba(255,255,255,.12);
  border-radius: 16px;
  padding: 28px 24px;
}

.ep-register-card h3 {
  font-size: 20px;
  font-weight: 700;
  color: #f1f5f9;
  margin: 0 0 8px;
}

.ep-register-count {
  font-size: 13px;
  color: #94a3b8;
  margin-bottom: 20px;
}

.ep-registered-success {
  text-align: center;
  padding: 32px 16px;
}

.ep-success-icon {
  font-size: 48px;
  display: block;
  margin-bottom: 12px;
}

.ep-registered-success h4 {
  font-size: 20px;
  color: #4ade80;
  margin: 0 0 8px;
}

.ep-registered-success p {
  font-size: 14px;
  color: #94a3b8;
}

.ep-register-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.ep-form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.ep-form-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.ep-form-group label {
  font-size: 12px;
  font-weight: 600;
  color: #cbd5e1;
}

.ep-form-group .form-control {
  background: rgba(255,255,255,.08);
  border: 1px solid rgba(255,255,255,.15);
  color: #f1f5f9;
  padding: 10px 12px;
  border-radius: 10px;
  font-size: 14px;
}

.ep-form-group .form-control::placeholder {
  color: #64748b;
}

.ep-form-group .form-control:focus {
  border-color: #60a5fa;
  box-shadow: 0 0 0 3px rgba(96,165,250,.2);
}

.ep-submit {
  background: #3b82f6;
  border: none;
  color: #fff;
  padding: 12px 24px;
  font-size: 15px;
  font-weight: 700;
  border-radius: 10px;
  width: 100%;
  cursor: pointer;
  transition: background 150ms;
}

.ep-submit:hover:not(:disabled) {
  background: #2563eb;
}

.ep-submit:disabled {
  opacity: .5;
  cursor: default;
}

.alert-danger {
  background: rgba(239,68,68,.15);
  border: 1px solid rgba(239,68,68,.3);
  color: #fca5a5;
  padding: 10px 14px;
  border-radius: 10px;
  font-size: 13px;
}

.ep-speakers-card {
  background: rgba(255,255,255,.06);
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 16px;
  padding: 20px 24px;
  margin-bottom: 16px;
}

.ep-speakers-card h3 {
  font-size: 14px;
  font-weight: 600;
  color: #e2e8f0;
  margin: 0 0 12px;
}

.ep-speakers-grid {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.ep-speaker-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  background: rgba(255,255,255,.04);
  border-radius: 12px;
}

.ep-speaker-featured {
  background: rgba(59,130,246,.1);
  border: 1px solid rgba(59,130,246,.2);
}

.ep-speaker-avatar {
  width: 40px;
  height: 40px;
  border-radius: 999px;
  overflow: hidden;
  flex-shrink: 0;
  background: rgba(255,255,255,.1);
  display: flex;
  align-items: center;
  justify-content: center;
}

.ep-speaker-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.ep-speaker-initials {
  font-size: 14px;
  font-weight: 700;
  color: #94a3b8;
}

.ep-speaker-info {
  display: flex;
  flex-direction: column;
  gap: 1px;
  min-width: 0;
}

.ep-speaker-info strong {
  font-size: 14px;
  color: #f1f5f9;
}

.ep-speaker-info > span {
  font-size: 12px;
  color: #94a3b8;
}

.ep-speaker-expertise {
  font-size: 11px;
  color: #64748b;
}

.ep-sessions-card {
  background: rgba(255,255,255,.06);
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 16px;
  padding: 20px 24px;
  margin-bottom: 16px;
}

.ep-sessions-card h3 {
  font-size: 14px;
  font-weight: 600;
  color: #e2e8f0;
  margin: 0 0 12px;
}

.ep-sessions-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.ep-session-item {
  display: flex;
  gap: 14px;
  padding: 10px 12px;
  background: rgba(255,255,255,.04);
  border-radius: 12px;
}

.ep-session-time {
  font-size: 12px;
  font-weight: 600;
  color: #3b82f6;
  white-space: nowrap;
  padding-top: 1px;
  min-width: 90px;
}

.ep-session-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.ep-session-info strong {
  font-size: 14px;
  color: #f1f5f9;
}

.ep-session-speaker,
.ep-session-room {
  font-size: 12px;
  color: #94a3b8;
}

@media (max-width: 600px) {
  .ep-backdrop { padding: 24px 16px; }
  .ep-title { font-size: 24px; }
  .ep-weather-card { flex-direction: column; text-align: center; }
  .ep-weather-right { text-align: center; }
  .ep-risk-bar-track { margin: 6px auto 4px; }
  .ep-forecast-grid { grid-template-columns: repeat(2, 1fr); }
  .ep-form-row { grid-template-columns: 1fr; }
}
</style>
