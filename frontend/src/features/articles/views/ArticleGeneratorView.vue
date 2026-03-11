<script lang="ts" setup>
import { ref, computed } from 'vue'
import { storeToRefs } from 'pinia'
import { useArticleStore } from '../../articles/store/articleStore'
import { 
  useSuggestTopics, 
  useUrlScraping, 
  useGenerateOutline, 
  useGenerateArticle 
} from '../../articles/composables'

// Store and API hooks
const store = useArticleStore()
const {
  blogTitle,
  keywords: tags,
  referenceUrls,
  scrapedReferences: scrapedUrls,
  toneValue,
  articleLength,
  includeLists,
  includeTables,
  outlineList,
  suggestedLinks
} = storeToRefs(store)

const { mutateAsync: suggestTopics, isPending: suggestTopicsLoading } = useSuggestTopics()
const { mutateAsync: scrapeUrl, isPending: isScraping } = useUrlScraping()
const { mutateAsync: generateOutline, isPending: architectLoading } = useGenerateOutline()
const { mutateAsync: generateArticle, isPending: generateLoading } = useGenerateArticle()

const errorMessage = ref<string | null>(null)
const currentStep = ref(1)

// Step 1: Definition
// const blogTitle = ref('')
// const suggestTopicsLoading = ref(false)

async function handleSuggestTopics() {
  if (!blogTitle.value.trim() || suggestTopicsLoading.value) return
  try {
    const data = await suggestTopics({ title: blogTitle.value })
    if (data.topics && data.topics.length > 0) {
      blogTitle.value = data.topics[0] || ''
    }
  } catch (error: any) {
    console.error('Failed to suggest topics:', error)
    if (error?.message?.includes('429')) {
      errorMessage.value = "⚠️ Límite de peticiones alcanzado. Espera un minuto."
      setTimeout(() => { errorMessage.value = null }, 5000)
    }
  }
}

const referenceUrl = ref('')
// const isScraping = ref(false)
// const scrapedUrls = ref<{url: string, title: string}[]>([])

async function handleScrape() {
  if (!referenceUrl.value.includes('http') || isScraping.value) return;
  
  try {
    const data = await scrapeUrl({ url: referenceUrl.value })
    scrapedUrls.value.push(data)
    referenceUrl.value = ''
  } catch (error: any) {
    console.error('Failed to scrape URL:', error)
    if (error?.message?.includes('429')) {
      errorMessage.value = "⚠️ Límite de peticiones alcanzado. Espera un minuto."
      setTimeout(() => { errorMessage.value = null }, 5000)
    } else {
      errorMessage.value = "No se pudo leer la URL."
      setTimeout(() => { errorMessage.value = null }, 3000)
    }
  }
}

function goNext(step: number) {
  currentStep.value = step
}

// Step 2: Architect Outline
// const outlineList = ref([...])

function addHeading() {
  outlineList.value.push({ id: Date.now(), text: 'Nuevo Encabezado', included: true })
}

function removeHeading(id: number) {
  outlineList.value = outlineList.value.filter((h: any) => h.id !== id)
}

const draggedItemIndex = ref<number | null>(null)

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

function addTag() {
  if (newTag.value.trim() && !tags.value.includes(newTag.value.trim())) {
    tags.value.push(newTag.value.trim())
    newTag.value = ''
  }
}

function removeTag(tag: string) {
  tags.value = tags.value.filter((t: any) => t !== tag)
}

// Step 3: View & Editor
const viewMode = ref<'editor' | 'demo'>('demo')
const seoScore = ref(85)
const showVersionHistory = ref(false)

const fakeArticle = `
# Cómo Ser Guardia Civil: Pasos para el Ingreso

¿Estás preparado para dar el paso y entrar en la Guardia Civil? Es una de las instituciones más respetadas de España, ofreciendo una carrera estable y gratificante. En esta guía, desglosamos exactamente lo que necesitas saber para aprobar las oposiciones y conseguir tu uniforme.

## 1. Requisitos Básicos

Antes de empezar a estudiar, asegúrate de cumplir los criterios mínimos:
- **Edad:** Entre 18 y 40 años.
- **Nacionalidad:** Ciudadanía española.
- **Estudios:** El requisito mínimo es la ESO (Educación Secundaria Obligatoria).
- **Físico:** Sin tatuajes excluyentes visibles con el uniforme.

::: image-slot [Espacio reservado: Imagen de la Guardia Civil respondiendo preguntas ciudadanas] :::

## 2. Las Pruebas Físicas

Aquí es donde muchos fallan, pero con preparación, puedes sobresalir.
Las pruebas incluyen:
- **Sprint de 50m:** La velocidad es clave.
- **Carrera de 1000m:** Prueba de resistencia.
- **Flexiones:** Fuerza del tren superior.
- **Natación de 50m:** ¡Sí, necesitas saber nadar!

💡 *Consejo Experto: Empieza a entrenar al menos 6 meses antes del examen.*

## 3. El Examen Teórico

El temario consta de 25 temas que incluyen derecho constitucional, derecho penal y sociología. Te enfrentarás a un examen tipo test, una prueba de ortografía, y una evaluación psicotécnica para medir tu razonamiento lógico.
`

// Transitions

async function handleProceedToArchitect() {
  if (architectLoading.value) return;
  try {
    const response = await generateOutline(store.getGenerateOutlinePayload())
    outlineList.value = response.outline;
    suggestedLinks.value = response.suggestedLinks;
    goNext(2);
  } catch (error: any) {
    console.error("Failed to generate outline", error)
    if (error?.message?.includes('429')) {
      errorMessage.value = "⚠️ Límite de peticiones alcanzado. Google Gemini requiere esperar ~1 minuto antes de generar el plan conceptual con esta cuenta."
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
     } else {
       errorMessage.value = "❌ Ocurrió un error inesperado al generar el artículo. Intenta de nuevo."
       setTimeout(() => { errorMessage.value = null }, 5000)
     }
  }
}

const generatedMarkdown = ref(fakeArticle)


</script>

<template>
  <div class="min-h-screen bg-slate-50 text-slate-800 pb-20 font-sans">
    <!-- Top Navigation -->
    <nav class="bg-white sticky top-0 z-40 shadow-sm border-b border-transparent">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row justify-between items-center h-auto sm:h-16 py-4 sm:py-0 gap-4">
          <div class="flex items-center gap-3">
            <div class="h-8 w-8 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold">N</div>
            <span class="text-xl font-extrabold bg-clip-text text-transparent bg-gradient-to-r from-indigo-600 to-purple-600 tracking-tight">
              NewsGen
            </span>
          </div>
          
          <!-- Stepper indicator -->
          <div class="flex items-center space-x-2 sm:space-x-4 text-xs sm:text-sm font-semibold text-slate-500 bg-slate-100/80 p-1.5 rounded-full z-10">
             <button @click="currentStep = 1" class="flex items-center px-4 py-1.5 rounded-full transition-all" :class="currentStep === 1 ? 'bg-white shadow-sm text-indigo-600' : 'hover:text-slate-800'">1. Definir</button>
             <svg class="h-4 w-4 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
             <button @click="currentStep = 2" class="flex items-center px-4 py-1.5 rounded-full transition-all" :class="currentStep === 2 ? 'bg-white shadow-sm text-indigo-600' : 'hover:text-slate-800'">2. Arquitectura</button>
             <svg class="h-4 w-4 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
             <button @click="currentStep = 3" class="flex items-center px-4 py-1.5 rounded-full transition-all" :class="currentStep === 3 ? 'bg-white shadow-sm text-indigo-600' : 'hover:text-slate-800'">3. Revisar</button>
          </div>
        </div>
      </div>
      
      <!-- Colored Progress Bar -->
      <div class="absolute bottom-[-1px] left-0 h-0.5 w-full bg-slate-100">
        <div 
          class="h-full bg-indigo-600 transition-all duration-500 ease-out" 
          :style="{ width: currentStep === 1 ? '33.3%' : currentStep === 2 ? '66.6%' : '100%' }"
        ></div>
      </div>
    </nav>

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

    <!-- Main Content -->
    <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 mt-8 sm:mt-12">
      
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
          <div class="text-center max-w-2xl mx-auto mb-10">
            <h1 class="text-4xl font-extrabold tracking-tight text-slate-900 sm:text-5xl mb-4">¿De qué trata la historia?</h1>
            <p class="text-lg text-slate-500">Agrega el título o pega URLs de referencia abajo. La IA lo organizará por ti.</p>
          </div>

          <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-100 overflow-hidden ring-1 ring-slate-900/5">
            <div class="p-8 space-y-8">
              
              <!-- Quick Start Title -->
              <div>
                <label for="blog-title" class="block text-sm font-semibold leading-6 text-slate-900 mb-2">Título del Blog</label>
                <input
                  id="blog-title"
                  v-model="blogTitle"
                  type="text"
                  class="block w-full rounded-2xl border-0 py-4 px-5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-lg sm:leading-relaxed transition-shadow"
                  placeholder="Ej. Cómo ser Guardia Civil, pasos para ingresar..."
                />
                <button 
                  @click="handleSuggestTopics" 
                  :disabled="suggestTopicsLoading"
                  class="mt-4 inline-flex items-center gap-2 rounded-xl bg-indigo-50 px-4 py-2.5 text-sm font-semibold text-indigo-700 hover:bg-indigo-100 transition-colors border border-indigo-100 disabled:opacity-75 disabled:cursor-not-allowed"
                >
                  <svg v-if="suggestTopicsLoading" class="animate-spin h-4 w-4 text-indigo-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                  <svg v-else class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a.75.75 0 01.75.75v1.5a.75.75 0 01-1.5 0v-1.5A.75.75 0 0110 2zM10 15a.75.75 0 01.75.75v1.5a.75.75 0 01-1.5 0v-1.5A.75.75 0 0110 15zM10 7a3 3 0 100 6 3 3 0 000-6zM15.657 5.404a.75.75 0 10-1.06-1.06l-1.061 1.06a.75.75 0 001.06 1.06l1.06-1.06zM6.464 14.596a.75.75 0 10-1.06-1.06l-1.06 1.06a.75.75 0 001.06 1.06l1.06-1.06zM18 10a.75.75 0 01-.75.75h-1.5a.75.75 0 010-1.5h1.5A.75.75 0 0118 10zM5 10a.75.75 0 01-.75.75h-1.5a.75.75 0 010-1.5h1.5A.75.75 0 015 10zM14.596 15.657a.75.75 0 001.06-1.06l-1.06-1.061a.75.75 0 10-1.06 1.06l1.06 1.06zM5.404 6.464a.75.75 0 001.06-1.06l-1.06-1.06a.75.75 0 10-1.061 1.06l1.06 1.061z" /></svg>
                  <span v-if="suggestTopicsLoading">Generando sugerencias...</span>
                  <span v-else>Sugerir temas con IA</span>
                </button>
              </div>

              <!-- Puntos Clave Input -->
              <div class="pt-6 border-t border-slate-100">
                <label for="puntos-clave" class="block text-sm font-semibold leading-6 text-slate-900 mb-2">Puntos clave (Opcional)</label>
                <div class="flex gap-2 mb-4">
                  <input 
                    id="puntos-clave"
                    v-model="newTag" 
                    @keydown.enter.prevent="addTag"
                    type="text" 
                    class="block w-full rounded-xl border-0 py-3 px-4 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm"
                    placeholder="Ej. Requisitos físicos... (Presiona Enter)" 
                  />
                  <button @click="addTag" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-sm transition-colors border border-slate-200 shadow-sm shrink-0">
                    Añadir
                  </button>
                </div>
                
                <!-- Tags List Inline -->
                <div class="flex flex-wrap gap-2" v-if="tags.length > 0">
                  <span v-for="(tag, index) in tags" :key="index" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium bg-indigo-50 text-indigo-700 border border-indigo-100">
                    {{ tag }}
                    <button @click="removeTag(tag)" class="text-indigo-400 hover:text-indigo-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600 rounded-full p-0.5" title="Eliminar punto clave">
                      <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                  </span>
                </div>
              </div>

              <!-- URL Scraper -->
              <div class="pt-6 border-t border-slate-100">
                <label for="reference-url" class="block text-sm font-semibold leading-6 text-slate-900 mb-2">URLs de Referencia</label>
                <input
                  id="reference-url"
                  v-model="referenceUrl"
                  @keydown.enter.prevent="handleScrape"
                  type="text"
                  class="block w-full rounded-xl border-0 py-3 px-4 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm"
                  placeholder="Pega un enlace y pulsa Enter..."
                />
                
                <!-- Smart URL Scraper Feedback -->
                <div v-if="isScraping" class="mt-4 flex items-center gap-2 text-sm text-indigo-600 font-medium bg-indigo-50 p-3 rounded-lg w-fit transition-all duration-300">
                  <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                  Analizando URLs...
                </div>
                <div v-else-if="scrapedUrls.length > 0" class="mt-4 space-y-2 transition-all duration-300">
                  <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Lectura de fuentes completada:</p>
                  <div class="flex flex-wrap gap-2">
                    <div v-for="url in scrapedUrls" :key="url.url" class="inline-flex items-center gap-2 rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                      <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" /></svg>
                      {{ url.title }}
                    </div>
                  </div>
                </div>
              </div>

            </div>
            
            <div class="p-8 border-t border-slate-100 bg-slate-50 flex justify-end">
              <button 
                @click="handleProceedToArchitect" 
                :disabled="architectLoading"
                class="group inline-flex items-center justify-center rounded-xl bg-indigo-600 px-8 py-3.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition-all duration-200 disabled:opacity-75 disabled:cursor-not-allowed"
              >
                <div v-if="architectLoading" class="flex items-center gap-2">
                  <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                  Redactando Plan...
                </div>
                <div v-else class="flex items-center">
                  Continuar a Arquitectura
                  <svg class="ml-2 -mr-1 h-5 w-5 group-hover:translate-x-1 transition-transform" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 10a.75.75 0 01.75-.75h10.638L10.23 5.29a.75.75 0 111.04-1.08l5.5 5.25a.75.75 0 010 1.08l-5.5 5.25a.75.75 0 11-1.04-1.08l4.158-3.96H3.75A.75.75 0 013 10z" clip-rule="evenodd" /></svg>
                </div>
              </button>
            </div>
          </div>
        </div>
        <!-- STEP 2: THE ARCHITECT -->
        <div v-else-if="currentStep === 2" class="space-y-8" key="step2">
          <div class="mb-8">
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl mb-2">Revisar Plan del Artículo</h1>
            <p class="text-slate-500">Ajusta la estructura principal y la configuración antes de generar el borrador.</p>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 lg:h-[calc(100vh-10rem)]">
            
            <!-- Left col: Interactive Outline and Links -->
            <div class="lg:col-span-2 flex flex-col gap-6 lg:sticky lg:top-24 max-h-[calc(100vh-10rem)]">
              
              <!-- Outline Card -->
              <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col items-stretch flex-1 overflow-hidden min-h-[50%]">
              <h2 class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
                <svg class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" /></svg>
                Esquema Interactivo
              </h2>
              <p class="text-sm text-slate-500 mb-6">Activa, reorganiza o edita los encabezados propuestos.</p>
              
              <ul class="space-y-3 overflow-y-auto flex-1 pr-2">
                <li v-for="(item, index) in outlineList" :key="item.id" 
                    draggable="true"
                    @dragstart="onDragStart(index)"
                    @dragover.prevent
                    @dragenter.prevent
                    @drop="onDrop(index)"
                    class="group flex items-center gap-3 bg-white border border-slate-200 rounded-xl p-3 shadow-sm hover:shadow-md transition-all cursor-move" 
                    :class="{'opacity-60 bg-slate-50': !item.included, 'ring-2 ring-indigo-500 ring-dashed opacity-50': draggedItemIndex === index}">
                  
                  <!-- Drag Handle -->
                  <span class="cursor-grab text-slate-300 hover:text-slate-500 shrink-0">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" /></svg>
                  </span>
                  
                  <!-- Checkbox -->
                  <div class="flex items-center shrink-0">
                    <input type="checkbox" v-model="item.included" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600 transition duration-150 cursor-pointer" />
                  </div>

                  <!-- Editable Input Text -->
                  <input 
                    type="text" 
                    v-model="item.text" 
                    class="text-sm font-semibold text-slate-800 flex-grow border-0 border-b border-transparent focus:border-indigo-600 focus:ring-0 bg-transparent px-1 py-1 transition-all" 
                    :class="{'line-through text-slate-500': !item.included}" 
                  />
                  
                  <!-- Delete Action -->
                  <button @click="removeHeading(item.id)" class="opacity-0 group-hover:opacity-100 text-slate-400 hover:text-red-500 transition-opacity p-1.5 rounded hover:bg-red-50 shrink-0">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd" /></svg>
                  </button>
                </li>
              </ul>
              
              <div class="mt-4 flex justify-start pt-4 border-t border-slate-100">
                <button @click="addHeading" class="inline-flex items-center gap-1.5 text-sm font-semibold text-indigo-600 hover:text-indigo-800 transition-colors px-3 py-2 bg-indigo-50 hover:bg-indigo-100 rounded-lg">
                  <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" /></svg>
                  Añadir nuevo encabezado
                </button>
              </div>
              </div>

              <!-- Suggested Links Card -->
              <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col items-stretch overflow-hidden shrink-0 max-h-[50%]">
                <h2 class="text-lg font-bold text-slate-900 mb-2 flex items-center gap-2">
                  <svg class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
                  Enlaces de Referencia Sugeridos
                </h2>
                <p class="text-sm text-slate-500 mb-6">La IA sugiere incluir estos enlaces relevantes como referencia en el artículo.</p>
                
                <ul class="space-y-3 overflow-y-auto flex-1 pr-2">
                  <li v-for="link in suggestedLinks" :key="link.id" class="group flex items-start gap-3 bg-white border border-slate-200 rounded-xl p-3 shadow-sm hover:shadow-md transition-all" :class="{'opacity-60 bg-slate-50': !link.included}">
                    
                    <!-- Checkbox -->
                    <div class="flex items-center shrink-0 mt-0.5">
                      <input type="checkbox" v-model="link.included" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600 transition duration-150 cursor-pointer" />
                    </div>

                    <!-- Link Info -->
                    <div class="flex-1 min-w-0">
                      <div class="flex items-center gap-2 mb-1">
                        <input 
                          type="text" 
                          v-model="link.title" 
                          class="text-sm font-semibold text-slate-800 w-full border-0 border-b border-transparent focus:border-indigo-600 focus:ring-0 bg-transparent px-1 py-0.5 transition-all" 
                          :class="{'line-through text-slate-500': !link.included}" 
                        />
                      </div>
                      <input 
                        type="text" 
                        v-model="link.url" 
                        class="text-xs text-slate-500 w-full border-0 border-b border-transparent focus:border-indigo-600 focus:ring-0 bg-transparent px-1 py-0.5 transition-all truncate" 
                        :class="{'line-through': !link.included}" 
                      />
                    </div>
                    
                    <!-- Delete Action -->
                    <button @click="removeLink(link.id)" class="opacity-0 group-hover:opacity-100 text-slate-400 hover:text-red-500 transition-opacity p-1.5 rounded hover:bg-red-50 shrink-0">
                      <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd" /></svg>
                    </button>
                  </li>
                </ul>

                <div class="mt-4 flex justify-start pt-4 border-t border-slate-100 gap-2 items-center">
                   <div class="flex-1 grid grid-cols-2 gap-2">
                      <input v-model="newLinkTitle" @keydown.enter="addLink" type="text" placeholder="Título del enlace..." class="block w-full rounded-lg border-0 py-2 px-3 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-xs">
                      <input v-model="newLinkUrl" @keydown.enter="addLink" type="text" placeholder="https://..." class="block w-full rounded-lg border-0 py-2 px-3 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-xs">
                   </div>
                   <button @click="addLink" class="inline-flex items-center justify-center shrink-0 w-8 h-8 font-semibold text-indigo-600 hover:text-indigo-800 transition-colors bg-indigo-50 hover:bg-indigo-100 rounded-lg" title="Añadir enlace">
                      <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" /></svg>
                   </button>
                </div>
              </div>
            </div>

            <!-- Right col: Settings Config Panel -->
            <div class="bg-white rounded-2xl shadow-lg border border-slate-200 flex flex-col lg:h-[calc(100vh-10rem)] lg:sticky lg:top-24 overflow-hidden lg:col-span-1">
              <div class="p-4 border-b border-slate-100 bg-slate-50 flex items-center gap-2 z-10 shrink-0">
                <svg class="h-5 w-5 text-indigo-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                <h3 class="font-bold text-slate-800">Ajustes del Artículo</h3>
              </div>
              
              <div class="flex-1 p-5 overflow-y-auto space-y-8">
                
                <!-- Keywords -->
                <div>
                  <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Etiquetas / Keywords</label>
                  <div class="flex flex-wrap gap-2 mb-3">
                    <span v-for="tag in tags" :key="tag" class="inline-flex items-center gap-1.5 rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 shadow-sm border border-slate-200">
                      {{ tag }}
                      <button @click="removeTag(tag)" class="-mr-1 text-slate-400 hover:text-red-500 rounded-full hover:bg-slate-200">
                         <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
                      </button>
                    </span>
                  </div>
                  <input
                    v-model="newTag"
                    @keydown.enter.prevent="addTag"
                    type="text"
                    class="block w-full rounded-xl border-0 py-2.5 px-3 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm"
                    placeholder="Añadir nueva y pulsar Enter..."
                  />
                </div>

                <!-- Tone Slider -->
                <div>
                  <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">
                    Tono: 
                    <span class="text-indigo-600">
                      {{ toneValue < 33 ? 'Profesional' : toneValue < 66 ? 'Cercano' : 'Viral/Audaz' }}
                    </span>
                  </label>
                  <input type="range" v-model="toneValue" min="0" max="100" class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer mb-2 focus:outline-none focus:ring-2 focus:ring-indigo-600 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:w-4 [&::-webkit-slider-thumb]:h-4 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-indigo-600 [&::-moz-range-thumb]:w-4 [&::-moz-range-thumb]:h-4 [&::-moz-range-thumb]:border-0 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:bg-indigo-600" />
                  <div class="flex justify-between text-[10px] uppercase font-bold text-slate-400">
                    <span>Prof.</span>
                    <span>Cercano</span>
                    <span>Viral</span>
                  </div>
                </div>

                <!-- Length -->
                <div>
                  <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Longitud</label>
                  <select v-model="articleLength" class="block w-full rounded-xl border-0 py-2.5 pl-3 pr-10 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6 cursor-pointer bg-white">
                    <option value="short">Corto (~500 palabras)</option>
                    <option value="medium">Mediano (~1000 palabras)</option>
                    <option value="long">Largo (~2000 palabras)</option>
                  </select>
                </div>

                <!-- Checkboxes / Toggles for tables and lists -->
                <div class="space-y-3">
                  <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Elementos Adicionales</label>
                  <label class="flex items-center justify-between cursor-pointer group">
                    <span class="text-sm font-medium text-slate-700 group-hover:text-slate-900 transition-colors">Incluir listas (viñetas)</span>
                    <input type="checkbox" v-model="includeLists" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600 transition duration-150 ease-in-out cursor-pointer" />
                  </label>
                  <label class="flex items-center justify-between cursor-pointer group">
                    <span class="text-sm font-medium text-slate-700 group-hover:text-slate-900 transition-colors">Incluir tablas de datos</span>
                    <input type="checkbox" v-model="includeTables" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-600 transition duration-150 ease-in-out cursor-pointer" />
                  </label>
                </div>

                <!-- Media Library -->
                <div>
                  <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Galería de Imágenes</label>
                  <div class="border-2 border-dashed border-slate-200 bg-slate-50 rounded-xl p-4 text-center hover:bg-slate-100 hover:border-slate-300 transition-colors cursor-pointer mb-4">
                    <svg class="mx-auto h-6 w-6 text-slate-400 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    <span class="text-xs text-indigo-600 font-semibold block">Subir o Arrastrar</span>
                  </div>
                  <div class="grid grid-cols-3 gap-3">
                    <div class="aspect-square bg-slate-100 rounded-lg flex items-center justify-center overflow-hidden border border-slate-200 cursor-grab hover:shadow-sm transition-shadow group relative">
                      <svg class="w-8 h-8 text-slate-300 group-hover:text-indigo-400 transition-colors" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" /></svg>
                      <div class="absolute inset-0 bg-indigo-600/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </div>
                    <div class="aspect-square bg-slate-100 rounded-lg flex items-center justify-center overflow-hidden border border-slate-200 cursor-grab hover:shadow-sm transition-shadow group relative">
                      <svg class="w-8 h-8 text-slate-300 group-hover:text-indigo-400 transition-colors" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" /></svg>
                      <div class="absolute inset-0 bg-indigo-600/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </div>
                  </div>
                  <p class="text-[10px] text-slate-400 mt-3 text-center leading-relaxed">Arrastra estas miniaturas para incluirlas en el texto.</p>
                </div>

              </div>
              
              <div class="p-4 border-t border-slate-100 bg-slate-50 shrink-0 flex justify-between gap-3">
                <button @click="goNext(1)" class="w-1/3 inline-flex items-center justify-center rounded-xl bg-white border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-600 shadow-sm hover:bg-slate-50 transition-all focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600">
                  <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 14l-2-2m0 0l2-2m-2 2h8m-9 5a9 9 0 110-18 9 9 0 010 18z" /></svg>
                  Volver
                </button>
                <button 
                  @click="handleGenerateArticle" 
                  :disabled="generateLoading"
                  class="w-2/3 inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition-all focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600 disabled:opacity-75 disabled:cursor-not-allowed"
                >
                  <div v-if="generateLoading" class="flex items-center gap-2">
                    <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Escribiendo artículo...
                  </div>
                  <div v-else class="flex items-center">
                    Generar Artículo
                    <svg class="ml-2 -mr-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                  </div>
                </button>
              </div>
            </div>

          </div>
        </div>
        <!-- STEP 3: EDITOR & DEMO -->
        <div v-else-if="currentStep === 3" class="space-y-6 mb-8" key="step3">
          
          <!-- Top Tool Bar -->
          <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 flex flex-col md:flex-row justify-between items-center gap-4">
            
            <!-- SEO Scorecard -->
            <div class="flex items-center gap-3 px-3 py-1.5 bg-slate-50 border border-slate-100 rounded-xl">
               <div class="relative flex h-3 w-3">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
              </div>
              <div class="flex flex-col">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Puntuación SEO</span>
                <span class="text-sm font-extrabold text-slate-700 leading-none">{{ seoScore }}/100</span>
              </div>
            </div>

            <!-- Toggle Switch -->
            <div class="flex bg-slate-100 p-1 rounded-xl w-full max-w-xs relative isolate">
              <button @click="viewMode = 'editor'" class="flex-1 py-1.5 text-sm font-semibold rounded-lg z-10 transition-colors" :class="viewMode === 'editor' ? 'text-indigo-700' : 'text-slate-500 hover:text-slate-700'">Editor</button>
              <button @click="viewMode = 'demo'" class="flex-1 py-1.5 text-sm font-semibold rounded-lg z-10 transition-colors" :class="viewMode === 'demo' ? 'text-indigo-700' : 'text-slate-500 hover:text-slate-700'">Vista Previa</button>
              <div class="absolute inset-y-1 left-1 w-[calc(50%-4px)] bg-white rounded-lg shadow-sm transition-transform duration-300 ease-out z-0" :class="viewMode === 'demo' ? 'translate-x-[100%]' : 'translate-x-0'"></div>
            </div>

            <!-- Actions (Version / Export) -->
            <div class="flex items-center gap-2">
              <button @click="goNext(2)" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-semibold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors focus:ring-2 focus:ring-indigo-600">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 14l-2-2m0 0l2-2m-2 2h8m-9 5a9 9 0 110-18 9 9 0 010 18z" /></svg>
                Volver
              </button>
              <button class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-semibold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors focus:ring-2 focus:ring-indigo-600">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                Historial
              </button>
              <button class="inline-flex items-center gap-2 px-4 py-2 text-sm font-bold text-white bg-indigo-600 border border-transparent rounded-xl shadow-sm hover:bg-indigo-500 transition-colors focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600">
                Publicar Vía API
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 2.75a.75.75 0 00-1.5 0v8.614L6.295 8.235a.75.75 0 10-1.09 1.03l4.25 4.5a.75.75 0 001.09 0l4.25-4.5a.75.75 0 00-1.09-1.03l-2.955 3.129V2.75z" /><path d="M3.5 12.75a.75.75 0 00-1.5 0v2.5A2.75 2.75 0 004.75 18h10.5A2.75 2.75 0 0018 15.25v-2.5a.75.75 0 00-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5z" /></svg>
              </button>
            </div>
          </div>

          <!-- Workplace Area -->
          <div class="flex flex-col md:flex-row gap-6 min-h-[600px] lg:h-[calc(100vh-14rem)]">
            
            <!-- EDITOR VIEW (Markdown code representation) -->
            <div v-show="viewMode === 'editor'" class="flex-1 bg-[#1e1e1e] rounded-2xl shadow-xl border border-slate-800 flex flex-col overflow-hidden animate-fade-in h-full">
              <div class="px-4 py-2 bg-[#2d2d2d] border-b border-[#404040] flex items-center justify-between">
                <span class="text-xs font-mono text-slate-400">guardia-civil-borrador.md</span>
                <span class="text-[10px] bg-indigo-500/20 text-indigo-300 px-2 py-0.5 rounded uppercase font-bold tracking-widest flex items-center gap-1">
                  <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14.5v-9l6 4.5-6 4.5z"/></svg>
                  Monaco Engine
                </span>
              </div>
              <textarea
                class="flex-1 w-full h-full bg-transparent text-[#d4d4d4] font-mono text-sm p-6 resize-none outline-none leading-relaxed"
                spellcheck="false"
                :value="fakeArticle"
              ></textarea>
            </div>

            <!-- DEMO VIEW (Rich rendered view) -->
            <div v-show="viewMode === 'demo'" class="flex-1 bg-white rounded-2xl shadow-xl border border-slate-100 overflow-y-auto animate-fade-in relative group p-8 sm:p-12 h-full">
              <div class="absolute top-4 right-4 opacity-0 group-hover:opacity-100 transition-opacity">
                <span class="bg-indigo-100 text-indigo-700 text-xs px-2 py-1 rounded font-semibold whitespace-nowrap shadow-sm border border-indigo-200">✨ Clic para editar bloque</span>
              </div>
              
              <article class="max-w-none space-y-8 text-slate-700 text-lg">
                
                <h1 class="group relative text-4xl sm:text-5xl font-extrabold text-slate-900 tracking-tight mb-6">
                  Cómo Ser Guardia Civil: Pasos para el Ingreso
                  <button class="absolute -left-14 top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 transition-opacity p-2 text-indigo-500 hover:bg-indigo-50 rounded-lg shadow-sm border border-indigo-100 bg-white" title="Regenerar Título">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14.5v-9l6 4.5-6 4.5z"/></svg>
                  </button>
                </h1>
                
                <div class="group relative rounded-xl hover:bg-slate-50 transition-colors p-4 -mx-4 border border-transparent hover:border-slate-200">
                  <p class="text-xl text-slate-600 leading-relaxed m-0">¿Estás preparado para dar el paso y entrar en la Guardia Civil? Es una de las instituciones más respetadas de España, ofreciendo una carrera estable y gratificante. En esta guía, desglosamos exactamente lo que necesitas saber para aprobar las oposiciones y conseguir tu uniforme.</p>
                  <button class="absolute right-4 top-4 opacity-0 group-hover:opacity-100 transition-opacity inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold text-indigo-700 bg-white border border-indigo-200 rounded-md shadow-sm hover:bg-indigo-50">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                    Reescribir
                  </button>
                </div>
                
                <div class="group relative rounded-xl hover:bg-slate-50 transition-colors p-4 -mx-4 border border-transparent hover:border-slate-200">
                  <h2 class="mt-0 text-2xl font-bold text-slate-800 mb-4 pb-2 border-b border-slate-100">1. Requisitos Básicos</h2>
                  <p class="mb-4">Antes de empezar a estudiar, asegúrate de cumplir los criterios mínimos:</p>
                  <ul class="mb-0 list-disc list-inside space-y-2">
                    <li><strong>Edad:</strong> Entre 18 y 40 años.</li>
                    <li><strong>Nacionalidad:</strong> Ciudadanía española.</li>
                    <li><strong>Estudios:</strong> El requisito mínimo es la ESO.</li>
                    <li><strong>Físico:</strong> Sin tatuajes excluyentes visibles con el uniforme.</li>
                  </ul>
                  <button class="absolute top-4 right-4 opacity-0 group-hover:opacity-100 transition-opacity inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold text-indigo-700 bg-white border border-indigo-200 rounded-md shadow-sm hover:bg-indigo-50">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                    Reescribir
                  </button>
                </div>

                <!-- AI Image Slot Placeholder -->
                <div class="not-prose my-8">
                  <div class="border-2 border-dashed border-indigo-200 bg-indigo-50/50 rounded-2xl p-8 text-center hover:bg-indigo-50 cursor-pointer transition-colors group/upload relative">
                    <div class="mx-auto h-12 w-12 text-indigo-300 mb-3 flex items-center justify-center rounded-full bg-white shadow-sm ring-1 ring-indigo-100 group-hover/upload:scale-110 transition-transform">
                      <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    </div>
                    <span class="mt-2 block text-sm font-semibold text-indigo-900">Arrastra una imagen aquí</span>
                    <span class="mt-1 block text-xs text-indigo-500">Sugerencia de IA: "Coloca una foto de la academia de la Guardia Civil aquí"</span>
                    <button class="mt-4 px-3 py-1.5 bg-white border border-indigo-200 text-indigo-600 text-xs font-bold rounded-lg shadow-sm hover:bg-slate-50 transition-colors">Buscar Archivos</button>
                  </div>
                </div>

                <div class="group relative rounded-xl hover:bg-slate-50 transition-colors p-4 -mx-4 border border-transparent hover:border-slate-200">
                  <h2 class="mt-0 text-2xl font-bold text-slate-800 mb-4 pb-2 border-b border-slate-100">2. Las Pruebas Físicas</h2>
                  <p class="mb-0 leading-relaxed">Aquí es donde muchos fallan, pero con preparación, puedes sobresalir. Las pruebas incluyen un sprint de 50m, 1000m de carrera, flexiones y natación de 50m.</p>
                  <button class="absolute top-4 right-4 opacity-0 group-hover:opacity-100 transition-opacity inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold text-indigo-700 bg-white border border-indigo-200 rounded-md shadow-sm hover:bg-indigo-50">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                    Reescribir
                  </button>
                </div>
                
                <!-- Notice component injected by AI -->
                <div class="my-6 rounded-xl border-l-4 border-amber-400 bg-amber-50 p-4 relative group">
                  <div class="flex mr-12">
                    <div class="flex-shrink-0">
                      <svg class="h-5 w-5 text-amber-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" /></svg>
                    </div>
                    <div class="ml-3">
                      <p class="text-sm font-medium text-amber-800">Consejo Experto: Empieza a entrenar al menos 6 meses antes del examen.</p>
                    </div>
                  </div>
                  <button class="absolute top-1/2 -translate-y-1/2 right-4 opacity-0 group-hover:opacity-100 transition-opacity p-2 text-amber-600 bg-amber-50 border border-amber-200 rounded-md shadow-sm hover:bg-amber-100" title="Reescribir Consejo">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                  </button>
                </div>

                <div class="group relative rounded-xl hover:bg-slate-50 transition-colors p-4 -mx-4 border border-transparent hover:border-slate-200">
                  <h2 class="mt-0 text-2xl font-bold text-slate-800 mb-4 pb-2 border-b border-slate-100">3. El Examen Teórico</h2>
                  <p class="mb-0 leading-relaxed">El temario consta de 25 temas que incluyen derecho constitucional, derecho penal y sociología. Te enfrentarás a un examen tipo test, una prueba de ortografía, y una evaluación psicotécnica.</p>
                  <button class="absolute top-4 right-4 opacity-0 group-hover:opacity-100 transition-opacity inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold text-indigo-700 bg-white border border-indigo-200 rounded-md shadow-sm hover:bg-indigo-50">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                    Reescribir
                  </button>
                </div>
              </article>
            </div>
          </div>
        </div>
      </transition>

    </main>
  </div>
</template>

<style scoped>
.animate-fade-in {
  animation: fadeIn 0.4s ease-out forwards;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>