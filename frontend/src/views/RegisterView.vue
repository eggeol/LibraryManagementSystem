<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowRight, BookOpen, CircleAlert, LoaderCircle, ShieldCheck } from '@lucide/vue'
import { useAuthStore } from '@/stores/auth'
import type { ApiError } from '@/types'

const auth = useAuthStore()
const router = useRouter()
const name = ref('')
const email = ref('')
const phone = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const loading = ref(false)
const error = ref('')

async function submit() {
  loading.value = true
  error.value = ''
  try {
    await auth.register({
      name: name.value,
      email: email.value,
      phone: phone.value || undefined,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    await router.replace('/dashboard')
  } catch (caught) {
    const failure = caught as { response?: { data?: ApiError } }
    error.value = Object.values(failure.response?.data?.errors ?? {})[0]?.[0]
      ?? failure.response?.data?.message
      ?? 'Your account could not be created. Please try again.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="grid min-h-screen bg-[#f6f7fb] lg:grid-cols-[0.94fr_1.06fr]">
    <section class="relative hidden overflow-hidden bg-[#10176e] px-10 py-9 text-white lg:flex lg:flex-col lg:justify-between xl:px-16 xl:py-12" :style="{ backgroundImage: 'radial-gradient(circle at 82% 15%, rgba(69, 91, 225, .46), transparent 29%), radial-gradient(circle at 7% 87%, rgba(29, 52, 162, .72), transparent 36%), linear-gradient(145deg, #10176e 0%, #18268f 56%, #10165f 100%)' }">
      <div class="absolute -right-44 top-24 size-[560px] rounded-full border border-white/10" />
      <header class="relative flex items-center gap-3"><span class="grid size-12 place-items-center rounded-2xl bg-white p-1.5 shadow-lg"><img src="/lnu-logo.png" alt="Leyte Normal University seal" class="size-full object-contain"></span><span><span class="block text-base font-bold">Leyte Normal University</span><span class="mt-0.5 block text-[10px] font-medium uppercase tracking-[0.16em] text-blue-100/75">SmartLib campus portal</span></span></header>
      <div class="relative max-w-xl pb-8"><span class="inline-flex rounded-full border border-white/20 bg-white/10 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-blue-50">A place for every reader</span><h1 class="mt-5 text-5xl font-semibold leading-[1.04] tracking-[-0.045em] xl:text-6xl">Find your next<br>good read.</h1><p class="mt-5 max-w-md text-sm leading-7 text-blue-50/80">Create your library account to browse the collection, request reservations, and keep your borrowing history close.</p><div class="mt-8 flex items-center gap-3 text-xs text-blue-50/80"><ShieldCheck :size="17" />Your new account starts with member access.</div></div>
      <p class="relative text-[10px] text-blue-100/60">LNU SmartLib · Library services workspace</p>
    </section>

    <section class="flex min-h-screen items-center justify-center px-5 py-10 sm:px-10">
      <div class="w-full max-w-[450px]">
        <div class="mb-7 flex items-center gap-3 lg:hidden"><span class="grid size-11 place-items-center rounded-xl bg-white p-1 shadow-sm ring-1 ring-slate-200"><img src="/lnu-logo.png" alt="Leyte Normal University seal" class="size-full object-contain"></span><span><span class="block text-sm font-bold text-slate-900">LNU SmartLib</span><span class="block text-[10px] text-slate-500">Campus library portal</span></span></div>
        <p class="text-[10px] font-bold uppercase tracking-[0.17em] text-lnu-700">Create your account</p>
        <h2 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Join LNU SmartLib</h2>
        <p class="mt-2 text-sm text-slate-500">Set up a member account to access the library collection.</p>

        <form class="mt-6 space-y-4 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_18px_50px_-32px_rgba(15,23,80,.32)] sm:p-6" @submit.prevent="submit">
          <label class="block space-y-1.5 text-[11px] font-semibold text-slate-700">Full name<input v-model="name" class="field" autocomplete="name" maxlength="255" required placeholder="Your name"></label>
          <label class="block space-y-1.5 text-[11px] font-semibold text-slate-700">University email<input v-model="email" class="field" type="email" autocomplete="email" required placeholder="name@lnu.edu.ph"></label>
          <label class="block space-y-1.5 text-[11px] font-semibold text-slate-700">Phone number <span class="font-normal text-slate-400">(optional)</span><input v-model="phone" class="field" type="tel" autocomplete="tel" maxlength="32" placeholder="Contact number"></label>
          <label class="block space-y-1.5 text-[11px] font-semibold text-slate-700">Password<input v-model="password" class="field" type="password" autocomplete="new-password" minlength="10" required placeholder="At least 10 characters"></label>
          <label class="block space-y-1.5 text-[11px] font-semibold text-slate-700">Confirm password<input v-model="passwordConfirmation" class="field" type="password" autocomplete="new-password" minlength="10" required placeholder="Re-enter your password"></label>
          <div v-if="error" role="alert" class="flex gap-2 rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800"><CircleAlert :size="16" class="mt-0.5 shrink-0" />{{ error }}</div>
          <button :disabled="loading" class="flex w-full items-center justify-center gap-2 rounded-xl bg-lnu-800 px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-lnu-900/15 transition hover:bg-lnu-900 disabled:opacity-60"><LoaderCircle v-if="loading" :size="16" class="animate-spin" />{{ loading ? 'Creating account…' : 'Create member account' }}<ArrowRight v-if="!loading" :size="15" /></button>
        </form>
        <p class="mt-5 text-center text-xs text-slate-500">Already have an account? <RouterLink to="/login" class="font-semibold text-lnu-800 hover:text-lnu-950">Sign in</RouterLink></p>
        <p class="mt-8 text-center text-[10px] text-slate-400">Member accounts cannot access staff or administrator tools.</p>
      </div>
    </section>
  </main>
</template>
