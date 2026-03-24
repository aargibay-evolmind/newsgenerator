<script setup lang="ts">
import '@/assets/main.css'
import { useDarkMode } from '@/composables/useDarkMode'

const { initDark } = useDarkMode()
initDark()
</script>

<template>
  <RouterView />
</template>
