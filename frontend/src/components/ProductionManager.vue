<template>
  <div class="card">
    <div class="card-header">
      <h3>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:6px"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        Produção
        <span v-if="progress.percent > 0" class="progress-label">{{ progress.done }}/{{ progress.total }} concluídas</span>
      </h3>
      <div class="flex-gap">
        <div v-if="tasks.length" class="filter-tabs">
          <button v-for="f in filterOptions" :key="f.key" class="filter-tab" :class="{ active: filter === f.key }" @click="filter = f.key">
            {{ f.label }}
            <span v-if="f.count" class="filter-count">{{ f.count }}</span>
          </button>
        </div>
        <button class="btn btn-primary btn-sm" @click="openCreate">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Nova Tarefa
        </button>
      </div>
    </div>

    <div v-if="tasks.length" class="progress-bar-wrap">
      <div class="progress-bar" :style="{ width: progress.percent + '%' }" :class="progressClass" />
    </div>

    <LoadingState v-if="loading" message="Carregando tarefas..." />
    <ErrorMessage v-else-if="loadError" :message="loadError" @retry="load" />

    <template v-else>
      <div v-if="showForm" class="task-form">
        <h4>{{ editingId ? 'Editar Tarefa' : 'Nova Tarefa' }}</h4>
        <div class="tf-row">
          <div class="tf-field tf-field-title">
            <label>Título *</label>
            <input v-model="form.title" type="text" class="form-control" placeholder="O que precisa ser feito?" />
          </div>
          <div class="tf-field">
            <label>Categoria</label>
            <select v-model="form.category" class="form-control">
              <option v-for="c in categories" :key="c.key" :value="c.key">{{ c.icon }} {{ c.label }}</option>
            </select>
          </div>
          <div class="tf-field">
            <label>Prioridade</label>
            <select v-model="form.priority" class="form-control">
              <option value="low">🟢 Baixa</option>
              <option value="medium">🟡 Média</option>
              <option value="high">🟠 Alta</option>
              <option value="critical">🔴 Crítica</option>
            </select>
          </div>
        </div>
        <div class="tf-row">
          <div class="tf-field tf-field-full">
            <label>Descrição</label>
            <input v-model="form.description" type="text" class="form-control" placeholder="Detalhes da tarefa" />
          </div>
        </div>
        <div class="tf-row">
          <div class="tf-field">
            <label>Responsável</label>
            <input v-model="form.assigned_to" type="text" class="form-control" placeholder="Nome da pessoa" />
          </div>
          <div class="tf-field">
            <label>Data limite</label>
            <input v-model="form.due_date" type="date" class="form-control" />
          </div>
          <div class="tf-field" v-if="editingId">
            <label>Status</label>
            <select v-model="form.status" class="form-control">
              <option value="pending">⏳ Pendente</option>
              <option value="in_progress">▶ Em andamento</option>
              <option value="completed">✅ Concluída</option>
              <option value="cancelled">❌ Cancelada</option>
            </select>
          </div>
        </div>
        <div class="tf-actions">
          <button class="btn btn-secondary btn-sm" @click="cancelForm">Cancelar</button>
          <button class="btn btn-primary btn-sm" :disabled="saving || !form.title" @click="save">
            {{ saving ? 'Salvando...' : (editingId ? 'Atualizar' : 'Adicionar') }}
          </button>
        </div>
        <p v-if="formError" class="form-error">{{ formError }}</p>
      </div>

      <div v-if="filteredTasks.length === 0" class="task-empty">
        <span class="empty-icon">{{ tasks.length === 0 ? '📋' : '🔍' }}</span>
        <p>{{ tasks.length === 0 ? 'Nenhuma tarefa de produção cadastrada' : 'Nenhuma tarefa encontrada para este filtro' }}</p>
        <button v-if="tasks.length === 0" class="btn btn-ghost btn-sm" @click="openCreate">Adicionar primeira tarefa</button>
      </div>

      <div v-else class="task-list" ref="listEl">
        <div
          v-for="task in filteredTasks"
          :key="task.id"
          class="task-item"
          :class="['task-priority-' + task.priority, { 'task-completed': task.status === 'completed', 'task-cancelled': task.status === 'cancelled' }]"
        >
          <button class="task-check" @click="toggleStatus(task)" :title="task.status === 'completed' ? 'Reabrir' : 'Concluir'">
            <svg v-if="task.status === 'completed'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#22C55E" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="9 12 11 14 15 10"/></svg>
            <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#CBD5E1" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>
          </button>
          <div class="task-body" @click="openEdit(task)">
            <div class="task-head">
              <span class="task-title">{{ task.title }}</span>
              <span :class="['badge', 'badge-xs', priorityBadge(task.priority)]">{{ priorityLabel(task.priority) }}</span>
              <span :class="['badge', 'badge-xs', categoryBadge(task.category)]">{{ categoryIcon(task.category) }} {{ categoryLabel(task.category) }}</span>
              <span v-if="task.status === 'in_progress'" class="badge badge-xs badge-info">Em andamento</span>
              <span v-if="task.status === 'cancelled'" class="badge badge-xs badge-danger">Cancelada</span>
            </div>
            <p v-if="task.description" class="task-desc">{{ task.description }}</p>
            <div class="task-meta">
              <span v-if="task.assigned_to">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                {{ task.assigned_to }}
              </span>
              <span v-if="task.due_date" :class="{ 'task-overdue': isOverdue(task) }">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                {{ formatDue(task.due_date) }}
              </span>
            </div>
          </div>
          <div class="task-actions">
            <button class="btn btn-ghost btn-xs" @click.stop="openEdit(task)" title="Editar">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button class="btn btn-ghost btn-xs" @click.stop="confirmRemove(task)" title="Excluir">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
            </button>
          </div>
        </div>
      </div>
    </template>

    <div v-if="deleteTarget" class="modal-overlay" @click.self="deleteTarget = null">
      <div class="modal-box">
        <h3>Excluir tarefa?</h3>
        <p>Tem certeza que deseja excluir <strong>{{ deleteTarget.title }}</strong>?</p>
        <div class="flex-gap" style="justify-content:flex-end;margin-top:20px;">
          <button class="btn btn-secondary" @click="deleteTarget = null">Cancelar</button>
          <button class="btn btn-danger" :disabled="deleting" @click="doRemove">
            {{ deleting ? 'Excluindo...' : 'Excluir' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { tasksApi } from '../services/api.js'
import { useToast } from '../composables/useToast.js'
import LoadingState from './LoadingState.vue'
import ErrorMessage from './ErrorMessage.vue'

const props = defineProps({
  eventId: { type: [Number, String], required: true },
})

const { show: showToast } = useToast()

const tasks = ref([])
const loading = ref(true)
const loadError = ref(null)
const showForm = ref(false)
const editingId = ref(null)
const saving = ref(false)
const formError = ref(null)
const filter = ref('all')
const deleteTarget = ref(null)
const deleting = ref(false)

const categories = [
  { key: 'logistics', icon: '🚚', label: 'Logística' },
  { key: 'venue', icon: '📍', label: 'Local' },
  { key: 'equipment', icon: '🔧', label: 'Equipamento' },
  { key: 'team', icon: '👥', label: 'Equipe' },
  { key: 'marketing', icon: '📢', label: 'Marketing' },
  { key: 'finance', icon: '💰', label: 'Financeiro' },
  { key: 'other', icon: '📌', label: 'Outro' },
]

const categoryMap = Object.fromEntries(categories.map(c => [c.key, c]))

const filterOptions = computed(() => {
  const counts = { all: tasks.value.length }
  const labels = { all: 'Todas' }
  for (const c of categories) {
    const count = tasks.value.filter(t => t.category === c.key).length
    if (count > 0) {
      counts[c.key] = count
      labels[c.key] = `${c.icon} ${c.label}`
    }
  }
  return Object.entries(counts).map(([key, count]) => ({ key, label: labels[key], count }))
})

const filteredTasks = computed(() => {
  if (filter.value === 'all') return tasks.value
  return tasks.value.filter(t => t.category === filter.value)
})

const progress = computed(() => {
  const total = tasks.value.length
  const done = tasks.value.filter(t => t.status === 'completed').length
  return { total, done, percent: total > 0 ? Math.round((done / total) * 100) : 0 }
})

const progressClass = computed(() => {
  const p = progress.value.percent
  if (p >= 100) return 'progress-done'
  if (p >= 60) return 'progress-good'
  if (p >= 30) return 'progress-warn'
  return ''
})

const form = ref(emptyForm())

function emptyForm() {
  return {
    title: '',
    description: '',
    assigned_to: '',
    due_date: '',
    priority: 'medium',
    status: 'pending',
    category: 'other',
  }
}

function openCreate() {
  editingId.value = null
  form.value = emptyForm()
  formError.value = null
  showForm.value = true
}

function openEdit(task) {
  editingId.value = task.id
  form.value = {
    title: task.title || '',
    description: task.description || '',
    assigned_to: task.assigned_to || '',
    due_date: task.due_date || '',
    priority: task.priority || 'medium',
    status: task.status || 'pending',
    category: task.category || 'other',
  }
  formError.value = null
  showForm.value = true
}

function cancelForm() {
  showForm.value = false
  editingId.value = null
  form.value = emptyForm()
  formError.value = null
}

function priorityLabel(p) {
  return { low: 'Baixa', medium: 'Média', high: 'Alta', critical: 'Crítica' }[p] || p
}

function priorityBadge(p) {
  return { low: 'badge-neutral', medium: 'badge-info', high: 'badge-warning', critical: 'badge-danger' }[p] || 'badge-neutral'
}

function categoryLabel(key) {
  return categoryMap[key]?.label || key
}

function categoryIcon(key) {
  return categoryMap[key]?.icon || '📌'
}

function categoryBadge(key) {
  return { logistics: 'badge-warning', venue: 'badge-info', equipment: 'badge-neutral', team: 'badge-success', marketing: 'badge-danger', finance: 'badge-neutral' }[key] || 'badge-neutral'
}

function isOverdue(task) {
  if (!task.due_date || task.status === 'completed' || task.status === 'cancelled') return false
  return new Date(task.due_date + 'T23:59:59') < new Date()
}

function formatDue(date) {
  if (!date) return ''
  const d = new Date(date + 'T12:00:00')
  const now = new Date()
  const diff = Math.ceil((d - now) / (1000 * 60 * 60 * 24))
  const formatted = d.toLocaleDateString('pt-BR', { day: 'numeric', month: 'short' })
  if (diff < 0) return `${formatted} (${Math.abs(diff)}d atrasado)`
  if (diff === 0) return `${formatted} (hoje)`
  if (diff === 1) return `${formatted} (amanhã)`
  if (diff <= 7) return `${formatted} (${diff}d)`
  return formatted
}

async function toggleStatus(task) {
  const newStatus = task.status === 'completed' ? 'pending' : 'completed'
  try {
    await tasksApi.updateStatus(props.eventId, task.id, newStatus)
    showToast(newStatus === 'completed' ? 'Tarefa concluída!' : 'Tarefa reaberta', 'success')
    await load()
  } catch (e) {
    showToast(e.message, 'error')
  }
}

async function save() {
  if (!form.value.title) return
  saving.value = true
  formError.value = null
  try {
    if (editingId.value) {
      await tasksApi.update(props.eventId, editingId.value, form.value)
      showToast('Tarefa atualizada', 'success')
    } else {
      await tasksApi.create(props.eventId, form.value)
      showToast('Tarefa criada', 'success')
    }
    cancelForm()
    await load()
  } catch (e) {
    formError.value = e.message
  } finally {
    saving.value = false
  }
}

function confirmRemove(task) {
  deleteTarget.value = task
}

async function doRemove() {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await tasksApi.remove(props.eventId, deleteTarget.value.id)
    showToast('Tarefa excluída', 'success')
    deleteTarget.value = null
    await load()
  } catch (e) {
    showToast(e.message, 'error')
  } finally {
    deleting.value = false
  }
}

async function load() {
  loading.value = true
  loadError.value = null
  try {
    const res = await tasksApi.list(props.eventId)
    tasks.value = res.data ?? []
  } catch (e) {
    loadError.value = e.message
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.progress-bar-wrap {
  height: 5px;
  background: var(--color-border);
  border-radius: var(--radius-full);
  margin-bottom: 16px;
  overflow: hidden;
}

.progress-bar {
  height: 100%;
  border-radius: var(--radius-full);
  transition: width 0.4s ease;
  background: var(--color-primary);
}

.progress-bar.progress-done { background: var(--color-success); }
.progress-bar.progress-good { background: var(--color-info); }
.progress-bar.progress-warn { background: var(--color-warning); }

.progress-label {
  font-size: 11px;
  font-weight: 500;
  color: var(--color-text-secondary);
  margin-left: 8px;
}

.filter-tabs {
  display: flex;
  gap: 4px;
  overflow-x: auto;
}

.filter-tab {
  font-size: 11px;
  padding: 4px 10px;
  border-radius: var(--radius-full);
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text-secondary);
  cursor: pointer;
  white-space: nowrap;
  transition: all var(--transition);
  font-family: var(--font);
}

.filter-tab:hover {
  border-color: var(--color-primary);
  color: var(--color-primary);
}

.filter-tab.active {
  background: var(--color-primary);
  color: #fff;
  border-color: var(--color-primary);
}

.filter-count {
  margin-left: 4px;
  font-size: 10px;
  opacity: 0.7;
}

.task-form {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 20px;
  margin-bottom: 20px;
  background: var(--color-bg);
}

.task-form h4 {
  font-size: 15px;
  font-weight: 700;
  margin-bottom: 16px;
}

.tf-row {
  display: flex;
  gap: 12px;
  margin-bottom: 12px;
}

.tf-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
  min-width: 0;
}

.tf-field label {
  font-size: 11px;
  font-weight: 600;
  color: var(--color-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.tf-field-full {
  flex: 2;
}

.tf-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  margin-top: 4px;
}

.task-empty {
  text-align: center;
  padding: 32px 16px;
  color: var(--color-text-secondary);
}

.task-empty .empty-icon {
  font-size: 28px;
  display: block;
  margin-bottom: 8px;
}

.task-empty p {
  font-size: 13px;
  margin-bottom: 12px;
}

.task-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.task-item {
  display: flex;
  gap: 10px;
  padding: 12px;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
  transition: border-color 0.15s, opacity 0.15s;
  align-items: flex-start;
}

.task-item:hover {
  border-color: var(--color-primary);
}

.task-completed {
  opacity: 0.6;
}

.task-completed .task-title {
  text-decoration: line-through;
  color: var(--color-text-muted);
}

.task-cancelled {
  opacity: 0.4;
}

.task-cancelled .task-title {
  text-decoration: line-through;
  color: var(--color-text-muted);
}

.task-priority-critical {
  border-left: 3px solid var(--color-danger);
}

.task-priority-high {
  border-left: 3px solid var(--color-warning);
}

.task-priority-medium {
  border-left: 3px solid var(--color-info);
}

.task-priority-low {
  border-left: 3px solid var(--color-border);
}

.task-check {
  flex-shrink: 0;
  background: none;
  border: none;
  cursor: pointer;
  padding: 2px;
  margin-top: 1px;
  line-height: 0;
}

.task-check:hover svg {
  transform: scale(1.15);
}

.task-body {
  flex: 1;
  min-width: 0;
  cursor: pointer;
}

.task-head {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  margin-bottom: 2px;
}

.task-title {
  font-size: 14px;
  font-weight: 600;
}

.task-desc {
  font-size: 12px;
  color: var(--color-text-secondary);
  margin: 2px 0;
  line-height: 1.4;
}

.task-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  font-size: 11.5px;
  color: var(--color-text-secondary);
  margin-top: 4px;
}

.task-meta span {
  display: flex;
  align-items: center;
  gap: 3px;
}

.task-overdue {
  color: var(--color-danger);
  font-weight: 600;
}

.task-actions {
  display: flex;
  gap: 2px;
  flex-shrink: 0;
  opacity: 0;
  transition: opacity 0.15s;
}

.task-item:hover .task-actions {
  opacity: 1;
}

.form-error {
  font-size: 12px;
  color: #EF4444;
  margin-top: 8px;
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

@media (max-width: 600px) {
  .tf-row {
    flex-direction: column;
    gap: 8px;
  }
  .task-actions {
    opacity: 1;
  }
  .filter-tabs {
    display: none;
  }
}
</style>
