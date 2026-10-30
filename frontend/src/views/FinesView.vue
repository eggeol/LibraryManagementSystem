<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { CircleAlert, CircleCheck, LoaderCircle, Wallet } from '@lucide/vue'

type Fine = { id: number; amount: number; paid_amount: number; status: string; created_at: string; member: { name: string; membership_code: string }; borrowing: { copy: { book: { title: string } } }; payments: Array<{ amount: number; method: string; paid_at: string }> }
const auth = useAuthStore()
const rows = ref<Fine[]>([])
const loading = ref(true)
const selected = ref<Fine | null>(null)
const amount = ref('')
const method = ref('cash')
const busy = ref(false)
const error = ref('')
const notice = ref('')
const balance = computed(() => rows.value.reduce((sum, fine) => sum + remaining(fine), 0))
const remaining = (fine: Fine) => ['paid', 'waived'].includes(fine.status) ? 0 : Math.max(0, Number(fine.amount) - Number(fine.paid_amount))
const failure = (e: unknown) => {
  const caught = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
  return Object.values(caught.response?.data?.errors ?? {})[0]?.[0] ?? caught.response?.data?.message ?? 'The request could not be completed.'
}
async function load() {
  loading.value = true
  try { rows.value = (await api.get('/fines?per_page=100')).data.data }
  catch (e) { error.value = failure(e) }
  finally { loading.value = false }
}
function openPayment(fine: Fine) {
  selected.value = fine
  amount.value = remaining(fine).toFixed(2)
  method.value = 'cash'
}
async function pay() {
  if (!selected.value) return
  busy.value = true
  error.value = ''
  try {
    await api.post('/fines/' + selected.value.id + '/payments', { amount: Number(amount.value), method: method.value })
    notice.value = 'Payment recorded.'
    selected.value = null
    await load()
  } catch (e) { error.value = failure(e) }
  finally { busy.value = false }
}
async function waive(fine: Fine) {
  const reason = window.prompt('Reason for waiving this fine:')
  if (!reason) return
  try { await api.post('/fines/' + fine.id + '/waive', { reason }); notice.value = 'Fine waived.'; await load() }
  catch (e) { error.value = failure(e) }
}
onMounted(load)
</script>

<template>
  <div class="space-y-5">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="text-[10px] font-bold uppercase tracking-[0.18em] text-lnu-800">{{ auth.isStaff ? 'Account services' : 'My account' }}</p><h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ auth.isStaff ? 'Fines & payments' : 'My balance' }}</h1><p class="mt-1.5 text-sm text-slate-500">{{ auth.isStaff ? 'Review overdue charges and record in-person payments.' : 'Review outstanding charges from overdue returns.' }}</p></div><div class="rounded-xl border border-slate-200 bg-white px-4 py-3"><p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Outstanding balance</p><p class="mt-1 text-xl font-semibold text-slate-900">USD {{ balance.toFixed(2) }}</p></div></div>
    <div v-if="notice" class="flex items-center gap-2 rounded-xl border border-lnu-200 bg-lnu-50 px-4 py-3 text-xs text-lnu-900"><CircleCheck :size="15" />{{ notice }}</div>
    <div v-if="error" role="alert" class="flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-800"><CircleAlert :size="15" />{{ error }}</div>
    <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
      <div class="overflow-x-auto"><table class="min-w-full text-left"><thead class="bg-slate-50/80"><tr><th class="px-5 py-3 text-[10px] font-bold uppercase tracking-wide text-slate-400">Book</th><th v-if="auth.isStaff" class="px-5 py-3 text-[10px] font-bold uppercase tracking-wide text-slate-400">Member</th><th class="px-5 py-3 text-[10px] font-bold uppercase tracking-wide text-slate-400">Fine</th><th class="px-5 py-3 text-[10px] font-bold uppercase tracking-wide text-slate-400">Paid</th><th class="px-5 py-3 text-[10px] font-bold uppercase tracking-wide text-slate-400">Balance</th><th class="px-5 py-3 text-[10px] font-bold uppercase tracking-wide text-slate-400">Status</th><th v-if="auth.isStaff" class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-wide text-slate-400">Action</th></tr></thead>
        <tbody v-if="loading"><tr><td :colspan="auth.isStaff ? 7 : 6" class="h-44 text-center"><LoaderCircle :size="20" class="mx-auto animate-spin text-lnu-800" /></td></tr></tbody>
        <tbody v-else-if="!rows.length"><tr><td :colspan="auth.isStaff ? 7 : 6" class="h-56 text-center"><Wallet :size="25" class="mx-auto text-slate-300" /><p class="mt-3 text-sm font-semibold">You’re all clear</p><p class="mt-1 text-xs text-slate-500">There are no fine records to show.</p></td></tr></tbody>
        <tbody v-else class="divide-y divide-slate-100"><tr v-for="fine in rows" :key="fine.id" class="hover:bg-slate-50/50"><td class="px-5 py-4 text-xs font-semibold text-slate-800">{{ fine.borrowing.copy.book.title }}</td><td v-if="auth.isStaff" class="px-5 py-4 text-xs">{{ fine.member.name }} <span class="text-[10px] text-slate-400">· {{ fine.member.membership_code }}</span></td><td class="px-5 py-4 text-xs">USD {{ Number(fine.amount).toFixed(2) }}</td><td class="px-5 py-4 text-xs">USD {{ Number(fine.paid_amount).toFixed(2) }}</td><td class="px-5 py-4 text-xs font-semibold">USD {{ remaining(fine).toFixed(2) }}</td><td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-semibold capitalize text-slate-600">{{ fine.status }}</span></td><td v-if="auth.isStaff" class="px-5 py-4 text-right"><div class="inline-flex gap-2"><button v-if="remaining(fine) > 0" class="rounded-lg border border-lnu-200 px-2.5 py-1.5 text-[10px] font-semibold text-lnu-900 hover:bg-lnu-50" @click="openPayment(fine)">Record payment</button><button v-if="auth.isAdmin && remaining(fine) > 0" class="rounded-lg px-2 py-1.5 text-[10px] font-semibold text-slate-500 hover:bg-slate-100" @click="waive(fine)">Waive</button><span v-if="fine.status === 'paid' || fine.status === 'waived'" class="text-[10px] text-slate-400">Closed</span></div></td></tr></tbody>
      </table></div>
    </section>
    <div v-if="selected" class="fixed inset-0 z-[70] grid place-items-center bg-slate-950/35 p-4" @click.self="selected = null"><form class="w-full max-w-md space-y-4 rounded-2xl bg-white p-5 shadow-xl" @submit.prevent="pay"><div><h2 class="text-base font-semibold">Record a payment</h2><p class="mt-1 text-xs text-slate-500">{{ selected.borrowing.copy.book.title }} · remaining USD {{ remaining(selected).toFixed(2) }}</p></div><label class="block space-y-1.5 text-[11px] font-semibold">Payment amount<input v-model="amount" class="field" type="number" min="0.01" :max="remaining(selected)" step="0.01" required></label><label class="block space-y-1.5 text-[11px] font-semibold">Method<select v-model="method" class="field"><option value="cash">Cash</option><option value="card">Card</option><option value="transfer">Transfer</option><option value="other">Other</option></select></label><p v-if="error" role="alert" class="text-xs text-rose-700">{{ error }}</p><div class="flex justify-end gap-2"><button type="button" class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-semibold" @click="selected = null">Cancel</button><button :disabled="busy" class="rounded-xl bg-lnu-950 px-4 py-2.5 text-xs font-semibold text-white disabled:opacity-60">{{ busy ? 'Saving…' : 'Save payment' }}</button></div></form></div>
  </div>
</template>
