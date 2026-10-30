<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import type { Paginated } from '@/types'
import { BookOpen, CircleAlert, CircleCheck, LoaderCircle, Pencil, Plus, Search, Trash2, X } from '@lucide/vue'

type Field = { key: string; label: string; type?: string; required?: boolean; options?: { id: number | string; name: string }[]; adminOnly?: boolean }
type Row = Record<string, any> & { id: number }
const route = useRoute()
const auth = useAuthStore()
const resource = computed(() => String(route.meta.resource ?? 'books'))
const title = computed(() => String(route.meta.title ?? 'Library'))
const rows = ref<Row[]>([])
const failedCoverIds = ref<number[]>([])
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const search = ref('')
const statusFilter = ref('')
const categoryFilter = ref('')
const availableOnly = ref(false)
const sortBy = ref('')
const sortDir = ref<'asc' | 'desc'>('asc')
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const notice = ref('')
const formOpen = ref(false)
const editing = ref<Row | null>(null)
const form = reactive<Record<string, any>>({})
const options = reactive<Record<string, { id: number | string; name: string }[]>>({})
const isBook = computed(() => resource.value === 'books')
const isReadOnly = computed(() => resource.value === 'audit')
const endpoint = computed(() => {
  if (resource.value === 'authors' || resource.value === 'publishers' || resource.value === 'categories') return '/lookups/' + resource.value
  if (resource.value === 'audit') return '/audit-logs'
  return '/' + resource.value
})
const heading = computed(() => resource.value === 'copies' ? 'Physical inventory' : resource.value === 'members' ? 'Member directory' : resource.value === 'audit' ? 'Audit history' : title.value)
const description = computed(() => resource.value === 'books'
  ? auth.isStaff ? 'Manage titles and discover what is available in the collection.' : 'Browse the collection, check availability, and reserve a book.'
  : resource.value === 'copies' ? 'Track every copy by accession number, shelf, and condition.'
    : resource.value === 'members' ? 'Manage membership details and staff access.'
      : resource.value === 'audit' ? 'A chronological record of account and collection changes.'
        : 'Keep ' + resource.value + ' details organized across the collection.')
const actionLabel = computed(() => resource.value === 'books' ? 'Add a title'
  : resource.value === 'copies' ? 'Add a copy'
    : resource.value === 'members' ? 'Add a member'
      : resource.value === 'audit' ? '' : 'Add ' + resource.value.slice(0, -1))
const columns = computed(() => {
  if (resource.value === 'books') return ['title', 'isbn', 'authors', 'categories', 'availability']
  if (resource.value === 'copies') return ['accession_number', 'book', 'shelf_location', 'status']
  if (resource.value === 'members') return ['name', 'membership_code', 'email', 'role', 'status', 'active_loans_count']
  if (resource.value === 'authors') return ['name', 'biography']
  if (resource.value === 'publishers') return ['name', 'email', 'website']
  if (resource.value === 'categories') return ['name', 'description']
  return ['action', 'user', 'entity_type', 'created_at']
})
const fields = computed<Field[]>(() => {
  if (resource.value === 'books') return [
    { key: 'title', label: 'Title', required: true },
    { key: 'isbn', label: 'ISBN' }, { key: 'subtitle', label: 'Subtitle' },
    { key: 'edition', label: 'Edition' }, { key: 'published_year', label: 'Published year', type: 'number' },
    { key: 'language', label: 'Language code', required: true },
    { key: 'publisher_id', label: 'Publisher', type: 'select', options: options.publishers },
    { key: 'author_ids', label: 'Authors', type: 'multi', options: options.authors },
    { key: 'category_ids', label: 'Categories', type: 'multi', options: options.categories },
    { key: 'cover_url', label: 'Cover image URL', type: 'url' }, { key: 'description', label: 'Description', type: 'textarea' },
  ]
  if (resource.value === 'copies') return [
    { key: 'book_id', label: 'Book title', type: 'select', options: options.books, required: true },
    { key: 'accession_number', label: 'Accession number', required: true }, { key: 'barcode', label: 'Barcode' },
    { key: 'shelf_location', label: 'Shelf location' },
    { key: 'status', label: 'Status', type: 'select', options: [{ id: 'available', name: 'Available' }, { id: 'damaged', name: 'Damaged' }, { id: 'lost', name: 'Lost' }] },
    { key: 'notes', label: 'Notes', type: 'textarea' },
  ]
  if (resource.value === 'members') return [
    { key: 'name', label: 'Full name', required: true }, { key: 'email', label: 'Email address', type: 'email', required: true },
    { key: 'phone', label: 'Phone number' }, { key: 'membership_code', label: 'Membership number' },
    { key: 'role', label: 'Role', type: 'select', adminOnly: true, options: auth.isAdmin ? [{ id: 'member', name: 'Member' }, { id: 'librarian', name: 'Librarian' }, { id: 'admin', name: 'Administrator' }] : [{ id: 'member', name: 'Member' }] },
    { key: 'status', label: 'Account status', type: 'select', options: [{ id: 'active', name: 'Active' }, { id: 'inactive', name: 'Inactive' }] },
    { key: 'password', label: editing.value ? 'New password (optional)' : 'Temporary password', type: 'password', required: !editing.value },
  ]
  const common: Field[] = [{ key: 'name', label: 'Name', required: true }]
  if (resource.value === 'authors') common.push({ key: 'biography', label: 'Biography', type: 'textarea' })
  if (resource.value === 'publishers') common.push({ key: 'email', label: 'Email address', type: 'email' }, { key: 'website', label: 'Website', type: 'url' })
  if (resource.value === 'categories') common.push({ key: 'description', label: 'Description', type: 'textarea' })
  return common
})
const visibleFields = computed(() => fields.value.filter((field) => !field.adminOnly || auth.isAdmin))
const labels: Record<string, string> = {
  title: 'Title', isbn: 'ISBN', authors: 'Authors', categories: 'Categories', availability: 'Available',
  accession_number: 'Accession number', book: 'Book title', shelf_location: 'Shelf', status: 'Status',
  name: 'Name', membership_code: 'Member ID', email: 'Email', role: 'Role', active_loans_count: 'On loan',
  biography: 'Biography', website: 'Website', description: 'Description', action: 'Action', user: 'User',
  entity_type: 'Record type', created_at: 'Date and time',
}
const message = (e: unknown) => {
  const caught = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
  return Object.values(caught.response?.data?.errors ?? {})[0]?.[0] ?? caught.response?.data?.message ?? 'Something went wrong. Please try again.'
}
const activeStatuses = computed(() => resource.value === 'members'
  ? ['active', 'inactive'] : ['available', 'reserved', 'borrowed', 'damaged', 'lost'])

async function loadOptions() {
  const tasks = ['authors', 'publishers', 'categories'].map(async (type) => {
    const { data } = await api.get('/lookups/' + type + '?per_page=100')
    options[type] = data.data
  })
  const books = api.get('/books?per_page=100').then(({ data }) => { options.books = data.data.map((book: Row) => ({ id: book.id, name: book.title })) })
  await Promise.all([...tasks, books])
}
async function load() {
  loading.value = true
  error.value = ''
  try {
    const params: Record<string, string | number> = { page: page.value, per_page: 15 }
    if (search.value.trim()) params.q = search.value.trim()
    if (statusFilter.value) params.status = statusFilter.value
    if (categoryFilter.value) params.category_id = categoryFilter.value
    if (availableOnly.value) params.available = 1
    if (sortBy.value) { params.sort_by = sortBy.value; params.sort_dir = sortDir.value }
    const { data } = await api.get<Paginated<Row>>(endpoint.value, { params })
    rows.value = data.data
    lastPage.value = data.last_page
    total.value = data.total
  } catch (e) { error.value = message(e) }
  finally { loading.value = false }
}
function openCreate() {
  editing.value = null
  Object.keys(form).forEach((key) => delete form[key])
  for (const field of visibleFields.value) form[field.key] = field.type === 'multi' ? [] : field.key === 'language' ? 'en' : ''
  formOpen.value = true
}
function openEdit(row: Row) {
  editing.value = row
  Object.keys(form).forEach((key) => delete form[key])
  for (const field of visibleFields.value) {
    if (field.key === 'author_ids') form[field.key] = row.authors?.map((item: Row) => item.id) ?? []
    else if (field.key === 'category_ids') form[field.key] = row.categories?.map((item: Row) => item.id) ?? []
    else form[field.key] = row[field.key] ?? ''
  }
  formOpen.value = true
}
async function save() {
  saving.value = true
  error.value = ''
  const payload: Record<string, unknown> = {}
  for (const field of visibleFields.value) {
    const value = form[field.key]
    if (field.key === 'password' && editing.value && !value) continue
    if (field.key === 'publisher_id' && value === '') payload[field.key] = null
    else if (field.type === 'number' && value !== '') payload[field.key] = Number(value)
    else if (value !== '') payload[field.key] = value
    else if (field.type === 'multi') payload[field.key] = []
  }
  if (isBook.value) { payload.author_ids ??= []; payload.category_ids ??= [] }
  if (resource.value === 'members' && !editing.value && !payload.role) payload.role = 'member'
  try {
    if (editing.value) await api.put(endpoint.value + '/' + editing.value.id, payload)
    else await api.post(endpoint.value, payload)
    formOpen.value = false
    notice.value = editing.value ? 'Changes saved.' : 'Record created.'
    await load()
  } catch (e) { error.value = message(e) }
  finally { saving.value = false }
}
async function remove(row: Row) {
  if (!window.confirm(resource.value === 'members' ? 'Deactivate this account?' : 'Remove this record?')) return
  try {
    await api.delete(endpoint.value + '/' + row.id)
    notice.value = resource.value === 'members' ? 'Account deactivated.' : 'Record removed.'
    await load()
  } catch (e) { error.value = message(e) }
}
async function reserve(row: Row) {
  try { await api.post('/books/' + row.id + '/reservations'); notice.value = 'Your reservation was added.' }
  catch (e) { error.value = message(e) }
}
function canSort(column: string) {
  if (resource.value === 'books') return ['title', 'isbn'].includes(column)
  if (resource.value === 'copies') return ['accession_number', 'status'].includes(column)
  if (resource.value === 'members') return ['name', 'email', 'role', 'status'].includes(column)
  return ['authors', 'publishers', 'categories'].includes(resource.value) && column === 'name'
}
function sort(column: string) {
  if (!canSort(column)) return
  if (sortBy.value === column) sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  else { sortBy.value = column; sortDir.value = 'asc' }
  page.value = 1
  load()
}
function display(row: Row, key: string): string {
  if (key === 'authors' || key === 'categories') return row[key]?.map((item: Row) => item.name).join(', ') || '—'
  if (key === 'availability') return String(row.available_copies_count ?? 0) + ' / ' + String(row.copies_count ?? 0)
  if (key === 'book') return row.book?.title ?? '—'
  if (key === 'user') return row.user?.name ?? 'System'
  if (key === 'created_at') return row.created_at ? new Date(row.created_at).toLocaleString() : '—'
  return String(row[key] ?? '—')
}
function markCoverFailed(id: number) {
  if (!failedCoverIds.value.includes(id)) failedCoverIds.value.push(id)
}
function statusClass(value: string) {
  return value === 'active' || value === 'available' || value === 'ready' ? 'bg-lnu-50 text-lnu-800'
    : value === 'inactive' || value === 'lost' || value === 'damaged' ? 'bg-rose-50 text-rose-700'
      : 'bg-slate-100 text-slate-600'
}
let timer: ReturnType<typeof setTimeout> | undefined
watch([search, statusFilter, categoryFilter, availableOnly], () => { clearTimeout(timer); page.value = 1; timer = setTimeout(load, 250) })
watch(() => resource.value, async () => { rows.value = []; page.value = 1; sortBy.value = ''; sortDir.value = 'asc'; if (['books', 'copies'].includes(resource.value)) await loadOptions(); await load() })
onMounted(async () => { if (['books', 'copies'].includes(resource.value)) await loadOptions(); await load() })
</script>

<template>
  <div class="space-y-5">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
      <div><p class="text-[10px] font-bold uppercase tracking-[0.18em] text-lnu-800">Library workspace</p><h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ heading }}</h1><p class="mt-1.5 text-sm text-slate-500">{{ description }}</p></div>
      <button v-if="actionLabel && !isReadOnly && (resource !== 'books' || auth.isStaff)" class="inline-flex items-center justify-center gap-2 rounded-xl bg-lnu-950 px-4 py-2.5 text-xs font-semibold text-white shadow-sm transition hover:bg-lnu-900" @click="openCreate"><Plus :size="16" />{{ actionLabel }}</button>
    </div>
    <div v-if="notice" class="flex items-center justify-between rounded-xl border border-lnu-200 bg-lnu-50 px-4 py-3 text-xs text-lnu-900"><span class="flex items-center gap-2"><CircleCheck :size="15" />{{ notice }}</span><button aria-label="Dismiss message" @click="notice = ''"><X :size="15" /></button></div>
    <div v-if="error" role="alert" class="flex items-center justify-between rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-800"><span class="flex items-center gap-2"><CircleAlert :size="15" />{{ error }}</span><button aria-label="Dismiss error" @click="error = ''"><X :size="15" /></button></div>
    <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
      <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="relative w-full sm:max-w-sm"><Search :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" /><input v-model="search" class="field !h-10 !pl-9 text-xs" :placeholder="isBook ? 'Search titles, authors, or ISBN…' : 'Search this list…'" type="search"></div>
        <div class="flex flex-wrap items-center gap-2">
          <select v-if="isBook" v-model="sortBy" class="field !h-10 !w-full text-xs sm:!w-36" aria-label="Sort catalog" @change="page = 1; load()"><option value="">Default order</option><option value="title">Title</option><option value="isbn">ISBN</option></select>
          <button v-if="isBook" type="button" :disabled="!sortBy" class="h-10 rounded-xl border border-slate-200 px-3 text-[11px] font-medium text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40" :aria-label="sortDir === 'asc' ? 'Sort ascending' : 'Sort descending'" @click="sortDir = sortDir === 'asc' ? 'desc' : 'asc'; load()">{{ sortDir === 'asc' ? 'A–Z' : 'Z–A' }}</button>
          <select v-if="isBook" v-model="categoryFilter" class="field !h-10 !w-full text-xs sm:!w-44"><option value="">All categories</option><option v-for="category in options.categories ?? []" :key="category.id" :value="category.id">{{ category.name }}</option></select>
          <label v-if="isBook" class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-[11px] font-medium text-slate-600"><input v-model="availableOnly" type="checkbox" class="accent-lnu-800"> In stock</label>
          <select v-if="['copies', 'members'].includes(resource)" v-model="statusFilter" class="field !h-10 !w-full text-xs sm:!w-40"><option value="">All statuses</option><option v-for="value in activeStatuses" :key="value" :value="value">{{ value }}</option></select>
        </div>
        <span class="text-[11px] text-slate-400">{{ total.toLocaleString() }} {{ isBook ? (total === 1 ? 'title' : 'titles') : 'records' }}</span>
      </div>
      <div v-if="isBook" class="border-t border-slate-100 bg-[#f7f8fc] p-4 sm:p-5">
        <div v-if="loading" class="grid min-h-52 place-items-center rounded-2xl border border-slate-200 bg-white"><LoaderCircle :size="22" class="animate-spin text-lnu-800" /></div>
        <div v-else-if="!rows.length" class="grid min-h-64 place-items-center rounded-2xl border border-slate-200 bg-white px-5 text-center"><div><span class="mx-auto grid size-12 place-items-center rounded-2xl bg-slate-50 text-slate-400"><BookOpen :size="20" /></span><p class="mt-3 text-sm font-semibold text-slate-800">No titles found</p><p class="mt-1 text-xs text-slate-500">Try a different search or adjust the filters.</p></div></div>
        <div v-else class="grid gap-3 md:grid-cols-2">
          <article v-for="row in rows" :key="row.id" class="flex min-w-0 gap-4 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm transition hover:border-lnu-200 hover:shadow-md">
            <div class="relative grid h-36 w-24 shrink-0 place-items-center overflow-hidden rounded-xl bg-[#111968] text-white shadow-sm sm:h-40 sm:w-28">
              <img v-if="row.cover_url && !failedCoverIds.includes(row.id)" :src="row.cover_url" :alt="'Cover of ' + row.title" class="absolute inset-0 size-full object-cover" loading="lazy" @error="markCoverFailed(row.id)">
              <BookOpen v-else :size="26" :stroke-width="1.5" class="text-blue-100/80" />
            </div>
            <div class="flex min-w-0 flex-1 flex-col">
              <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                  <p class="line-clamp-2 text-sm font-semibold leading-5 text-slate-900">{{ row.title }}</p>
                  <p v-if="row.subtitle" class="mt-1 line-clamp-1 text-[10px] text-slate-500">{{ row.subtitle }}</p>
                </div>
                <span class="shrink-0 rounded-full px-2 py-1 text-[9px] font-semibold" :class="Number(row.available_copies_count ?? 0) > 0 ? 'bg-lnu-50 text-lnu-800' : 'bg-amber-50 text-amber-800'">{{ Number(row.available_copies_count ?? 0) > 0 ? 'Available' : Number(row.copies_count ?? 0) > 0 ? 'On loan' : 'No copies' }}</span>
              </div>
              <p class="mt-1.5 line-clamp-1 text-[11px] text-slate-500">{{ row.authors?.map((author: Row) => author.name).join(', ') || 'Author not listed' }}</p>
              <p class="mt-1 text-[10px] text-slate-400">{{ row.published_year || 'Year not listed' }}<span v-if="row.isbn"> · ISBN {{ row.isbn }}</span></p>
              <div v-if="row.categories?.length" class="mt-2 flex flex-wrap gap-1">
                <span v-for="category in row.categories.slice(0, 2)" :key="category.id" class="rounded-md bg-slate-100 px-1.5 py-1 text-[9px] font-medium text-slate-600">{{ category.name }}</span>
                <span v-if="row.categories.length > 2" class="rounded-md bg-slate-100 px-1.5 py-1 text-[9px] font-medium text-slate-500">+{{ row.categories.length - 2 }}</span>
              </div>
              <p v-if="row.description" class="mt-2 line-clamp-2 text-[10px] leading-4 text-slate-500">{{ row.description }}</p>
              <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3" :class="row.description || row.categories?.length ? 'mt-3' : 'mt-auto'">
                <span class="text-[10px] text-slate-500">{{ row.available_copies_count ?? 0 }} of {{ row.copies_count ?? 0 }} copies available</span>
                <div class="flex items-center gap-1.5">
                  <button v-if="resource === 'books' && !auth.isStaff" class="rounded-lg bg-lnu-950 px-2.5 py-1.5 text-[10px] font-semibold text-white hover:bg-lnu-900" @click="reserve(row)">Reserve</button>
                  <template v-if="resource === 'books' && auth.isStaff">
                    <button class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1.5 text-[10px] font-semibold text-slate-600 hover:bg-slate-50" @click="openEdit(row)"><Pencil :size="12" /> Edit</button>
                    <button class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1.5 text-[10px] font-semibold text-slate-600 hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700" @click="remove(row)"><Trash2 :size="12" /> Remove</button>
                  </template>
                </div>
              </div>
            </div>
          </article>
        </div>
      </div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-left">
          <thead class="bg-slate-50/80"><tr><th v-for="column in columns" :key="column" class="whitespace-nowrap px-4 py-3 text-[10px] font-bold uppercase tracking-wide text-slate-400 sm:px-5"><button :disabled="!canSort(column)" class="inline-flex items-center gap-1 disabled:cursor-default" :class="canSort(column) ? 'hover:text-slate-800' : ''" @click="sort(column)">{{ labels[column] ?? column }}<span v-if="sortBy === column">{{ sortDir === 'asc' ? '↑' : '↓' }}</span></button></th><th v-if="!isReadOnly" class="px-4 py-3 text-right text-[10px] font-bold uppercase tracking-wide text-slate-400 sm:px-5">Actions</th></tr></thead>
          <tbody v-if="loading"><tr><td :colspan="columns.length + (isReadOnly ? 0 : 1)" class="h-48 text-center"><LoaderCircle :size="22" class="mx-auto animate-spin text-lnu-800" /></td></tr></tbody>
          <tbody v-else-if="!rows.length"><tr><td :colspan="columns.length + (isReadOnly ? 0 : 1)" class="h-64 px-5 text-center"><span class="mx-auto grid size-12 place-items-center rounded-2xl bg-slate-50 text-slate-400"><BookOpen :size="20" /></span><p class="mt-3 text-sm font-semibold text-slate-800">No records found.</p><p class="mt-1 text-xs text-slate-500">Try another search or create a new record.</p></td></tr></tbody>
          <tbody v-else class="divide-y divide-slate-100">
            <tr v-for="row in rows" :key="row.id" class="transition hover:bg-slate-50/60">
              <td v-for="column in columns" :key="column" class="max-w-[300px] px-4 py-3.5 text-xs text-slate-600 sm:px-5">
                <span v-if="column === 'status' || column === 'role'" class="rounded-full px-2.5 py-1 text-[10px] font-semibold capitalize" :class="statusClass(String(row[column]))">{{ display(row, column) }}</span>
                <span v-else-if="column === 'title' || column === 'name' || column === 'accession_number'" class="block truncate font-semibold text-slate-800">{{ display(row, column) }}</span>
                <span v-else class="block truncate">{{ display(row, column) }}</span>
              </td>
              <td v-if="!isReadOnly" class="whitespace-nowrap px-4 py-3.5 text-right sm:px-5">
                <div class="inline-flex items-center gap-1">
                  <button v-if="resource === 'books' && !auth.isStaff" class="rounded-lg border border-lnu-200 px-2.5 py-1.5 text-[10px] font-semibold text-lnu-900 hover:bg-lnu-50" @click="reserve(row)">Reserve</button>
                  <template v-if="resource !== 'books' || auth.isStaff">
                    <button v-if="resource !== 'copies' || !['borrowed', 'reserved'].includes(row.status)" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-800" aria-label="Edit record" @click="openEdit(row)"><Pencil :size="14" /></button>
                    <button v-if="resource === 'books' || resource === 'members' || ['authors', 'publishers', 'categories', 'copies'].includes(resource)" class="rounded-lg p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-700" aria-label="Remove record" @click="remove(row)"><Trash2 :size="14" /></button>
                  </template>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="!loading && rows.length" class="flex items-center justify-between border-t border-slate-100 px-4 py-3.5 sm:px-5">
        <p class="text-[11px] text-slate-500">Page {{ page }} of {{ lastPage }} <span class="text-slate-300">·</span> {{ total }} total</p>
        <div class="flex gap-2"><button class="rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] font-medium text-slate-600 disabled:opacity-40" :disabled="page <= 1" @click="page--; load()">Previous</button><button class="rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] font-medium text-slate-600 disabled:opacity-40" :disabled="page >= lastPage" @click="page++; load()">Next</button></div>
      </div>
    </section>
    <div v-if="formOpen" class="fixed inset-0 z-[70] grid place-items-center overflow-y-auto bg-slate-950/35 p-4" @click.self="formOpen = false">
      <section class="my-6 w-full max-w-2xl rounded-2xl bg-white shadow-xl">
        <div class="flex items-start justify-between border-b border-slate-100 px-5 py-4"><div><h2 class="text-sm font-semibold">{{ editing ? 'Edit record' : actionLabel }}</h2><p class="mt-1 text-[11px] text-slate-500">Fields marked required must be completed.</p></div><button class="rounded-lg p-2 text-slate-400 hover:bg-slate-100" aria-label="Close form" @click="formOpen = false"><X :size="17" /></button></div>
        <form class="grid gap-4 p-5 sm:grid-cols-2" @submit.prevent="save">
          <div v-for="field in visibleFields" :key="field.key" class="space-y-1.5" :class="['textarea', 'multi'].includes(field.type ?? '') || field.key === 'description' ? 'sm:col-span-2' : ''">
            <label :for="'field-' + field.key" class="block text-[11px] font-semibold text-slate-700">{{ field.label }}<span v-if="field.required" class="ml-1 text-rose-600">*</span></label>
            <textarea v-if="field.type === 'textarea'" :id="'field-' + field.key" v-model="form[field.key]" rows="3" class="field min-h-24 resize-y py-2" />
            <select v-else-if="field.type === 'select'" :id="'field-' + field.key" v-model="form[field.key]" :required="field.required" class="field">
              <option value="">{{ field.key === 'publisher_id' ? 'No publisher selected' : 'Choose one' }}</option>
              <option v-for="option in field.options ?? []" :key="option.id" :value="option.id">{{ option.name }}</option>
            </select>
            <select v-else-if="field.type === 'multi'" :id="'field-' + field.key" v-model="form[field.key]" multiple class="field min-h-28 py-2"><option v-for="option in field.options ?? []" :key="option.id" :value="option.id">{{ option.name }}</option></select>
            <input v-else :id="'field-' + field.key" v-model="form[field.key]" :type="field.type ?? 'text'" :required="field.required" :autocomplete="field.key === 'password' ? 'new-password' : 'off'" class="field">
            <p v-if="field.type === 'multi'" class="text-[10px] text-slate-400">Use Ctrl or Command to select multiple options.</p>
          </div>
          <p v-if="error" role="alert" class="text-xs text-rose-700 sm:col-span-2">{{ error }}</p>
          <div class="flex justify-end gap-2 border-t border-slate-100 pt-4 sm:col-span-2">
            <button type="button" class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50" @click="formOpen = false">Cancel</button>
            <button type="submit" :disabled="saving" class="inline-flex items-center gap-2 rounded-xl bg-lnu-950 px-4 py-2.5 text-xs font-semibold text-white hover:bg-lnu-900 disabled:opacity-60"><LoaderCircle v-if="saving" :size="14" class="animate-spin" />{{ saving ? 'Saving…' : 'Save record' }}</button>
          </div>
        </form>
      </section>
    </div>
  </div>
</template>
