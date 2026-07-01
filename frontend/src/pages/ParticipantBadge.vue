<template>
  <div class="pb-wrapper">
    <div class="pb-backdrop">
      <div class="pb-container">
        <div v-if="loading" class="pb-loading">
          <span class="spinner" style="width:32px;height:32px;border-width:3px" />
          <p>Carregando credencial...</p>
        </div>

        <div v-else-if="error" class="pb-error">
          <span class="pb-error-icon">🎫</span>
          <h3>Credencial não encontrada</h3>
          <p>Este link é inválido ou a inscrição não existe mais.</p>
        </div>

        <template v-else-if="data">
          <!-- Badge Card -->
          <div class="pb-badge" :class="{ 'pb-checked-in': data.registration.checked_in_at }">
            <div class="pb-badge-header">
              <span class="pb-badge-brand">⛅ inEvent</span>
              <span v-if="data.registration.checked_in_at" class="pb-checkin-stamp">✓ CHECK-IN</span>
            </div>

            <div class="pb-badge-body">
              <div class="pb-badge-avatar">
                {{ initials }}
              </div>
              <h1 class="pb-badge-name">{{ data.registration.name }}</h1>
              <p v-if="data.registration.company" class="pb-badge-company">{{ data.registration.company }}</p>
              <p class="pb-badge-email">{{ data.registration.email }}</p>
            </div>

            <div class="pb-badge-qr">
              <canvas ref="qrCanvas"></canvas>
            </div>

            <div class="pb-badge-footer">
              <div class="pb-badge-event">
                <strong>{{ data.event.name }}</strong>
                <span>{{ formatDate(data.event.event_date) }}{{ data.event.event_time ? ' às ' + data.event.event_time.slice(0, 5) : '' }}</span>
                <span>{{ data.event.city }}{{ data.event.venue ? ' — ' + data.event.venue : '' }}</span>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="pb-actions">
            <button class="btn btn-primary" @click="downloadBadge">
              📥 Baixar Credencial
            </button>
            <button class="btn btn-secondary" @click="printBadge">
              🖨 Imprimir
            </button>
            <router-link :to="`/e/${route.params.id}/feedback/${route.params.token}`" class="btn btn-secondary">
              ⭐ Avaliar Evento
            </router-link>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { badgeApi } from '../services/api.js'
import QRCode from 'qrcode'

const route = useRoute()

const data = ref(null)
const loading = ref(true)
const error = ref(false)
const qrCanvas = ref(null)

const initials = computed(() => {
  if (!data.value?.registration?.name) return '?'
  const parts = data.value.registration.name.trim().split(/\s+/)
  if (parts.length >= 2) return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
  return parts[0][0].toUpperCase()
})

function formatDate(d) {
  if (!d) return '—'
  return new Date(d + 'T12:00:00').toLocaleDateString('pt-BR', { day: 'numeric', month: 'long', year: 'numeric' })
}

function badgeUrl() {
  const origin = window.location.origin
  return `${origin}/e/${route.params.id}/badge/${route.params.token}`
}

async function generateQR() {
  await nextTick()
  if (!qrCanvas.value) return
  try {
    await QRCode.toCanvas(qrCanvas.value, badgeUrl(), {
      width: 180,
      margin: 2,
      color: { dark: '#0f172a', light: '#ffffff' },
    })
  } catch {
    // Silent
  }
}

async function downloadBadge() {
  const canvas = qrCanvas.value
  if (!canvas) return
  const link = document.createElement('a')
  link.download = `credencial-${data.value.registration.name.replace(/\s+/g, '-').toLowerCase()}.png`
  // Clone badge to canvas for download
  const badge = document.querySelector('.pb-badge')
  if (!badge) return
  try {
    const html2canvas = await import('html2canvas')
    const canvasImg = await html2canvas.default(badge, {
      backgroundColor: '#ffffff',
      scale: 2,
    })
    link.href = canvasImg.toDataURL('image/png')
    link.click()
  } catch {
    // Fallback: just download QR
    link.href = canvas.toDataURL('image/png')
    link.click()
  }
}

function printBadge() {
  window.print()
}

onMounted(async () => {
  try {
    const res = await badgeApi.get(route.params.id, route.params.token)
    data.value = res.data
    await generateQR()
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.pb-wrapper {
  min-height: 100vh;
  font-family: var(--font, 'Inter', system-ui, sans-serif);
  background: #0f172a;
  color: #e2e8f0;
}

.pb-backdrop {
  min-height: 100vh;
  padding: 40px 20px;
  background: linear-gradient(135deg, #0f172a 0%, #1a2744 50%, #0f172a 100%);
}

.pb-container {
  max-width: 420px;
  margin: 0 auto;
}

.pb-loading,
.pb-error {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 24px;
  gap: 12px;
  color: #94a3b8;
}

.pb-error-icon { font-size: 48px; }
.pb-error h3 { color: #f1f5f9; font-size: 20px; }
.pb-error p { color: #94a3b8; font-size: 14px; }

.pb-badge {
  background: #ffffff;
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 20px 60px rgba(0,0,0,.4);
  position: relative;
}

.pb-checked-in {
  box-shadow: 0 20px 60px rgba(34,197,94,.2);
}

.pb-badge-header {
  background: #0f172a;
  padding: 14px 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.pb-badge-brand {
  font-weight: 800;
  font-size: 14px;
  color: #f8fafc;
  letter-spacing: -.2px;
}

.pb-checkin-stamp {
  font-size: 11px;
  font-weight: 700;
  color: #22c55e;
  text-transform: uppercase;
  letter-spacing: .08em;
  background: rgba(34,197,94,.12);
  padding: 3px 10px;
  border-radius: 999px;
}

.pb-badge-body {
  text-align: center;
  padding: 28px 24px 20px;
}

.pb-badge-avatar {
  width: 64px;
  height: 64px;
  border-radius: 999px;
  background: linear-gradient(135deg, #2563eb, #7c3aed);
  color: #fff;
  font-size: 22px;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 16px;
}

.pb-badge-name {
  font-size: 22px;
  font-weight: 800;
  color: #0f172a;
  margin-bottom: 4px;
  line-height: 1.2;
}

.pb-badge-company {
  font-size: 14px;
  color: #475569;
  margin-bottom: 2px;
}

.pb-badge-email {
  font-size: 13px;
  color: #94a3b8;
}

.pb-badge-qr {
  display: flex;
  justify-content: center;
  padding: 0 24px 20px;
}

.pb-badge-qr canvas {
  border-radius: 12px;
  border: 2px solid #f1f5f9;
}

.pb-badge-footer {
  background: #f8fafc;
  padding: 16px 20px;
  border-top: 1px solid #e2e8f0;
}

.pb-badge-event {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.pb-badge-event strong {
  font-size: 14px;
  color: #0f172a;
}

.pb-badge-event span {
  font-size: 12px;
  color: #64748b;
}

.pb-actions {
  display: flex;
  gap: 10px;
  margin-top: 20px;
  justify-content: center;
}

.pb-actions .btn {
  padding: 10px 20px;
  font-size: 13px;
  font-weight: 600;
  border-radius: 12px;
}

@media print {
  .pb-wrapper { background: #fff; }
  .pb-backdrop { background: #fff; padding: 0; }
  .pb-loading, .pb-error, .pb-actions { display: none !important; }
  .pb-badge { box-shadow: none; border: 1px solid #e2e8f0; }
  .pb-badge-header { background: #0f172a; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .pb-badge-avatar { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
