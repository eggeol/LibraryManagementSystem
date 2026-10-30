import axios from 'axios'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  headers: {
    Accept: 'application/json',
  },
  timeout: 10_000,
  withCredentials: true,
  withXSRFToken: true,
})
