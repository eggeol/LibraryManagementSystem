<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  Activity, ArrowLeftRight, Bell, BookOpen, Building2, CalendarClock, ChartNoAxesColumn,
  ChevronRight, CircleHelp, LayoutDashboard, LogOut, Menu, MessageCircle, Settings,
  ShieldCheck, Tags, UserRound, Users, Wallet, X,
} from '@lucide/vue'
import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

defineProps<{ mobileOpen: boolean }>()
const emit = defineEmits<{ 'update:mobileOpen': [value: boolean] }>()
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const unread = ref(0)
const greeting = computed(() => auth.user?.name?.split(' ')[0] ?? 'there')

const navGroups = computed(() => {
  const groups = [
    { label: 'Workspace', items: [{ label: 'Overview', to: '/dashboard', icon: LayoutDashboard }] },
    { label: 'Collection', items: [{ label: 'Book catalog', to: '/catalog', icon: BookOpen }] },
    { label: 'My account', items: [
      { label: auth.isStaff ? 'Members' : 'My loans', to: auth.isStaff ? '/members' : '/circulation', icon: Users },
      { label: 'Reservations', to: '/reservations', icon: CalendarClock },
      { label: 'Fines & payments', to: '/fines', icon: Wallet },
      { label: 'Notifications', to: '/notifications', icon: Bell },
    ] },
  ]
  if (auth.isStaff) {
    groups.splice(2, 0, { label: 'Collection tools', items: [
      { label: 'Inventory', to: '/inventory', icon: Building2 },
      { label: 'Authors', to: '/authors', icon: UserRound },
      { label: 'Publishers', to: '/publishers', icon: Building2 },
      { label: 'Categories', to: '/categories', icon: Tags },
    ] })
    groups.splice(3, 0, { label: 'Operations', items: [
      { label: 'Circulation desk', to: '/circulation', icon: ArrowLeftRight },
      { label: 'Reports', to: '/reports', icon: ChartNoAxesColumn },
    ] })
    if (auth.isAdmin) {
      groups.push({ label: 'Administration', items: [
        { label: 'Audit log', to: '/audit', icon: ShieldCheck },
        { label: 'Settings', to: '/settings', icon: Settings },
      ] })
    }
  }
  groups.push({ label: 'Support', items: [{ label: 'Library assistant', to: '/assistant', icon: MessageCircle }] })
  return groups
})

async function loadUnread() {
  try {
    const { data } = await api.get('/notifications')
    unread.value = data.unread_count
  } catch { unread.value = 0 }
}
async function logout() {
  await auth.logout()
  await router.replace('/login')
}
onMounted(loadUnread)
</script>

<template>
  <div class="min-h-screen bg-[#f4f6fc] text-slate-900">
    <div v-if="mobileOpen" class="fixed inset-0 z-40 bg-slate-950/35 lg:hidden" @click="emit('update:mobileOpen', false)" />
    <aside
      class="fixed inset-y-0 left-0 z-50 flex w-[258px] flex-col border-r border-slate-200 bg-white transition-transform lg:translate-x-0"
      :class="mobileOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <div class="flex h-[76px] items-center justify-between border-b border-slate-100 px-5">
        <RouterLink to="/dashboard" class="flex items-center gap-3">
          <img src="/lnu-logo.png" alt="Leyte Normal University seal" class="size-10 rounded-full object-contain">
          <span><span class="block text-sm font-bold tracking-tight">LNU SmartLib</span><span class="block text-[11px] text-slate-500">Campus library portal</span></span>
        </RouterLink>
        <button class="rounded-lg p-2 text-slate-500 lg:hidden" aria-label="Close navigation" @click="emit('update:mobileOpen', false)"><X :size="18" /></button>
      </div>

      <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
        <section v-for="group in navGroups" :key="group.label">
          <p class="mb-2 px-3 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">{{ group.label }}</p>
          <div class="space-y-1">
            <RouterLink
              v-for="item in group.items" :key="item.to" :to="item.to"
              class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] font-medium transition"
              :class="route.path === item.to ? 'bg-lnu-50 text-lnu-950' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950'"
            >
              <component :is="item.icon" :size="17" :stroke-width="1.8" />
              <span class="flex-1">{{ item.label }}</span>
              <span v-if="item.to === '/notifications' && unread" class="rounded-full bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-rose-700">{{ unread }}</span>
              <ChevronRight v-if="route.path === item.to" :size="14" class="text-lnu-800" />
            </RouterLink>
          </div>
        </section>
      </nav>

      <div class="border-t border-slate-100 p-3">
        <div class="flex items-center gap-3 rounded-xl bg-slate-50 p-3">
          <span class="grid size-9 place-items-center rounded-full bg-lnu-100 text-xs font-bold text-lnu-900">{{ greeting.slice(0, 1).toUpperCase() }}</span>
          <div class="min-w-0 flex-1">
            <p class="truncate text-xs font-semibold">{{ auth.user?.name }}</p>
            <p class="truncate text-[10px] capitalize text-slate-500">{{ auth.user?.role }}</p>
          </div>
          <button class="rounded-lg p-2 text-slate-500 hover:bg-white hover:text-slate-900" title="Sign out" @click="logout"><LogOut :size="16" /></button>
        </div>
      </div>
    </aside>

    <div class="lg:pl-[258px]">
      <header class="sticky top-0 z-30 flex h-[76px] items-center justify-between border-b border-slate-200/80 bg-white/90 px-5 backdrop-blur sm:px-8">
        <div class="flex items-center gap-3">
          <button class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="Open navigation" @click="emit('update:mobileOpen', true)"><Menu :size="19" /></button>
          <div><p class="text-sm font-semibold">{{ route.meta.title }}</p><p class="hidden text-[11px] text-slate-500 sm:block">Welcome back, {{ auth.user?.name }}. Here’s your library at a glance.</p></div>
        </div>
        <div class="flex items-center gap-2">
          <span v-if="auth.isStaff" class="hidden rounded-full border border-lnu-100 bg-lnu-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-lnu-800 sm:inline-flex">{{ auth.user?.role }} access</span>
          <RouterLink to="/notifications" class="relative rounded-xl border border-slate-200 p-2.5 text-slate-600 transition hover:bg-slate-50" aria-label="Notifications">
            <Bell :size="17" />
            <span v-if="unread" class="absolute right-1.5 top-1.5 size-2 rounded-full bg-rose-500 ring-2 ring-white" />
          </RouterLink>
          <RouterLink to="/assistant" class="hidden items-center gap-2 rounded-xl bg-lnu-950 px-3 py-2.5 text-xs font-semibold text-white transition hover:bg-lnu-900 sm:inline-flex"><CircleHelp :size="15" /> Help</RouterLink>
        </div>
      </header>
      <main class="mx-auto max-w-[1500px] p-5 sm:p-8">
        <slot />
      </main>
      <footer class="mx-auto flex max-w-[1500px] items-center justify-between px-5 pb-7 text-[11px] text-slate-400 sm:px-8">
        <span>Library operations workspace</span><span class="inline-flex items-center gap-1.5"><Activity :size="12" /> Secure session</span>
      </footer>
    </div>
  </div>
</template>
