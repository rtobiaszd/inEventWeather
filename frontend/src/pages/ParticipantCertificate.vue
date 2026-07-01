<template>
  <div class="pc-wrapper">
    <div class="pc-backdrop">
      <div class="pc-container">
        <div v-if="loading" class="pc-loading">
          <span class="spinner" />
          <p>Carregando certificado...</p>
        </div>

        <div v-else-if="error" class="pc-error">
          <span class="pc-error-icon">📜</span>
          <h3>{{ errorMsg }}</h3>
          <p>Não foi possível carregar o certificado.</p>
        </div>

        <template v-else-if="data">
          <!-- Certificado -->
          <div ref="certEl" class="pc-cert" :class="{ 'pc-verified': data.certificate }">
            <div class="pc-cert-border">
              <div class="pc-cert-top">
                <span class="pc-cert-badge">CERTIFICADO</span>
                <div class="pc-cert-seal">
                  <svg viewBox="0 0 48 48" width="36" height="36" fill="none">
                    <circle cx="24" cy="24" r="22" stroke="#2563eb" stroke-width="2.5" fill="#eff6ff" />
                    <path d="M16 24l6 6 10-10" stroke="#2563eb" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
                </div>
              </div>

              <div class="pc-cert-body">
                <p class="pc-cert-label">Certificamos que</p>
                <h1 class="pc-cert-name">{{ data.registration.name }}</h1>
                <p class="pc-cert-desc">participou do evento</p>
                <h2 class="pc-cert-event">{{ data.event.name }}</h2>

                <div class="pc-cert-meta">
                  <div class="pc-cert-meta-item">
                    <span class="pc-meta-label">Data</span>
                    <span class="pc-meta-value">{{ formatDate(data.event.event_date) }}</span>
                  </div>
                  <div class="pc-cert-meta-item">
                    <span class="pc-meta-label">Local</span>
                    <span class="pc-meta-value">{{ data.event.city }}{{ data.event.venue ? ' — ' + data.event.venue : '' }}</span>
                  </div>
                  <div class="pc-cert-meta-item">
                    <span class="pc-meta-label">Carga Horária</span>
                    <span class="pc-meta-value">{{ hours }}h</span>
                  </div>
                </div>

                <div class="pc-cert-organizer">
                  <span class="pc-org-label">Realização</span>
                  <span class="pc-org-name">{{ data.event.organizer || 'inEvent' }}</span>
                </div>
              </div>

              <div class="pc-cert-footer">
                <div class="pc-cert-hash">
                  <span>Código de verificação:</span>
                  <code>{{ data.certificate.hash.slice(0, 20) }}...</code>
                </div>
                <div class="pc-cert-qr">
                  <canvas ref="qrCanvas"></canvas>
                </div>
              </div>
            </div>
          </div>

          <div class="pc-actions">
            <button class="btn btn-primary" @click="downloadCert">
              📥 Baixar Certificado
            </button>
            <button class="btn btn-secondary" @click="printCert">
              🖨 Imprimir
            </button>
            <a :href="data.certificate.verify_url" target="_blank" class="btn btn-secondary">
              🔗 Verificar Certificado
            </a>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { certificateApi } from '../services/api.js'
import QRCode from 'qrcode'

const route = useRoute()

const data = ref(null)
const loading = ref(true)
const error = ref(false)
const errorMsg = ref('')
const qrCanvas = ref(null)
const certEl = ref(null)

const hours = computed(() => {
  if (!data.value?.event) return 4
  return 4
})

function formatDate(d) {
  if (!d) return '—'
  return new Date(d + 'T12:00:00').toLocaleDateString('pt-BR', { day: 'numeric', month: 'long', year: 'numeric' })
}

async function generateQR() {
  await nextTick()
  if (!qrCanvas.value || !data.value?.certificate?.verify_url) return
  try {
    await QRCode.toCanvas(qrCanvas.value, data.value.certificate.verify_url, {
      width: 100,
      margin: 1,
      color: { dark: '#1e293b', light: '#ffffff' },
    })
  } catch {
    // Silent
  }
}

async function downloadCert() {
  const el = certEl.value
  if (!el) return
  try {
    const html2canvas = await import('html2canvas')
    const canvas = await html2canvas.default(el, {
      backgroundColor: '#ffffff',
      scale: 2,
      width: 800,
    })
    const link = document.createElement('a')
    link.download = `certificado-${data.value.registration.name.replace(/\s+/g, '-').toLowerCase()}.png`
    link.href = canvas.toDataURL('image/png')
    link.click()
  } catch {
    // Fallback
  }
}

function printCert() {
  window.print()
}

onMounted(async () => {
  try {
    const res = await certificateApi.show(route.params.id, route.params.token)
    data.value = res.data
    await generateQR()
  } catch (err) {
    error.value = true
    errorMsg.value = err.message?.includes('check-in')
      ? 'Check-in necessário'
      : 'Certificado não encontrado'
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.pc-wrapper {
  min-height: 100vh;
  font-family: var(--font, 'Inter', system-ui, sans-serif);
  background: #0f172a;
  color: #e2e8f0;
}

.pc-backdrop {
  min-height: 100vh;
  padding: 40px 20px;
  background: linear-gradient(135deg, #0f172a 0%, #1a2744 50%, #0f172a 100%);
}

.pc-container {
  max-width: 800px;
  margin: 0 auto;
}

.pc-loading,
.pc-error {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 80px 24px;
  gap: 12px;
  color: #94a3b8;
}

.pc-error-icon { font-size: 48px; }
.pc-error h3 { color: #f1f5f9; font-size: 20px; }
.pc-error p { color: #94a3b8; font-size: 14px; }

.pc-cert {
  background: #ffffff;
  border-radius: 0;
  overflow: hidden;
  box-shadow: 0 20px 60px rgba(0,0,0,.4);
  position: relative;
}

.pc-cert-border {
  border: 3px solid #2563eb;
  margin: 12px;
  position: relative;
}

.pc-cert-top {
  background: linear-gradient(135deg, #1e3a8a, #2563eb);
  padding: 20px 28px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.pc-cert-badge {
  font-size: 13px;
  font-weight: 800;
  color: #bfdbfe;
  letter-spacing: .15em;
  text-transform: uppercase;
}

.pc-cert-seal {
  background: #fff;
  border-radius: 999px;
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 8px rgba(0,0,0,.15);
}

.pc-cert-body {
  padding: 36px 40px 28px;
  text-align: center;
}

.pc-cert-label {
  font-size: 14px;
  color: #64748b;
  margin-bottom: 8px;
  text-transform: uppercase;
  letter-spacing: .05em;
}

.pc-cert-name {
  font-size: 30px;
  font-weight: 800;
  color: #0f172a;
  margin-bottom: 12px;
  line-height: 1.15;
}

.pc-cert-desc {
  font-size: 14px;
  color: #64748b;
  margin-bottom: 4px;
}

.pc-cert-event {
  font-size: 22px;
  font-weight: 700;
  color: #2563eb;
  margin-bottom: 24px;
}

.pc-cert-meta {
  display: flex;
  justify-content: center;
  gap: 32px;
  margin-bottom: 24px;
  flex-wrap: wrap;
}

.pc-cert-meta-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
  align-items: center;
}

.pc-meta-label {
  font-size: 11px;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: .06em;
  font-weight: 600;
}

.pc-meta-value {
  font-size: 14px;
  color: #334155;
  font-weight: 600;
}

.pc-cert-organizer {
  display: flex;
  flex-direction: column;
  gap: 2px;
  align-items: center;
  padding-top: 16px;
  border-top: 1px solid #e2e8f0;
}

.pc-org-label {
  font-size: 11px;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: .06em;
}

.pc-org-name {
  font-size: 14px;
  color: #475569;
  font-weight: 600;
}

.pc-cert-footer {
  background: #f8fafc;
  padding: 16px 28px;
  border-top: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.pc-cert-hash {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.pc-cert-hash span {
  font-size: 11px;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: .04em;
}

.pc-cert-hash code {
  font-size: 12px;
  color: #475569;
  background: #e2e8f0;
  padding: 2px 8px;
  border-radius: 4px;
  font-family: 'SF Mono', 'Fira Code', monospace;
}

.pc-cert-qr canvas {
  border-radius: 6px;
  border: 1px solid #e2e8f0;
}

.pc-actions {
  display: flex;
  gap: 10px;
  margin-top: 24px;
  justify-content: center;
  flex-wrap: wrap;
}

.pc-actions .btn {
  padding: 10px 20px;
  font-size: 13px;
  font-weight: 600;
  border-radius: 12px;
}

@media print {
  .pc-wrapper { background: #fff; }
  .pc-backdrop { background: #fff; padding: 0; }
  .pc-loading, .pc-error, .pc-actions { display: none !important; }
  .pc-cert { box-shadow: none; border: 1px solid #e2e8f0; }
  .pc-cert-border { border-color: #2563eb !important; }
  .pc-cert-top { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
