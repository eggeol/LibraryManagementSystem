import axios from 'axios'
import { defineStore } from 'pinia'
import { api } from '@/lib/api'
import type { User } from '@/types'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as User | null,
    ready: false,
  }),
  getters: {
    isStaff: (state) => state.user?.role === 'admin' || state.user?.role === 'librarian',
    isAdmin: (state) => state.user?.role === 'admin',
  },
  actions: {
    async fetchMe() {
      if (this.ready) return this.user
      try {
        const { data } = await api.get<{ user: User }>('/auth/me')
        this.user = data.user
      } catch {
        this.user = null
      } finally {
        this.ready = true
      }
      return this.user
    },
    async login(email: string, password: string) {
      await axios.get('/sanctum/csrf-cookie', { withCredentials: true, withXSRFToken: true })
      const { data } = await api.post<{ user: User }>('/auth/login', { email, password })
      this.user = data.user
      this.ready = true
      return data.user
    },
    async register(payload: { name: string; email: string; phone?: string; password: string; password_confirmation: string }) {
      await axios.get('/sanctum/csrf-cookie', { withCredentials: true, withXSRFToken: true })
      const { data } = await api.post<{ user: User }>('/auth/register', payload)
      this.user = data.user
      this.ready = true
      return data.user
    },
    async logout() {
      try {
        await api.post('/auth/logout')
      } finally {
        this.user = null
        this.ready = true
      }
    },
  },
})
