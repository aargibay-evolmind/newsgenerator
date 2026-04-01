<script setup lang="ts">
import { computed, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useSavedArticle, useDeleteArticle } from '../composables';
import MainHeader from '@/components/MainHeader.vue';
import { renderMarkdown, revokeMarkdownBlobs } from '@/utils/markdown';

const route = useRoute();
const router = useRouter();
const articleId = route.params.id as string;

const { data: article, isLoading, isError } = useSavedArticle(articleId);
const { mutate: deleteArticle } = useDeleteArticle();

const renderedHtml = computed(() => renderMarkdown(article.value?.data?.markdown));

onUnmounted(() => {
  revokeMarkdownBlobs();
});

function goBack() {
  router.push({ name: 'SavedArticles' });
}

function handleDelete() {
  if (confirm('¿Estás seguro de que quieres eliminar este artículo?')) {
    deleteArticle(articleId, {
      onSuccess: () => router.push({ name: 'SavedArticles' })
    });
  }
}
</script>

<template>
  <div class="bg-background dark:bg-dark-background text-text dark:text-dark-text font-sans min-h-screen flex flex-col transition-colors">
    <MainHeader />
    
    <main class="flex-1 w-full max-w-4xl mx-auto px-4 sm:px-8 py-10 pb-24">
      <!-- Loading State -->
      <div v-if="isLoading" class="flex flex-col items-center justify-center py-20">
        <svg class="animate-spin h-10 w-10 text-primary mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
        <span class="text-secondary font-bold">Cargando artículo...</span>
      </div>

      <!-- Error State -->
      <div v-else-if="isError || !article" class="bg-red-50 dark:bg-red-900/10 p-10 rounded-[3rem] border border-red-100 flex flex-col items-center text-center">
        <span class="text-4xl mb-4">❌</span>
        <h2 class="text-xl font-bold text-red-900 mb-2">Error al cargar el artículo</h2>
        <p class="text-red-700/70 mb-6">No pudimos encontrar el artículo solicitado.</p>
        <button @click="goBack" class="px-6 py-3 bg-red-600 text-white rounded-2xl font-bold hover:bg-red-700 transition-colors shadow-lg shadow-red-200">Volver al panel</button>
      </div>

      <!-- Article Content -->
      <div v-else class="animate-in fade-in duration-700">
        <!-- Navigation -->
        <div class="flex items-center justify-between mb-10">
          <button 
            @click="goBack"
            class="flex items-center gap-2 text-sm font-bold text-secondary hover:text-primary transition-colors group"
          >
            <svg class="h-4 w-4 group-hover:-translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7" /></svg>
            Volver
          </button>
          
          <div class="flex items-center gap-3">
            <button 
              @click="handleDelete"
              class="p-2 text-secondary/30 hover:text-red-500 hover:bg-red-50 rounded-xl transition-all"
              title="Eliminar artículo"
            >
              <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            </button>
            <router-link 
              :to="{ name: 'EditSavedArticle', params: { id: articleId } }"
              class="px-5 py-2.5 bg-primary text-white text-sm font-bold rounded-xl hover:bg-primary/90 transition-all shadow-lg shadow-primary/20"
            >
              Editar Artículo
            </router-link>
          </div>
        </div>

        <header class="mb-12">
          <div class="flex items-center gap-3 mb-6">
            <span v-if="article.data?.readingTime" class="px-2.5 py-1 bg-primary/5 text-[10px] font-black text-primary rounded-full uppercase tracking-wider flex items-center gap-1">
              <svg class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
              {{ article.data.readingTime }} min lectura
            </span>
            <span class="text-[10px] font-bold text-secondary tracking-widest uppercase">{{ new Date(article.created_at).toLocaleDateString('es-ES', { day: 'numeric', month: 'long', year: 'numeric' }) }}</span>
          </div>
          <div v-if="article.data?.keywords?.length" class="flex flex-wrap gap-2">
            <span v-for="tag in article.data.keywords" :key="tag" class="text-[10px] font-bold text-secondary/50 bg-secondary/5 px-2.5 py-1 rounded-lg">#{{ tag.toLowerCase().replace(/\s+/g, '') }}</span>
          </div>
        </header>

        <!-- Preview View (Rendered) -->
        <article 
          class="prose prose-slate prose-lg dark:prose-invert max-w-none 
                 prose-h2:text-2xl prose-h2:font-black prose-h2:tracking-tight prose-h2:mt-12 prose-h2:mb-6
                 prose-p:text-slate-600 dark:prose-p:text-dark-text/70 prose-p:leading-relaxed prose-p:mb-6
                 prose-img:rounded-[2rem] prose-img:shadow-2xl prose-img:my-10
                 prose-strong:text-slate-900 dark:prose-strong:text-dark-text prose-strong:font-bold
                 prose-a:text-primary prose-a:no-underline hover:prose-a:underline"
          v-html="renderedHtml"
        ></article>
      </div>
    </main>
  </div>
</template>

<style scoped>
@reference "../../../assets/main.css";

/* Custom styles for rendered article */
:deep(h2) {
  @apply text-3xl font-black tracking-tight mt-12 mb-6 text-slate-900 dark:text-dark-text;
}

:deep(p) {
  @apply text-slate-600 dark:text-dark-text/70 leading-relaxed mb-6;
}

:deep(ul), :deep(ol) {
  @apply mb-8 space-y-3;
}

:deep(li) {
  @apply text-slate-600 dark:text-dark-text/70;
}

:deep(img) {
  @apply rounded-[2rem] shadow-2xl my-12 mx-auto block;
}

:deep(blockquote) {
  @apply border-l-4 border-primary/20 pl-6 italic text-slate-500 my-10 bg-slate-50/50 dark:bg-dark-surface p-8 rounded-r-3xl;
}
</style>
