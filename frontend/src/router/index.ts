import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const staffRoutes = [
  { path: '/members', name: 'members', component: () => import('@/views/ResourceView.vue'), meta: { title: 'Members', resource: 'members', staff: true } },
  { path: '/inventory', name: 'inventory', component: () => import('@/views/ResourceView.vue'), meta: { title: 'Inventory', resource: 'copies', staff: true } },
  { path: '/authors', name: 'authors', component: () => import('@/views/ResourceView.vue'), meta: { title: 'Authors', resource: 'authors', staff: true } },
  { path: '/publishers', name: 'publishers', component: () => import('@/views/ResourceView.vue'), meta: { title: 'Publishers', resource: 'publishers', staff: true } },
  { path: '/categories', name: 'categories', component: () => import('@/views/ResourceView.vue'), meta: { title: 'Categories', resource: 'categories', staff: true } },
  { path: '/reports', name: 'reports', component: () => import('@/views/ReportsView.vue'), meta: { title: 'Reports', staff: true } },
  { path: '/audit', name: 'audit', component: () => import('@/views/ResourceView.vue'), meta: { title: 'Audit log', resource: 'audit', admin: true } },
  { path: '/settings', name: 'settings', component: () => import('@/views/SettingsView.vue'), meta: { title: 'Settings', staff: true } },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/login', name: 'login', component: () => import('@/views/LoginView.vue'), meta: { guest: true, title: 'Sign in' } },
    { path: '/register', name: 'register', component: () => import('@/views/RegisterView.vue'), meta: { guest: true, title: 'Register' } },
    { path: '/', redirect: '/dashboard' },
    { path: '/dashboard', name: 'dashboard', component: () => import('@/views/DashboardView.vue'), meta: { title: 'Overview' } },
    { path: '/catalog', name: 'catalog', component: () => import('@/views/ResourceView.vue'), meta: { title: 'Book catalog', resource: 'books' } },
    ...staffRoutes,
    { path: '/circulation', name: 'circulation', component: () => import('@/views/CirculationView.vue'), meta: { title: 'Circulation' } },
    { path: '/reservations', name: 'reservations', component: () => import('@/views/ReservationsView.vue'), meta: { title: 'Reservations' } },
    { path: '/fines', name: 'fines', component: () => import('@/views/FinesView.vue'), meta: { title: 'Fines' } },
    { path: '/notifications', name: 'notifications', component: () => import('@/views/NotificationsView.vue'), meta: { title: 'Notifications' } },
    { path: '/assistant', name: 'assistant', component: () => import('@/views/AssistantView.vue'), meta: { title: 'Library assistant' } },
    { path: '/:pathMatch(.*)*', redirect: '/dashboard' },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  if (!auth.ready) await auth.fetchMe()
  if (!to.meta.guest && !auth.user) return { name: 'login', query: { redirect: to.fullPath } }
  if (to.meta.guest && auth.user) return { name: 'dashboard' }
  if (to.meta.staff && !auth.isStaff) return { name: 'dashboard' }
  if (to.meta.admin && !auth.isAdmin) return { name: 'dashboard' }
  return true
})

export default router
