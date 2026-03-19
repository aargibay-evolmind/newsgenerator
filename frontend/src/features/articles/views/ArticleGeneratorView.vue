<script lang="ts" setup>
import { ref, computed, watch, onUnmounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useArticleStore } from '../../articles/store/articleStore'
import { 
  useSuggestTopics, 
  useUrlScraping, 
  useGenerateOutline, 
  useGenerateArticle,
  useSaveArticle
} from '../../articles/composables'
import MainHeader from '@/components/MainHeader.vue';
import DualPaneEditor from '../components/DualPaneEditor.vue'
import GenerationStepper from '../components/GenerationStepper.vue'

// Store and API hooks
const store = useArticleStore()
const {
  blogTitle,
  keywords: tags,
  keyPoints,
  referenceUrls,
  scrapedReferences,
  additionalContext,
  audience,
  searchIntent,
  toneValue,
  includeLists,
  includeTables,
  sectionCount,
  outlineList,
  suggestedLinks,
  uploadedImages
} = storeToRefs(store)
const { mutateAsync: suggestTopics, isPending: suggestTopicsLoading } = useSuggestTopics()
const { mutateAsync: scrapeUrl, isPending: isScraping } = useUrlScraping()
const { mutateAsync: generateOutline, isPending: architectLoading } = useGenerateOutline()
const { mutateAsync: generateArticle, isPending: generateLoading } = useGenerateArticle()
const { mutateAsync: saveArticleMutation, isPending: isSavingArticle } = useSaveArticle()

const errorMessage = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const currentStep = ref(1)
const suggestedTopics = ref<string[]>([])

async function handleSuggestTopics() {
  try {
    const data = await suggestTopics({ title: blogTitle.value })
    if (data.topics && data.topics.length > 0) {
      suggestedTopics.value = data.topics.slice(0, 3)
    }
  } catch (error: any) {
    if (error?.message?.includes('429')) {
      errorMessage.value = "⚠️ Límite de peticiones alcanzado. Espera un minuto."
      setTimeout(() => { errorMessage.value = null }, 5000)
    } else if (error?.message?.includes('503')) {
      errorMessage.value = "⚠️ Los servidores de IA de Google están saturados en este momento. Intenta de nuevo en unos segundos."
      setTimeout(() => { errorMessage.value = null }, 5000)
    }
  }
}

const referenceUrl = ref('')

async function handleScrape() {
  if (!referenceUrl.value.includes('http') || isScraping.value) return;
  
  try {
    const data = await scrapeUrl({ url: referenceUrl.value })
    scrapedReferences.value.push(data)
    referenceUrl.value = ''
  } catch (error: any) {
    console.error('Failed to scrape URL:', error)
    if (error?.message?.includes('429')) {
      errorMessage.value = "⚠️ Límite de peticiones alcanzado. Espera un minuto."
      setTimeout(() => { errorMessage.value = null }, 5000)
    } else if (error?.message?.includes('503')) {
      errorMessage.value = "⚠️ Los servidores de IA de Google están saturados en este momento. Intenta de nuevo en unos segundos."
      setTimeout(() => { errorMessage.value = null }, 5000)
    } else {
      errorMessage.value = "No se pudo leer la URL."
      setTimeout(() => { errorMessage.value = null }, 3000)
    }
  }
}
function removeScrapedLink(url: string) {
  scrapedReferences.value = scrapedReferences.value.filter((r: any) => r.url !== url)
}

function removeUrlFallback(url: string) {
  referenceUrls.value = referenceUrls.value.filter((u: string) => u !== url)
}

function goNext(step: number) {
  currentStep.value = step
}

// Step 2: Architect Outline

function addHeading() {
  outlineList.value.push({ id: Date.now(), text: 'Nuevo Encabezado', included: true })
}

function removeHeading(id: number) {
  outlineList.value = outlineList.value.filter((h: any) => h.id !== id)
}

const draggedItemIndex = ref<number | null>(null)



const allOutlineIncluded = computed(() => {
  return outlineList.value.length > 0 && outlineList.value.every((i: any) => i.included)
})

function toggleAllOutline() {
  const targetValue = !allOutlineIncluded.value
  outlineList.value.forEach((item: any) => {
    item.included = targetValue
  })
}

function onDragStart(index: number) {
  draggedItemIndex.value = index
}

function onDrop(index: number) {
  if (draggedItemIndex.value === null) return
  const itemToMove = outlineList.value.splice(draggedItemIndex.value, 1)[0]
  if (itemToMove) {
    outlineList.value.splice(index, 0, itemToMove)
  }
  draggedItemIndex.value = null
}

// const suggestedLinks = ref([...])

function removeLink(id: number) {
  suggestedLinks.value = suggestedLinks.value.filter((l: any) => l.id !== id)
}

const newLinkUrl = ref('')
const newLinkTitle = ref('')

function addLink() {
  if (newLinkUrl.value.trim() && newLinkTitle.value.trim()) {
    suggestedLinks.value.push({
      id: Date.now(),
      title: newLinkTitle.value.trim(),
      url: newLinkUrl.value.trim(),
      included: true
    })
    newLinkUrl.value = ''
    newLinkTitle.value = ''
  }
}

// Step 2 Tags functionality
const newTag = ref('')
const newKeyPoint = ref('')

function addTag() {
  if (newTag.value.trim() && !tags.value.includes(newTag.value.trim())) {
    tags.value.push(newTag.value.trim())
    newTag.value = ''
  }
}

function removeTag(tag: string) {
  tags.value = tags.value.filter((t: any) => t !== tag)
}

function addKeyPoint() {
  if (newKeyPoint.value.trim() && !keyPoints.value.includes(newKeyPoint.value.trim())) {
    keyPoints.value.push(newKeyPoint.value.trim())
    newKeyPoint.value = ''
  }
}

function removeKeyPoint(point: string) {
  keyPoints.value = keyPoints.value.filter((p: any) => p !== point)
}

async function saveArticleToDb() {
  if (isSavingArticle.value) return;
  
  // Estimate reading time (roughly 200 wpm)
  const words = generatedMarkdown.value.trim().split(/\s+/).length;
  const readingTime = Math.max(1, Math.ceil(words / 200));

  try {
    await saveArticleMutation({
      title: blogTitle.value.trim() || 'Noticia Sin Título',
      data: {
        markdown: generatedMarkdown.value,
        tone: toneValue.value,
        keywords: [...tags.value],
        readingTime,
        audience: audience.value,
        searchIntent: searchIntent.value
      }
    });
    successMessage.value = "✅ Noticia guardada correctamente.";
    setTimeout(() => { successMessage.value = null }, 4000);
  } catch (error) {
    errorMessage.value = "❌ Error al guardar la noticia.";
    setTimeout(() => { errorMessage.value = null }, 5000);
  }
}

// Transitions

const titleError = ref(false)
const sectionCountError = ref(false)

async function handleProceedToArchitect() {
  if (architectLoading.value) return;

  if (!blogTitle.value.trim()) {
    titleError.value = true
    return
  }

  if (sectionCount.value < 5 || sectionCount.value > 10) {
    sectionCountError.value = true
    return
  }

  try {
    const response = await generateOutline(store.getGenerateOutlinePayload())
    outlineList.value = response.outline;
    
    // Merge user-added references with AI-suggested links
    const aiLinks = response.suggestedLinks || [];
    const userLinks = scrapedReferences.value.map((ref, idx) => ({
      id: 9000 + idx, // Use high IDs to avoid collision
      title: ref.title || 'Referencia del Usuario',
      url: ref.url,
      included: true // User-added links are included by default
    }));
    
    suggestedLinks.value = [...userLinks, ...aiLinks];
    goNext(2);
  } catch (error: any) {
    console.error("Failed to generate outline", error)
    if (error?.message?.includes('429')) {
      errorMessage.value = "⚠️ Límite de peticiones alcanzado. Google Gemini requiere esperar ~1 minuto antes de generar el plan conceptual con esta cuenta."
      setTimeout(() => { errorMessage.value = null }, 8000)
    } else if (error?.message?.includes('503')) {
      errorMessage.value = "⚠️ Los servidores de IA de Google están sobrecargados (503). Por favor, haz clic en Continuar de nuevo en unos segundos."
      setTimeout(() => { errorMessage.value = null }, 8000)
    } else {
      errorMessage.value = "❌ Ocurrió un error inesperado al analizar la estructura. Intenta de nuevo."
      setTimeout(() => { errorMessage.value = null }, 5000)
    }
  }
}

async function handleGenerateArticle() {
  if (generateLoading.value) return;
  
  try {
    const response = await generateArticle(store.getGenerateArticlePayload())
    // @ts-ignore Since mocked preview uses statically written markdown, replace with real data.
    // fakeArticle is read-only constant block originally, so we redefine viewing context or simply overwrite string logic via pinia later. Let's do a fast ref.
    generatedMarkdown.value = response.markdown;
    goNext(3);
  } catch (error: any) {
     console.error("Failed to generate final article", error)
     if (error?.message?.includes('429')) {
       errorMessage.value = "⚠️ Límite de peticiones alcanzado. Google Gemini requiere esperar ~1 minuto antes de generar otro artículo con esta cuenta."
       setTimeout(() => { errorMessage.value = null }, 8000)
     } else if (error?.message?.includes('503')) {
       errorMessage.value = "⚠️ Los servidores de IA de Google están sobrecargados (503). Por favor, inténtalo de nuevo en unos instantes."
       setTimeout(() => { errorMessage.value = null }, 8000)
     } else {
       errorMessage.value = "❌ Ocurrió un error inesperado al generar el artículo. Intenta de nuevo."
       setTimeout(() => { errorMessage.value = null }, 5000)
     }
  }
}

const generatedMarkdown = ref('')

const fileInput = ref<HTMLInputElement | null>(null)

function triggerImageUpload() {
  fileInput.value?.click()
}

async function handleImageUpload(event: Event) {
  const input = event.target as HTMLInputElement
  if (!input.files || input.files.length === 0) return

  Array.from(input.files).forEach(file => {
    processImageFile(file)
  })
  
  input.value = ''
}

function processImageFile(file: File) {
  const reader = new FileReader()
  reader.onload = (e) => {
    const data = e.target?.result as string
    uploadedImages.value.push({
      id: `img-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
      name: file.name,
      data
    })
  }
  reader.readAsDataURL(file)
}

function deleteImage(id: string) {
  uploadedImages.value = uploadedImages.value.filter(img => img.id !== id)
}

function insertImage(img: { name: string, data: string }) {
  const markdown = `\n![${img.name}](${img.data})\n`
  generatedMarkdown.value += markdown
}

// Global scroll lock for Step 3 - Force absolute height and overflow hidden
watch(currentStep, (newStep) => {
  if (typeof window !== 'undefined') {
    if (newStep === 3) {
      document.body.style.overflow = 'hidden';
      document.body.style.height = '100%';
      document.documentElement.style.overflow = 'hidden';
      document.documentElement.style.height = '100%';
    } else {
      document.body.style.overflow = '';
      document.body.style.height = '';
      document.documentElement.style.overflow = '';
      document.documentElement.style.height = '';
    }
  }
}, { immediate: true })

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    document.body.style.overflow = '';
    document.body.style.height = '';
    document.documentElement.style.overflow = '';
    document.documentElement.style.height = '';
  }
})

</script>

<template>
  <div class="flex flex-col h-screen bg-background overflow-hidden relative font-sans text-text">
    <MainHeader />

    <!-- Global Error Toast -->
    <transition
      enter-active-class="transform ease-out duration-300 transition"
      enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
      enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-active-class="transition ease-in duration-100"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="errorMessage" class="fixed top-20 right-4 z-50 max-w-sm bg-white border-l-4 border-red-500 rounded-r-lg shadow-xl p-4 flex items-start gap-3">
        <svg class="h-5 w-5 text-red-500 mt-0.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" /></svg>
        <div class="flex-1">
          <p class="text-sm font-medium text-slate-800">{{ errorMessage }}</p>
        </div>
        <button @click="errorMessage = null" class="text-slate-400 hover:text-slate-600">
          <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
        </button>
      </div>
    </transition>

    <!-- Global Success Toast -->
    <transition
      enter-active-class="transform ease-out duration-300 transition"
      enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
      enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-active-class="transition ease-in duration-100"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="successMessage" class="fixed top-36 right-4 z-50 max-w-sm bg-white border-l-4 border-green-500 rounded-r-lg shadow-xl p-4 flex items-start gap-3">
        <svg class="h-5 w-5 text-green-500 mt-0.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" /></svg>
        <div class="flex-1">
          <p class="text-sm font-medium text-slate-800">{{ successMessage }}</p>
        </div>
        <button @click="successMessage = null" class="text-slate-400 hover:text-slate-600">
          <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
        </button>
      </div>
    </transition>

    <!-- Horizontal Stepper -->
    <GenerationStepper v-model="currentStep" />

    <!-- Content Area Container -->
    <div :class="['flex-1 flex flex-col w-full min-h-0', currentStep === 3 ? 'h-[calc(100vh-105px)] overflow-hidden' : 'overflow-y-auto']">
      
      <!-- Main Content -->
      <main :class="['transition-all duration-300 flex-1 flex flex-col min-h-0 mx-auto w-full transition-all duration-300', currentStep === 3 ? 'max-w-none px-4 h-full py-2' : 'max-w-7xl px-6 sm:px-12 lg:px-16 py-8 sm:py-12 mb-20']">
      
      <transition 
        enter-active-class="transition-opacity duration-300 ease-out" 
        enter-from-class="opacity-0" 
        enter-to-class="opacity-100" 
        leave-active-class="transition-opacity duration-200 ease-in" 
        leave-from-class="opacity-100" 
        leave-to-class="opacity-0" 
        mode="out-in"
      >
        <!-- STEP 1: INITIAL DEFINITION -->
        <div v-if="currentStep === 1" class="space-y-8" key="step1">

          <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <!-- MAIN CONTENT -->
            <div class="lg:col-span-3 bg-white rounded-2xl shadow-sm border border-secondary/10 overflow-hidden ring-1 ring-text/5">
              <div class="p-8 space-y-8">
                
                <!-- Quick Start Title -->
                <div>
                  <label for="blog-title" class="block text-xs font-bold text-secondary uppercase tracking-widest mb-3">Título de la Noticia</label>
                  <input
                    id="blog-title"
                    v-model="blogTitle"
                    @input="titleError = false"
                    type="text"
                    class="block w-full rounded-2xl border-0 py-4 px-5 text-text shadow-sm ring-1 ring-inset placeholder:text-secondary/40 focus:outline-none focus:ring-2 focus:ring-inset sm:text-lg sm:leading-relaxed transition-all"
                    :class="titleError 
                      ? 'ring-red-400 focus:ring-red-400 bg-red-50/30' 
                      : 'ring-secondary/20 focus:ring-primary'"
                    placeholder="Ej. Cómo ser Guardia Civil, pasos para ingresar..."
                  />
                  <div v-if="titleError" class="mt-2 flex items-center gap-1.5 text-red-600 text-sm font-medium animate-in fade-in slide-in-from-top-1 duration-200">
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" /></svg>
                    El título de la noticia es obligatorio.
                  </div>
                  <div class="mt-4">
                    <button 
                      @click="handleSuggestTopics" 
                      :disabled="suggestTopicsLoading"
                      class="inline-flex items-center gap-2 rounded-xl bg-primary/10 px-4 py-2.5 text-sm font-semibold text-primary hover:bg-primary/20 transition-colors border border-primary/10 disabled:opacity-75 disabled:cursor-not-allowed"
                    >
                      <svg v-if="suggestTopicsLoading" class="animate-spin h-4 w-4 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                      <svg v-else class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a.75.75 0 01.75.75v1.5a.75.75 0 01-1.5 0v-1.5A.75.75 0 0110 2zM10 15a.75.75 0 01.75.75v1.5a.75.75 0 01-1.5 0v-1.5A.75.75 0 0110 15zM10 7a3 3 0 100 6 3 3 0 000-6zM15.657 5.404a.75.75 0 10-1.06-1.06l-1.061 1.06a.75.75 0 001.06 1.06l1.06-1.06zM6.464 14.596a.75.75 0 10-1.06-1.06l-1.06 1.06a.75.75 0 001.06 1.06l1.06-1.06zM18 10a.75.75 0 01-.75.75h-1.5a.75.75 0 010-1.5h1.5A.75.75 0 0118 10zM5 10a.75.75 0 01-.75.75h-1.5a.75.75 0 010-1.5h1.5A.75.75 0 015 10zM14.596 15.657a.75.75 0 001.06-1.06l-1.06-1.061a.75.75 0 10-1.06 1.06l1.06 1.06zM5.404 6.464a.75.75 0 001.06-1.06l-1.06-1.06a.75.75 0 10-1.061 1.06l1.06 1.061z" /></svg>
                      <span v-if="suggestTopicsLoading">Generando...</span>
                      <span v-else>Sugerir temas con IA</span>
                    </button>

                    <!-- Stacked selectable topics -->
                    <div v-if="suggestedTopics.length > 0" class="mt-3 rounded-2xl border border-primary/10 bg-primary/5 overflow-hidden animate-in fade-in slide-in-from-top-2 duration-300">
                      <div class="flex items-center justify-between px-4 py-2 border-b border-primary/10">
                        <span class="text-xs font-bold text-primary uppercase tracking-widest">Sugerencias</span>
                        <button @click="suggestedTopics = []" class="flex items-center justify-center h-6 w-6 rounded-full text-secondary hover:bg-primary/10 hover:text-primary transition-colors" title="Limpiar sugerencias">
                          <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                      </div>
                      <div class="divide-y divide-primary/5">
                        <button 
                          v-for="(topic, idx) in suggestedTopics" 
                          :key="idx"
                          @click="blogTitle = topic; suggestedTopics = []"
                          class="w-full text-left px-4 py-3 text-sm font-medium text-text hover:bg-primary hover:text-white transition-colors duration-150 flex items-center gap-3 group"
                        >
                          <span class="shrink-0 h-5 w-5 rounded-full bg-primary/10 group-hover:bg-primary/20 flex items-center justify-center text-[10px] font-bold text-primary transition-colors hover:text-white">{{ idx + 1 }}</span>
                          {{ topic }}
                        </button>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Leads -->
                <div class="pt-6 border-t border-secondary/10">
                  <div class="mb-4">
                    <label for="puntos-clave" class="block text-xs font-bold text-secondary uppercase tracking-widest mb-1">Leads (Puntos clave)</label>
                    <p class="text-xs text-secondary">Define los ejes principales que la noticia debe cubrir para asegurar que el contenido sea relevante y completo.</p>
                  </div>
                  <div class="flex gap-2 mb-4">
                    <input 
                      id="puntos-clave"
                      v-model="newKeyPoint" 
                      @keydown.enter.prevent="addKeyPoint"
                      type="text" 
                      class="block w-full rounded-xl border-0 py-3 px-4 text-text shadow-sm ring-1 ring-inset ring-secondary/20 placeholder:text-secondary/40 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary sm:text-sm"
                      placeholder="Ej. Requisitos físicos... (Presiona Enter)" 
                    />
                    <button @click="addKeyPoint" class="px-4 py-2 bg-secondary/10 hover:bg-secondary/20 text-text font-semibold rounded-xl text-sm transition-colors border border-secondary/20 shadow-sm shrink-0">
                      Añadir
                    </button>
                  </div>
                  <!-- Leads List Inline -->
                  <div class="flex flex-wrap gap-2" v-if="keyPoints.length > 0">
                    <span v-for="(point, index) in keyPoints" :key="index" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium bg-primary/10 text-primary border border-primary/20">
                      {{ point }}
                      <button @click="removeKeyPoint(point)" class="text-primary/60 hover:text-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary rounded-full p-0.5" title="Eliminar punto clave">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                      </button>
                    </span>
                  </div>
                </div>

                <!-- Keywords (Moved from sidebar) -->
                <div class="pt-6 border-t border-secondary/10">
                  <div class="mb-4">
                    <label class="block text-xs font-bold text-secondary uppercase tracking-widest mb-1">Etiquetas / Keywords</label>
                    <p class="text-xs text-secondary">Añade palabras clave para mejorar el enfoque del contenido y el SEO.</p>
                  </div>
                  <div class="flex gap-2 mb-4">
                    <div class="relative flex-1 group ring-1 ring-inset ring-secondary/20 rounded-xl focus-within:ring-2 focus-within:ring-primary overflow-hidden shadow-sm bg-white">
                      <input
                        v-model="newTag"
                        @keydown.enter.prevent="addTag"
                        type="text"
                        class="block w-full border-0 py-3 px-4 pr-10 text-text placeholder:text-secondary/40 focus:outline-none focus:ring-0 text-sm"
                        placeholder="Añadir etiqueta y pulsar Enter..."
                      />
                      <button @click="addTag" class="absolute right-1 top-1.5 h-8 w-8 flex items-center justify-center text-secondary/30 hover:text-primary transition-colors hover:bg-primary/5 rounded-lg">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" /></svg>
                      </button>
                    </div>
                  </div>
                  <!-- Keywords List -->
                  <div class="flex flex-wrap gap-2" v-if="tags.length > 0">
                    <span v-for="tag in tags" :key="tag" class="inline-flex items-center gap-1.5 rounded-lg bg-secondary/10 px-3 py-1.5 text-xs font-semibold text-text shadow-sm border border-secondary/20 transition-all hover:bg-secondary/20">
                      {{ tag }}
                      <button @click="removeTag(tag)" class="-mr-1 text-secondary/40 hover:text-red-500 rounded-full hover:bg-secondary/30 transition-colors p-0.5" title="Eliminar etiqueta">
                         <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
                      </button>
                    </span>
                  </div>
                </div>

                <!-- Reference URLs -->
                <div class="pt-6 border-t border-secondary/10">
                  <div class="mb-4">
                    <label for="reference-url" class="block text-xs font-bold text-secondary uppercase tracking-widest mb-1">URLs de referencia <span class="normal-case font-medium text-secondary/60">(Opcional)</span></label>
                    <p class="text-xs text-secondary">Añade enlaces a artículos, noticias o fuentes oficiales que la IA deba analizar para generar el contenido.</p>
                  </div>
                  <div class="flex gap-2 mb-4">
                    <div class="relative flex-1 group ring-1 ring-secondary/20 rounded-xl focus-within:ring-2 focus-within:ring-primary overflow-hidden transition-all bg-white">
                      <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                        <svg class="h-4 w-4 text-secondary/40 group-focus-within:text-primary transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
                      </div>
                      <input 
                        id="reference-url"
                        v-model="referenceUrl" 
                        @keydown.enter.prevent="handleScrape"
                        type="text" 
                        class="block w-full border-0 py-3 pl-10 pr-4 text-text placeholder:text-secondary/40 focus:outline-none focus:ring-0 sm:text-sm bg-transparent"
                        placeholder="https://boe.es/noticia-importante..." 
                      />
                    </div>
                    <button 
                      @click="handleScrape" 
                      :disabled="isScraping"
                      class="px-5 py-2.5 bg-primary text-white font-bold rounded-xl text-sm transition-all hover:bg-primary/90 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                    >
                      <svg v-if="isScraping" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                      {{ isScraping ? 'Leyendo...' : 'Analizar URL' }}
                    </button>
                  </div>

                  <!-- Scraped references list -->
                  <div class="space-y-2" v-if="scrapedReferences.length > 0">
                    <div v-for="(ref, index) in scrapedReferences" :key="index" class="flex items-center justify-between p-3 rounded-xl bg-primary/5 border border-primary/10 group animate-in fade-in slide-in-from-top-1 duration-200">
                      <div class="flex items-center gap-3 overflow-hidden">
                        <div class="h-8 w-8 rounded-lg bg-white border border-primary/20 flex items-center justify-center shrink-0">
                           <svg class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        </div>
                        <div class="flex flex-col min-w-0">
                          <span class="text-xs font-bold text-text truncate leading-tight">{{ ref.title || 'Nueva Referencia' }}</span>
                          <span class="text-[10px] text-secondary truncate">{{ ref.url }}</span>
                        </div>
                      </div>
                      <button @click="removeScrapedLink(ref.url)" class="p-1 px-2 text-primary/40 hover:text-red-500 transition-colors" title="Eliminar referencia">
                         <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Context and Guidelines moved from sidebar -->
                <div class="pt-6 border-t border-secondary/10">
                  <label for="additional-context" class="block text-xs font-bold text-secondary uppercase tracking-widest mb-1">Contexto y Directrices <span class="normal-case font-medium text-secondary/60">(Opcional)</span></label>
                  <p class="text-xs text-secondary mb-3">Añade indicaciones específicas para la IA: estilo, enfoque, datos a incluir o excluir, etc.</p>
                  <textarea
                    id="additional-context"
                    v-model="additionalContext"
                    rows="4"
                    class="block w-full rounded-xl border-0 py-3 px-4 text-text shadow-sm ring-1 ring-inset ring-secondary/20 placeholder:text-secondary/40 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary text-sm resize-none transition-shadow"
                    placeholder="Ej: Tono cercano y optimista. No mencionar requisitos de edad..."
                  ></textarea>
                </div>

              </div>
            </div>

            <!-- SIDEBAR: TARGETING -->
            <aside class="lg:col-span-1 h-full relative">
              <div class="bg-slate-50/50 rounded-2xl shadow-sm border border-secondary/10 p-5 lg:sticky lg:top-24 flex flex-col h-full ring-1 ring-secondary/5">
                <div class="flex items-center gap-2 pb-4 border-b border-secondary/10 shrink-0">
                  <div class="p-1.5 rounded-lg bg-primary/10 text-primary">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                    </svg>
                  </div>
                  <h3 class="font-bold text-sm text-text">Preferencias</h3>
                </div>

                <div class="flex-1 flex flex-col pt-6 overflow-y-auto pr-2 custom-scrollbar">
                  <div class="space-y-8 pb-4">
                    <!-- Nivel de Lenguaje Técnico -->
                    <div>
                      <label for="audience" class="block text-[10px] font-bold text-secondary uppercase tracking-widest mb-2.5">Lenguaje Técnico</label>
                      <select
                        id="audience"
                        v-model="audience"
                        class="block w-full rounded-xl border-0 py-2.5 px-3 text-text shadow-sm ring-1 ring-inset ring-secondary/10 focus:outline-none focus:ring-2 focus:ring-primary text-xs bg-white transition-all hover:bg-white/80"
                      >
                        <option value="General">General</option>
                        <option value="Principiantes">Principiantes</option>
                        <option value="Expertos">Profesionales</option>
                        <option value="Estudiantes">Académico</option>
                        <option value="Técnico">Perfil Técnico</option>
                      </select>
                    </div>

                    <!-- Objetivo del artículo -->
                    <div>
                      <label for="intent" class="block text-[10px] font-bold text-secondary uppercase tracking-widest mb-2.5">Objetivo del artículo</label>
                      <select
                        id="intent"
                        v-model="searchIntent"
                        class="block w-full rounded-xl border-0 py-2.5 px-3 text-text shadow-sm ring-1 ring-inset ring-secondary/10 focus:outline-none focus:ring-2 focus:ring-primary text-xs bg-white transition-all hover:bg-white/80"
                      >
                        <option value="Informativo">Informativo</option>
                        <option value="Tutorial">Tutorial</option>
                        <option value="Transaccional">Transaccional</option>
                        <option value="Comparativo">Comparativo</option>
                      </select>
                    </div>

                    <!-- Tone Slider -->
                    <div>
                      <label class="block text-[10px] font-bold text-secondary uppercase tracking-widest mb-4">
                        Tono: 
                        <span class="text-primary ml-1">
                          {{ toneValue < 33 ? 'Profesional' : toneValue < 66 ? 'Cercano' : 'Viral/Audaz' }}
                        </span>
                      </label>
                      <div class="px-1">
                        <input type="range" v-model.number="toneValue" min="0" max="100" class="w-full h-1.5 bg-secondary/10 rounded-lg appearance-none cursor-pointer mb-2.5 focus:outline-none focus:ring-2 focus:ring-primary [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:w-3.5 [&::-webkit-slider-thumb]:h-3.5 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-primary [&::-moz-range-thumb]:w-3.5 [&::-moz-range-thumb]:h-3.5 [&::-moz-range-thumb]:border-0 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:bg-primary" />
                        <div class="flex justify-between text-[9px] uppercase font-bold text-secondary/30 tracking-tighter">
                          <span>Prof.</span>
                          <span>Cercano</span>
                          <span>Viral</span>
                        </div>
                      </div>
                    </div>

                    <!-- Section Count Slider -->
                    <div>
                      <label class="block text-[10px] font-bold text-secondary uppercase tracking-widest mb-4">
                        Nº de Secciones: 
                        <span class="text-primary ml-1">{{ sectionCount }}</span>
                      </label>
                      <div class="px-1">
                        <input type="range" v-model.number="sectionCount" min="5" max="10" class="w-full h-1.5 bg-secondary/10 rounded-lg appearance-none cursor-pointer mb-2.5 focus:outline-none focus:ring-2 focus:ring-primary [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:w-3.5 [&::-webkit-slider-thumb]:h-3.5 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-primary [&::-moz-range-thumb]:w-3.5 [&::-moz-range-thumb]:h-3.5 [&::-moz-range-thumb]:border-0 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:bg-primary" />
                        <div class="flex justify-between text-[9px] uppercase font-bold text-secondary/30 tracking-tighter">
                          <span>5 secc.</span>
                          <span>7 secc.</span>
                          <span>10 secc.</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="mt-auto pt-5 border-t border-secondary/10 flex flex-col items-stretch gap-4">
                  <div class="w-full bg-primary/5 rounded-xl p-3 border border-primary/10">
                    <p class="text-[9px] text-secondary leading-relaxed font-medium">
                      <span class="text-primary font-bold uppercase tracking-tighter mr-1">Pro Tip:</span> 
                      Estos ajustes permiten a la IA optimizar la complejidad del lenguaje y el formato de la noticia.
                    </p>
                  </div>

                  <button 
                    @click="handleProceedToArchitect" 
                    :disabled="architectLoading"
                    class="group w-full inline-flex items-center justify-center rounded-xl bg-primary px-5 py-3.5 text-xs font-bold text-white shadow-sm hover:bg-primary/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary transition-all duration-200 disabled:opacity-75 disabled:cursor-not-allowed uppercase tracking-wider"
                  >
                    <div v-if="architectLoading" class="flex items-center gap-2">
                      <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                      Generando...
                    </div>
                    <div v-else class="flex items-center">
                      Continuar a Diseño
                      <svg class="ml-1.5 -mr-1 h-4 w-4 group-hover:translate-x-1 transition-transform" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 10a.75.75 0 01.75-.75h10.638L10.23 5.29a.75.75 0 111.04-1.08l5.5 5.25a.75.75 0 010 1.08l-5.5 5.25a.75.75 0 11-1.04-1.08l4.158-3.96H3.75A.75.75 0 013 10z" clip-rule="evenodd" /></svg>
                    </div>
                  </button>
                </div>
              </div>
            </aside>
          </div>
        </div>
        <!-- STEP 2: THE ARCHITECT -->
        <div v-else-if="currentStep === 2" class="flex flex-col gap-6" key="step2">

          <!-- Outline Card -->
          <div class="w-full bg-white rounded-2xl shadow-sm border border-secondary/10 p-6 flex flex-col ring-1 ring-text/5">
          <div class="flex items-center justify-between mb-1">
                <h2 class="text-lg font-bold text-text flex items-center gap-2">
                  <svg class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" /></svg>
                  Esquema Interactivo
                </h2>
                <button 
                  @click="toggleAllOutline" 
                  class="text-[10px] font-bold text-primary hover:text-primary/80 uppercase tracking-wider px-2 py-1 bg-primary/5 rounded border border-primary/10 transition-colors"
                >
                  {{ allOutlineIncluded ? 'Deseleccionar Todo' : 'Seleccionar Todo' }}
                </button>
              </div>
              <p class="text-sm text-secondary mb-6">Activa, reorganiza o edita los encabezados propuestos.</p>
              
              <ul class="space-y-3 pr-2">
                <li v-for="(item, index) in outlineList" :key="item.id" 
                    draggable="true"
                    @dragstart="onDragStart(index)"
                    @dragover.prevent
                    @dragenter.prevent
                    @drop="onDrop(index)"
                    class="group flex items-center gap-3 bg-white border border-secondary/20 rounded-xl p-3 hover:border-secondary/30 hover:bg-secondary/5 transition-all cursor-move" 
                    :class="{'border-dashed border-secondary/30 bg-secondary/5 opacity-40': draggedItemIndex === index}">
                  
                  <!-- Drag Handle -->
                  <span class="cursor-grab text-secondary/30 hover:text-secondary shrink-0">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" /></svg>
                  </span>
                  
                  <!-- Checkbox -->
                  <div class="flex items-center shrink-0">
                    <input type="checkbox" v-model="item.included" class="h-4 w-4 rounded border-secondary/30 text-primary focus:ring-secondary/40 transition duration-150 cursor-pointer" />
                  </div>

                  <!-- Editable Input Text -->
                  <div class="flex-grow flex flex-col sm:flex-row sm:items-center gap-2">
                    <input 
                      type="text" 
                      v-model="item.text" 
                      class="text-sm font-semibold text-text flex-grow border-0 border-b border-transparent focus:border-primary focus:ring-0 bg-transparent px-1 py-1 transition-all" 
                    />
                    
                    <!-- Budget Selector -->
                    <select
                      v-if="item.included"
                      v-model="item.budget"
                      class="shrink-0 rounded-lg border-0 py-1 pl-2 pr-7 text-xs font-semibold text-secondary bg-secondary/5 ring-1 ring-inset ring-secondary/20 focus:outline-none focus:ring-2 focus:ring-secondary/40 cursor-pointer"
                    >
                      <option value="short">Breve (~100 palabras)</option>
                      <option value="medium">Normal (~250 palabras)</option>
                      <option value="long">Extenso (~500 palabras)</option>
                    </select>
                  </div>
                  
                  <!-- Delete Action -->
                  <button @click="removeHeading(item.id)" class="opacity-0 group-hover:opacity-100 text-secondary/40 hover:text-red-500 transition-opacity p-1.5 rounded hover:bg-red-50 shrink-0">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd" /></svg>
                  </button>
                </li>
              </ul>
              
              <div class="mt-4 flex flex-wrap items-center justify-between pt-4 border-t border-secondary/10 gap-3">
                <div class="flex gap-2 flex-wrap">
                  <button @click="addHeading" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:text-primary px-3 py-2 bg-primary/10 hover:bg-primary/20 rounded-xl border border-primary/10">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" /></svg>
                    Encabezado
                  </button>

                </div>

                <!-- Elementos Adicionales inline -->
                <div class="flex items-center gap-6">
                  <label class="flex items-center gap-2 cursor-pointer group select-none">
                    <input type="checkbox" v-model="includeLists" class="h-4 w-4 rounded border-secondary/30 text-primary focus:ring-secondary/40 transition duration-150 ease-in-out cursor-pointer" />
                    <span class="text-xs font-semibold text-secondary group-hover:text-text transition-colors">Incluir listas</span>
                  </label>
                  <label class="flex items-center gap-2 cursor-pointer group select-none">
                    <input type="checkbox" v-model="includeTables" class="h-4 w-4 rounded border-secondary/30 text-primary focus:ring-secondary/40 transition duration-150 ease-in-out cursor-pointer" />
                    <span class="text-xs font-semibold text-secondary group-hover:text-text transition-colors">Incluir tablas</span>
                  </label>
                </div>
              </div>
          </div>

          <!-- Suggested Links Card -->
          <div class="w-full bg-white rounded-2xl shadow-sm border border-secondary/10 p-6 flex flex-col ring-1 ring-text/5">
                <h2 class="text-lg font-bold text-text mb-1 flex items-center gap-2">
                  <svg class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
                  Enlaces Sugeridos
                </h2>
                <p class="text-sm text-secondary mb-6">La IA sugiere incluir estos enlaces relevantes como referencia en el artículo.</p>
                
                <ul class="space-y-3 pr-2">
                  <li v-for="link in suggestedLinks" :key="link.id" class="group flex items-start gap-3 bg-white border border-secondary/20 rounded-xl p-3 hover:border-secondary/30 hover:bg-secondary/5 transition-all">
                    
                    <!-- Checkbox -->
                    <div class="flex items-center shrink-0 mt-0.5">
                      <input type="checkbox" v-model="link.included" class="h-4 w-4 rounded border-secondary/30 text-primary focus:ring-secondary/40 transition duration-150 cursor-pointer" />
                    </div>

                    <!-- Link Info -->
                    <div class="flex-1 min-w-0">
                      <div class="flex items-center gap-2 mb-1">
                        <input 
                          type="text" 
                          v-model="link.title" 
                          class="text-sm font-semibold text-text w-full border-0 border-b border-transparent focus:border-primary focus:ring-0 bg-transparent px-1 py-0.5 transition-all" 
                        />
                      </div>
                      <input 
                        type="text" 
                        v-model="link.url" 
                        class="text-xs text-secondary w-full border-0 border-b border-transparent focus:border-primary focus:ring-0 bg-transparent px-1 py-0.5 transition-all truncate" 
                      />
                    </div>
                    
                    <!-- Delete Action -->
                    <button @click="removeLink(link.id)" class="opacity-0 group-hover:opacity-100 text-secondary/40 hover:text-red-500 transition-opacity p-1.5 rounded hover:bg-red-50 shrink-0">
                      <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd" /></svg>
                    </button>
                  </li>
                </ul>

                <div class="mt-4 flex justify-start pt-4 border-t border-slate-100 gap-2 items-center">
                   <div class="flex-1 grid grid-cols-2 gap-2">
                      <input v-model="newLinkTitle" @keydown.enter="addLink" type="text" placeholder="Título del enlace..." class="block w-full rounded-lg border-0 py-2 px-3 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-slate-400 sm:text-xs">
                      <input v-model="newLinkUrl" @keydown.enter="addLink" type="text" placeholder="https://..." class="block w-full rounded-lg border-0 py-2 px-3 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-slate-400 sm:text-xs">
                   </div>
                   <button @click="addLink" class="inline-flex items-center justify-center shrink-0 w-8 h-8 font-semibold text-indigo-600 hover:text-indigo-800 transition-colors bg-indigo-50 hover:bg-indigo-100 rounded-lg" title="Añadir enlace">
                      <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" /></svg>
                   </button>
                </div>
              </div>

          <!-- Action bar -->
          <div class="flex justify-between items-center gap-4 mt-2 pb-4">
            <button @click="goNext(1)" class="inline-flex items-center gap-2 rounded-xl bg-white border border-secondary/20 px-5 py-3 text-sm font-semibold text-secondary hover:bg-secondary/5 transition-all focus:ring-2 focus:ring-offset-2 focus:ring-primary">
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
              Volver
            </button>
            <button
              @click="handleGenerateArticle"
              :disabled="generateLoading"
              class="group inline-flex items-center justify-center rounded-xl bg-primary px-8 py-3 text-sm font-semibold text-white shadow-sm hover:bg-primary/90 transition-all focus:ring-2 focus:ring-offset-2 focus:ring-primary disabled:opacity-75 disabled:cursor-not-allowed"
            >
              <div v-if="generateLoading" class="flex items-center gap-2">
                <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                Escribiendo artículo...
              </div>
              <div v-else class="flex items-center gap-2">
                Generar Artículo
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
              </div>
            </button>
          </div>
        </div>
        <!-- STEP 3: EDITOR & DEMO -->
        <!-- STEP 3: MINIMALIST EDITOR & PREVIEW -->
        <DualPaneEditor
          v-else-if="currentStep === 3"
          key="step3"
          v-model="generatedMarkdown"
          :title="blogTitle"
          :is-saving="isSavingArticle"
          @save="saveArticleToDb"
          @back="goNext(2)"
        />
      </transition>

    </main>
    </div>
  </div>
</template>

<style scoped>
@reference "../../../assets/main.css";

.animate-fade-in {
  animation: fadeIn 0.4s ease-out forwards;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

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
  @apply px-8 py-4 space-y-2 !mt-0 border-t border-secondary/10;
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
