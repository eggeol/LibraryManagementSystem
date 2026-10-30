<script setup lang="ts">
import { onMounted, ref } from 'vue'
import {
  ArrowDownRight, ArrowRight, ArrowUpRight, BookOpen, Bot, CalendarClock, CircleAlert,
  CircleCheck, LoaderCircle, RefreshCw, Sparkles, Users, Wallet,
} from '@lucide/vue'
import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

type DashboardData = {
  stats: Record<string, number>
  recent_loans: Array<{ id: number; due_at: string; borrowed_at: string; copy: { book: { title: string } }; member: { name: string; membership_code: string } }>
  recent_activity: Array<{ id: number; action: string; created_at: string; user?: { name: string } }>
}
const auth = useAuthStore()
const data = ref<DashboardData | null>(null)
const loading = ref(true)
const issue = ref('')
async function load() {
  loading.value = true
  issue.value = ''
  try { data.value = (await api.get<DashboardData>('/dashboard')).data }
  catch { issue.value = 'We could not load your library overview.' }
  finally { loading.value = false }
}
onMounted(load)

const tiles = [
  { key: 'titles', label: 'Book titles', icon: BookOpen, tone: 'lnu' },
  { key: 'available_copies', label: 'Available copies', icon: CircleCheck, tone: 'blue' },
  { key: 'members', label: 'Active members', icon: Users, tone: 'violet' },
  { key: 'active_loans', label: 'Books on loan', icon: CalendarClock, tone: 'amber' },
]
const formatDate = (value: string) => new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric' }).format(new Date(value))
const actionLabel = (value: string) => value.replaceAll('.', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
</script>

<template>
  <div class="space-y-7">
    <section class="relative overflow-hidden rounded-[26px] bg-[#111968] p-6 text-white shadow-sm sm:p-9">
      <div class="pointer-events-none absolute -right-20 -top-32 size-[360px] rounded-full border border-white/10" />
      <div class="pointer-events-none absolute -right-4 -top-16 size-[230px] rounded-full border border-white/10" />
      <div class="relative flex flex-col justify-between gap-7 lg:flex-row lg:items-end">
        <div class="max-w-2xl">
          <p class="mb-3 text-[10px] font-bold uppercase tracking-[0.2em] text-blue-200">LNU SmartLib</p>
          <h1 class="text-3xl font-semibold leading-tight tracking-[-0.03em] sm:text-4xl">Your library, smarter.</h1>
          <p class="mt-3 max-w-xl text-sm leading-6 text-blue-50/75">Explore the collection and keep your library activity in view from one campus workspace.</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <RouterLink to="/catalog" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-xs font-semibold text-lnu-950 transition hover:bg-lnu-50"><BookOpen :size="15" /> Browse catalog</RouterLink>
          <RouterLink v-if="auth.isStaff" to="/circulation" class="inline-flex items-center gap-2 rounded-xl border border-white/25 px-4 py-2.5 text-xs font-semibold text-white transition hover:bg-white/10"><CalendarClock :size="15" /> Circulation desk</RouterLink>
          <RouterLink v-else to="/reservations" class="inline-flex items-center gap-2 rounded-xl border border-white/25 px-4 py-2.5 text-xs font-semibold text-white transition hover:bg-white/10"><CalendarClock :size="15" /> My reservations</RouterLink>
        </div>
      </div>
    </section>

    <div v-if="issue" class="flex items-center justify-between rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"><span>{{ issue }}</span><button class="font-semibold" @click="load">Retry</button></div>
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <article v-for="tile in tiles" :key="tile.key" class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
        <div class="flex items-start justify-between">
          <div><p class="text-xs font-medium text-slate-500">{{ tile.label }}</p><p class="mt-3 text-3xl font-semibold tracking-tight">{{ loading ? '—' : (data?.stats[tile.key] ?? 0).toLocaleString() }}</p></div>
          <span class="grid size-10 place-items-center rounded-xl bg-lnu-50 text-lnu-900"><component :is="tile.icon" :size="18" /></span>
        </div>
        <div class="mt-4 flex items-center gap-1 text-[11px] text-slate-400"><ArrowUpRight :size="13" class="text-lnu-700" /> Live from your library database</div>
      </article>
    </section>

    <section class="grid gap-5 xl:grid-cols-[1.45fr_0.75fr]">
      <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
          <div><h2 class="text-sm font-semibold">Currently on loan</h2><p class="mt-1 text-[11px] text-slate-500">Recent active checkouts and due dates</p></div>
          <RouterLink to="/circulation" class="inline-flex items-center gap-1 text-xs font-semibold text-lnu-900 hover:text-lnu-700">View all <ArrowRight :size="14" /></RouterLink>
        </div>
        <div v-if="loading" class="grid min-h-40 place-items-center"><LoaderCircle :size="20" class="animate-spin text-lnu-800" /></div>
        <div v-else-if="!data?.recent_loans.length" class="p-10 text-center"><BookOpen :size="25" class="mx-auto text-slate-300" /><p class="mt-3 text-sm font-medium text-slate-700">No active checkouts yet</p><p class="mt-1 text-xs text-slate-500">When books are checked out, they’ll show up here.</p></div>
        <div v-else class="divide-y divide-slate-100">
          <div v-for="loan in data.recent_loans" :key="loan.id" class="flex items-center justify-between gap-4 px-5 py-4">
            <div class="flex min-w-0 items-center gap-3">
              <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-slate-50 text-lnu-900"><BookOpen :size="17" /></span>
              <div class="min-w-0"><p class="truncate text-xs font-semibold">{{ loan.copy.book.title }}</p><p class="mt-1 text-[11px] text-slate-500">{{ auth.isStaff ? loan.member.name : 'Checked out by you' }}</p></div>
            </div>
            <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-semibold" :class="new Date(loan.due_at) < new Date() ? 'bg-rose-50 text-rose-700' : 'bg-lnu-50 text-lnu-800'">Due {{ formatDate(loan.due_at) }}</span>
          </div>
        </div>
      </div>

      <div class="space-y-5">
      <aside class="rounded-2xl border border-slate-200/80 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
          <div><h2 class="text-sm font-semibold">{{ auth.isStaff ? 'Recent activity' : 'Your account' }}</h2><p class="mt-1 text-[11px] text-slate-500">{{ auth.isStaff ? 'A snapshot of library operations' : 'Membership and account status' }}</p></div>
          <RefreshCw :size="15" class="cursor-pointer text-slate-400 hover:text-slate-700" @click="load" />
        </div>
        <div v-if="auth.isStaff" class="divide-y divide-slate-100">
          <div v-for="activity in data?.recent_activity ?? []" :key="activity.id" class="flex gap-3 px-5 py-3.5">
            <span class="mt-0.5 grid size-7 shrink-0 place-items-center rounded-lg bg-slate-50 text-slate-500"><ArrowDownRight :size="14" /></span>
            <div class="min-w-0"><p class="truncate text-[11px] font-semibold text-slate-700">{{ actionLabel(activity.action) }}</p><p class="mt-1 text-[10px] text-slate-400">{{ activity.user?.name ?? 'System' }} · {{ formatDate(activity.created_at) }}</p></div>
          </div>
          <div v-if="!data?.recent_activity.length" class="p-8 text-center text-xs text-slate-400">Activity will appear here as your team works.</div>
        </div>
        <div v-else class="space-y-4 p-5">
          <div class="flex items-center justify-between rounded-xl bg-slate-50 p-3"><span class="text-xs text-slate-600">Member</span><span class="text-xs font-semibold">{{ auth.user?.membership_code }}</span></div>
          <div class="flex items-center justify-between rounded-xl bg-slate-50 p-3"><span class="text-xs text-slate-600">Account status</span><span class="inline-flex items-center gap-1.5 text-xs font-semibold text-lnu-800"><CircleCheck :size="14" /> Active</span></div>
          <div class="rounded-xl border border-amber-100 bg-amber-50 p-3"><p class="flex items-center gap-2 text-xs font-semibold text-amber-900"><CircleAlert :size="14" /> Keep an eye on due dates</p><p class="mt-1 text-[11px] leading-5 text-amber-800/80">Return books on time to avoid late fees and keep the collection moving.</p></div>
          <RouterLink to="/fines" class="flex items-center gap-2 text-xs font-semibold text-lnu-900"><Wallet :size="14" /> View account balance</RouterLink>
        </div>
      </aside>
      <RouterLink to="/assistant" class="block rounded-2xl border border-lnu-100 bg-white p-5 shadow-sm transition hover:border-lnu-200 hover:shadow-md">
        <div class="flex items-start gap-3">
          <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-lnu-50 text-lnu-900"><Bot :size="18" /></span>
          <div class="min-w-0 flex-1">
            <div class="flex items-center justify-between gap-2"><h2 class="text-xs font-semibold">Library assistant</h2><span class="rounded-full bg-lnu-50 px-2 py-1 text-[9px] font-semibold text-lnu-800">Guided help</span></div>
            <p class="mt-1 text-[11px] leading-5 text-slate-500">Get quick answers and shortcuts for common library tasks.</p>
          </div>
          <ArrowRight :size="15" class="mt-1 shrink-0 text-lnu-700" />
        </div>
        <div class="mt-4 flex items-center gap-2 rounded-xl bg-[#f5f7ff] px-3 py-2.5 text-[10px] text-slate-500"><Sparkles :size="13" class="text-lnu-700" />Prepared FAQ answers · No AI service</div>
      </RouterLink>
      </div>
    </section>
  </div>
</template>
