<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { ArrowLeftRight, BookOpen, CalendarClock, CircleAlert, CircleCheck, LoaderCircle, RotateCcw } from '@lucide/vue'

type Loan = { id: number; status: string; borrowed_at: string; due_at: string; returned_at: string | null; copy: { id: number; accession_number?: string; book: { id: number; title: string } }; member: { id: number; name: string; membership_code: string } }
const auth = useAuthStore()
const rows = ref<Loan[]>([])
const copies = ref<Array<{ id: number; accession_number: string; book: { title: string } }>>([])
const members = ref<Array<{ id: number; name: string; membership_code: string }>>([])
const status = ref('')
const loading = ref(true)
const issuing = ref(false)
const formOpen = ref(false)
const copyId = ref('')
const memberId = ref('')
const dueAt = ref('')
const error = ref('')
const notice = ref('')
const isMember = computed(() => !auth.isStaff)

const failure = (e: unknown) => {
  const caught = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
  return Object.values(caught.response?.data?.errors ?? {})[0]?.[0] ?? caught.response?.data?.message ?? 'The request could not be completed.'
}
const shortDate = (date: string) => new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', year: 'numeric' }).format(new Date(date))

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/borrowings', { params: status.value ? { status: status.value, per_page: 100 } : { per_page: 100 } })
    rows.value = data.data
  } catch (e) { error.value = failure(e) }
  finally { loading.value = false }
}
async function loadIssueOptions() {
  const [copyResponse, memberResponse] = await Promise.all([
    api.get('/copies?status=available&per_page=100'),
    api.get('/members?status=active&per_page=100'),
  ])
  copies.value = copyResponse.data.data.filter((copy: any) => copy.status === 'available')
  members.value = memberResponse.data.data.filter((member: any) => member.role === 'member')
}
async function openIssue() {
  error.value = ''
  try {
    await loadIssueOptions()
    copyId.value = ''
    memberId.value = ''
    dueAt.value = ''
    formOpen.value = true
  } catch (e) { error.value = failure(e) }
}
async function issue() {
  issuing.value = true
  error.value = ''
  try {
    await api.post('/borrowings', { book_copy_id: Number(copyId.value), user_id: Number(memberId.value), ...(dueAt.value ? { due_at: new Date(dueAt.value).toISOString() } : {}) })
    notice.value = 'The book has been checked out.'
    formOpen.value = false
    await load()
  } catch (e) { error.value = failure(e) }
  finally { issuing.value = false }
}
async function returnCopy(loan: Loan) {
  if (!window.confirm('Check this book back in?')) return
  error.value = ''
  try {
    const { data } = await api.post('/borrowings/' + loan.id + '/return')
    notice.value = data.fine ? 'Book returned. An overdue fine was added to the member account.' : 'Book returned successfully.'
    await load()
  } catch (e) { error.value = failure(e) }
}
async function renew(loan: Loan) {
  error.value = ''
  try { await api.post('/borrowings/' + loan.id + '/renew'); notice.value = 'Due date extended.'; await load() }
  catch (e) { error.value = failure(e) }
}
onMounted(load)
</script>

<template>
  <div class="space-y-5">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
      <div><p class="text-[10px] font-bold uppercase tracking-[0.18em] text-lnu-800">{{ isMember ? 'My account' : 'Daily operations' }}</p><h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ isMember ? 'My loans' : 'Circulation desk' }}</h1><p class="mt-1.5 text-sm text-slate-500">{{ isMember ? 'Keep track of the books you have checked out.' : 'Issue books, record returns, and keep borrowing moving.' }}</p></div>
      <button v-if="auth.isStaff" class="inline-flex items-center justify-center gap-2 rounded-xl bg-lnu-950 px-4 py-2.5 text-xs font-semibold text-white hover:bg-lnu-900" @click="openIssue"><ArrowLeftRight :size="16" /> Check out a book</button>
    </div>
    <div v-if="notice" class="flex items-center gap-2 rounded-xl border border-lnu-200 bg-lnu-50 px-4 py-3 text-xs text-lnu-900"><CircleCheck :size="15" />{{ notice }}</div>
    <div v-if="error" role="alert" class="flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-800"><CircleAlert :size="15" />{{ error }}</div>
    <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
      <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="text-sm font-semibold">{{ rows.length }} {{ rows.length === 1 ? 'loan' : 'loans' }}</h2><p class="mt-1 text-[11px] text-slate-500">Overdue loans are highlighted automatically.</p></div><select v-if="auth.isStaff" v-model="status" class="field !h-10 !w-full text-xs sm:!w-44" @change="load"><option value="">All loan records</option><option value="borrowed">Active</option><option value="overdue">Overdue</option><option value="returned">Returned</option><option value="lost">Lost</option></select></div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-left">
          <thead class="bg-slate-50/80"><tr><th class="px-5 py-3 text-[10px] font-bold uppercase tracking-wide text-slate-400">Book</th><th v-if="auth.isStaff" class="px-5 py-3 text-[10px] font-bold uppercase tracking-wide text-slate-400">Member</th><th class="px-5 py-3 text-[10px] font-bold uppercase tracking-wide text-slate-400">Checked out</th><th class="px-5 py-3 text-[10px] font-bold uppercase tracking-wide text-slate-400">Due date</th><th class="px-5 py-3 text-[10px] font-bold uppercase tracking-wide text-slate-400">Status</th><th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-wide text-slate-400">Action</th></tr></thead>
          <tbody v-if="loading"><tr><td colspan="6" class="h-44 text-center"><LoaderCircle :size="20" class="mx-auto animate-spin text-lnu-800" /></td></tr></tbody>
          <tbody v-else-if="!rows.length"><tr><td colspan="6" class="h-56 text-center"><BookOpen :size="24" class="mx-auto text-slate-300" /><p class="mt-3 text-sm font-semibold">No borrowing records found.</p><p class="mt-1 text-xs text-slate-500">New checkouts will appear in this list.</p></td></tr></tbody>
          <tbody v-else class="divide-y divide-slate-100">
            <tr v-for="loan in rows" :key="loan.id" class="hover:bg-slate-50/50">
              <td class="px-5 py-4"><div class="flex items-center gap-3"><span class="grid size-9 place-items-center rounded-xl bg-lnu-50 text-lnu-900"><BookOpen :size="16" /></span><span><span class="block text-xs font-semibold text-slate-800">{{ loan.copy.book.title }}</span><span class="mt-1 block text-[10px] text-slate-400">{{ loan.copy.accession_number }}</span></span></div></td>
              <td v-if="auth.isStaff" class="px-5 py-4"><span class="block text-xs font-semibold">{{ loan.member.name }}</span><span class="mt-1 block text-[10px] text-slate-400">{{ loan.member.membership_code }}</span></td>
              <td class="whitespace-nowrap px-5 py-4 text-xs text-slate-600">{{ shortDate(loan.borrowed_at) }}</td>
              <td class="whitespace-nowrap px-5 py-4 text-xs font-semibold" :class="loan.status === 'borrowed' && new Date(loan.due_at) < new Date() ? 'text-rose-700' : 'text-slate-700'">{{ shortDate(loan.due_at) }}</td>
              <td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-[10px] font-semibold capitalize" :class="loan.status === 'returned' ? 'bg-slate-100 text-slate-600' : loan.status === 'borrowed' && new Date(loan.due_at) < new Date() ? 'bg-rose-50 text-rose-700' : 'bg-lnu-50 text-lnu-800'">{{ loan.status === 'borrowed' && new Date(loan.due_at) < new Date() ? 'Overdue' : loan.status }}</span></td>
              <td class="whitespace-nowrap px-5 py-4 text-right">
                <button v-if="auth.isStaff && loan.status === 'borrowed'" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-[10px] font-semibold text-slate-700 hover:bg-slate-50" @click="returnCopy(loan)">Return</button>
                <button v-else-if="isMember && loan.status === 'borrowed'" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-[10px] font-semibold text-slate-700 hover:bg-slate-50" @click="renew(loan)"><RotateCcw :size="12" /> Renew</button>
                <span v-else-if="loan.returned_at" class="text-[10px] text-slate-400">Returned {{ shortDate(loan.returned_at) }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p class="flex items-center gap-2 border-t border-slate-100 px-5 py-3 text-[10px] text-slate-400"><CalendarClock :size="13" /> Standard loan period: 14 days. Renewals depend on the reservation queue.</p>
    </section>
    <div v-if="formOpen" class="fixed inset-0 z-[70] grid place-items-center bg-slate-950/35 p-4" @click.self="formOpen = false">
      <form class="w-full max-w-lg space-y-4 rounded-2xl bg-white p-5 shadow-xl" @submit.prevent="issue">
        <div><h2 class="text-base font-semibold">Check out a book</h2><p class="mt-1 text-xs text-slate-500">Select an active member and an available physical copy.</p></div>
        <label class="block space-y-1.5 text-[11px] font-semibold text-slate-700">Member<select v-model="memberId" required class="field"><option value="">Choose a member</option><option v-for="member in members" :key="member.id" :value="member.id">{{ member.name }} · {{ member.membership_code }}</option></select></label>
        <label class="block space-y-1.5 text-[11px] font-semibold text-slate-700">Available copy<select v-model="copyId" required class="field"><option value="">Choose a copy</option><option v-for="copy in copies" :key="copy.id" :value="copy.id">{{ copy.book.title }} · {{ copy.accession_number }}</option></select></label>
        <label class="block space-y-1.5 text-[11px] font-semibold text-slate-700">Due date (optional)<input v-model="dueAt" type="datetime-local" class="field"></label>
        <p v-if="error" class="text-xs text-rose-700">{{ error }}</p>
        <div class="flex justify-end gap-2 pt-2"><button type="button" class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-semibold" @click="formOpen = false">Cancel</button><button :disabled="issuing" class="inline-flex items-center gap-2 rounded-xl bg-lnu-950 px-4 py-2.5 text-xs font-semibold text-white disabled:opacity-60"><LoaderCircle v-if="issuing" :size="14" class="animate-spin" />Confirm checkout</button></div>
      </form>
    </div>
  </div>
</template>
