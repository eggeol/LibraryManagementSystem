export type Role = 'admin' | 'librarian' | 'member'

export interface User {
  id: number
  name: string
  email: string
  role: Role
  membership_code: string | null
  phone: string | null
  status: 'active' | 'inactive'
}

export interface Paginated<T> {
  data: T[]
  current_page: number
  last_page: number
  total: number
}

export interface ApiError {
  message?: string
  errors?: Record<string, string[]>
}
