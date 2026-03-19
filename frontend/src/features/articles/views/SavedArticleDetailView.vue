<script setup lang="ts">
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useSavedArticle, useDeleteArticle } from '../composables';
import { marked } from 'marked';
import MainHeader from '@/components/MainHeader.vue';
import DOMPurify from 'dompurify';

const route = useRoute();
const router = useRouter();
const articleId = route.params.id as string;

const { data: article, isLoading, isError } = useSavedArticle(articleId);
const { mutate: deleteArticle } = useDeleteArticle();

const renderedHtml = computed(() => {
  if (!article.value?.data?.markdown) return '';
  const rawHtml = marked.parse(article.value.data.markdown) as string;
  return DOMPurify.sanitize(rawHtml, {
    ADD_ATTR: ['src'],
    ALLOWED_URI_REGEXP: /^(?:(?:(?:f|ht)tps?|mailto|tel|callto|cid|xmpp|data):|[^a-z]|[a-z+.\-]+(?:[^a-z+.\-:]|$))/i
  }).replace(/<details>/g, '<details open>');
});

function goBack() {
  router.push({ name: 'SavedArticles' });
}

function handleDelete() {
  if (confirm('¿Estás seguro de que quieres eliminar esta noticia?')) {
    deleteArticle(articleId, {
      onSuccess: () => {
        router.push({ name: 'SavedArticles' });
      }
    });
  }
}
</script>

<template>
  <div class="bg-background text-text font-sans flex flex-col min-h-screen pb-20">
    <MainHeader />

    <main class="flex-1 w-full max-w-4xl mx-auto px-4 sm:px-8 py-8">
      <div v-if="isLoading" class="flex justify-center items-center py-20">
        <svg class="animate-spin h-8 w-8 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
      </div>
      <div v-else-if="isError || !article" class="text-red-500 py-10 bg-red-50 px-6 rounded-xl border border-red-100">
        Error al cargar la noticia.
      </div>
      <div v-else class="space-y-6">
        <!-- Breadcrumbs / Actions -->
        <div class="flex items-center justify-between gap-4 mt-4">
          <button @click="goBack" class="inline-flex items-center gap-2 text-xs font-bold text-secondary hover:text-primary transition-colors">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            Volver al Historial
          </button>
          
          <div class="flex items-center gap-3">
            <button 
              @click="handleDelete"
              class="inline-flex items-center justify-center gap-2 rounded-xl bg-white border border-red-200 px-5 py-2.5 text-xs font-bold text-red-500 hover:bg-red-50 transition-all shadow-sm"
            >
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
              Eliminar
            </button>
            <router-link :to="{ name: 'EditSavedArticle', params: { id: articleId } }" class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-primary/90 transition-all shadow-lg shadow-primary/10">
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
              Editar Noticia
            </router-link>
          </div>
        </div>

        <div class="bg-white rounded-3xl shadow-xl border border-slate-100 overflow-hidden">
          <!-- Header -->
          <div class="px-8 py-8 border-b border-slate-100 bg-slate-50/50">
            <h1 class="text-3xl font-extrabold text-slate-900 leading-tight mb-4">{{ article.title }}</h1>
            <div class="flex items-center gap-4 text-[10px] font-black text-secondary/40 uppercase tracking-widest">
              <div class="flex items-center gap-1.5">
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                {{ new Date(article.created_at).toLocaleDateString() }}
              </div>
            </div>
          </div>
          
          <!-- Content -->
          <div class="p-8 sm:p-12">
            <div 
              v-html="renderedHtml" 
              class="prose prose-slate max-w-none prose-headings:font-extrabold prose-headings:tracking-tight prose-a:text-primary prose-img:rounded-3xl prose-img:shadow-lg prose-details:p-0 prose-details:bg-transparent prose-details:border-0 prose-summary:list-none prose-summary:p-0"
            ></div>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<style scoped>
@reference "../../../assets/main.css";

/* Custom TOC Styles */
:deep(details) {
  @apply bg-background border border-secondary/20 rounded-xl my-6 overflow-hidden transition-all duration-300;
}

:deep(summary) {
  @apply px-5 py-3 cursor-pointer font-bold text-text bg-secondary/5 hover:bg-secondary/10 transition-colors list-none flex items-center gap-2;
}

:deep(summary::-webkit-details-marker) {
  display: none;
}

:deep(summary::before) {
  content: "▸";
}

:deep(details[open] summary::before) {
  content: "▾";
}

:deep(details > ul) {
  @apply px-8 py-4 space-y-2 !mt-0 border-t border-secondary/10 px-6 sm:px-12 lg:px-16;
}

:deep(details > ul li a) {
  @apply text-primary hover:text-primary/80 no-underline transition-colors block text-sm font-medium;
}

/* Table Responsive Fix */
:deep(.prose table) {
  @apply block w-full overflow-x-auto border-collapse;
  -webkit-overflow-scrolling: touch;
}

:deep(.prose thead) {
  @apply bg-secondary/5;
}

:deep(.prose th), :deep(.prose td) {
  @apply border border-secondary/10 px-4 py-2 min-w-[120px];
}
</style>
