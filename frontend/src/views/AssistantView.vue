<script setup lang="ts">
import { ref } from 'vue'
import type { Component } from 'vue'
import {
  ArrowRight, Bell, BookOpen, Bot, CalendarClock, CircleHelp, MessageCircle,
  Search, Sparkles, Wallet,
} from '@lucide/vue'
import { useAuthStore } from '@/stores/auth'

type HelpTopic = {
  id: string
  question: string
  answer: string
  action: string
  to: string
  keywords: string[]
  icon: Component
}

const auth = useAuthStore()
const topics: HelpTopic[] = [
  {
    id: 'catalog', question: 'How do I find a book?',
    answer: 'Open the catalog and search by title, author, or ISBN. You can filter for titles with available copies.',
    action: 'Open catalog', to: '/catalog', keywords: ['find', 'search', 'catalog', 'book', 'title', 'author', 'isbn', 'available', 'availability'], icon: BookOpen,
  },
  {
    id: 'loans', question: 'How do I check or renew a loan?',
    answer: auth.isStaff
      ? 'Open the circulation desk to review current checkouts and record returns. The standard loan period is 14 days.'
      : 'Open My loans to see your due dates. Use Renew on an eligible loan; renewals depend on the reservation queue. The standard loan period is 14 days.',
    action: auth.isStaff ? 'Open circulation desk' : 'View my loans', to: '/circulation', keywords: ['loan', 'loans', 'borrow', 'borrowed', 'checkout', 'due', 'date', 'renew', 'renewal', 'extend', 'overdue', 'days'], icon: CalendarClock,
  },
  {
    id: 'reservations', question: 'How do I reserve a book?',
    answer: auth.isStaff
      ? 'Open the reservation queue to review requests. Members can place reservations from the catalog and manage active requests on their reservations page.'
      : 'In the catalog, choose Reserve on a title. Open My reservations to check its status or cancel an active request.',
    action: auth.isStaff ? 'Open reservation queue' : 'View reservations', to: '/reservations', keywords: ['reserve', 'reservation', 'reservations', 'hold', 'holds', 'waiting', 'cancel', 'status'], icon: CircleHelp,
  },
  {
    id: 'fines', question: 'Where can I see my fines or balance?',
    answer: auth.isStaff
      ? 'Open Fines & payments to review balances and fine records. Staff can record payments and manage eligible fine adjustments.'
      : 'Open Fines & payments to review fine records and your outstanding balance. Library staff record payments in the system.',
    action: 'View fines', to: '/fines', keywords: ['fine', 'fines', 'balance', 'payment', 'payments', 'paid', 'charge', 'charges'], icon: Wallet,
  },
  {
    id: 'notifications', question: 'Where can I find library updates?',
    answer: 'Open Notifications to see notices about your library account and requests.',
    action: 'View notifications', to: '/notifications', keywords: ['notification', 'notifications', 'notice', 'notices', 'alert', 'alerts', 'updates', 'messages'], icon: Bell,
  },
]

const question = ref('')
const askedQuestion = ref('')
const answer = ref<HelpTopic | null>(null)

function ask(value = question.value) {
  const clean = value.trim()
  if (!clean) return
  askedQuestion.value = clean
  const normalized = clean.toLowerCase().replace(/[^a-z0-9 ]/g, ' ')
  const ranked = topics.map((topic) => ({
    topic,
    score: topic.keywords.reduce((score, keyword) => {
      if (!normalized.includes(keyword)) return score
      const weight = ['book', 'title'].includes(keyword) ? 0.25 : keyword.includes(' ') ? 2 : 1
      return score + weight
    }, 0),
  })).sort((a, b) => b.score - a.score)
  answer.value = ranked[0]?.score ? ranked[0].topic : null
}

function askTopic(topic: HelpTopic) {
  question.value = topic.question
  ask(topic.question)
}
</script>

<template>
  <div class="mx-auto max-w-3xl space-y-5">
    <header>
      <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-lnu-800">Guided help</p>
      <h1 class="mt-2 text-2xl font-semibold tracking-tight">Library assistant</h1>
      <p class="mt-1.5 text-sm text-slate-500">Quick answers and shortcuts for common SmartLib tasks.</p>
    </header>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
      <div class="flex items-center justify-between gap-3 border-b border-slate-100 p-5 sm:p-6">
        <div class="flex min-w-0 items-center gap-3">
          <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-lnu-50 text-lnu-900"><Bot :size="20" /></span>
          <div><h2 class="text-sm font-semibold">SmartLib help desk</h2><p class="mt-1 text-[11px] text-slate-500">Curated answers for the library prototype</p></div>
        </div>
        <span class="shrink-0 rounded-full bg-lnu-50 px-2.5 py-1 text-[10px] font-semibold text-lnu-800">Guided FAQ</span>
      </div>

      <div class="space-y-6 bg-[#f7f8fc] p-5 sm:p-7">
        <div class="space-y-4" aria-live="polite">
          <div class="flex items-start gap-3">
            <span class="grid size-8 shrink-0 place-items-center rounded-xl bg-lnu-950 text-white"><Sparkles :size="15" /></span>
            <div class="max-w-xl rounded-2xl rounded-tl-sm border border-slate-200 bg-white p-4">
              <p class="text-xs font-semibold text-slate-800">Hi! I can point you to the right part of the library system.</p>
              <p class="mt-1.5 text-xs leading-5 text-slate-500">Choose a common question or type a few words below.</p>
            </div>
          </div>

          <template v-if="askedQuestion">
            <div class="ml-auto flex max-w-xl items-start justify-end gap-3">
              <div class="rounded-2xl rounded-tr-sm bg-lnu-950 px-4 py-3 text-xs text-white">{{ askedQuestion }}</div>
              <span class="grid size-8 shrink-0 place-items-center rounded-xl bg-slate-200 text-slate-600"><MessageCircle :size="15" /></span>
            </div>

            <div class="flex items-start gap-3">
              <span class="grid size-8 shrink-0 place-items-center rounded-xl bg-lnu-950 text-white"><Sparkles :size="15" /></span>
              <div class="max-w-xl rounded-2xl rounded-tl-sm border border-slate-200 bg-white p-4">
                <template v-if="answer">
                  <p class="text-xs leading-5 text-slate-700">{{ answer.answer }}</p>
                  <RouterLink :to="answer.to" class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold text-lnu-800 hover:text-lnu-950">{{ answer.action }} <ArrowRight :size="13" /></RouterLink>
                </template>
                <template v-else>
                  <p class="text-xs font-semibold text-slate-800">I couldn’t match that to a help topic yet.</p>
                  <p class="mt-1.5 text-xs leading-5 text-slate-500">Try one of the common questions below, or contact library staff through the library’s usual support channels.</p>
                </template>
              </div>
            </div>
          </template>
        </div>

        <div>
          <p class="mb-2.5 text-[10px] font-semibold uppercase tracking-wide text-slate-400">Common questions</p>
          <div class="flex flex-wrap gap-2">
            <button v-for="topic in topics" :key="topic.id" type="button" class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-2 text-[11px] font-medium text-slate-600 transition hover:border-lnu-200 hover:bg-lnu-50 hover:text-lnu-900" @click="askTopic(topic)">
              <component :is="topic.icon" :size="13" />{{ topic.question }}
            </button>
          </div>
        </div>

        <form class="flex gap-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-sm" @submit.prevent="ask()">
          <Search :size="17" class="ml-2 mt-2.5 shrink-0 text-slate-400" />
          <input v-model="question" aria-label="Ask a library help question" class="min-w-0 flex-1 border-0 bg-transparent px-1 py-2 text-xs outline-none placeholder:text-slate-400 focus:ring-0" placeholder="Try “How do I renew a loan?”">
          <button type="submit" class="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-lnu-950 px-3.5 py-2.5 text-xs font-semibold text-white transition hover:bg-lnu-900"><span class="hidden sm:inline">Ask</span><ArrowRight :size="14" /></button>
        </form>

        <p class="flex items-start gap-2 border-t border-slate-200 pt-4 text-[10px] leading-4 text-slate-400"><CircleHelp :size="13" class="mt-0.5 shrink-0" />This prototype uses a small set of prepared answers. It sends no messages to an AI service; check with library staff for official policies.</p>
      </div>
    </section>
  </div>
</template>
