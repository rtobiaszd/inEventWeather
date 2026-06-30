<template>
  <div class="stack">
    <div class="page-header">
      <div>
        <h2>Histórico de Consultas</h2>
        <p>Todas as consultas climáticas realizadas</p>
      </div>
    </div>

    <LoadingState v-if="loading" message="Carregando histórico..." />
    <ErrorMessage v-else-if="error" :message="error" @retry="load" />

    <div v-else class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>Cidade</th>
            <th>País</th>
            <th>Temperatura</th>
            <th>Sensação</th>
            <th>Umidade</th>
            <th>Vento</th>
            <th>Condição</th>
            <th>AQI</th>
            <th>Data</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="history.length === 0">
            <td colspan="9">
              <div class="empty-state" style="padding:32px">
                <span class="empty-icon">🕐</span>
                <h3>Nenhuma consulta realizada ainda</h3>
                <p>As consultas aparecem aqui automaticamente quando você pesquisa o clima.</p>
              </div>
            </td>
          </tr>
          <tr v-for="item in history" :key="item.id">
            <td class="font-medium">{{ item.city }}</td>
            <td class="td-muted">{{ item.country }}</td>
            <td>{{ item.temperature ? item.temperature + '°C' : '—' }}</td>
            <td class="td-muted">{{ item.feels_like ? item.feels_like + '°C' : '—' }}</td>
            <td class="td-muted">{{ item.humidity != null ? item.humidity + '%' : '—' }}</td>
            <td class="td-muted">{{ item.wind_speed ? (item.wind_speed * 3.6).toFixed(1) + ' km/h' : '—' }}</td>
            <td>
              <span v-if="item.weather_description" class="badge badge-neutral" style="text-transform:capitalize;">
                {{ item.weather_description }}
              </span>
              <span v-else class="td-muted">—</span>
            </td>
            <td>
              <span v-if="item.aqi" :class="['badge', aqiBadge(item.aqi)]">{{ item.aqi }}</span>
              <span v-else class="td-muted">—</span>
            </td>
            <td class="td-muted">{{ formatDate(item.created_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { historyApi } from '../services/api.js'
import LoadingState from '../components/LoadingState.vue'
import ErrorMessage from '../components/ErrorMessage.vue'

const history = ref([])
const loading = ref(false)
const error   = ref(null)

async function load() {
  loading.value = true
  error.value   = null
  try {
    const res = await historyApi.list(100)
    history.value = res.data ?? []
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function aqiBadge(aqi) {
  if (aqi >= 4) return 'badge-danger'
  if (aqi >= 3) return 'badge-warning'
  return 'badge-success'
}

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleString('pt-BR', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

onMounted(load)
</script>
