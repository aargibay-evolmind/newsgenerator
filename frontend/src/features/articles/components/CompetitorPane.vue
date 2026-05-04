<script setup lang="ts">
import { renderMarkdown } from '@/utils/markdown'

const props = defineProps<{
  markdown: string;
  url: string;
}>();
</script>

<template>
  <div class="h-full flex flex-col bg-slate-50 dark:bg-dark-surface overflow-hidden transition-colors">
    <!-- Header -->
    <div class="px-6 py-4 border-b border-secondary/10 dark:border-dark-border bg-background dark:bg-dark-background flex items-center justify-between shrink-0">
      <div class="flex items-center gap-3">
        <div class="p-1.5 rounded-lg bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
          </svg>
        </div>
        <div>
          <h3 class="text-xs font-black text-text dark:text-dark-text uppercase tracking-widest leading-none mb-1">Vista Competencia</h3>
          <p class="text-[10px] text-secondary/50 dark:text-dark-text/30 font-medium truncate max-w-[200px]" :title="url">{{ url }}</p>
        </div>
      </div>
      <span class="px-2 py-0.5 bg-orange-500/10 text-orange-500 text-[9px] font-black uppercase tracking-wider rounded border border-orange-500/20">Solo Lectura</span>
    </div>

    <!-- Content Area -->
    <div class="flex-1 overflow-y-auto p-8 sm:p-12 custom-scrollbar bg-slate-50/50 dark:bg-dark-surface/50">
      <div v-if="markdown" class="prose prose-sm dark:prose-invert max-w-none prose-headings:text-text dark:prose-headings:text-dark-text prose-p:text-secondary dark:prose-p:text-dark-text/80" v-html="renderMarkdown(markdown)">
      </div>
      <div v-else class="h-full flex flex-col items-center justify-center text-center p-12 opacity-40">
        <svg class="h-12 w-12 text-secondary mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
        </svg>
        <p class="text-sm font-bold uppercase tracking-widest text-secondary">Cargando artículo...</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
@reference "../../../assets/main.css";

:deep(.prose h1) { @apply text-2xl font-black mb-6; }
:deep(.prose h2) { @apply text-xl font-bold mt-8 mb-4 border-b border-secondary/10 pb-2; }
:deep(.prose h3) { @apply text-lg font-bold mt-6 mb-3; }
:deep(.prose p) { @apply mb-4 leading-relaxed; }
:deep(.prose ul) { @apply list-disc pl-5 mb-4; }
:deep(.prose ol) { @apply list-decimal pl-5 mb-4; }
:deep(.prose li) { @apply mb-1; }
:deep(.prose img) { @apply rounded-xl shadow-lg my-8; }
</style>
