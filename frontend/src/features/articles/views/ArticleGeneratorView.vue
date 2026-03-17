<script lang="ts" setup>
import { ref, computed, watch, onUnmounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useArticleStore } from '../../articles/store/articleStore'
import { 
  useSuggestTopics, 
  useUrlScraping, 
  useGenerateOutline, 
  useGenerateArticle,
  useRegenerateSection
} from '../../articles/composables'
import { marked } from 'marked'
import DOMPurify from 'dompurify'
import MarkdownEditor from '../components/MarkdownEditor.vue'

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
  articleLength,
  includeLists,
  includeTables,
  outlineList,
  suggestedLinks,
  uploadedImages
} = storeToRefs(store)
const { mutateAsync: suggestTopics, isPending: suggestTopicsLoading } = useSuggestTopics()
const { mutateAsync: scrapeUrl, isPending: isScraping } = useUrlScraping()
const { mutateAsync: generateOutline, isPending: architectLoading } = useGenerateOutline()
const { mutateAsync: generateArticle, isPending: generateLoading } = useGenerateArticle()
const { mutateAsync: regenerateSection } = useRegenerateSection()

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

// Step 3: View & Editor
const showVersionHistory = ref(false)
const previewViewMode = ref<'rendered' | 'html'>('rendered')
const isCopied = ref(false)

// Regeneration State
const isRegenerating = ref(false)
const showRegenInput = ref(false)
const regenGuidelines = ref('')
const selectedText = ref('')
const selectionIndices = ref({ start: 0, end: 0 })
const newVersion = ref('')
const isReviewingRegen = ref(false)

const showSaveDropdown = ref(false)

function downloadArticle(format: 'markdown' | 'html') {
  let content = generatedMarkdown.value
  let filename = blogTitle.value.trim() || 'articulo-generado'
  
  // Sanitize filename
  filename = filename.toLowerCase()
    .normalize("NFD").replace(/[\u0300-\u036f]/g, "") // Remove accents
    .replace(/[^a-z0-9]/g, '-')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '')
  
  if (format === 'html') {
    // Wrap in a basic HTML structure if it's HTML format
    const htmlContent = marked.parse(content)
    content = `<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>${blogTitle.value}</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; max-width: 800px; margin: 40px auto; padding: 20px; color: #333; }
        img { max-width: 100%; height: auto; border-radius: 8px; }
        h1, h2, h3 { color: #111; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto; }
        code { font-family: monospace; background: #f4f4f4; padding: 2px 4px; border-radius: 3px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #f8f8f8; }
        details { border: 1px solid #eee; padding: 10px; border-radius: 8px; margin: 20px 0; }
        summary { font-weight: bold; cursor: pointer; }
    </style>
</head>
<body>
    ${htmlContent}
</body>
</html>`
  }
  
  const blob = new Blob([content], { type: 'text/plain' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `${filename}.txt`
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
  
  showSaveDropdown.value = false
}
const markdownEditor = ref<HTMLTextAreaElement | null>(null)
const editorType = ref<'markdown' | 'visual'>('markdown')

const renderedHtml = computed(() => {
  const rawHtml = marked.parse(generatedMarkdown.value) as string
  return DOMPurify.sanitize(rawHtml, {
    ADD_ATTR: ['src'],
    ALLOWED_URI_REGEXP: /^(?:(?:(?:f|ht)tps?|mailto|tel|callto|cid|xmpp|data):|[^a-z]|[a-z+.\-]+(?:[^a-z+.\-:]|$))/i
  }).replace(/<details>/g, '<details open>')
})

function copyToClipboard() {
  const content = previewViewMode.value === 'rendered' ? generatedMarkdown.value : renderedHtml.value
  
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(content).then(() => {
      handleCopySuccess()
    }).catch(err => {
      console.error('Clipboard error:', err)
      fallbackCopyTextToClipboard(content)
    })
  } else {
    fallbackCopyTextToClipboard(content)
  }
}

function handleCopySuccess() {
  isCopied.value = true
  setTimeout(() => {
    isCopied.value = false
  }, 2000)
}

function fallbackCopyTextToClipboard(text: string) {
  const textArea = document.createElement("textarea")
  textArea.value = text
  
  // Ensure the textarea is not visible
  textArea.style.position = "fixed"
  textArea.style.left = "-9999px"
  textArea.style.top = "0"
  document.body.appendChild(textArea)
  textArea.focus()
  textArea.select()

  try {
    const successful = document.execCommand('copy')
    if (successful) {
      handleCopySuccess()
    } else {
      console.error('No se pudo copiar el texto.')
    }
  } catch (err) {
    console.error('Fallback copy error:', err)
  }

  document.body.removeChild(textArea)
}

// Regeneration Logic
function handleRegenerateClick() {
  if (!markdownEditor.value) return

  const start = markdownEditor.value.selectionStart
  const end = markdownEditor.value.selectionEnd
  const text = generatedMarkdown.value.substring(start, end).trim()

  if (!text) {
    errorMessage.value = "⚠️ Por favor, selecciona el texto que deseas regenerar en el editor."
    setTimeout(() => { errorMessage.value = null }, 3000)
    return
  }

  selectedText.value = text
  selectionIndices.value = { start, end }
  showRegenInput.value = true
}

async function confirmRegeneration() {
  if (!regenGuidelines.value.trim() || isRegenerating.value) return

  isRegenerating.value = true
  try {
    const response = await regenerateSection({
      articleTitle: blogTitle.value,
      sectionHeading: "Sección seleccionada",
      currentContent: selectedText.value,
      guidelines: regenGuidelines.value
    })
    
    newVersion.value = response.content
    isReviewingRegen.value = true
    showRegenInput.value = false
    regenGuidelines.value = ''
  } catch (error: any) {
    console.error("Regeneration failed", error)
    errorMessage.value = "❌ Error al regenerar la sección. Intenta de nuevo."
    setTimeout(() => { errorMessage.value = null }, 5000)
  } finally {
    isRegenerating.value = false
  }
}

function acceptRegen() {
  const { start, end } = selectionIndices.value
  generatedMarkdown.value = 
    generatedMarkdown.value.substring(0, start) + 
    newVersion.value + 
    generatedMarkdown.value.substring(end)
  
  cancelRegen()
}

function cancelRegen() {
  isReviewingRegen.value = false
  showRegenInput.value = false
  newVersion.value = ''
  selectedText.value = ''
}

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
  <div :class="['bg-background text-text font-sans flex flex-col', currentStep === 3 ? 'h-screen overflow-hidden' : 'min-h-screen pb-20']">
    <!-- Top Navigation -->
    <nav class="bg-white sticky top-0 z-40 shadow-sm border-b border-secondary/10 shrink-0">
      <div class="px-6 sm:px-12 lg:px-16 w-full">
        <div class="flex flex-col sm:flex-row justify-between items-center h-auto sm:h-16 py-4 sm:py-0 gap-4">
          <div class="flex items-center gap-3">
            <div class="h-8 w-8 rounded-lg bg-gradient-to-br from-primary to-primary flex items-center justify-center text-white font-bold">N</div>
            <span class="text-xl font-extrabold bg-clip-text text-transparent bg-gradient-to-r from-primary to-primary tracking-tight">
              NewsGen
            </span>
          </div>
          
          <!-- Stepper indicator -->
          <div class="flex items-center space-x-2 sm:space-x-4 text-xs sm:text-sm font-semibold text-secondary bg-secondary/10 p-1.5 rounded-full z-10">
             <button @click="currentStep = 1" class="flex items-center px-4 py-1.5 rounded-full transition-all" :class="currentStep === 1 ? 'bg-white shadow-sm text-primary' : 'hover:text-text'">1. Definición</button>
             <svg class="h-4 w-4 text-secondary/30" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
             <button @click="currentStep = 2" class="flex items-center px-4 py-1.5 rounded-full transition-all" :class="currentStep === 2 ? 'bg-white shadow-sm text-primary' : 'hover:text-text'">2. Diseño</button>
             <svg class="h-4 w-4 text-secondary/30" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
             <button @click="currentStep = 3" class="flex items-center px-4 py-1.5 rounded-full transition-all" :class="currentStep === 3 ? 'bg-white shadow-sm text-primary' : 'hover:text-text'">3. Revisar</button>
          </div>
        </div>
      </div>
      
      <!-- Colored Progress Bar -->
      <div class="absolute bottom-[-1px] left-0 h-0.5 w-full bg-secondary/10">
        <div 
          class="h-full bg-primary transition-all duration-500 ease-out" 
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
    <main :class="['transition-all duration-300 flex-1 flex flex-col min-h-0', currentStep === 3 ? 'px-6 sm:px-10 w-full' : 'mt-8 sm:mt-12 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-20 overflow-visible']">
      
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
            <h1 class="text-4xl font-extrabold tracking-tight text-text sm:text-5xl mb-4">¿De qué trata la noticia?</h1>
            <p class="text-lg text-secondary">Agrega el título o pega URLs de referencia abajo. La IA lo organizará por ti.</p>
          </div>

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

                <!-- Puntos Clave Input -->
                <div class="pt-6 border-t border-secondary/10">
                  <div class="mb-4">
                    <label for="puntos-clave" class="block text-xs font-bold text-secondary uppercase tracking-widest mb-1">Puntos clave <span class="normal-case font-medium text-secondary/60">(Opcional)</span></label>
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
                  
                  <!-- Tags List Inline -->
                  <div class="flex flex-wrap gap-2" v-if="keyPoints.length > 0">
                    <span v-for="(point, index) in keyPoints" :key="index" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium bg-primary/10 text-primary border border-primary/20">
                      {{ point }}
                      <button @click="removeKeyPoint(point)" class="text-primary/60 hover:text-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary rounded-full p-0.5" title="Eliminar punto clave">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
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
            <aside class="lg:col-span-1 h-full">
              <div class="bg-white rounded-2xl shadow-sm border border-secondary/10 p-6 lg:sticky lg:top-24 flex flex-col h-full min-h-[500px]">
                <div class="flex items-center gap-2 pb-4 border-b border-secondary/10 shrink-0">
                  <svg class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                  </svg>
                  <h3 class="font-bold text-text">Preferencias</h3>
                </div>

                <div class="flex-1 flex flex-col pt-8 overflow-y-auto pr-2 custom-scrollbar">
                  <!-- Keywords moved from Step 2 -->
                  <div class="mb-8">
                    <label class="block text-xs font-bold text-secondary uppercase tracking-widest mb-3">Etiquetas / Keywords</label>
                    <div class="flex flex-wrap gap-2 mb-3" v-if="tags.length > 0">
                      <span v-for="tag in tags" :key="tag" class="inline-flex items-center gap-1.5 rounded-lg bg-secondary/10 px-2.5 py-1.5 text-xs font-semibold text-text shadow-sm border border-secondary/20 transition-all hover:bg-secondary/20">
                        {{ tag }}
                        <button @click="removeTag(tag)" class="-mr-1 text-secondary/40 hover:text-red-500 rounded-full hover:bg-secondary/30 transition-colors p-0.5" title="Eliminar etiqueta">
                           <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
                        </button>
                      </span>
                    </div>
                    <div class="relative group group-focus-within:ring-2 group-focus-within:ring-primary rounded-xl overflow-hidden shadow-sm ring-1 ring-inset ring-secondary/20">
                      <input
                        v-model="newTag"
                        @keydown.enter.prevent="addTag"
                        type="text"
                        class="block w-full border-0 py-3 px-4 pr-10 text-text placeholder:text-secondary/40 focus:outline-none focus:ring-0 text-sm bg-white"
                        placeholder="Añadir y pulsar Enter..."
                      />
                      <button @click="addTag" class="absolute right-1 top-1.5 h-8 w-8 flex items-center justify-center text-secondary/30 hover:text-primary transition-colors hover:bg-primary/5 rounded-lg">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" /></svg>
                      </button>
                    </div>
                  </div>

                  <div class="space-y-8">
                    <div>
                      <label for="audience" class="block text-xs font-bold text-secondary uppercase tracking-widest mb-3">Público Objetivo</label>
                      <select
                        id="audience"
                        v-model="audience"
                        class="block w-full rounded-xl border-0 py-3 px-4 text-text shadow-sm ring-1 ring-inset ring-secondary/10 focus:outline-none focus:ring-2 focus:ring-primary sm:text-sm bg-secondary/5"
                      >
                        <option value="General">General</option>
                        <option value="Principiantes">Principiantes</option>
                        <option value="Expertos">Profesionales</option>
                        <option value="Estudiantes">Académico</option>
                        <option value="Técnico">Perfil Técnico</option>
                      </select>
                    </div>

                    <div>
                      <label for="intent" class="block text-xs font-bold text-secondary uppercase tracking-widest mb-3">Intención de Búsqueda</label>
                      <select
                        id="intent"
                        v-model="searchIntent"
                        class="block w-full rounded-xl border-0 py-3 px-4 text-text shadow-sm ring-1 ring-inset ring-secondary/10 focus:outline-none focus:ring-2 focus:ring-primary sm:text-sm bg-secondary/5"
                      >
                        <option value="Informativo">Informativo</option>
                        <option value="Tutorial">Tutorial</option>
                        <option value="Transaccional">Transaccional</option>
                        <option value="Comparativo">Comparativo</option>
                      </select>
                    </div>
                  </div>
                </div>

                <div class="mt-auto pt-6 border-t border-secondary/10 flex flex-col items-stretch gap-4">
                  <div class="w-full bg-primary/5 rounded-xl p-4 border border-primary/10">
                    <p class="text-[10px] text-secondary leading-relaxed font-medium">
                      <span class="text-primary font-bold uppercase tracking-tighter mr-1">Pro Tip:</span> 
                      Estos ajustes permiten a la IA optimizar la complejidad del lenguaje y el formato de la noticia.
                    </p>
                  </div>

                  <button 
                    @click="handleProceedToArchitect" 
                    :disabled="architectLoading"
                    class="group w-full inline-flex items-center justify-center rounded-xl bg-primary px-6 py-4 text-sm font-semibold text-white shadow-sm hover:bg-primary/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary transition-all duration-200 disabled:opacity-75 disabled:cursor-not-allowed"
                  >
                    <div v-if="architectLoading" class="flex items-center gap-2">
                      <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                      Generando...
                    </div>
                    <div v-else class="flex items-center">
                      Continuar a Diseño
                      <svg class="ml-2 -mr-1 h-5 w-5 group-hover:translate-x-1 transition-transform" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 10a.75.75 0 01.75-.75h10.638L10.23 5.29a.75.75 0 111.04-1.08l5.5 5.25a.75.75 0 010 1.08l-5.5 5.25a.75.75 0 11-1.04-1.08l4.158-3.96H3.75A.75.75 0 013 10z" clip-rule="evenodd" /></svg>
                    </div>
                  </button>
                </div>
              </div>
            </aside>
          </div>
        </div>
        <!-- STEP 2: THE ARCHITECT -->
        <div v-else-if="currentStep === 2" class="space-y-8" key="step2">
          <div class="text-center max-w-2xl mx-auto mb-10">
            <h1 class="text-4xl font-extrabold tracking-tight text-text sm:text-5xl mb-4">Diseño del Artículo</h1>
            <p class="text-lg text-secondary">Ajusta la estructura y configuración antes de generar el borrador.</p>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            
            <!-- Left col: Interactive Outline and Links -->
            <div class="lg:col-span-3 flex flex-col gap-6">
              
              <!-- Outline Card -->
              <div class="bg-white rounded-2xl shadow-sm border border-secondary/10 p-6 flex flex-col items-stretch shrink-0 ring-1 ring-text/5">
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
                    :class="{'opacity-50 bg-secondary/5': !item.included, 'border-dashed border-secondary/30 bg-secondary/5 opacity-40': draggedItemIndex === index}">
                  
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
                      :class="{'line-through text-secondary': !item.included}" 
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
              
              <div class="mt-4 flex flex-wrap justify-between pt-4 border-t border-secondary/10 gap-3">
                <button @click="addHeading" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:text-primary px-3 py-2 bg-primary/10 hover:bg-primary/20 rounded-xl border border-primary/10">
                  <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" /></svg>
                  Encabezado
                </button>
                <div class="flex gap-2">
                  <button @click="addFAQTemplate" class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:text-primary px-3 py-2 bg-primary/10 hover:bg-primary/20 rounded-xl border border-primary/10">
                    <span>+ FAQ</span>
                  </button>
                  <button @click="addProsConsTemplate" class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:text-primary px-3 py-2 bg-primary/10 hover:bg-primary/20 rounded-xl border border-primary/10">
                    <span>+ Pros/Contras</span>
                  </button>
                </div>
              </div>
              </div>

              <!-- Suggested Links Card -->
              <div class="bg-white rounded-2xl shadow-sm border border-secondary/10 p-6 flex flex-col items-stretch shrink-0 ring-1 ring-text/5">
                <h2 class="text-lg font-bold text-text mb-1 flex items-center gap-2">
                  <svg class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
                  Enlaces Sugeridos
                </h2>
                <p class="text-sm text-secondary mb-6">La IA sugiere incluir estos enlaces relevantes como referencia en el artículo.</p>
                
                <ul class="space-y-3 pr-2">
                  <li v-for="link in suggestedLinks" :key="link.id" class="group flex items-start gap-3 bg-white border border-secondary/20 rounded-xl p-3 hover:border-secondary/30 hover:bg-secondary/5 transition-all" :class="{'opacity-60 bg-secondary/5': !link.included}">
                    
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
                          :class="{'line-through text-secondary': !link.included}" 
                        />
                      </div>
                      <input 
                        type="text" 
                        v-model="link.url" 
                        class="text-xs text-secondary w-full border-0 border-b border-transparent focus:border-primary focus:ring-0 bg-transparent px-1 py-0.5 transition-all truncate" 
                        :class="{'line-through': !link.included}" 
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
            </div>

            <!-- Right col: Settings Config Panel -->
            <div class="bg-white rounded-2xl shadow-lg border border-secondary/20 flex flex-col lg:h-[calc(100vh-10rem)] lg:sticky lg:top-24 overflow-hidden lg:col-span-1">
              <div class="p-4 border-b border-secondary/10 bg-background flex items-center gap-2 z-10 shrink-0">
                <svg class="h-5 w-5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                <h3 class="font-bold text-text">Ajustes del Artículo</h3>
              </div>
              
              <div class="flex-1 p-5 overflow-y-auto space-y-8">
                

                <!-- Tone Slider -->
                <div>
                  <label class="block text-xs font-bold text-secondary uppercase tracking-wider mb-3">
                    Tono: 
                    <span class="text-primary">
                      {{ toneValue < 33 ? 'Profesional' : toneValue < 66 ? 'Cercano' : 'Viral/Audaz' }}
                    </span>
                  </label>
                  <input type="range" v-model.number="toneValue" min="0" max="100" class="w-full h-2 bg-secondary/20 rounded-lg appearance-none cursor-pointer mb-2 focus:outline-none focus:ring-2 focus:ring-primary [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:w-4 [&::-webkit-slider-thumb]:h-4 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-primary [&::-moz-range-thumb]:w-4 [&::-moz-range-thumb]:h-4 [&::-moz-range-thumb]:border-0 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:bg-primary" />
                  <div class="flex justify-between text-[10px] uppercase font-bold text-secondary/40">
                    <span>Prof.</span>
                    <span>Cercano</span>
                    <span>Viral</span>
                  </div>
                </div>

                <!-- Length -->
                <div>
                  <label class="block text-xs font-bold text-secondary uppercase tracking-wider mb-2">Longitud</label>
                  <select v-model="articleLength" class="block w-full rounded-xl border-0 py-2.5 pl-3 pr-10 text-text shadow-sm ring-1 ring-inset ring-secondary/20 focus:outline-none focus:ring-2 focus:ring-primary sm:text-sm sm:leading-6 cursor-pointer bg-white">
                    <option value="short">Corto (~500 palabras)</option>
                    <option value="medium">Mediano (~1000 palabras)</option>
                    <option value="long">Largo (~2000 palabras)</option>
                  </select>
                </div>

                <!-- Checkboxes / Toggles for tables and lists -->
                <div class="space-y-3">
                  <label class="block text-xs font-bold text-secondary uppercase tracking-wider mb-2">Elementos Adicionales</label>
                  <label class="flex items-center justify-between cursor-pointer group">
                    <span class="text-sm font-medium text-text group-hover:text-primary transition-colors">Incluir listas (viñetas)</span>
                    <input type="checkbox" v-model="includeLists" class="h-4 w-4 rounded border-secondary/30 text-primary focus:ring-secondary/40 transition duration-150 ease-in-out cursor-pointer" />
                  </label>
                  <label class="flex items-center justify-between cursor-pointer group">
                    <span class="text-sm font-medium text-text group-hover:text-primary transition-colors">Incluir tablas de datos</span>
                    <input type="checkbox" v-model="includeTables" class="h-4 w-4 rounded border-secondary/30 text-primary focus:ring-secondary/40 transition duration-150 ease-in-out cursor-pointer" />
                  </label>
                </div>


              </div>
              
              <div class="p-6 border-t border-secondary/10 bg-background shrink-0 flex justify-between gap-3">
                <button @click="goNext(1)" class="inline-flex items-center justify-center rounded-xl bg-white border border-secondary/20 px-5 py-3 text-sm font-semibold text-secondary hover:bg-secondary/5 transition-all focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                  <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                  Volver
                </button>
                <button 
                  @click="handleGenerateArticle" 
                  :disabled="generateLoading"
                  class="group flex-1 inline-flex items-center justify-center rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-primary/90 transition-all focus:ring-2 focus:ring-offset-2 focus:ring-primary disabled:opacity-75 disabled:cursor-not-allowed"
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
        <!-- STEP 3: MINIMALIST EDITOR & PREVIEW -->
        <div v-else-if="currentStep === 3" class="flex-1 flex flex-col min-h-0 bg-white rounded-2xl shadow-xl border border-secondary/10 overflow-hidden" key="step3">
          
          <!-- Top Tool Bar -->
          <div class="bg-background border-b border-secondary/10 p-4 flex justify-between items-center shrink-0">
            <div class="flex items-center gap-4">
              <button @click="goNext(2)" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-secondary bg-white border border-secondary/20 rounded-lg hover:bg-secondary/5 transition-colors">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                Volver
              </button>
              <div class="relative">
                <button 
                  @click="showSaveDropdown = !showSaveDropdown"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-accent bg-accent/5 border border-accent/20 rounded-lg hover:bg-accent/10 transition-colors"
                >
                  <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg>
                  Guardar
                </button>
                
                <!-- Dropdown -->
                <div v-if="showSaveDropdown" class="absolute left-0 mt-2 w-48 bg-white rounded-xl shadow-2xl border border-secondary/10 py-2 z-50">
                  <div class="px-3 py-2 border-b border-secondary/5 mb-1">
                    <span class="text-[10px] font-bold text-secondary/40 uppercase tracking-widest">Descargar como</span>
                  </div>
                  <button 
                    @click="downloadArticle('markdown')"
                    class="w-full text-left px-4 py-2 text-xs font-semibold text-text hover:bg-primary/5 hover:text-primary transition-colors flex items-center gap-2"
                  >
                    <svg class="h-3.5 w-3.5 text-secondary/40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    Markdown (.txt)
                  </button>
                  <button 
                    @click="downloadArticle('html')"
                    class="w-full text-left px-4 py-2 text-xs font-semibold text-text hover:bg-primary/5 hover:text-primary transition-colors flex items-center gap-2"
                  >
                    <svg class="h-3.5 w-3.5 text-secondary/40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" /></svg>
                    HTML (.txt)
                  </button>
                </div>

                <!-- Backdrop for closing dropdown -->
                <div v-if="showSaveDropdown" @click="showSaveDropdown = false" class="fixed inset-0 z-40"></div>
              </div>
              <div class="h-6 w-px bg-secondary/10"></div>
            </div>

            <div class="flex items-center gap-3">
              <button class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-secondary bg-white border border-secondary/20 rounded-lg hover:bg-secondary/5 transition-colors">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                Historial
              </button>
            </div>
          </div>

          <!-- Dual Pane Workplace Area -->
          <div class="flex-1 flex items-stretch overflow-hidden min-h-0">
            
            <!-- EDITOR PANE -->
            <div class="flex-1 flex flex-col min-w-0 min-h-0 border-r border-secondary/10 bg-slate-50">
              <div class="h-11 px-4 bg-background border-b border-secondary/10 flex items-center justify-between shrink-0">
                <span class="text-[10px] font-black text-secondary/40 uppercase tracking-widest leading-none">Editor de Texto</span>
                <div class="flex items-center gap-2">
                  <select 
                    v-model="editorType"
                    class="bg-white border border-secondary/20 rounded px-2 py-0.5 text-[10px] font-bold text-secondary focus:ring-0 focus:outline-none cursor-pointer mr-2"
                  >
                    <option value="markdown">Markdown</option>
                    <option value="visual">Vista Visual (Milkdown)</option>
                  </select>
                  <button 
                    v-if="editorType === 'markdown'"
                    @click="handleRegenerateClick"
                    class="flex items-center gap-1.5 px-2 py-1 text-[9px] font-bold text-primary bg-primary/5 border border-primary/20 rounded hover:bg-primary/10 transition-colors"
                  >
                    ✨ Regenerar Selección
                  </button>
                  <span class="text-[9px] font-mono text-secondary/40">borrador.md</span>
                </div>
              </div>

              <!-- Regeneration Input Area -->
              <transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-2"
              >
                <div v-if="showRegenInput" class="p-4 bg-primary/5 border-b border-secondary/10 flex flex-col gap-3">
                  <div class="flex items-center justify-between">
                    <label class="text-[10px] font-bold text-primary uppercase tracking-wider">Directrices para la IA</label>
                    <button @click="showRegenInput = false" class="text-secondary/40 hover:text-secondary">
                      <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                  </div>
                  <div class="flex gap-2">
                    <input 
                      v-model="regenGuidelines"
                      @keydown.enter="confirmRegeneration"
                      type="text" 
                      placeholder="Ej: hazlo más profesional, resume este punto..." 
                      class="flex-1 bg-white border border-secondary/20 rounded-lg px-3 py-2 text-xs text-text focus:ring-1 focus:ring-primary focus:outline-none"
                    />
                    <button 
                      @click="confirmRegeneration"
                      :disabled="isRegenerating"
                      class="px-4 py-2 bg-primary text-white text-xs font-bold rounded-lg hover:bg-primary/90 transition-colors disabled:opacity-50"
                    >
                      {{ isRegenerating ? 'Generando...' : 'Confirmar' }}
                    </button>
                  </div>
                </div>
              </transition>

              <!-- Diff Review Area -->
              <div v-if="isReviewingRegen" class="flex-1 overflow-hidden flex flex-col bg-white">
                <div class="px-6 py-4 border-b border-secondary/10 flex items-center justify-between bg-white shadow-sm z-10">
                  <h3 class="text-xs font-bold text-text uppercase tracking-widest">Revisar Cambios</h3>
                  <div class="flex gap-2">
                    <button @click="cancelRegen" class="px-3 py-1.5 text-xs font-bold text-secondary bg-white border border-secondary/20 rounded-lg hover:bg-secondary/5 transition-colors">
                      Descartar
                    </button>
                    <button @click="acceptRegen" class="px-3 py-1.5 text-xs font-bold text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors">
                      Aceptar Cambios
                    </button>
                  </div>
                </div>
                <div class="flex-1 flex divide-x divide-secondary/10 overflow-hidden">
                  <div class="flex-1 flex flex-col">
                    <div class="px-4 py-2 bg-red-50/50 border-b border-secondary/10 text-[10px] font-bold text-red-600 uppercase">Original</div>
                    <div class="flex-1 p-6 overflow-y-auto bg-red-50/30 text-sm font-mono text-red-800 line-through decoration-red-400 whitespace-pre-wrap leading-relaxed">{{ selectedText }}</div>
                  </div>
                  <div class="flex-1 flex flex-col">
                    <div class="px-4 py-2 bg-green-50/50 border-b border-secondary/10 text-[10px] font-bold text-green-600 uppercase">Nueva Versión</div>
                    <div class="flex-1 p-6 overflow-y-auto bg-green-50/30 text-sm font-mono text-green-800 whitespace-pre-wrap leading-relaxed">{{ newVersion }}</div>
                  </div>
                </div>
              </div>

                <div v-if="editorType === 'visual' && !isReviewingRegen" class="flex-1 min-h-0 flex flex-col">
                  <MarkdownEditor v-model="generatedMarkdown" />
                </div>

                <textarea
                  v-else-if="editorType === 'markdown' && !isReviewingRegen"
                  ref="markdownEditor"
                  v-model="generatedMarkdown"
                  class="flex-1 w-full p-8 text-sm font-mono border-0 focus:ring-0 resize-none bg-transparent text-slate-800 leading-relaxed overflow-y-auto custom-scrollbar"
                  placeholder="Escribe tu artículo aquí..."
                ></textarea>
            </div>

            <!-- PREVIEW PANE -->
            <div class="flex-1 flex flex-col min-w-0 min-h-0 bg-white">
              <div class="h-11 px-4 bg-background border-b border-secondary/10 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-4">
                  <span class="text-[10px] font-black text-secondary/40 uppercase tracking-widest leading-none">Vista Previa</span>
                  <select 
                    v-model="previewViewMode"
                    class="bg-white border border-secondary/20 rounded px-2 py-0.5 text-[10px] font-bold text-secondary focus:ring-0 focus:outline-none cursor-pointer"
                  >
                    <option value="rendered">Visualización</option>
                    <option value="html">Código HTML</option>
                  </select>
                </div>
                <div class="flex items-center gap-3">
                  <button 
                    @click="copyToClipboard"
                    :class="[
                      'flex items-center gap-1.5 px-2 py-1 text-[10px] font-bold border rounded transition-all',
                      isCopied 
                        ? 'text-green-600 bg-green-50 border-green-200' 
                        : 'text-primary bg-primary/5 border-primary/20 hover:bg-primary/10'
                    ]"
                  >
                    <svg v-if="!isCopied" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" /></svg>
                    <svg v-else class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    {{ isCopied ? '¡Copiado!' : 'Copiar' }}
                  </button>
                  <div class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-accent animate-pulse"></span>
                    <span class="text-[9px] font-bold text-accent uppercase tracking-wider">Live</span>
                  </div>
                </div>
              </div>
              <div class="flex-1 overflow-y-auto overscroll-contain p-8 sm:p-12 custom-scrollbar">
                <div 
                  v-if="previewViewMode === 'rendered'"
                  v-html="renderedHtml" 
                  class="prose prose-slate prose-indigo max-w-none prose-p:text-base prose-p:leading-relaxed prose-li:text-base prose-img:rounded-2xl shadow-none"
                ></div>
                <div v-else class="h-full">
                  <textarea
                    readonly
                    :value="renderedHtml"
                    class="w-full h-full p-4 border border-secondary/10 rounded-xl bg-slate-50 text-xs font-mono text-secondary focus:ring-0 resize-none leading-relaxed"
                  ></textarea>
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
