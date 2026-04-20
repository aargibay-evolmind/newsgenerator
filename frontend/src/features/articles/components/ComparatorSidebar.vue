<script setup lang="ts">
import { onMounted, watch, computed, ref } from 'vue';
import { useAnalyzeCompetitor } from '../composables/useAnalyzeCompetitor';
import { apiClient } from '@/lib/apiClient';
import type { AnalyzeCompetitorResponse } from '../types';

const props = defineProps<{
  competitorUrl: string;
}>();

const { mutate: analyzeCompetitor, data, isPending, isError, error } = useAnalyzeCompetitor();

const errorMessage = computed(() => {
  if (error.value && typeof error.value === 'object' && 'message' in error.value) {
    const rawMessage = (error.value as { message: string }).message || '';
    if (rawMessage.includes('429')) {
       return 'Límite de la API de IA alcanzado (429). Espera ~1 minuto e inténtalo de nuevo.';
    }
    if (rawMessage.includes('503')) {
       return 'Los servidores de Gemini IA están saturados (503). Reintenta en unos instantes.';
    }
    return rawMessage;
  }
  return 'No se ha podido analizar la URL.';
});

onMounted(() => {
  if (props.competitorUrl) {
    analyzeCompetitor({
      competitorUrl: props.competitorUrl
    });
  }
});

// If the competitor URL changes while mounted, re-fetch.
watch(() => props.competitorUrl, (newUrl) => {
  if (newUrl) {
    analyzeCompetitor({
      competitorUrl: newUrl
    });
  }
});

const isDownloading = ref(false);

async function downloadMarkdown() {
  if (isDownloading.value) return;
  
  isDownloading.value = true;
  try {
    const response = await apiClient<AnalyzeCompetitorResponse>('/articles/analyze-competitor', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ 
        competitorUrl: props.competitorUrl,
        includeMarkdown: true
      })
    });
    
    if (response.markdown) {
      const blob = new Blob([response.markdown], { type: 'text/markdown;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `competencia-${new URL(props.competitorUrl).hostname}.md`;
      a.click();
      URL.revokeObjectURL(url);
    }
  } catch (err) {
    console.error("Failed to download markdown", err);
    alert('Error al generar el Markdown. Es posible que se haya excedido el límite de la API.');
  } finally {
    isDownloading.value = false;
  }
}
</script>

<template>
  <div class="h-full bg-slate-50 dark:bg-dark-surface border-l border-secondary/10 dark:border-dark-border flex flex-col shrink-0 overflow-hidden transition-colors w-[320px]">
    <!-- Header -->
    <div class="p-4 border-b border-secondary/10 dark:border-dark-border flex items-center justify-between bg-background dark:bg-dark-background shrink-0">
      <div class="flex items-center gap-2">
        <div class="p-1 rounded-lg bg-orange-50 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
          </svg>
        </div>
        <h3 class="text-xs font-black text-text dark:text-dark-text uppercase tracking-widest">Análisis Competencia</h3>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isPending" class="flex-1 flex flex-col items-center justify-center p-6 text-center space-y-4">
      <svg class="animate-spin h-8 w-8 text-orange-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
      <p class="text-xs font-bold text-secondary dark:text-dark-text/60 animate-pulse">Analizando estructura y contenido...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="isError" class="flex-1 p-6 text-center">
      <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 mb-4">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
      </div>
      <h4 class="text-sm font-bold text-text dark:text-dark-text mb-2">Error de Análisis</h4>
      <p class="text-[10px] text-secondary/60 dark:text-dark-text/40 break-words">{{ errorMessage }}</p>
      <button @click="analyzeCompetitor({ competitorUrl: props.competitorUrl })" class="mt-6 px-4 py-2 bg-text dark:bg-dark-text text-white dark:text-dark-background text-xs font-bold rounded-lg hover:bg-opacity-90">Reintentar</button>
    </div>

    <!-- Result State -->
    <div v-else-if="data" class="flex-1 overflow-y-auto custom-scrollbar flex flex-col p-5 space-y-6">
      
      <!-- Competitor Link -->
      <div class="bg-orange-50/50 dark:bg-orange-900/10 border border-orange-100 dark:border-orange-900/30 rounded-xl p-3">
        <p class="text-[9px] font-bold text-orange-600 dark:text-orange-400 uppercase tracking-widest mb-1">Rival analizado</p>
        <a :href="props.competitorUrl" target="_blank" rel="noopener noreferrer" class="text-[10px] text-text dark:text-dark-text hover:text-orange-500 font-mono truncate block" :title="props.competitorUrl">
          {{ props.competitorUrl }}
        </a>
      </div>

      <!-- Action Button -->
      <button @click="downloadMarkdown" :disabled="isDownloading" class="flex items-center justify-center gap-2 w-full py-2.5 px-3 bg-text dark:bg-dark-text text-background dark:text-dark-background rounded-xl text-xs font-bold transition-all hover:bg-opacity-90 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
        <svg v-if="isDownloading" class="animate-spin h-4 w-4 text-background dark:text-dark-background" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
        <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
        {{ isDownloading ? 'Procesando (Tarda...' : 'Descargar Artículo (.MD)' }}
      </button>

      <!-- Total Length -->
      <div class="space-y-4">
        <h4 class="text-[10px] font-black text-secondary/40 dark:text-dark-text/30 uppercase tracking-widest border-b border-secondary/10 dark:border-dark-border pb-2">Extensión Total</h4>
        <div class="bg-background dark:bg-dark-background border border-secondary/20 dark:border-dark-border rounded-xl p-3 shadow-sm flex items-end justify-between">
          <span class="text-2xl font-black text-text dark:text-dark-text leading-none">{{ data.totalLength }}</span>
          <span class="text-xs text-secondary/60 dark:text-dark-text/50 font-bold mb-0.5">palabras</span>
        </div>
      </div>

      <!-- Keywords Comp -->
      <div class="space-y-4">
        <h4 class="text-[10px] font-black text-secondary/40 dark:text-dark-text/30 uppercase tracking-widest border-b border-secondary/10 dark:border-dark-border pb-2">Palabras Clave (Top)</h4>
        <div class="flex flex-wrap gap-1.5">
          <span v-for="kw in data.keywords" :key="kw" class="px-2 py-1 bg-secondary/10 dark:bg-dark-surface text-text dark:text-dark-text text-[10px] font-medium rounded-md border border-secondary/20 dark:border-dark-border">
            {{ kw }}
          </span>
        </div>
      </div>

      <!-- IA Suggestions (Headers Breakdown) -->
      <div class="space-y-4">
        <h4 class="text-[10px] font-black text-secondary/40 dark:text-dark-text/30 uppercase tracking-widest border-b border-secondary/10 dark:border-dark-border pb-2 flex items-center gap-1.5">
          <svg class="h-3 w-3 text-orange-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h6a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" /></svg>
          Estructura y Encabezados
        </h4>
        
        <ul class="space-y-2">
          <li v-for="(header, idx) in data.headers" :key="idx" class="flex flex-col bg-background dark:bg-dark-surface border border-secondary/10 dark:border-dark-border rounded-lg p-2.5 transition-all">
            <span class="text-[11px] text-text dark:text-dark-text font-bold mb-1">{{ header.text }}</span>
            <span class="text-[10px] text-orange-600 dark:text-orange-400 font-semibold">{{ header.length }} palabras</span>
          </li>
        </ul>
      </div>

    </div>
  </div>
</template>

