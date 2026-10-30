<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { BookOpen, CalendarClock, CircleAlert, CircleCheck, LoaderCircle, X } from '@lucide/vue'

type Reservation = { id: number; status: string; reserved_at: string; expires_at: string | null; book: { id: number; title: string; isbn: string | null }; copy: { id: number; accession_number: string; shelf_location: string | null } | null; member: { id: number; name: string; membership_code: string } }
const auth = useAuthStore()
const rows = ref<Reservation[]>([])
const loading = ref(true)
const error = ref('')
const notice = ref('')
const failure = (e: unknown) => {
  const caught = e as { response?: { data?: { message?: string } } }
  return caught.response?.data?.message ?? 'The request could not be completed.'
}
const date = (value: string) => new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
async function load() {
  loading.value = true
  try { rows.value = (await api.get('/reservations?per_page=100')).data.data }
  catch (e) { error.value = failure(e) }
  finally { loading.value = false }
}
async function cancel(item: Reservation) {
  if (!window.confirm('Cancel this reservation?')) return
  try { await api.delete('/reservations/' + item.id); notice.value = 'Reservation cancelled.'; await load() }
  catch (e) { error.value = failure(e) }
}
async function checkOut(item: Reservation) {
  if (!item.copy || !window.confirm('Check out this reserved copy to ' + item.member.name + '?')) return
  try {
    await api.post('/borrowings', { book_copy_id: item.copy.id, user_id: item.member.id })
    notice.value = 'Reserved book checked out.'
    await load()
  } catch (e) { error.value = failure(e) }
}
onMounted(load)
</script>

<template>
  <div class="space-y-5">
    <div><p class="text-[10px] font-bold uppercase tracking-[0.18em] text-lnu-800">Collection requests</p><h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ auth.isStaff ? 'Reservation queue' : 'My reservations' }}</h1><p class="mt-1.5 text-sm text-slate-500">{{ auth.isStaff ? 'Manage holds and waiting requests for the collection.' : 'Your place in line for titles that are currently checked out.' }}</p></div>
    <div v-if="notice" class="flex items-center gap-2 rounded-xl border border-lnu-200 bg-lnu-50 px-4 py-3 text-xs text-lnu-900"><CircleCheck :size="15" />{{ notice }}</div>
    <div v-if="error" role="alert" class="flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-800"><CircleAlert :size="15" />{{ error }}</div>
    <section class="grid gap-4">
      <div v-if="loading" class="grid min-h-48 place-items-center rounded-2xl border border-slate-200 bg-white"><LoaderCircle :size="22" class="animate-spin text-lnu-800" /></div>
      <div v-else-if="!rows.length" class="rounded-2xl border border-slate-200 bg-white p-12 text-center"><CalendarClock :size="27" class="mx-auto text-slate-300" /><p class="mt-3 text-sm font-semibold">No active reservations</p><p class="mt-1 text-xs text-slate-500">Browse the catalog and reserve a title to see it here.</p><RouterLink to="/catalog" class="mt-4 inline-flex rounded-lg bg-lnu-950 px-3 py-2 text-xs font-semibold text-white">Browse catalog</RouterLink></div>
      <article v-for="item in rows" :key="item.id" class="flex flex-col justify-between gap-4 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm sm:flex-row sm:items-center">
        <div class="flex min-w-0 items-start gap-4"><span class="grid size-11 shrink-0 place-items-center rounded-xl bg-lnu-50 text-lnu-900"><BookOpen :size="19" /></span><div class="min-w-0"><p class="truncate text-sm font-semibold">{{ item.book.title }}</p><p v-if="auth.isStaff" class="mt-1 text-xs text-slate-500">{{ item.member.name }} · {{ item.member.membership_code }}</p><p class="mt-1 text-[11px] text-slate-400">Requested {{ date(item.reserved_at) }}</p><p v-if="item.copy" class="mt-2 text-xs font-medium text-lnu-900">Ready for pickup at {{ item.copy.shelf_location || 'the circulation desk' }} · {{ item.copy.accession_number }}</p></div></div>
        <div class="flex items-center justify-between gap-3 sm:justify-end"><span class="rounded-full px-2.5 py-1 text-[10px] font-semibold capitalize" :class="item.status === 'ready' ? 'bg-lnu-50 text-lnu-800' : item.status === 'requested' ? 'bg-amber-50 text-amber-800' : 'bg-slate-100 text-slate-600'">{{ item.status }}</span><span v-if="item.expires_at" class="text-[10px] text-slate-500">Hold until {{ date(item.expires_at) }}</span><button v-if="auth.isStaff && item.status === 'ready'" class="rounded-lg border border-lnu-200 px-2.5 py-1.5 text-[10px] font-semibold text-lnu-900 hover:bg-lnu-50" @click="checkOut(item)">Check out</button><button v-if="['requested', 'ready'].includes(item.status)" class="rounded-lg p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-700" aria-label="Cancel reservation" @click="cancel(item)"><X :size="16" /></button></div>
      </article>
    </section>
  </div>
</template>
