import { ref, computed } from 'vue'
import http from '../services/api.js'

// Defaults de permissão por role (usado quando permissions === null)
const ROLE_DEFAULTS = {
  admin: {
    events:    { view: true, create: true, edit: true, delete: true },
    weather:   { view: true },
    favorites: { view: true, manage: true },
    history:   { view: true },
    users:     { manage: true },
    reports:   { view: true, export: true },
  },
  editor: {
    events:    { view: true, create: true, edit: true, delete: false },
    weather:   { view: true },
    favorites: { view: true, manage: true },
    history:   { view: true },
    users:     { manage: false },
    reports:   { view: true, export: true },
  },
  viewer: {
    events:    { view: true, create: false, edit: false, delete: false },
    weather:   { view: true },
    favorites: { view: true, manage: false },
    history:   { view: true },
    users:     { manage: false },
    reports:   { view: true, export: true },
  },
}

// Singleton — estado compartilhado entre todos os componentes
const _token = ref(localStorage.getItem('ew_token') || null)
const _user  = ref(JSON.parse(localStorage.getItem('ew_user') || 'null'))

if (_token.value) {
  http.defaults.headers.common['Authorization'] = `Bearer ${_token.value}`
}

function _persist(data) {
  _token.value = data.token
  _user.value  = data.user
  localStorage.setItem('ew_token', data.token)
  localStorage.setItem('ew_user', JSON.stringify(data.user))
  http.defaults.headers.common['Authorization'] = `Bearer ${data.token}`
}

export function useAuth() {
  const isLoggedIn = computed(() => !!_token.value)
  const isAdmin    = computed(() => _user.value?.role === 'admin')

  function can(module, action = 'view') {
    const user = _user.value
    if (!user) return false
    const role  = user.role || 'viewer'
    const perms = user.permissions ?? ROLE_DEFAULTS[role] ?? ROLE_DEFAULTS.viewer
    return perms[module]?.[action] === true
  }

  async function login(username, password) {
    const res = await http.post('/auth/login', { username, password })
    _persist(res.data)
  }

  async function register(name, username, password) {
    const res = await http.post('/auth/register', { name, username, password })
    _persist(res.data)
  }

  async function logout() {
    try { await http.post('/auth/logout') } catch (_) {}
    _token.value = null
    _user.value  = null
    localStorage.removeItem('ew_token')
    localStorage.removeItem('ew_user')
    delete http.defaults.headers.common['Authorization']
  }

  return {
    user:      _user,
    token:     _token,
    isLoggedIn,
    isAdmin,
    can,
    login,
    register,
    logout,
  }
}

export { ROLE_DEFAULTS }
