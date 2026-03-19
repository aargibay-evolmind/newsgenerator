<script lang="ts" setup>
import { ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useSavedArticle, useUpdateArticle } from '../composables'
import DualPaneEditor from '../components/DualPaneEditor.vue'
import MainHeader from '@/components/MainHeader.vue'

const route = useRoute()
const router = useRouter()
const articleId = route.params.id as string

const { data: article, isLoading, isError } = useSavedArticle(articleId)
const { mutateAsync: updateArticleMutation, isPending: isUpdating } = useUpdateArticle()

const generatedMarkdown = ref('')

watch(article, (newArticle) => {
  if (newArticle?.data?.markdown) {
    generatedMarkdown.value = newArticle.data.markdown
  }
}, { immediate: true })

const successMessage = ref<string | null>(null)
const errorMessage = ref<string | null>(null)

async function handleSave() {
  if (isUpdating.value || !article.value) return;
  try {
    await updateArticleMutation({
      id: articleId,
      data: {
        title: article.value.title,
        data: {
          markdown: generatedMarkdown.value
        }
      }
    });
    successMessage.value = "✅ Cambios guardados correctamente.";
    setTimeout(() => { successMessage.value = null; }, 4000);
  } catch (error) {
    errorMessage.value = "❌ Error al guardar los cambios.";
    setTimeout(() => { errorMessage.value = null; }, 5000);
  }
}

function handleBack() {
  router.push({ name: 'SavedArticleDetail', params: { id: articleId } });
}
</script>

<template>
  <div class="bg-background text-text font-sans flex flex-col h-screen overflow-hidden">
    <MainHeader />
    
    <main class="flex-1 flex flex-col min-h-0">
      <!-- Loading State -->
      <div v-if="isLoading" class="flex-1 flex justify-center items-center">
        <div class="bg-white p-6 rounded-2xl shadow-xl border border-secondary/10 flex items-center gap-4">
          <svg class="animate-spin h-6 w-6 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
          <span class="font-bold text-secondary">Cargando editor...</span>
        </div>
      </div>
      
      <!-- Error State -->
      <div v-else-if="isError || !article" class="flex-1 flex justify-center items-center">
        <div class="bg-red-50 p-6 rounded-2xl shadow-xl border border-red-200 text-red-600 flex items-center gap-3">
          <span class="font-bold">❌ Error cargando la noticia.</span>
          <button @click="router.push('/')" class="px-3 py-1.5 bg-white rounded-lg border border-red-200 hover:bg-red-100 transition-colors text-xs font-bold">Volver</button>
        </div>
      </div>
      
      <!-- Editor -->
      <DualPaneEditor
        v-else
        v-model="generatedMarkdown"
        :title="article.title"
        :is-saving="isUpdating"
        save-prompt="Guardar Cambios"
        back-prompt="Volver"
        @save="handleSave"
        @back="handleBack"
      />
    </main>

    <!-- Success Toast -->
    <transition
      enter-active-class="transform ease-out duration-300 transition"
      enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
      enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-active-class="transition ease-in duration-100"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="successMessage" class="fixed top-20 right-4 z-50 max-w-sm w-full bg-white border-l-4 border-green-500 rounded-lg shadow-2xl p-4 flex items-start gap-4 ring-1 ring-black/5">
        <div class="rounded-full bg-green-100 p-1 shrink-0 mt-0.5">
          <svg class="h-5 w-5 text-green-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
        </div>
        <div class="flex-1 w-0">
          <p class="text-sm font-semibold text-slate-900 leading-snug">{{ successMessage }}</p>
        </div>
        <button @click="successMessage = null" class="shrink-0 text-slate-400 hover:text-slate-500 hover:bg-slate-100 p-1 rounded-md transition-colors">
          <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
        </button>
      </div>
    </transition>
  </div>
</template>

<style scoped>
@reference "../../../assets/main.css";
</style>
