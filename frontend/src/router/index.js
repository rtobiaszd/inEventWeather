import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  {
    path: '/login',
    name: 'login',
    component: () => import('../pages/Login.vue'),
    meta: { title: 'Login', public: true },
  },
  {
    path: '/',
    redirect: '/dashboard',
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: () => import('../pages/Dashboard.vue'),
    meta: { title: 'Dashboard' },
  },
  {
    path: '/weather',
    name: 'weather',
    component: () => import('../pages/Weather.vue'),
    meta: { title: 'Clima', module: 'weather' },
  },
  {
    path: '/events',
    name: 'events',
    component: () => import('../pages/Events.vue'),
    meta: { title: 'Eventos', module: 'events' },
  },
  {
    path: '/events/create',
    name: 'events.create',
    component: () => import('../pages/EventCreate.vue'),
    meta: { title: 'Novo Evento', module: 'events', action: 'create' },
  },
  {
    path: '/events/:id/edit',
    name: 'events.edit',
    component: () => import('../pages/EventEdit.vue'),
    meta: { title: 'Editar Evento', module: 'events', action: 'edit' },
  },
  {
    path: '/events/map',
    name: 'events.map',
    component: () => import('../pages/EventsMap.vue'),
    meta: { title: 'Mapa de Eventos', module: 'events' },
  },
  {
    path: '/events/agenda',
    name: 'events.agenda',
    component: () => import('../pages/Agenda.vue'),
    meta: { title: 'Agenda', module: 'events' },
  },
  {
    path: '/favorites',
    name: 'favorites',
    component: () => import('../pages/Favorites.vue'),
    meta: { title: 'Favoritos', module: 'favorites' },
  },
  {
    path: '/history',
    name: 'history',
    component: () => import('../pages/History.vue'),
    meta: { title: 'Histórico', module: 'history' },
  },
  // Admin
  {
    path: '/admin/users',
    name: 'admin.users',
    component: () => import('../pages/admin/Users.vue'),
    meta: { title: 'Usuários', adminOnly: true },
  },
  {
    path: '/admin/event-types',
    name: 'admin.event-types',
    component: () => import('../pages/admin/EventTypes.vue'),
    meta: { title: 'Tipos de Evento', adminOnly: true },
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/dashboard',
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('ew_token')
  const user  = JSON.parse(localStorage.getItem('ew_user') || 'null')

  if (!to.meta.public && !token) {
    return next({ name: 'login' })
  }

  if (to.name === 'login' && token) {
    return next({ name: 'dashboard' })
  }

  if (to.meta.adminOnly && user?.role !== 'admin') {
    return next({ name: 'dashboard' })
  }

  next()
})

router.afterEach((to) => {
  document.title = to.meta.title
    ? `${to.meta.title} — Event Weather`
    : 'Event Weather Dashboard'
})

export default router
