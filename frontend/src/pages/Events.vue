<template>
  <div class="stack">
    <div class="page-header">
      <div>
        <h2>Eventos</h2>
        <p v-if="filtered.length > 0">{{ filtered.length }} evento{{ filtered.length !== 1 ? 's' : '' }} encontrado{{ filtered.length !== 1 ? 's' : '' }}</p>
        <p v-else>Gerencie seus eventos com análise climática</p>
      </div>
      <div class="flex-gap">
        <button class="btn btn-ghost btn-sm" @click="exportCSV" :disabled="filtered.length === 0">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Exportar CSV
        </button>
          <RouterLink to="/events/agenda" class="btn btn-ghost btn-sm">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Agenda
          </RouterLink>
          <RouterLink to="/events/best-dates" class="btn btn-ghost btn-sm">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Melhor Data
          </RouterLink>
        <RouterLink to="/events/map" class="btn btn-ghost btn-sm">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>
          Mapa
        </RouterLink>
        <button v-if="can('events', 'create')" class="btn btn-ghost btn-sm" @click="openImport">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Importar Eventos
        </button>
        <RouterLink v-if="can('events', 'create')" to="/events/create" class="btn btn-primary">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Novo Evento
        </RouterLink>
      </div>
    </div>

    <!-- Barra de filtros: busca + tipo + status -->
    <div class="filter-bar">
      <div class="filter-search-group">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Buscar por nome ou cidade..."
          class="filter-search-input"
        />
      </div>

      <select v-model="statusFilter" class="form-control filter-select">
        <option value="all">Todos status</option>
        <option value="planned">📋 Planejados</option>
        <option value="confirmed">✅ Confirmados</option>
        <option value="in_progress">▶ Em andamento</option>
        <option value="completed">🏁 Realizados</option>
        <option value="cancelled">❌ Cancelados</option>
      </select>

      <div class="filter-type-group">
        <button
          v-for="t in typeFilters"
          :key="t.slug"
          :class="['btn', 'btn-sm', activeFilter === t.slug ? 'btn-primary' : 'btn-ghost']"
          @click="activeFilter = t.slug"
        >
          {{ t.icon }} {{ t.name }}
        </button>
      </div>
    </div>

    <LoadingState v-if="loading" message="Carregando eventos..." />
    <ErrorMessage v-else-if="error" :message="error" @retry="load" />
    <EventTable v-else :events="filtered" @edit="goEdit" @duplicate="confirmDuplicate" @delete="confirmDelete" />

    <!-- Import modal -->
    <div v-if="showImportModal" class="modal-overlay" @click.self="showImportModal = false">
      <div class="modal-box">
        <h3>Importar Eventos Externos</h3>
        <p v-if="!importing && !importResult">
          Busca eventos nas próximas datas nas plataformas Sympla, Eventim e Eventbrite
          para as cidades: São Paulo, Rio de Janeiro, Curitiba e Matinhos.
        </p>

        <div v-if="importing" class="pl-loading" style="padding:24px 0">
          <span class="spinner" style="width:28px;height:28px;border-width:3px" />
          <p style="margin-top:10px">Importando eventos...</p>
        </div>

        <div v-else-if="importResult" class="import-result">
          <div class="import-result-header">
            <span class="import-result-icon">✅</span>
            <span>{{ importResult.total_imported }} evento{{ importResult.total_imported !== 1 ? 's' : '' }} importado{{ importResult.total_imported !== 1 ? 's' : '' }}</span>
          </div>
          <table class="import-table" v-if="importResult.results.length">
            <thead>
              <tr><th>Plataforma</th><th>Cidade</th><th>Importados</th><th>Erro</th></tr>
            </thead>
            <tbody>
              <tr v-for="r in importResult.results" :key="r.provider + r.city">
                <td>{{ r.provider }}</td>
                <td>{{ r.city }}</td>
                <td>{{ r.imported }}</td>
                <td><span v-if="r.error" class="text-danger">{{ r.error }}</span><span v-else class="text-success">OK</span></td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex-gap" style="justify-content:flex-end;margin-top:20px">
          <button class="btn btn-secondary" @click="showImportModal = false">
            {{ importResult ? 'Fechar' : 'Cancelar' }}
          </button>
          <button v-if="!importing && !importResult" class="btn btn-primary" @click="doImport">
            Importar Agora
          </button>
          <button v-if="importResult" class="btn btn-primary" @click="showImportModal = false; load()">
            OK
          </button>
        </div>
      </div>
    </div>

    <!-- Delete confirmation modal -->
    <div v-if="deleteTarget" class="modal-overlay" @click.self="deleteTarget = null">
      <div class="modal-box">
        <h3>Excluir evento?</h3>
        <p>Tem certeza que deseja excluir o evento <strong>{{ deleteTarget.name }}</strong>?<br/>Esta ação não pode ser desfeita.</p>
        <div class="flex-gap" style="justify-content:flex-end;margin-top:20px;">
          <button class="btn btn-secondary" @click="deleteTarget = null">Cancelar</button>
          <button class="btn btn-danger" :disabled="deleting" @click="doDelete">
            {{ deleting ? 'Excluindo...' : 'Excluir' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { eventsApi, eventTypesApi } from '../services/api.js'
import { useAuth } from '../composables/useAuth.js'
import { useToast } from '../composables/useToast.js'
import EventTable   from '../components/EventTable.vue'
import LoadingState from '../components/LoadingState.vue'
import ErrorMessage from '../components/ErrorMessage.vue'

const router    = useRouter()
const { can }   = useAuth()
const { show: showToast } = useToast()

const events        = ref([])
const eventTypes    = ref([])
const loading       = ref(false)
const error         = ref(null)
const activeFilter  = ref('all')
const searchQuery   = ref('')
const statusFilter  = ref('all')

const deleteTarget  = ref(null)
const deleting      = ref(false)
const duplicating   = ref(false)
const showImportModal = ref(false)
const importing     = ref(false)
const importResult  = ref(null)

const typeFilters = computed(() => [
  { slug: 'all', name: 'Todos', icon: '📋' },
  ...eventTypes.value,
])

const filtered = computed(() => {
  let list = events.value

  if (activeFilter.value !== 'all') {
    list = list.filter(e => e.type === activeFilter.value)
  }

  if (statusFilter.value !== 'all') {
    list = list.filter(e => e.status === statusFilter.value)
  }

  const q = searchQuery.value.trim().toLowerCase()
  if (q) {
    list = list.filter(e =>
      (e.name || '').toLowerCase().includes(q) ||
      (e.city || '').toLowerCase().includes(q)
    )
  }

  return list
})

function formatDate(date) {
  if (!date) return ''
  return new Date(date + 'T00:00:00').toLocaleDateString('pt-BR')
}

function exportCSV() {
  const headers = ['Nome','Cidade','País','Data','Horário','Tipo','Status','Local','Organizador','Público','Orçamento','Receita','Preço Ingresso']
  const rows = filtered.value.map(e => [
    e.name,
    e.city,
    e.country,
    formatDate(e.event_date),
    e.event_time || '',
    e.type === 'outdoor' ? 'Outdoor' : 'Indoor',
    ({ planned: 'Planejado', confirmed: 'Confirmado', in_progress: 'Em andamento', completed: 'Realizado', cancelled: 'Cancelado' })[e.status] || e.status,
    e.venue || '',
    e.organizer || '',
    e.expected_audience || '',
    e.budget || '',
    e.revenue || '',
    e.ticket_price || '',
  ])

  const csv = [headers.join(','), ...rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(','))].join('\n')
  const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `eventos_${new Date().toISOString().slice(0, 10)}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

async function load() {
  loading.value = true
  error.value   = null
  try {
    const [evRes, typRes] = await Promise.all([eventsApi.list(), eventTypesApi.list()])
    events.value     = evRes.data ?? []
    eventTypes.value = typRes.data ?? []
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function openImport() {
  showImportModal.value = true
  importing.value = false
  importResult.value = null
}

async function doImport() {
  importing.value = true
  importResult.value = null
  try {
    const res = await eventsApi.importExternal()
    importResult.value = res.data
    showToast(`${res.data.total_imported} evento(s) importado(s) com sucesso!`, 'success')
  } catch (e) {
    showToast(e.message, 'error')
    showImportModal.value = false
  } finally {
    importing.value = false
  }
}

function goEdit(event) {
  router.push({ name: 'events.edit', params: { id: event.id } })
}

async function confirmDuplicate(event) {
  if (duplicating.value) return
  duplicating.value = true
  try {
    const res = await eventsApi.duplicate(event.id)
    events.value.unshift(res.data)
    showToast(`"${event.name}" duplicado com sucesso!`, 'success')
  } catch (e) {
    showToast(e.message, 'error')
  } finally {
    duplicating.value = false
  }
}

function confirmDelete(event) {
  deleteTarget.value = event
}

async function doDelete() {
  deleting.value = true
  try {
    await eventsApi.remove(deleteTarget.value.id)
    events.value = events.value.filter(e => e.id !== deleteTarget.value.id)
    deleteTarget.value = null
  } catch (e) {
    alert(e.message)
  } finally {
    deleting.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.filter-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

.filter-search-group {
  position: relative;
  flex: 1;
  min-width: 200px;
  max-width: 320px;
}

.filter-search-group svg {
  position: absolute;
  left: 10px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-text-muted);
  pointer-events: none;
}

.filter-search-input {
  width: 100%;
  padding: 7px 10px 7px 32px;
  border: 1.5px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 13px;
  background: var(--color-surface);
  color: var(--color-text);
  outline: none;
  transition: border-color var(--transition), box-shadow var(--transition);
}

.filter-search-input:focus {
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px var(--color-primary-light);
}

.filter-select {
  width: auto;
  min-width: 150px;
  padding: 7px 10px;
  font-size: 13px;
}

.filter-type-group {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 200;
}

.modal-box {
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  padding: 28px;
  max-width: 420px;
  width: 90%;
  box-shadow: var(--shadow-lg);
}

.modal-box h3 { font-size: 17px; font-weight: 700; margin-bottom: 10px; }
.modal-box p  { font-size: 13.5px; color: var(--color-text-secondary); line-height: 1.6; }
.import-result { margin-top: 12px; }
.import-result-header { font-size: 15px; font-weight: 600; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
.import-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.import-table th, .import-table td { padding: 6px 10px; text-align: left; border-bottom: 1px solid var(--color-border); }
.import-table th { font-weight: 600; color: var(--color-text-secondary); }
.text-success { color: var(--color-success); }
.text-danger  { color: var(--color-danger); }
.pl-loading { display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--color-text-muted); }
</style>
