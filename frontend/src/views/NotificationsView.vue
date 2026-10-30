<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api } from '@/lib/api'
import { Bell, BookOpen, CircleCheck, LoaderCircle } from '@lucide/vue'

type Notice = { id: string; read_at: string | null; created_at: string; data: { title: string; message: string } }
const rows = ref<Notice[]>([])
const unread = ref(0)
const loading = ref(true)
const error = ref('')
async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/notifications')
    rows.value = data.notifications.data
    unread.value = data.unread_count
  } catch { error.value = 'Notifications could not be loaded.' }
  finally { loading.value = false }
}
async function markRead(item: Notice) {
  if (!item.read_at) await api.post('/notifications/' + item.id + '/read')
  await load()
}
async function markAll() {
  await api.post('/notifications/read-all')
  await load()
}
onMounted(load)
</script>

<template>
  <div class="space-y-5">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="text-[10px] font-bold uppercase tracking-[0.18em] text-lnu-800">Your updates</p><h1 class="mt-2 text-2xl font-semibold tracking-tight">Notifications</h1><p class="mt-1.5 text-sm text-slate-500">Updates about checkouts, returns, and reservations.</p></div><button v-if="unread" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50" @click="markAll">Mark all as read</button></div>
    <p v-if="error" class="rounded-xl bg-rose-50 p-4 text-xs text-rose-800">{{ error }}</p>
    <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
      <div v-if="loading" class="grid h-48 place-items-center"><LoaderCircle :size="21" class="animate-spin text-lnu-800" /></div>
      <div v-else-if="!rows.length" class="p-12 text-center"><Bell :size="25" class="mx-auto text-slate-300" /><p class="mt-3 text-sm font-semibold">You’re all caught up</p><p class="mt-1 text-xs text-slate-500">New library updates will appear here.</p></div>
      <div v-else class="divide-y divide-slate-100">
        <button v-for="item in rows" :key="item.id" class="flex w-full gap-4 p-5 text-left transition hover:bg-slate-50" :class="!item.read_at ? 'bg-lnu-50/30' : ''" @click="markRead(item)">
          <span class="grid size-10 shrink-0 place-items-center rounded-xl" :class="item.read_at ? 'bg-slate-50 text-slate-400' : 'bg-lnu-50 text-lnu-900'"><BookOpen :size="17" /></span>
          <span class="min-w-0 flex-1"><span class="flex items-center gap-2 text-xs font-semibold">{{ item.data.title }}<span v-if="!item.read_at" class="size-1.5 rounded-full bg-lnu-700" /></span><span class="mt-1 block text-xs text-slate-600">{{ item.data.message }}</span><span class="mt-2 block text-[10px] text-slate-400">{{ new Date(item.created_at).toLocaleString() }}</span></span>
          <CircleCheck v-if="item.read_at" :size="16" class="shrink-0 text-slate-300" />
        </button>
      </div>
    </section>
  </div>
</template>
