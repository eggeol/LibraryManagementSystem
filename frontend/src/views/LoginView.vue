<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowRight, BookOpen, CalendarDays, CircleAlert, Eye, EyeOff, LoaderCircle, Search, ShieldCheck,
} from '@lucide/vue'
import { useAuthStore } from '@/stores/auth'
import type { ApiError } from '@/types'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const email = ref('admin@library.test')
const password = ref('password')
const showPassword = ref(false)
const loading = ref(false)
const error = ref('')

async function submit() {
  loading.value = true
  error.value = ''
  try {
    await auth.login(email.value, password.value)
    await router.replace(typeof route.query.redirect === 'string' ? route.query.redirect : '/dashboard')
  } catch (caught) {
    const failure = caught as { response?: { data?: ApiError } }
    error.value = failure.response?.data?.errors?.email?.[0]
      ?? failure.response?.data?.message
      ?? 'We could not sign you in. Check your connection and try again.'
  } finally {
    loading.value = false
  }
}

function chooseAccount(account: string) {
  email.value = account
  password.value = 'password'
  error.value = ''
}
</script>

<template>
  <main class="grid min-h-screen bg-[#f6f7fb] lg:grid-cols-[0.94fr_1.06fr]">
    <section
      class="relative hidden min-h-screen overflow-hidden bg-[#10176e] px-10 py-9 text-white lg:flex lg:flex-col lg:justify-between xl:px-16 xl:py-12"
      :style="{ backgroundImage: 'radial-gradient(circle at 82% 15%, rgba(69, 91, 225, .46), transparent 29%), radial-gradient(circle at 7% 87%, rgba(29, 52, 162, .72), transparent 36%), linear-gradient(145deg, #10176e 0%, #18268f 56%, #10165f 100%)' }"
    >
      <div class="absolute -right-44 top-24 size-[560px] rounded-full border border-white/10" />
      <div class="absolute -right-20 top-48 size-[310px] rounded-full border border-white/10" />
      <div class="absolute bottom-20 left-0 h-48 w-full bg-gradient-to-t from-[#080f4e]/45 to-transparent" />
      <header class="relative flex items-center gap-3">
        <span class="grid size-12 place-items-center rounded-2xl bg-white p-1.5 shadow-lg shadow-blue-950/20"><img src="/lnu-logo.png" alt="Leyte Normal University seal" class="size-full object-contain"></span>
        <span><span class="block text-base font-bold tracking-tight">Leyte Normal University</span><span class="mt-0.5 block text-[10px] font-medium uppercase tracking-[0.16em] text-blue-100/75">SmartLib campus portal</span></span>
      </header>

      <div class="relative max-w-xl pb-6">
        <span class="inline-flex rounded-full border border-white/20 bg-white/10 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-blue-50">LNU SmartLib</span>
        <h1 class="mt-5 text-5xl font-semibold leading-[1.04] tracking-[-0.045em] xl:text-6xl">Your library,<br>smarter.</h1>
        <p class="mt-5 max-w-md text-sm leading-7 text-blue-50/80">Access the collection, keep track of your loans, and stay connected to library services from one campus workspace.</p>

        <div class="mt-9 grid gap-3">
          <div class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/[0.07] px-3.5 py-3">
            <span class="grid size-9 place-items-center rounded-lg bg-white/10 text-blue-100"><Search :size="16" /></span>
            <span><span class="block text-xs font-semibold">Explore the collection</span><span class="mt-0.5 block text-[10px] text-blue-100/65">Find titles, authors, and available copies.</span></span>
          </div>
          <div class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/[0.07] px-3.5 py-3">
            <span class="grid size-9 place-items-center rounded-lg bg-white/10 text-blue-100"><CalendarDays :size="16" /></span>
            <span><span class="block text-xs font-semibold">Keep loans in view</span><span class="mt-0.5 block text-[10px] text-blue-100/65">See due dates, renewals, and reservation updates.</span></span>
          </div>
          <div class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/[0.07] px-3.5 py-3">
            <span class="grid size-9 place-items-center rounded-lg bg-white/10 text-blue-100"><ShieldCheck :size="16" /></span>
            <span><span class="block text-xs font-semibold">A secure library account</span><span class="mt-0.5 block text-[10px] text-blue-100/65">Your information is shown according to your role.</span></span>
          </div>
        </div>
      </div>

      <footer class="relative flex items-center gap-2 text-[10px] text-blue-100/60"><span class="size-1.5 rounded-full bg-emerald-300" /> Library services workspace</footer>
    </section>

    <section class="flex min-h-screen items-center justify-center px-5 py-10 sm:px-10">
      <div class="w-full max-w-[430px]">
        <div class="mb-8 flex items-center gap-3 lg:hidden">
          <span class="grid size-11 place-items-center rounded-xl bg-white p-1 shadow-sm ring-1 ring-slate-200"><img src="/lnu-logo.png" alt="Leyte Normal University seal" class="size-full object-contain"></span>
          <span><span class="block text-sm font-bold text-slate-900">LNU SmartLib</span><span class="block text-[10px] text-slate-500">Campus library portal</span></span>
        </div>

        <div class="mb-5 flex items-center justify-between">
          <div><p class="text-[10px] font-bold uppercase tracking-[0.17em] text-lnu-700">Welcome back</p><h2 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Sign in to your account</h2><p class="mt-2 text-sm text-slate-500">Use your library account to continue to SmartLib.</p></div>
          <BookOpen :size="22" class="hidden text-lnu-700 sm:block" />
        </div>

        <form class="mt-7 space-y-5 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_18px_50px_-32px_rgba(15,23,80,.32)] sm:p-7" @submit.prevent="submit">
          <div>
            <label for="email" class="mb-2 block text-[11px] font-semibold text-slate-700">University email</label>
            <input id="email" v-model="email" autocomplete="username" type="email" required class="field" placeholder="name@lnu.edu.ph">
          </div>
          <div>
            <div class="mb-2 flex justify-between"><label for="password" class="text-[11px] font-semibold text-slate-700">Password</label></div>
            <div class="relative">
              <input id="password" v-model="password" autocomplete="current-password" :type="showPassword ? 'text' : 'password'" required class="field pr-11" placeholder="Enter your password">
              <button type="button" class="absolute inset-y-0 right-0 grid w-11 place-items-center text-slate-400 hover:text-slate-700" :aria-label="showPassword ? 'Hide password' : 'Show password'" @click="showPassword = !showPassword"><EyeOff v-if="showPassword" :size="17" /><Eye v-else :size="17" /></button>
            </div>
          </div>
          <div v-if="error" role="alert" class="flex gap-2 rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800"><CircleAlert :size="16" class="mt-0.5 shrink-0" />{{ error }}</div>
          <button type="submit" :disabled="loading" class="flex w-full items-center justify-center gap-2 rounded-xl bg-lnu-800 px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-lnu-900/15 transition hover:bg-lnu-900 disabled:cursor-wait disabled:opacity-70">
            <LoaderCircle v-if="loading" :size="17" class="animate-spin" />{{ loading ? 'Signing in…' : 'Sign in' }}<ArrowRight v-if="!loading" :size="15" />
          </button>

          <details class="rounded-xl border border-dashed border-slate-200 bg-slate-50/70 p-3">
            <summary class="cursor-pointer text-[11px] font-semibold text-slate-600">Local demo accounts</summary>
            <p class="mt-2 text-[10px] text-slate-500">Development only · password: password</p>
            <div class="mt-2 grid grid-cols-3 gap-2">
              <button type="button" class="rounded-lg border border-slate-200 bg-white px-2 py-2 text-[10px] font-semibold text-slate-600 hover:border-lnu-300 hover:text-lnu-900" @click="chooseAccount('admin@library.test')">Admin</button>
              <button type="button" class="rounded-lg border border-slate-200 bg-white px-2 py-2 text-[10px] font-semibold text-slate-600 hover:border-lnu-300 hover:text-lnu-900" @click="chooseAccount('librarian@library.test')">Librarian</button>
              <button type="button" class="rounded-lg border border-slate-200 bg-white px-2 py-2 text-[10px] font-semibold text-slate-600 hover:border-lnu-300 hover:text-lnu-900" @click="chooseAccount('member@library.test')">Member</button>
            </div>
          </details>
        </form>

        <div class="mt-5 rounded-xl border border-lnu-100 bg-lnu-50/70 px-4 py-3 text-center">
          <p class="text-[11px] text-slate-600">New to LNU SmartLib?</p>
          <RouterLink to="/register" class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-lnu-800 hover:text-lnu-950">Create a library member account <ArrowRight :size="13" /></RouterLink>
        </div>
        <p class="mt-8 text-center text-[10px] text-slate-400">LNU SmartLib · Library services and collection access</p>
      </div>
    </section>
  </main>
</template>
