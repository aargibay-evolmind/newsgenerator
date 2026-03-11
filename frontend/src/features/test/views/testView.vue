<script lang="ts" setup>
import { useAskGemini } from '../composables/useAskGemini'

const { mutate, data, isPending, isError, error } = useAskGemini()

function handleAsk() {
  mutate('Why is the sky blue?')
}
</script>

<template>
  <div>
    <h1>Test page</h1>

    <button :disabled="isPending" @click="handleAsk">
      {{ isPending ? 'Asking Gemini…' : 'Ask Gemini' }}
    </button>

    <p v-if="isError" style="color: red">Error: {{ error?.message }}</p>

    <div v-if="data">
      <h2>Response</h2>
      <p>{{ data }}</p>
    </div>
  </div>
</template>