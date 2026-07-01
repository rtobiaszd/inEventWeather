<template>
  <div class="cv-wrapper">
    <div class="cv-backdrop">
      <div class="cv-container">
        <div v-if="loading" class="cv-loading">
          <span class="spinner" />
          <p>Verificando certificado...</p>
        </div>

        <div v-else-if="error" class="cv-result cv-invalid">
          <span class="cv-icon">✕</span>
          <h3>Certificado Inválido</h3>
          <p>O código de verificação não corresponde a nenhum certificado válido.</p>
          <div class="cv-hash-display">{{ route.params.hash }}</div>
        </div>

        <div v-else-if="data" class="cv-result cv-valid">
          <div class="cv-valid-icon">
            <svg viewBox="0 0 64 64" width="64" height="64" fill="none">
              <circle cx="32" cy="32" r="30" stroke="#22c55e" stroke-width="3" fill="#f0fdf4" />
              <path d="M22 32l8 8 12-12" stroke="#22c55e" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </div>

          <h3>Certificado Válido</h3>
          <p class="cv-valid-sub">Este certificado foi emitido pela plataforma inEvent e é autêntico.</p>

          <div class="cv-details">
            <div class="cv-detail-item">
              <span class="cv-detail-label">Participante</span>
              <span class="cv-detail-value">{{ data.participant.name }}</span>
            </div>
            <div class="cv-detail-item">
              <span class="cv-detail-label">Evento</span>
              <span class="cv-detail-value">{{ data.event.name }}</span>
            </div>
            <div class="cv-detail-item">
              <span class="cv-detail-label">Data do Evento</span>
              <span class="cv-detail-value">{{ formatDate(data.event.event_date) }}</span>
            </div>
            <div class="cv-detail-item">
              <span class="cv-detail-label">Carga Horária</span>
              <span class="cv-detail-value">{{ data.event.hours }}h</span>
            </div>
            <div class="cv-detail-item">
              <span class="cv-detail-label">Emitido em</span>
              <span class="cv-detail-value">{{ formatDateTime(data.issued_at) }}</span>
            </div>
          </div>

          <div class="cv-hash-display">{{ route.params.hash }}</div>

          <div class="cv-badge">
            <span>⛅ inEvent</span>
            <span>Plataforma de Gestão de Eventos</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { certificateApi } from '../services/api.js'

const route = useRoute()

const data = ref(null)
const loading = ref(true)
const error = ref(false)

function formatDate(d) {
  if (!d) return '—'
  return new Date(d + 'T12:00:00').toLocaleDateString('pt-BR', { day: 'numeric', month: 'long', year: 'numeric' })
}

function formatDateTime(dt) {
  if (!dt) return '—'
  return new Date(dt).toLocaleString('pt-BR', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

onMounted(async () => {
  try {
    const res = await certificateApi.verify(route.params.hash)
    data.value = res.data
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.cv-wrapper {
  min-height: 100vh;
  font-family: var(--font, 'Inter', system-ui, sans-serif);
  background: #0f172a;
  color: #e2e8f0;
}

.cv-backdrop {
  min-height: 100vh;
  padding: 60px 20px;
  background: linear-gradient(135deg, #0f172a 0%, #1a2744 50%, #0f172a 100%);
}

.cv-container {
  max-width: 480px;
  margin: 0 auto;
}

.cv-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 80px 24px;
  gap: 12px;
  color: #94a3b8;
}

.cv-result {
  background: #ffffff;
  border-radius: 20px;
  padding: 40px 32px;
  text-align: center;
  box-shadow: 0 20px 60px rgba(0,0,0,.4);
}

.cv-valid { border: 1px solid #22c55e; }
.cv-invalid { border: 1px solid #ef4444; }

.cv-icon {
  font-size: 48px;
  display: block;
  margin-bottom: 12px;
}

.cv-valid-icon { margin-bottom: 16px; }

.cv-result h3 {
  font-size: 22px;
  font-weight: 800;
  margin-bottom: 8px;
}

.cv-valid h3 { color: #16a34a; }
.cv-invalid h3 { color: #dc2626; }

.cv-valid-sub {
  color: #64748b;
  font-size: 14px;
  margin-bottom: 24px;
}

.cv-details {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 20px;
  text-align: left;
}

.cv-detail-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 12px;
  background: #f8fafc;
  border-radius: 8px;
}

.cv-detail-label {
  font-size: 12px;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: .04em;
  font-weight: 600;
}

.cv-detail-value {
  font-size: 14px;
  color: #1e293b;
  font-weight: 600;
}

.cv-hash-display {
  font-family: 'SF Mono', 'Fira Code', monospace;
  font-size: 11px;
  color: #94a3b8;
  background: #f1f5f9;
  padding: 6px 12px;
  border-radius: 6px;
  word-break: break-all;
  margin-bottom: 20px;
}

.cv-badge {
  display: flex;
  flex-direction: column;
  gap: 2px;
  align-items: center;
  padding-top: 16px;
  border-top: 1px solid #e2e8f0;
}

.cv-badge span:first-child {
  font-weight: 800;
  font-size: 14px;
  color: #0f172a;
}

.cv-badge span:last-child {
  font-size: 11px;
  color: #94a3b8;
}
</style>
