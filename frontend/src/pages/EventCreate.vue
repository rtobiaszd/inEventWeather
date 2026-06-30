<template>
  <div class="stack">
    <div class="page-header">
      <div>
        <h2>Novo Evento</h2>
        <p>Preencha os dados do evento abaixo</p>
      </div>
      <RouterLink to="/events" class="btn btn-secondary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        Voltar
      </RouterLink>
    </div>

    <div class="card">
      <form @submit.prevent="submit" class="form-section">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Nome do Evento <span class="required">*</span></label>
            <input v-model="form.name" type="text" class="form-control" placeholder="Ex: Festival de Verão" required />
          </div>
          <div class="form-group">
            <label class="form-label">Tipo <span class="required">*</span></label>
            <select v-model="form.type" class="form-control" required>
              <option value="outdoor">🌤 Outdoor (ao ar livre)</option>
              <option value="indoor">🏛 Indoor (coberto)</option>
            </select>
          </div>
        </div>

        <div class="form-row-3">
          <div class="form-group">
            <label class="form-label">Cidade <span class="required">*</span></label>
            <input v-model="form.city" type="text" class="form-control" placeholder="Ex: São Paulo" required />
          </div>
          <div class="form-group">
            <label class="form-label">País <span class="required">*</span></label>
            <input v-model="form.country" type="text" class="form-control" placeholder="BR" maxlength="2" required />
          </div>
          <div class="form-group">
            <label class="form-label">Público Estimado</label>
            <input v-model.number="form.expected_audience" type="number" class="form-control" placeholder="0" min="0" />
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Data <span class="required">*</span></label>
            <input v-model="form.event_date" type="date" class="form-control" required />
          </div>
          <div class="form-group">
            <label class="form-label">Horário <span class="required">*</span></label>
            <input v-model="form.event_time" type="time" class="form-control" required />
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Descrição</label>
          <textarea v-model="form.description" class="form-control" placeholder="Descreva o evento..." />
        </div>

        <div v-if="errorMsg" class="alert alert-danger">{{ errorMsg }}</div>

        <div class="flex-gap">
          <button type="submit" class="btn btn-primary" :disabled="submitting">
            {{ submitting ? 'Salvando...' : 'Criar Evento' }}
          </button>
          <RouterLink to="/events" class="btn btn-secondary">Cancelar</RouterLink>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { eventsApi } from '../services/api.js'

const router = useRouter()

const form = ref({
  name: '', city: '', country: 'BR',
  event_date: '', event_time: '',
  type: 'outdoor', expected_audience: 0, description: '',
})

const submitting = ref(false)
const errorMsg   = ref(null)

async function submit() {
  submitting.value = true
  errorMsg.value   = null
  try {
    await eventsApi.create(form.value)
    router.push({ name: 'events' })
  } catch (e) {
    errorMsg.value = e.message
  } finally {
    submitting.value = false
  }
}
</script>
