import axios from 'axios'

const BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8080/api'

const http = axios.create({
  baseURL: BASE_URL,
  timeout: 15000,
  headers: { 'Content-Type': 'application/json' },
})

// Injeta token salvo no localStorage em todas as requisições
const storedToken = localStorage.getItem('ew_token')
if (storedToken) {
  http.defaults.headers.common['Authorization'] = `Bearer ${storedToken}`
}

http.interceptors.response.use(
  (res) => res.data,
  (err) => {
    // API retorna { error: '...' } (controller) ou { message: '...' } (exception handler)
    const apiMessage = err.response?.data?.error || err.response?.data?.message

    if (err.response?.status === 401) {
      localStorage.removeItem('ew_token')
      localStorage.removeItem('ew_user')
      delete http.defaults.headers.common['Authorization']
      const onLoginPage = window.location.pathname.includes('/login')
      if (!onLoginPage) {
        window.location.href = '/login'
        return Promise.reject(new Error('Sessão expirada. Faça login novamente.'))
      }
      // Na tela de login: mostra o erro real ("Usuário ou senha incorretos")
      return Promise.reject(new Error(apiMessage || 'Usuário ou senha incorretos'))
    }

    const message = apiMessage ||
      (err.code === 'ECONNABORTED' ? 'Tempo de conexão esgotado' : 'Erro de comunicação com o servidor')
    return Promise.reject(new Error(message))
  }
)

export const weatherApi = {
  search:     (city, country = 'BR', opts = {}) => http.get('/weather/search',      { params: { city, country }, signal: opts.signal }),
  forecast:   (city, country = 'BR') => http.get('/weather/forecast',    { params: { city, country } }),
  airQuality: (city, country = 'BR') => http.get('/weather/air-quality', { params: { city, country } }),
  bestDates:  (city, country = 'BR') => http.get('/weather/best-dates',  { params: { city, country } }),
}

export const eventsApi = {
  list:   ()         => http.get('/events'),
  get:    (id)       => http.get(`/events/${id}`),
  create: (data)     => http.post('/events', data),
  update: (id, data) => http.put(`/events/${id}`, data),
  remove: (id)       => http.delete(`/events/${id}`),
  duplicate: (id)    => http.post(`/events/${id}/duplicate`),
  upcomingWeather: () => http.get('/events/upcoming-weather'),
  riskAlerts:         () => http.get('/events/risk-alerts'),
  financialInsights:  () => http.get('/events/financial-insights'),
}

export const historyApi = {
  list: (limit = 50) => http.get('/history', { params: { limit } }),
}

export const favoritesApi = {
  list:   ()     => http.get('/favorites'),
  add:    (data) => http.post('/favorites', data),
  remove: (id)   => http.delete(`/favorites/${id}`),
}

export const usersApi = {
  list:              ()         => http.get('/users'),
  get:               (id)       => http.get(`/users/${id}`),
  create:            (data)     => http.post('/users', data),
  update:            (id, data) => http.put(`/users/${id}`, data),
  updatePermissions: (id, perms)=> http.patch(`/users/${id}/permissions`, { permissions: perms }),
  remove:            (id)       => http.delete(`/users/${id}`),
}

export const sessionsApi = {
  list:   (eventId)          => http.get(`/events/${eventId}/sessions`),
  get:    (eventId, id)      => http.get(`/events/${eventId}/sessions/${id}`),
  create: (eventId, data)    => http.post(`/events/${eventId}/sessions`, data),
  update: (eventId, id, data)=> http.put(`/events/${eventId}/sessions/${id}`, data),
  remove: (eventId, id)      => http.delete(`/events/${eventId}/sessions/${id}`),
  conflicts: (eventId)       => http.get(`/events/${eventId}/sessions/conflicts`),
  optimize:  (eventId)       => http.get(`/events/${eventId}/sessions/optimize`),
  applyOptimization: (eventId) => http.post(`/events/${eventId}/sessions/optimize/apply`),
}

export const speakersApi = {
  list:   (params)        => http.get('/speakers', { params }),
  get:    (id)            => http.get(`/speakers/${id}`),
  create: (data)          => http.post('/speakers', data),
  update: (id, data)      => http.put(`/speakers/${id}`, data),
  remove: (id)            => http.delete(`/speakers/${id}`),
  linkToEvent:   (speakerId, data) => http.post(`/speakers/${speakerId}/link-event`, data),
  unlinkFromEvent: (speakerId, eventId) => http.delete(`/speakers/${speakerId}/unlink-event/${eventId}`),
  linkToSession: (speakerId, data) => http.post(`/speakers/${speakerId}/link-session`, data),
  unlinkFromSession: (speakerId, sessionId) => http.delete(`/speakers/${speakerId}/unlink-session/${sessionId}`),
}

export const registrationApi = {
  list:       (eventId)            => http.get(`/events/${eventId}/registrations`),
  create:     (eventId, data)      => http.post(`/events/${eventId}/registrations`, data),
  get:        (eventId, id)        => http.get(`/events/${eventId}/registrations/${id}`),
  update:     (eventId, id, data)  => http.put(`/events/${eventId}/registrations/${id}`, data),
  checkIn:    (eventId, id)        => http.post(`/events/${eventId}/registrations/${id}/checkin`),
  undoCheckIn:(eventId, id)        => http.post(`/events/${eventId}/registrations/${id}/checkin/undo`),
  remove:     (eventId, id)        => http.delete(`/events/${eventId}/registrations/${id}`),
}

export const publicApi = {
  list:     (params)  => http.get('/events/public', { params }),
  event:    (id)      => http.get(`/events/${id}/public`),
  register: (id, data)=> http.post(`/events/${id}/register`, data),
}

export const badgeApi = {
  get:            (eventId, token) => http.get(`/events/${eventId}/badge/${token}`),
  checkInByToken: (eventId, token) => http.post(`/events/${eventId}/checkin-by-token`, { token }),
}

export const feedbackApi = {
  form:        (eventId, token)  => http.get(`/events/${eventId}/feedback/${token}`),
  submit:      (eventId, token, data) => http.post(`/events/${eventId}/feedback/${token}`, data),
  results:     (eventId)         => http.get(`/events/${eventId}/feedback/results`),
  list:        (eventId)         => http.get(`/events/${eventId}/feedback`),
}

export const eventTypesApi = {
  list:   ()         => http.get('/event-types'),
  create: (data)     => http.post('/event-types', data),
  update: (id, data) => http.put(`/event-types/${id}`, data),
  remove: (id)       => http.delete(`/event-types/${id}`),
}

export const tasksApi = {
  list:          (eventId)             => http.get(`/events/${eventId}/tasks`),
  create:        (eventId, data)       => http.post(`/events/${eventId}/tasks`, data),
  update:        (eventId, id, data)   => http.put(`/events/${eventId}/tasks/${id}`, data),
  updateStatus:  (eventId, id, status) => http.patch(`/events/${eventId}/tasks/${id}/status`, { status }),
  reorder:       (eventId, tasks)      => http.put(`/events/${eventId}/tasks/reorder`, { tasks }),
  remove:        (eventId, id)         => http.delete(`/events/${eventId}/tasks/${id}`),
}

export const authApi = {
  me:      ()        => http.get('/auth/me'),
  updateProfile: (data) => http.put('/auth/profile', data),
}

export { http }
export default http
