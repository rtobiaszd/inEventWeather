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
    meta: { title: 'Clima' },
  },
  {
    path: '/events',
    name: 'events',
    component: () => import('../pages/Events.vue'),
    meta: { title: 'Eventos' },
  },
  {
    path: '/events/create',
    name: 'events.create',
    component: () => import('../pages/EventCreate.vue'),
    meta: { title: 'Novo Evento' },
  },
  {
    path: '/events/:id/edit',
    name: 'events.edit',
    component: () => import('../pages/EventEdit.vue'),
    meta: { title: 'Editar Evento' },
  },
  {
    path: '/favorites',
    name: 'favorites',
    component: () => import('../pages/Favorites.vue'),
    meta: { title: 'Favoritos' },
  },
  {
    path: '/history',
    name: 'history',
    component: () => import('../pages/History.vue'),
    meta: { title: 'Histórico' },
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('ew_token')

  if (!to.meta.public && !token) {
    next({ name: 'login' })
  } else if (to.name === 'login' && token) {
    next({ name: 'dashboard' })
  } else {
    next()
  }
})

router.afterEach((to) => {
  document.title = to.meta.title
    ? `${to.meta.title} — Event Weather`
    : 'Event Weather Dashboard'
})

export default router
