<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import AppShell from '@/components/AppShell.vue'

const route = useRoute()
const isGuestPage = computed(() => route.meta.guest === true)
const mobileNavOpen = ref(false)
watch(() => route.fullPath, () => { mobileNavOpen.value = false })
</script>

<template>
  <RouterView v-if="isGuestPage" />
  <AppShell v-else v-model:mobile-open="mobileNavOpen">
    <RouterView />
  </AppShell>
</template>
