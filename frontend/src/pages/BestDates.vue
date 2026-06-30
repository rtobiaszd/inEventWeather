<template>
  <div class="stack">
    <div class="page-header">
      <div>
        <h2>Recomendador de Data Ideal</h2>
        <p>Descubra a melhor data para seu evento outdoor baseado no clima</p>
      </div>
    </div>

    <div class="card">
      <div class="search-bar">
        <div class="search-input-group">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>
          <input v-model="city" type="text" placeholder="Cidade (ex: São Paulo)" @keyup.enter="search" />
        </div>
        <button class="btn btn-primary" :disabled="loading" @click="search">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          {{ loading ? 'Analisando...' : 'Analisar Datas' }}
        </button>
      </div>
    </div>

    <LoadingState v-if="loading" message="Analisando previsão para os próximos 14 dias..." />

    <ErrorMessage v-else-if="error" :message="error" @retry="search" />

    <template v-else-if="result">
      <div v-if="result.best_date" class="card best-date-highlight">
        <div class="best-date-highlight-content">
          <div class="best-date-highlight-icon">🏆</div>
          <div>
            <span class="best-date-highlight-label">Melhor data recomendada</span>
            <span class="best-date-highlight-date">
              {{ formatDate(result.best_date.date) }} — {{ result.best_date.weekday }}
            </span>
            <div class="best-date-highlight-stats">
              <span>🌡 {{ result.best_date.avg_temperature }}°C</span>
              <span>🌧 {{ result.best_date.max_rain }}% chuva</span>
              <span>💨 {{ result.best_date.max_wind }} km/h</span>
            </div>
          </div>
          <span :class="['badge', statusBadgeClass(result.best_date.status)]" style="font-size:13px;padding:6px 14px">
            {{ statusLabel(result.best_date.status) }}
          </span>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3>Datas recomendadas para {{ result.city_name }}</h3>
          <span class="text-sm text-muted">{{ result.dates.length }} dias analisados</span>
        </div>

        <div class="best-dates-grid">
          <div
            v-for="d in result.dates"
            :key="d.date"
            :class="['best-date-card', `best-date-${d.status.toLowerCase()}`]"
          >
            <div class="best-date-card-header">
              <span class="best-date-day">{{ formatDay(d.date) }}</span>
              <span class="best-date-weekday">{{ d.weekday }}</span>
            </div>
            <div class="best-date-card-score">
              <div class="best-date-score-ring">
                <svg viewBox="0 0 36 36" width="48" height="48">
                  <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#E2E8F0" stroke-width="3"/>
                  <path
                    d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                    fill="none"
                    :stroke="scoreColor(d.score)"
                    stroke-width="3"
                    :stroke-dasharray="`${(100 - d.score) / 100 * 100}, 100`"
                    stroke-linecap="round"
                  />
                  <text x="18" y="20" text-anchor="middle" font-size="9" font-weight="700" fill="#1E293B">{{ 100 - d.score }}</text>
                  <text x="18" y="26" text-anchor="middle" font-size="4" fill="#64748B">pts</text>
                </svg>
              </div>
            </div>
            <div class="best-date-card-stats">
              <div class="bds-row">
                <span class="bds-label">Temperatura</span>
                <span class="bds-value">{{ d.avg_temperature }}°C</span>
              </div>
              <div class="bds-row">
                <span class="bds-label">Chuva máx</span>
                <span class="bds-value">{{ d.max_rain }}%</span>
              </div>
              <div class="bds-row">
                <span class="bds-label">Vento máx</span>
                <span class="bds-value">{{ d.max_wind }} km/h</span>
              </div>
              <div class="bds-row">
                <span class="bds-label">Condição</span>
                <span class="bds-value text-sm">{{ d.conditions }}</span>
              </div>
            </div>
            <span :class="['badge', statusBadgeClass(d.status), 'best-date-badge']">
              {{ statusLabel(d.status) }}
            </span>
          </div>
        </div>
      </div>
    </template>

    <div v-else class="empty-state">
      <span class="empty-icon">🔮</span>
      <h3>Descubra a melhor data</h3>
      <p>Digite o nome de uma cidade para analisar o clima dos próximos 14 dias e encontrar a data ideal para seu evento.</p>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import { weatherApi } from '../services/api.js'
import LoadingState from '../components/LoadingState.vue'
import ErrorMessage from '../components/ErrorMessage.vue'

const route = useRoute()

const city = ref(route.query.city || '')
const result = ref(null)
const loading = ref(false)
const error = ref(null)

async function search() {
  if (!city.value.trim()) return
  loading.value = true
  error.value = null
  result.value = null
  try {
    const res = await weatherApi.bestDates(city.value.trim(), 'BR')
    result.value = res.data
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function formatDate(date) {
  if (!date) return ''
  const [y, m, d] = date.split('-')
  return `${d}/${m}`
}

function formatDay(date) {
  if (!date) return ''
  const [y, m, d] = date.split('-')
  return `${d}/${m}`
}

function statusLabel(status) {
  return { IDEAL: 'Ideal', FAVORABLE: 'Favorável', CAUTION: 'Cautela', AVOID: 'Evitar' }[status] || status
}

function statusBadgeClass(status) {
  return { IDEAL: 'badge-success', FAVORABLE: 'badge-info', CAUTION: 'badge-warning', AVOID: 'badge-danger' }[status] || 'badge-neutral'
}

function scoreColor(score) {
  if (score <= 20) return '#22C55E'
  if (score <= 50) return '#3B82F6'
  if (score <= 70) return '#F59E0B'
  return '#EF4444'
}
</script>

<style scoped>
.best-date-highlight {
  background: linear-gradient(135deg, #065F46 0%, #047857 100%);
  color: #fff;
  border: none;
}

.best-date-highlight-content {
  display: flex;
  align-items: center;
  gap: 16px;
}

.best-date-highlight-icon {
  font-size: 40px;
  flex-shrink: 0;
}

.best-date-highlight-label {
  display: block;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  opacity: 0.75;
  margin-bottom: 2px;
}

.best-date-highlight-date {
  display: block;
  font-size: 20px;
  font-weight: 700;
}

.best-date-highlight-stats {
  display: flex;
  gap: 16px;
  margin-top: 6px;
  font-size: 13px;
  opacity: 0.85;
}

.best-dates-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 12px;
}

.best-date-card {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  transition: box-shadow 150ms, transform 150ms;
}

.best-date-card:hover {
  box-shadow: var(--shadow-md);
  transform: translateY(-2px);
}

.best-date-ideal {
  border-color: #BBF7D0;
  background: #F0FDF4;
}

.best-date-favorable {
  border-color: #BFDBFE;
  background: #EFF6FF;
}

.best-date-caution {
  border-color: #FDE68A;
  background: #FFFBEB;
}

.best-date-avoid {
  border-color: #FECACA;
  background: #FEF2F2;
}

.best-date-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.best-date-day {
  font-size: 16px;
  font-weight: 700;
}

.best-date-weekday {
  font-size: 11px;
  color: var(--color-text-secondary);
  text-transform: capitalize;
}

.best-date-card-score {
  display: flex;
  justify-content: center;
  padding: 4px 0;
}

.best-date-score-ring {
  display: flex;
  align-items: center;
  justify-content: center;
}

.best-date-card-stats {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.bds-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
}

.bds-label {
  color: var(--color-text-secondary);
}

.bds-value {
  font-weight: 600;
}

.best-date-badge {
  align-self: center;
  font-size: 11px;
}

@media (max-width: 600px) {
  .best-date-highlight-content {
    flex-direction: column;
    text-align: center;
  }

  .best-dates-grid {
    grid-template-columns: 1fr 1fr;
  }
}
</style>
