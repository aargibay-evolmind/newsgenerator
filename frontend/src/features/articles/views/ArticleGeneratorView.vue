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
import { marked } from 'marked'
import DOMPurify from 'dompurify'

// Store and API hooks
const store = useArticleStore()
const {
  blogTitle,
  keywords: tags,
  referenceUrls,
  scrapedReferences,
  additionalContext,
  audience,
  searchIntent,
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

function addFAQTemplate() {
  const lastId = outlineList.value.length > 0 ? Math.max(...outlineList.value.map(h => h.id)) : Date.now()
  outlineList.value.push({ id: lastId + 1, text: 'Preguntas Frecuentes (FAQ)', included: true, budget: 'medium' })
  outlineList.value.push({ id: lastId + 2, text: '¿Cuáles son los plazos principales?', included: true, budget: 'short' })
  outlineList.value.push({ id: lastId + 3, text: '¿Qué documentación es necesaria?', included: true, budget: 'short' })
}

function addProsConsTemplate() {
  const lastId = outlineList.value.length > 0 ? Math.max(...outlineList.value.map(h => h.id)) : Date.now()
  outlineList.value.push({ id: lastId + 1, text: 'Ventajas y Desventajas', included: true, budget: 'medium' })
  outlineList.value.push({ id: lastId + 2, text: 'Puntos Fuertes (Pros)', included: true, budget: 'medium' })
  outlineList.value.push({ id: lastId + 3, text: 'Puntos Débiles (Contras)', included: true, budget: 'medium' })
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
const showVersionHistory = ref(false)

/**
 * Dynamic SEO Score Calculation
 * Criteria:
 * 1. Title presence (H1) -> 20 pts
 * 2. Article length (> 600 words) -> 25 pts
 * 3. Keyword density (Presence of tags) -> 20 pts
 * 4. Structure (H2/H3 presence) -> 15 pts
 * 5. Multimedia/Links (Markdown links presence) -> 10 pts
 * 6. Readability (Lists presence) -> 10 pts
 */
const seoScore = computed(() => {
  let score = 0
  const content = generatedMarkdown.value
  if (!content) return 0

  // 1. H1 Check
  if (/^#\s+.+$/m.test(content)) score += 20

  // 2. Length Check
  const wordCount = content.split(/\s+/).filter(w => w.length > 0).length
  if (wordCount > 600) score += 25
  else if (wordCount > 300) score += 15

  // 3. Keyword Density (Tags)
  const tagsPresent = tags.value.filter(tag => 
    new RegExp(tag, 'gi').test(content)
  ).length
  if (tags.value.length > 0) {
    score += Math.min(20, Math.floor((tagsPresent / tags.value.length) * 20))
  } else {
    score += 20 // No tags to check, assume default
  }

  // 4. Structure (Subheadings)
  if (/^##\s+.+$/m.test(content)) score += 10
  if (/^###\s+.+$/m.test(content)) score += 5

  // 5. Links
  if (/\[.+\]\(.+\)/.test(content)) score += 10

  // 6. Lists
  if (/^\s*[-*+]\s+.+$/m.test(content) || /^\s*\d+\.\s+.+$/m.test(content)) score += 10

  return score
})

const seoAnalysis = computed(() => {
  const content = generatedMarkdown.value
  const wordCount = content.split(/\s+/).filter(w => w.length > 0).length
  const tagsPresent = tags.value.filter(tag => new RegExp(tag, 'gi').test(content))
  
  return [
    { label: 'Título H1 detectado', value: /^#\s+.+$/m.test(content) },
    { label: 'Longitud óptima (>600 palabras)', value: wordCount > 600 },
    { label: 'Palabras clave integradas', value: tags.value.length > 0 && tagsPresent.length === tags.value.length },
    { label: 'Subencabezados (H2/H3)', value: /^##\s+.+$/m.test(content) },
    { label: 'Enlaces de referencia', value: /\[.+\]\(.+\)/.test(content) },
    { label: 'Listas para legibilidad', value: /^\s*[-*+]\s+.+$/m.test(content) || /^\s*\d+\.\s+.+$/m.test(content) }
  ]
})

const googleTitle = computed(() => {
  const match = generatedMarkdown.value.match(/^#\s+(.+)$/m)
  return match ? match[1] : blogTitle.value
})

const googleSnippet = computed(() => {
  // Take first paragraph after H1
  const lines = generatedMarkdown.value.split('\n')
  const firstPara = lines.find(l => l.trim().length > 50 && !l.startsWith('#'))
  return (firstPara || 'Visualiza cómo aparecerá tu noticia en los resultados de búsqueda de Google. Optimiza el contenido para atraer más clics.').substring(0, 160) + '...'
})

const SEO_PROMPT_TEMPLATE = `
# [TITULO]

[INTRODUCCION]

## 1. [SECCION 1]

[CONTENIDO 1]
`

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

const titleError = ref(false)

async function handleProceedToArchitect() {
  if (architectLoading.value) return;

  if (!blogTitle.value.trim()) {
    titleError.value = true
    return
  }

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

const generatedMarkdown = ref(fakeArticle)

const renderedMarkdown = computed(() => {
  const rawHtml = marked.parse(generatedMarkdown.value) as string
  return DOMPurify.sanitize(rawHtml)
})


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
             <button @click="currentStep = 1" class="flex items-center px-4 py-1.5 rounded-full transition-all" :class="currentStep === 1 ? 'bg-white shadow-sm text-indigo-600' : 'hover:text-slate-800'">1. Definición</button>
             <svg class="h-4 w-4 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
             <button @click="currentStep = 2" class="flex items-center px-4 py-1.5 rounded-full transition-all" :class="currentStep === 2 ? 'bg-white shadow-sm text-indigo-600' : 'hover:text-slate-800'">2. Diseño</button>
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
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8 sm:mt-12">
      
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
            <h1 class="text-4xl font-extrabold tracking-tight text-slate-900 sm:text-5xl mb-4">¿De qué trata la noticia?</h1>
            <p class="text-lg text-slate-500">Agrega el título o pega URLs de referencia abajo. La IA lo organizará por ti.</p>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <!-- MAIN CONTENT -->
            <div class="lg:col-span-3 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden ring-1 ring-slate-900/5">
              <div class="p-8 space-y-8">
                
                <!-- Quick Start Title -->
                <div>
                  <label for="blog-title" class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-3">Título de la Noticia</label>
                  <input
                    id="blog-title"
                    v-model="blogTitle"
                    @input="titleError = false"
                    type="text"
                    class="block w-full rounded-2xl border-0 py-4 px-5 text-slate-900 shadow-sm ring-1 ring-inset placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-inset sm:text-lg sm:leading-relaxed transition-all"
                    :class="titleError 
                      ? 'ring-red-400 focus:ring-red-400 bg-red-50/30' 
                      : 'ring-slate-200 focus:ring-slate-400'"
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
                      class="inline-flex items-center gap-2 rounded-xl bg-indigo-50 px-4 py-2.5 text-sm font-semibold text-indigo-700 hover:bg-indigo-100 transition-colors border border-indigo-100 disabled:opacity-75 disabled:cursor-not-allowed"
                    >
                      <svg v-if="suggestTopicsLoading" class="animate-spin h-4 w-4 text-indigo-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                      <svg v-else class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a.75.75 0 01.75.75v1.5a.75.75 0 01-1.5 0v-1.5A.75.75 0 0110 2zM10 15a.75.75 0 01.75.75v1.5a.75.75 0 01-1.5 0v-1.5A.75.75 0 0110 15zM10 7a3 3 0 100 6 3 3 0 000-6zM15.657 5.404a.75.75 0 10-1.06-1.06l-1.061 1.06a.75.75 0 001.06 1.06l1.06-1.06zM6.464 14.596a.75.75 0 10-1.06-1.06l-1.06 1.06a.75.75 0 001.06 1.06l1.06-1.06zM18 10a.75.75 0 01-.75.75h-1.5a.75.75 0 010-1.5h1.5A.75.75 0 0118 10zM5 10a.75.75 0 01-.75.75h-1.5a.75.75 0 010-1.5h1.5A.75.75 0 015 10zM14.596 15.657a.75.75 0 001.06-1.06l-1.06-1.061a.75.75 0 10-1.06 1.06l1.06 1.06zM5.404 6.464a.75.75 0 001.06-1.06l-1.06-1.06a.75.75 0 10-1.061 1.06l1.06 1.061z" /></svg>
                      <span v-if="suggestTopicsLoading">Generando...</span>
                      <span v-else>Sugerir temas con IA</span>
                    </button>

                    <!-- Stacked selectable topics -->
                    <div v-if="suggestedTopics.length > 0" class="mt-3 rounded-2xl border border-indigo-100 bg-indigo-50/40 overflow-hidden animate-in fade-in slide-in-from-top-2 duration-300">
                      <div class="flex items-center justify-between px-4 py-2 border-b border-indigo-100">
                        <span class="text-xs font-bold text-indigo-600 uppercase tracking-widest">Sugerencias</span>
                        <button @click="suggestedTopics = []" class="flex items-center justify-center h-6 w-6 rounded-full text-slate-400 hover:bg-indigo-100 hover:text-indigo-700 transition-colors" title="Limpiar sugerencias">
                          <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                      </div>
                      <div class="divide-y divide-indigo-100/70">
                        <button 
                          v-for="(topic, idx) in suggestedTopics" 
                          :key="idx"
                          @click="blogTitle = topic; suggestedTopics = []"
                          class="w-full text-left px-4 py-3 text-sm font-medium text-slate-700 hover:bg-indigo-600 hover:text-white transition-colors duration-150 flex items-center gap-3 group"
                        >
                          <span class="shrink-0 h-5 w-5 rounded-full bg-indigo-100 group-hover:bg-indigo-500 flex items-center justify-center text-[10px] font-bold text-indigo-600 group-hover:text-white transition-colors">{{ idx + 1 }}</span>
                          {{ topic }}
                        </button>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Puntos Clave Input -->
                <div class="pt-6 border-t border-slate-100">
                  <div class="mb-4">
                    <label for="puntos-clave" class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1">Puntos clave <span class="normal-case font-medium text-slate-400">(Opcional)</span></label>
                    <p class="text-xs text-slate-500">Define los ejes principales que la noticia debe cubrir para asegurar que el contenido sea relevante y completo.</p>
                  </div>
                  <div class="flex gap-2 mb-4">
                    <input 
                      id="puntos-clave"
                      v-model="newTag" 
                      @keydown.enter.prevent="addTag"
                      type="text" 
                      class="block w-full rounded-xl border-0 py-3 px-4 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-slate-400 sm:text-sm"
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
                      <button @click="removeTag(tag)" class="text-indigo-400 hover:text-indigo-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-400 rounded-full p-0.5" title="Eliminar punto clave">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                      </button>
                    </span>
                  </div>
                </div>

                <!-- URL Scraper -->
                <div class="pt-6 border-t border-slate-100">
                  <label for="reference-url" class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-3">URLs de Referencia</label>
                  <input
                    id="reference-url"
                    v-model="referenceUrl"
                    @keydown.enter.prevent="handleScrape"
                    type="text"
                    class="block w-full rounded-2xl border-0 py-4 px-5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-slate-400 sm:text-sm sm:leading-relaxed transition-shadow"
                    placeholder="Pega un enlace y pulsa Enter..."
                  />
                  
                  <!-- Smart URL Scraper Feedback -->
                  <div v-if="isScraping" class="mt-4 flex items-center gap-2 text-sm text-indigo-600 font-medium bg-indigo-50 p-3 rounded-lg w-fit transition-all duration-300">
                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Analizando URLs...
                  </div>
                  <div v-else-if="scrapedReferences.length > 0" class="mt-4 space-y-2 transition-all duration-300">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Lectura de fuentes completada:</p>
                    <div class="flex flex-wrap gap-2">
                      <div v-for="url in scrapedReferences" :key="url.url" class="inline-flex items-center gap-2 rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" /></svg>
                        {{ url.title }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <div class="p-6 border-t border-slate-100 bg-slate-50 flex justify-end">
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
                    Continuar a Diseño
                    <svg class="ml-2 -mr-1 h-5 w-5 group-hover:translate-x-1 transition-transform" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 10a.75.75 0 01.75-.75h10.638L10.23 5.29a.75.75 0 111.04-1.08l5.5 5.25a.75.75 0 010 1.08l-5.5 5.25a.75.75 0 11-1.04-1.08l4.158-3.96H3.75A.75.75 0 013 10z" clip-rule="evenodd" /></svg>
                  </div>
                </button>
              </div>
            </div>

            <!-- SIDEBAR: TARGETING -->
            <aside class="lg:col-span-1">
              <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 lg:sticky lg:top-24 h-full flex flex-col">
                <div class="flex items-center gap-2 pb-4 border-b border-slate-50 shrink-0">
                  <svg class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                  </svg>
                  <h3 class="font-bold text-slate-800">Preferencias</h3>
                </div>

                <div class="space-y-6 pt-6">
                  <div>
                    <label for="audience" class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-3">Público Objetivo</label>
                    <select
                      id="audience"
                      v-model="audience"
                      class="block w-full rounded-xl border-0 py-3 px-4 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-200 focus:outline-none focus:ring-2 focus:ring-slate-400 sm:text-sm bg-slate-50/50"
                    >
                      <option value="General">General (Todos los públicos)</option>
                      <option value="Principiantes">Principiantes / Novatos</option>
                      <option value="Expertos">Expertos / Profesionales</option>
                      <option value="Estudiantes">Estudiantes / Académico</option>
                      <option value="Técnico">Perfil Técnico / Desarrolladores</option>
                    </select>
                  </div>

                  <div>
                    <label for="intent" class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-3">Intención de Búsqueda</label>
                    <select
                      id="intent"
                      v-model="searchIntent"
                      class="block w-full rounded-xl border-0 py-3 px-4 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-200 focus:outline-none focus:ring-2 focus:ring-slate-400 sm:text-sm bg-slate-50/50"
                    >
                      <option value="Informativo">Informativo (Noticia / Saber qué)</option>
                      <option value="Tutorial">Tutorial / Guía Paso a Paso</option>
                      <option value="Transaccional">Transaccional (Venta / Trámite)</option>
                      <option value="Comparativo">Comparativo (Cual es mejor)</option>
                    </select>
                  </div>
                </div>

                <div class="mt-6 pt-6 border-t border-slate-100">
                  <label for="additional-context" class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1">Contexto y Directrices <span class="normal-case font-medium text-slate-400">(Opcional)</span></label>
                  <p class="text-xs text-slate-500 mb-3">Añade indicaciones específicas para la IA: estilo, enfoque, datos a incluir o excluir, etc.</p>
                  <textarea
                    id="additional-context"
                    v-model="additionalContext"
                    rows="4"
                    class="block w-full rounded-xl border-0 py-3 px-4 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-slate-400 text-xs resize-none transition-shadow"
                    placeholder="Ej: Tono cercano y optimista. No mencionar requisitos de edad..."
                  ></textarea>
                </div>

                <div class="mt-auto pt-6">
                  <div class="bg-indigo-50/50 rounded-2xl p-4 border border-indigo-100/50">
                    <p class="text-[10px] text-slate-500 leading-relaxed font-medium">
                      <span class="text-indigo-600 font-bold uppercase tracking-tighter mr-1">Pro Tip:</span> 
                      Estos ajustes permiten a la IA optimizar la complejidad del lenguaje y el formato de la noticia.
                    </p>
                  </div>
                </div>
              </div>
            </aside>
          </div>
        </div>
        <!-- STEP 2: THE ARCHITECT -->
        <div v-else-if="currentStep === 2" class="space-y-8" key="step2">
          <div class="text-center max-w-2xl mx-auto mb-10">
            <h1 class="text-4xl font-extrabold tracking-tight text-slate-900 sm:text-5xl mb-4">Diseño del Artículo</h1>
            <p class="text-lg text-slate-500">Ajusta la estructura y configuración antes de generar el borrador.</p>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            
            <!-- Left col: Interactive Outline and Links -->
            <div class="lg:col-span-3 flex flex-col gap-6">
              
              <!-- Outline Card -->
              <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col items-stretch shrink-0 ring-1 ring-slate-900/5">
              <h2 class="text-lg font-bold text-slate-900 mb-1 flex items-center gap-2">
                <svg class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" /></svg>
                Esquema Interactivo
              </h2>
              <p class="text-sm text-slate-500 mb-6">Activa, reorganiza o edita los encabezados propuestos.</p>
              
              <ul class="space-y-3 pr-2">
                <li v-for="(item, index) in outlineList" :key="item.id" 
                    draggable="true"
                    @dragstart="onDragStart(index)"
                    @dragover.prevent
                    @dragenter.prevent
                    @drop="onDrop(index)"
                    class="group flex items-center gap-3 bg-white border border-slate-200 rounded-xl p-3 hover:border-slate-300 hover:bg-slate-50/50 transition-all cursor-move" 
                    :class="{'opacity-50 bg-slate-50': !item.included, 'border-dashed border-slate-300 bg-slate-50 opacity-40': draggedItemIndex === index}">
                  
                  <!-- Drag Handle -->
                  <span class="cursor-grab text-slate-300 hover:text-slate-500 shrink-0">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" /></svg>
                  </span>
                  
                  <!-- Checkbox -->
                  <div class="flex items-center shrink-0">
                    <input type="checkbox" v-model="item.included" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-slate-400 transition duration-150 cursor-pointer" />
                  </div>

                  <!-- Editable Input Text -->
                  <div class="flex-grow flex flex-col sm:flex-row sm:items-center gap-2">
                    <input 
                      type="text" 
                      v-model="item.text" 
                      class="text-sm font-semibold text-slate-800 flex-grow border-0 border-b border-transparent focus:border-indigo-600 focus:ring-0 bg-transparent px-1 py-1 transition-all" 
                      :class="{'line-through text-slate-500': !item.included}" 
                    />
                    
                    <!-- Budget Selector -->
                    <select
                      v-if="item.included"
                      v-model="item.budget"
                      class="shrink-0 rounded-lg border-0 py-1 pl-2 pr-7 text-xs font-semibold text-slate-600 bg-slate-50 ring-1 ring-inset ring-slate-200 focus:outline-none focus:ring-2 focus:ring-slate-400 cursor-pointer"
                    >
                      <option value="short">Breve (~100 palabras)</option>
                      <option value="medium">Normal (~250 palabras)</option>
                      <option value="long">Extenso (~500 palabras)</option>
                    </select>
                  </div>
                  
                  <!-- Delete Action -->
                  <button @click="removeHeading(item.id)" class="opacity-0 group-hover:opacity-100 text-slate-400 hover:text-red-500 transition-opacity p-1.5 rounded hover:bg-red-50 shrink-0">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd" /></svg>
                  </button>
                </li>
              </ul>
              
              <div class="mt-4 flex flex-wrap justify-between pt-4 border-t border-slate-100 gap-3">
                <button @click="addHeading" class="inline-flex items-center gap-1.5 text-sm font-semibold text-indigo-600 hover:text-indigo-800 transition-colors px-3 py-2 bg-indigo-50 hover:bg-indigo-100 rounded-xl border border-indigo-100">
                  <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" /></svg>
                  Encabezado
                </button>
                <div class="flex gap-2">
                  <button @click="addFAQTemplate" class="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition-colors px-3 py-2 bg-indigo-50 hover:bg-indigo-100 rounded-xl border border-indigo-100">
                    <span>+ FAQ</span>
                  </button>
                  <button @click="addProsConsTemplate" class="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition-colors px-3 py-2 bg-indigo-50 hover:bg-indigo-100 rounded-xl border border-indigo-100">
                    <span>+ Pros/Contras</span>
                  </button>
                </div>
              </div>
              </div>

              <!-- Suggested Links Card -->
              <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col items-stretch shrink-0 ring-1 ring-slate-900/5">
                <h2 class="text-lg font-bold text-slate-900 mb-1 flex items-center gap-2">
                  <svg class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
                  Enlaces Sugeridos
                </h2>
                <p class="text-sm text-slate-500 mb-6">La IA sugiere incluir estos enlaces relevantes como referencia en el artículo.</p>
                
                <ul class="space-y-3 pr-2">
                  <li v-for="link in suggestedLinks" :key="link.id" class="group flex items-start gap-3 bg-white border border-slate-200 rounded-xl p-3 hover:border-slate-300 hover:bg-slate-50/50 transition-all" :class="{'opacity-60 bg-slate-50': !link.included}">
                    
                    <!-- Checkbox -->
                    <div class="flex items-center shrink-0 mt-0.5">
                      <input type="checkbox" v-model="link.included" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-slate-400 transition duration-150 cursor-pointer" />
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
                      <input v-model="newLinkTitle" @keydown.enter="addLink" type="text" placeholder="Título del enlace..." class="block w-full rounded-lg border-0 py-2 px-3 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-slate-400 sm:text-xs">
                      <input v-model="newLinkUrl" @keydown.enter="addLink" type="text" placeholder="https://..." class="block w-full rounded-lg border-0 py-2 px-3 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-slate-400 sm:text-xs">
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
                    class="block w-full rounded-xl border-0 py-2.5 px-3 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-200 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-slate-400 sm:text-sm"
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
                  <input type="range" v-model="toneValue" min="0" max="100" class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer mb-2 focus:outline-none focus:outline-none focus:ring-2 focus:ring-slate-400 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:w-4 [&::-webkit-slider-thumb]:h-4 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-indigo-600 [&::-moz-range-thumb]:w-4 [&::-moz-range-thumb]:h-4 [&::-moz-range-thumb]:border-0 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:bg-indigo-600" />
                  <div class="flex justify-between text-[10px] uppercase font-bold text-slate-400">
                    <span>Prof.</span>
                    <span>Cercano</span>
                    <span>Viral</span>
                  </div>
                </div>

                <!-- Length -->
                <div>
                  <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Longitud</label>
                  <select v-model="articleLength" class="block w-full rounded-xl border-0 py-2.5 pl-3 pr-10 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-200 focus:outline-none focus:ring-2 focus:ring-slate-400 sm:text-sm sm:leading-6 cursor-pointer bg-white">
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
                    <input type="checkbox" v-model="includeLists" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-slate-400 transition duration-150 ease-in-out cursor-pointer" />
                  </label>
                  <label class="flex items-center justify-between cursor-pointer group">
                    <span class="text-sm font-medium text-slate-700 group-hover:text-slate-900 transition-colors">Incluir tablas de datos</span>
                    <input type="checkbox" v-model="includeTables" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-slate-400 transition duration-150 ease-in-out cursor-pointer" />
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
              
              <div class="p-6 border-t border-slate-100 bg-slate-50 shrink-0 flex justify-between gap-3">
                <button @click="goNext(1)" class="inline-flex items-center justify-center rounded-xl bg-white border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-600 shadow-sm hover:bg-slate-50 transition-all focus:ring-2 focus:ring-offset-2 focus:ring-slate-400">
                  <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                  Volver
                </button>
                <button 
                  @click="handleGenerateArticle" 
                  :disabled="generateLoading"
                  class="group flex-1 inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition-all focus:ring-2 focus:ring-offset-2 focus:ring-slate-400 disabled:opacity-75 disabled:cursor-not-allowed"
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
            
            <div class="flex items-center gap-3">
              <button @click="goNext(2)" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-semibold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors focus:outline-none focus:ring-2 focus:ring-slate-400">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                Volver
              </button>

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
            </div>

            <!-- Toggle Switch -->
            <div class="flex bg-slate-100 p-1 rounded-xl w-full max-w-xs relative isolate">
              <button @click="viewMode = 'editor'" class="flex-1 py-1.5 text-sm font-semibold rounded-lg z-10 transition-colors" :class="viewMode === 'editor' ? 'text-indigo-700' : 'text-slate-500 hover:text-slate-700'">Editor</button>
              <button @click="viewMode = 'demo'" class="flex-1 py-1.5 text-sm font-semibold rounded-lg z-10 transition-colors" :class="viewMode === 'demo' ? 'text-indigo-700' : 'text-slate-500 hover:text-slate-700'">Vista Previa</button>
              <div class="absolute inset-y-1 left-1 w-[calc(50%-4px)] bg-white rounded-lg shadow-sm transition-transform duration-300 ease-out z-0" :class="viewMode === 'demo' ? 'translate-x-[100%]' : 'translate-x-0'"></div>
            </div>

            <!-- Actions (Version / Export) -->
            <div class="flex items-center gap-2">
              <button class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-semibold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors focus:outline-none focus:ring-2 focus:ring-slate-400">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                Historial
              </button>
              <button class="inline-flex items-center gap-2 px-4 py-2 text-sm font-bold text-white bg-indigo-600 border border-transparent rounded-xl shadow-sm hover:bg-indigo-500 transition-colors focus:ring-2 focus:ring-offset-2 focus:ring-slate-400">
                Publicar Vía API
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 2.75a.75.75 0 00-1.5 0v8.614L6.295 8.235a.75.75 0 10-1.09 1.03l4.25 4.5a.75.75 0 001.09 0l4.25-4.5a.75.75 0 00-1.09-1.03l-2.955 3.129V2.75z" /><path d="M3.5 12.75a.75.75 0 00-1.5 0v2.5A2.75 2.75 0 004.75 18h10.5A2.75 2.75 0 0018 15.25v-2.5a.75.75 0 00-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5z" /></svg>
              </button>
            </div>
          </div>

          <!-- Workplace Area -->
          <div class="flex flex-col lg:flex-row gap-6 min-h-[600px] lg:h-[calc(100vh-14rem)]">
            
            <!-- Main Content Area -->
            <div class="flex-1 flex flex-col gap-6">
              <!-- EDITOR VIEW -->
              <div v-show="viewMode === 'editor'" class="flex-1 bg-[#1e1e1e] rounded-2xl shadow-xl border border-slate-800 flex flex-col overflow-hidden animate-fade-in h-full">
                <div class="px-4 py-2 bg-[#2d2d2d] border-b border-[#404040] flex items-center justify-between">
                  <div class="flex items-center gap-4">
                    <span class="text-xs font-mono text-slate-400">borrador.md</span>
                    <div class="h-4 w-px bg-slate-700"></div>
                    <div class="flex items-center gap-2">
                       <button class="text-[10px] font-bold text-slate-400 hover:text-white transition-colors uppercase tracking-widest px-2 py-1 rounded hover:bg-white/5 border border-transparent hover:border-slate-700">✨ Simplificar</button>
                       <button class="text-[10px] font-bold text-slate-400 hover:text-white transition-colors uppercase tracking-widest px-2 py-1 rounded hover:bg-white/5 border border-transparent hover:border-slate-700">🚀 Viralizar</button>
                       <button class="text-[10px] font-bold text-slate-400 hover:text-white transition-colors uppercase tracking-widest px-2 py-1 rounded hover:bg-white/5 border border-transparent hover:border-slate-700">⚙️ +Técnico</button>
                    </div>
                  </div>
                  <span class="text-[10px] bg-indigo-500/20 text-indigo-300 px-2 py-0.5 rounded uppercase font-bold tracking-widest flex items-center gap-1">
                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14.5v-9l6 4.5-6 4.5z"/></svg>
                    Live Engine
                  </span>
                </div>
                <textarea
                  class="flex-1 w-full h-full bg-transparent text-[#d4d4d4] font-mono text-sm p-6 resize-none outline-none leading-relaxed"
                  spellcheck="false"
                  v-model="generatedMarkdown"
                ></textarea>
              </div>

              <!-- DEMO VIEW -->
              <div v-show="viewMode === 'demo'" class="flex-1 bg-white rounded-2xl shadow-xl border border-slate-100 overflow-y-auto animate-fade-in relative group p-8 sm:p-12 h-full">
                <div class="absolute top-4 right-4 opacity-0 group-hover:opacity-100 transition-opacity">
                  <span class="bg-indigo-100 text-indigo-700 text-xs px-2 py-1 rounded font-semibold whitespace-nowrap shadow-sm border border-indigo-200">✨ Vista Previa Lectura</span>
                </div>
                
                <article v-html="renderedMarkdown" class="prose prose-slate prose-indigo max-w-none prose-headings:font-extrabold prose-h1:text-4xl sm:prose-h1:text-5xl prose-p:text-lg prose-p:leading-relaxed prose-li:text-lg prose-img:rounded-2xl"></article>
              </div>
            </div>

            <!-- SEO & Snippet Sidebar -->
            <div class="w-full lg:w-80 flex flex-col gap-6 shrink-0 h-full overflow-y-auto pr-1">
              
              <!-- Google Snippet Simulator -->
              <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-4 border-b border-slate-100 bg-slate-50 flex items-center gap-2">
                  <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14.5v-9l6 4.5-6 4.5z"/></svg>
                  <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest">Vista previa Google</h3>
                </div>
                <div class="p-5 font-sans space-y-1">
                  <div class="text-[14px] text-slate-600 line-clamp-1">https://tusitio.es › noticias</div>
                  <div class="text-[20px] text-[#1a0dab] hover:underline cursor-pointer leading-tight line-clamp-2">{{ googleTitle }}</div>
                  <div class="text-[14px] text-[#4d5156] leading-relaxed line-clamp-3">
                    <span class="text-slate-500">hace 2 horas — </span>{{ googleSnippet }}
                  </div>
                </div>
              </div>

              <!-- SEO Verification Checklist -->
              <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex-1">
                <div class="p-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
                  <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest">Verificación SEO</h3>
                  <span class="text-xs font-bold text-indigo-600">{{ seoScore }}%</span>
                </div>
                <div class="p-5 space-y-4">
                  <div v-for="item in seoAnalysis" :key="item.label" class="flex items-start gap-3">
                    <div class="mt-0.5 shrink-0">
                      <svg v-if="item.value" class="h-5 w-5 text-emerald-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                      <svg v-else class="h-5 w-5 text-slate-300" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" /></svg>
                    </div>
                    <span class="text-xs font-medium" :class="item.value ? 'text-slate-800' : 'text-slate-400'">{{ item.label }}</span>
                  </div>
                  
                  <!-- Smart Tips -->
                  <div class="mt-6 pt-6 border-t border-slate-100">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Consejo de IA</div>
                    <div class="bg-indigo-50 rounded-xl p-3 border border-indigo-100">
                      <p class="text-[11px] text-indigo-700 leading-relaxed font-medium">
                        {{ seoScore < 90 ? 'Añade más palabras clave secundarias para mejorar la autoridad temática.' : '¡Excelente trabajo! El artículo cumple con todos los estándares SEO recomendados.' }}
                      </p>
                    </div>
                  </div>
                </div>
              </div>

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