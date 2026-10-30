<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api } from '@/lib/api'
import { ChartNoAxesColumn, CircleAlert, LoaderCircle, TrendingUp, Users } from '@lucide/vue'

type ReportData = {
  totals: Record<string, number>
  monthly_activity: Array<{ month: string; total: number }>
  popular_titles: Array<{ title: string; total: number }>
  overdue_loans: Array<{ id: number; due_at: string; member: { name: string; membership_code: string }; copy: { book: { title: string } } }>
  recent_audit: Array<{ id: number; action: string; created_at: string; user?: { name: string } }>
}
const report = ref<ReportData | null>(null)
const loading = ref(true)
const error = ref('')
const peak = computed(() => Math.max(1, ...((report.value?.monthly_activity ?? []).map((item) => item.total))))
const failure = (e: unknown) => {
  const caught = e as { response?: { data?: { message?: string } } }
  return caught.response?.data?.message ?? 'Reports could not be loaded.'
}
onMounted(async () => {
  try { report.value = (await api.get<ReportData>('/reports')).data }
  catch (e) { error.value = failure(e) }
  finally { loading.value = false }
})
const date = (value: string) => new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
</script>

<template>
  <div class="space-y-6">
    <div><p class="text-[10px] font-bold uppercase tracking-[0.18em] text-lnu-800">Insights</p><h1 class="mt-2 text-2xl font-semibold tracking-tight">Reports & analytics</h1><p class="mt-1.5 text-sm text-slate-500">A clear view of collection use and member activity.</p></div>
    <div v-if="error" role="alert" class="flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-800"><CircleAlert :size="15" />{{ error }}</div>
    <div v-if="loading" class="grid min-h-48 place-items-center rounded-2xl border border-slate-200 bg-white"><LoaderCircle :size="22" class="animate-spin text-lnu-800" /></div>
    <template v-else-if="report">
      <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article v-for="metric in [
          { label: 'Active members', value: report.totals.members, icon: Users },
          { label: 'Book titles', value: report.totals.titles, icon: ChartNoAxesColumn },
          { label: 'Checkouts · 30 days', value: report.totals.checkouts, icon: TrendingUp },
          { label: 'Overdue now', value: report.totals.overdue, icon: CircleAlert },
        ]" :key="metric.label" class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
          <span class="grid size-10 place-items-center rounded-xl bg-lnu-50 text-lnu-900"><component :is="metric.icon" :size="18" /></span>
          <p class="mt-4 text-xs text-slate-500">{{ metric.label }}</p><p class="mt-1 text-3xl font-semibold tracking-tight">{{ (metric.value ?? 0).toLocaleString() }}</p>
        </article>
      </section>
      <section class="grid gap-5 xl:grid-cols-[1.2fr_0.8fr]">
        <article class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
          <div class="flex items-center justify-between"><div><h2 class="text-sm font-semibold">Monthly circulation</h2><p class="mt-1 text-[11px] text-slate-500">Checkouts over the last six months</p></div><span class="rounded-lg bg-lnu-50 p-2 text-lnu-900"><TrendingUp :size="16" /></span></div>
          <div v-if="!report.monthly_activity.length" class="grid h-52 place-items-center text-xs text-slate-400">No circulation data for this period.</div>
          <div v-else class="mt-8 flex h-48 items-end gap-3 border-b border-slate-100 pb-1 sm:gap-5">
            <div v-for="item in report.monthly_activity" :key="item.month" class="flex h-full flex-1 flex-col items-center justify-end gap-2">
              <span class="text-[10px] font-semibold text-slate-500">{{ item.total }}</span>
              <div class="w-full max-w-12 rounded-t-lg bg-lnu-800 transition-all" :style="{ height: Math.max(8, (item.total / peak) * 130) + 'px' }" />
              <span class="text-[10px] text-slate-400">{{ item.month }}</span>
            </div>
          </div>
        </article>
        <article class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
          <div><h2 class="text-sm font-semibold">Popular titles</h2><p class="mt-1 text-[11px] text-slate-500">Most checked out in the last 90 days</p></div>
          <div v-if="!report.popular_titles.length" class="grid h-52 place-items-center text-xs text-slate-400">No popular titles yet.</div>
          <div v-else class="mt-4 divide-y divide-slate-100">
            <div v-for="(item, index) in report.popular_titles" :key="item.title" class="flex items-center gap-3 py-3">
              <span class="grid size-7 shrink-0 place-items-center rounded-lg bg-slate-50 text-[10px] font-bold text-slate-500">{{ String(index + 1).padStart(2, '0') }}</span>
              <span class="min-w-0 flex-1 truncate text-xs font-semibold">{{ item.title }}</span><span class="text-[10px] text-slate-500">{{ item.total }} loans</span>
            </div>
          </div>
        </article>
      </section>
      <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-semibold">Overdue books</h2><p class="mt-1 text-[11px] text-slate-500">Members with active loans past their due date</p></div>
        <div v-if="!report.overdue_loans.length" class="p-10 text-center text-xs text-slate-400">There are no overdue loans right now.</div>
        <div v-else class="overflow-x-auto"><table class="min-w-full text-left"><thead class="bg-slate-50/70"><tr><th class="px-5 py-3 text-[10px] font-bold uppercase tracking-wide text-slate-400">Book</th><th class="px-5 py-3 text-[10px] font-bold uppercase tracking-wide text-slate-400">Member</th><th class="px-5 py-3 text-[10px] font-bold uppercase tracking-wide text-slate-400">Due date</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="loan in report.overdue_loans" :key="loan.id"><td class="px-5 py-3 text-xs font-semibold">{{ loan.copy.book.title }}</td><td class="px-5 py-3 text-xs">{{ loan.member.name }} <span class="text-[10px] text-slate-400">· {{ loan.member.membership_code }}</span></td><td class="px-5 py-3 text-xs font-semibold text-rose-700">{{ date(loan.due_at) }}</td></tr></tbody></table></div>
      </section>
    </template>
  </div>
</template>
