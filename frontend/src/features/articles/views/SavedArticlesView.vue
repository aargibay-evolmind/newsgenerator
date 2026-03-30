<script setup lang="ts">
import { ref, computed } from 'vue';
import { useSavedArticles, useDeleteArticle } from '../composables';
import MainHeader from '@/components/MainHeader.vue';
import { useRouter } from 'vue-router';
import { useArticleStore } from '../store/articleStore';

const router = useRouter();
const { data: articles, isLoading, isError } = useSavedArticles();
const { mutate: deleteArticle } = useDeleteArticle();
const { getToneLabel } = useArticleStore();

const searchQuery = ref('');
const sortBy = ref<'newest' | 'oldest' | 'alphabetical'>('newest');

function goToArticle(id: string) {
  router.push({ name: 'SavedArticleDetail', params: { id } });
}

function handleDelete(id: string) {
  if (confirm('¿Estás seguro de que quieres eliminar este artículo?')) {
    deleteArticle(id);
  }
}

const stats = computed(() => {
  if (!articles.value) return { count: 0, readingTime: 0, today: 0 };
  
  const now = new Date();
  const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();
  
  return articles.value.reduce((acc: any, art: any) => {
    acc.count++;
    acc.readingTime += art.data?.readingTime || 0;
    if (new Date(art.created_at).getTime() >= todayStart) {
      acc.today++;
    }
    return acc;
  }, { count: 0, readingTime: 0, today: 0 });
});

const filteredArticles = computed(() => {
  if (!articles.value) return [];
  
  const result = articles.value.filter((art: any) => 
    art.title.toLowerCase().includes(searchQuery.value.toLowerCase())
  );
  
  if (sortBy.value === 'newest') {
    result.sort((a: any, b: any) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime());
  } else if (sortBy.value === 'oldest') {
    result.sort((a: any, b: any) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime());
  } else if (sortBy.value === 'alphabetical') {
    result.sort((a: any, b: any) => a.title.localeCompare(b.title));
  }
  
  return result;
});
</script>

<template>
  <div class="bg-background dark:bg-dark-background text-text dark:text-dark-text font-sans flex flex-col min-h-screen pb-32 transition-colors duration-300">
    <MainHeader />

    <main class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-8 py-10">
      <!-- Header Section -->
      <div class="mb-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div>
          <h1 class="text-4xl font-black tracking-tighter bg-clip-text text-transparent bg-gradient-to-r from-primary to-primary/60">Panel de Control</h1>
          <p class="text-sm text-secondary dark:text-dark-text/60 mt-2 font-medium">Gestiona tu estrategia de contenidos y artículos generados.</p>
        </div>
        
        <router-link 
          to="/generador" 
          class="inline-flex items-center gap-2.5 px-6 py-4 bg-primary text-white text-sm font-black rounded-[1.25rem] hover:bg-primary/90 transition-all shadow-xl shadow-primary/20 hover:scale-[1.02] active:scale-[0.98]"
        >
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4" />
          </svg>
          Nuevo Artículo
        </router-link>
      </div>

      <!-- Stats Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-12">
        <div class="bg-background dark:bg-dark-surface p-6 rounded-[2rem] border border-secondary/10 dark:border-dark-border shadow-sm flex flex-col gap-1 transition-colors">
          <span class="text-[10px] font-bold text-secondary dark:text-dark-text/40 uppercase tracking-widest">Total Artículos</span>
          <div class="flex items-end gap-2">
            <span class="text-3xl font-black text-text dark:text-dark-text leading-none">{{ stats.count }}</span>
            <span class="text-xs font-bold text-secondary dark:text-dark-text/40 pb-0.5">generados</span>
          </div>
        </div>
        <div class="bg-background dark:bg-dark-surface p-6 rounded-[2rem] border border-secondary/10 dark:border-dark-border shadow-sm flex flex-col gap-1 transition-colors">
          <span class="text-[10px] font-bold text-secondary dark:text-dark-text/40 uppercase tracking-widest">Tiempo de Lectura</span>
          <div class="flex items-end gap-2">
            <span class="text-3xl font-black text-text dark:text-dark-text leading-none">~{{ stats.readingTime }}</span>
            <span class="text-xs font-bold text-secondary dark:text-dark-text/40 pb-0.5">minutos totales</span>
          </div>
        </div>
        <div class="bg-background dark:bg-dark-surface p-6 rounded-[2rem] border border-secondary/10 dark:border-dark-border shadow-sm flex flex-col gap-1 transition-colors">
          <span class="text-[10px] font-bold text-secondary dark:text-dark-text/40 uppercase tracking-widest">Actividad Hoy</span>
          <div class="flex items-end gap-2 text-primary">
            <span class="text-3xl font-black leading-none">+{{ stats.today }}</span>
            <span class="text-xs font-bold pb-0.5">nuevos artículos</span>
          </div>
        </div>
      </div>


      <!-- Controls -->
      <div class="flex flex-col md:flex-row gap-4 mb-8">
        <div class="flex-1 relative group">
          <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none">
            <svg class="h-4 w-4 text-secondary dark:text-dark-text/30 group-focus-within:text-primary transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <input 
            v-model="searchQuery" 
            type="text" 
            placeholder="Buscar por título..."
            class="w-full pl-11 pr-4 py-3.5 bg-background dark:bg-dark-surface border border-secondary/10 dark:border-dark-border rounded-2xl text-sm font-medium text-text dark:text-dark-text placeholder:text-secondary/40 dark:placeholder:text-dark-text/30 focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all shadow-sm"
          />
        </div>
        <div class="flex gap-4">
          <select 
            v-model="sortBy"
            class="px-4 py-3.5 bg-background dark:bg-dark-surface border border-secondary/10 dark:border-dark-border rounded-2xl text-sm font-bold text-text dark:text-dark-text outline-none focus:ring-2 focus:ring-primary/20 shadow-sm cursor-pointer transition-colors"
          >
            <option value="newest">Más recientes</option>
            <option value="oldest">Más antiguos</option>
            <option value="alphabetical">Alfabético</option>
          </select>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div v-for="i in 6" :key="i" class="bg-background dark:bg-dark-surface rounded-[2rem] border border-secondary/10 dark:border-dark-border p-6 h-48 animate-pulse transition-colors">
          <div class="h-4 w-3/4 bg-secondary/5 dark:bg-dark-background rounded-full mb-4"></div>
          <div class="h-4 w-1/2 bg-secondary/5 dark:bg-dark-background rounded-full mb-6"></div>
          <div class="mt-auto flex justify-between">
            <div class="h-3 w-20 bg-secondary/5 dark:bg-dark-background rounded-full"></div>
            <div class="h-3 w-24 bg-secondary/5 dark:bg-dark-background rounded-full"></div>
          </div>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="isError" class="p-8 bg-red-50 dark:bg-red-900/10 rounded-3xl border border-red-100 dark:border-red-900/30 flex flex-col items-center text-center transition-colors">
        <div class="h-14 w-14 bg-background dark:bg-dark-surface rounded-2xl shadow-sm border border-red-100 dark:border-red-900/30 flex items-center justify-center mb-4">
          <svg class="h-6 w-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
        </div>
        <h3 class="text-lg font-bold text-red-900 dark:text-red-400">Error de conexión</h3>
        <p class="text-red-600/70 dark:text-red-400/60 text-sm mt-1 mb-6">No pudimos recuperar tus artículos en este momento.</p>
        <button @click="router.go(0)" class="px-5 py-2.5 bg-red-600 text-white text-xs font-bold rounded-xl hover:bg-red-700 transition-colors">Reintentar</button>
      </div>

      <!-- Empty State -->
      <div v-else-if="!articles || articles.length === 0" class="text-center py-20 bg-background dark:bg-dark-surface rounded-[3rem] border-2 border-secondary/10 dark:border-dark-border border-dashed shadow-sm transition-colors">
        <div class="h-24 w-24 bg-secondary/5 dark:bg-dark-background rounded-[2rem] flex items-center justify-center mx-auto mb-6">
          <svg class="h-10 w-10 text-secondary/20 dark:text-dark-text/10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l4 4v10a2 2 0 01-2 2z" />
          </svg>
        </div>
        <h3 class="text-2xl font-black text-text dark:text-dark-text mb-2">Empieza a crear hoy</h3>
        <p class="text-secondary dark:text-dark-text/40 font-medium mb-8 max-w-sm mx-auto">Tu repositorio de noticias está vacío. Usa nuestra IA de vanguardia para generar contenido académico imparable.</p>
        <router-link to="/generador" class="inline-flex items-center gap-2 px-10 py-5 bg-primary text-white text-sm font-black rounded-2xl hover:bg-primary/90 transition-all shadow-xl shadow-primary/20">
          Generar mi primer artículo
        </router-link>
      </div>

      <!-- Main Content Area (Results) -->
      <div v-else-if="articles && articles.length > 0">
        <!-- Results Info (Search Summary) -->
        <div v-if="searchQuery && filteredArticles.length > 0" class="mb-6 ml-2 animate-in fade-in slide-in-from-left-4 duration-500">
          <span class="text-xs font-bold text-secondary dark:text-dark-text/30">Mostrando {{ filteredArticles.length }} resultados para "{{ searchQuery }}"</span>
        </div>

        <!-- Article Grid -->
        <div v-if="filteredArticles.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          <div 
            v-for="article in filteredArticles" 
            :key="article.id"
            class="bg-background dark:bg-dark-surface rounded-[2rem] shadow-sm border border-secondary/10 dark:border-dark-border p-7 flex flex-col hover:shadow-xl hover:shadow-primary/5 transition-all cursor-pointer group relative"
            @click="goToArticle(article.id)"
          >
            <!-- Metadata Badges -->
            <div class="flex items-center gap-2 mb-4">
              <span v-if="article.data?.tone !== undefined" class="px-2.5 py-1 bg-secondary/5 dark:bg-dark-background text-[10px] font-black text-secondary dark:text-dark-text/40 rounded-full uppercase tracking-wider">
                {{ getToneLabel(article.data.tone) }}
              </span>
              <span v-if="article.data?.readingTime" class="px-2.5 py-1 bg-primary/5 text-[10px] font-black text-primary rounded-full uppercase tracking-wider flex items-center gap-1">
                <svg class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                {{ article.data.readingTime }} min
              </span>
            </div>

            <div class="flex justify-between items-start mb-6">
              <h2 class="text-xl font-black text-text dark:text-dark-text group-hover:text-primary transition-colors line-clamp-2 leading-tight pr-6" :title="article.title">
                {{ article.title }}
              </h2>
              <button 
                @click.stop="handleDelete(article.id)"
                class="absolute top-6 right-6 p-2 text-secondary/20 dark:text-dark-text/10 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-xl transition-all opacity-0 group-hover:opacity-100"
                title="Eliminar artículo"
              >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </div>

            <!-- Keywords Mini-grid -->
            <div v-if="article.data?.keywords && article.data.keywords.length > 0" class="flex flex-wrap gap-2 mb-8">
              <span 
                v-for="kw in article.data.keywords.slice(0, 3)" 
                :key="kw"
                class="text-[10px] font-bold text-secondary/40 dark:text-dark-text/20 border border-secondary/10 dark:border-dark-border/40 px-2 py-0.5 rounded-lg"
              >
                #{{ kw.toLowerCase().replace(/\s+/g, '') }}
              </span>
            </div>

            <div class="mt-auto pt-6 flex items-center justify-between border-t border-secondary/5 dark:border-dark-border/40">
              <div class="flex flex-col">
                <span class="text-[9px] font-bold text-secondary dark:text-dark-text/20 uppercase tracking-widest leading-none mb-1">Creado el</span>
                <span class="text-[11px] font-black text-secondary/60 dark:text-dark-text/40">{{ new Date(article.created_at).toLocaleDateString() }}</span>
              </div>
              <div class="h-10 w-10 rounded-full bg-secondary/5 dark:bg-dark-background flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-white transition-all shadow-sm">
                <svg class="h-5 w-5 group-hover:translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" /></svg>
              </div>
            </div>
          </div>
        </div>

        <!-- No Results for Search -->
        <div v-else-if="searchQuery" class="text-center py-24 bg-background dark:bg-dark-surface rounded-[3rem] border border-secondary/10 dark:border-dark-border shadow-sm animate-in zoom-in-95 duration-300 transition-colors">
          <div class="h-20 w-20 bg-secondary/5 dark:bg-dark-background rounded-full flex items-center justify-center mx-auto mb-6">
            <svg class="h-8 w-8 text-secondary/20 dark:text-dark-text/10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <h3 class="text-xl font-bold text-text dark:text-dark-text mb-2">Sin resultados</h3>
          <p class="text-secondary dark:text-dark-text/40 font-medium">No encontramos nada que coincida con "{{ searchQuery }}"</p>
          <button @click="searchQuery = ''" class="mt-6 text-sm font-black text-primary hover:underline">Limpiar búsqueda</button>
        </div>
      </div>
    </main>
  </div>
</template>

