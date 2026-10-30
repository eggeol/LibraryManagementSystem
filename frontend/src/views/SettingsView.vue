<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { CircleAlert, CircleCheck, LoaderCircle, Save, Settings2 } from '@lucide/vue'

const auth = useAuthStore()
const settings = reactive({ library_name: '', loan_days: 14, fine_per_day: 0.5, reservation_hold_hours: 48, max_active_loans: 5 })
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const notice = ref('')
async function load() {
  try { Object.assign(settings, (await api.get('/settings')).data) }
  catch { error.value = 'Settings could not be loaded.' }
  finally { loading.value = false }
}
async function save() {
  saving.value = true
  error.value = ''
  try { Object.assign(settings, (await api.put('/settings', settings)).data); notice.value = 'Library settings saved.' }
  catch (e) {
    const caught = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    error.value = Object.values(caught.response?.data?.errors ?? {})[0]?.[0] ?? caught.response?.data?.message ?? 'Settings could not be saved.'
  } finally { saving.value = false }
}
onMounted(load)
</script>

<template>
  <div class="mx-auto max-w-4xl space-y-5">
    <div><p class="text-[10px] font-bold uppercase tracking-[0.18em] text-lnu-800">Administration</p><h1 class="mt-2 text-2xl font-semibold tracking-tight">Library settings</h1><p class="mt-1.5 text-sm text-slate-500">Set the defaults used by checkout, reservations, and account rules.</p></div>
    <div v-if="notice" class="flex items-center gap-2 rounded-xl border border-lnu-200 bg-lnu-50 px-4 py-3 text-xs text-lnu-900"><CircleCheck :size="15" />{{ notice }}</div>
    <div v-if="error" role="alert" class="flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-800"><CircleAlert :size="15" />{{ error }}</div>
    <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
      <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4"><span class="grid size-9 place-items-center rounded-xl bg-lnu-50 text-lnu-900"><Settings2 :size="17" /></span><div><h2 class="text-sm font-semibold">Circulation policy</h2><p class="mt-1 text-[11px] text-slate-500">Changes apply to future transactions.</p></div></div>
      <div v-if="loading" class="grid h-48 place-items-center"><LoaderCircle :size="21" class="animate-spin text-lnu-800" /></div>
      <form v-else class="grid gap-5 p-5 sm:grid-cols-2" @submit.prevent="save">
        <label class="space-y-1.5 text-xs font-semibold sm:col-span-2">Library name<input v-model="settings.library_name" class="field" maxlength="120" required></label>
        <label class="space-y-1.5 text-xs font-semibold">Standard loan period <span class="font-normal text-slate-400">(days)</span><input v-model.number="settings.loan_days" class="field" type="number" min="1" max="90" required></label>
        <label class="space-y-1.5 text-xs font-semibold">Fine per overdue day <span class="font-normal text-slate-400">(USD)</span><input v-model.number="settings.fine_per_day" class="field" type="number" min="0" max="100" step="0.01" required></label>
        <label class="space-y-1.5 text-xs font-semibold">Reservation pickup window <span class="font-normal text-slate-400">(hours)</span><input v-model.number="settings.reservation_hold_hours" class="field" type="number" min="1" max="336" required></label>
        <label class="space-y-1.5 text-xs font-semibold">Maximum active loans <span class="font-normal text-slate-400">(per member)</span><input v-model.number="settings.max_active_loans" class="field" type="number" min="1" max="50" required></label>
        <div class="flex items-center justify-between border-t border-slate-100 pt-4 sm:col-span-2"><p class="max-w-lg text-[11px] leading-5 text-slate-400">{{ auth.isAdmin ? 'These rules are enforced on the server for every checkout and return.' : 'Only administrators can change library policy.' }}</p><button v-if="auth.isAdmin" :disabled="saving" class="inline-flex items-center gap-2 rounded-xl bg-lnu-950 px-4 py-2.5 text-xs font-semibold text-white disabled:opacity-60"><LoaderCircle v-if="saving" :size="14" class="animate-spin" /><Save v-else :size="14" />Save settings</button></div>
      </form>
    </section>
  </div>
</template>
