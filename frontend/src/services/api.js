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
  search:     (city, country = 'BR') => http.get('/weather/search',      { params: { city, country } }),
  forecast:   (city, country = 'BR') => http.get('/weather/forecast',    { params: { city, country } }),
  airQuality: (city, country = 'BR') => http.get('/weather/air-quality', { params: { city, country } }),
}

export const eventsApi = {
  list:   ()         => http.get('/events'),
  get:    (id)       => http.get(`/events/${id}`),
  create: (data)     => http.post('/events', data),
  update: (id, data) => http.put(`/events/${id}`, data),
  remove: (id)       => http.delete(`/events/${id}`),
}

export const historyApi = {
  list: (limit = 50) => http.get('/history', { params: { limit } }),
}

export const favoritesApi = {
  list:   ()     => http.get('/favorites'),
  add:    (data) => http.post('/favorites', data),
  remove: (id)   => http.delete(`/favorites/${id}`),
}

export default http
