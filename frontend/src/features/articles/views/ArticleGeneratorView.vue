<script lang="ts" setup>
import { ref, computed } from 'vue'
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
const showSeoDetails = ref(false)
const regenGuidelines = ref<Record<number, string>>({})

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

// Parse markdown into granular blocks for interactive preview
interface MarkdownBlock {
  id: string
  type: 'h1' | 'h2' | 'h3' | 'paragraph' | 'image' | 'list' | 'other'
  content: string
  html: string
}

interface ParsedSection {
  heading: string      // e.g. "## 1. Requisitos Básicos"
  headingText: string  // e.g. "1. Requisitos Básicos"
  content: string      // body text after the heading
  html: string         // rendered body HTML
  isRegenerating: boolean
  h1Html?: string
}

const _sectionRegen = ref<Record<number, boolean>>({})

const parsedSections = computed<ParsedSection[]>(() => {
  const md = generatedMarkdown.value
  // Split by any ## heading (H2)
  const parts = md.split(/(?=^## )/m)
  return parts
    .filter(p => p.trim())
    .map((part, idx) => {
      const lines = part.split('\n')
      const firstLine = lines[0] ?? ''
      const isHeading = firstLine.startsWith('## ')
      const heading = isHeading ? firstLine : ''
      const headingText = isHeading ? heading.replace(/^##\s+/, '') : (idx === 0 ? 'Introducción' : '')
      
      let content = isHeading ? lines.slice(1).join('\n').trim() : part.trim()
      let h1Html = ''

      // Special handling for section 0 to split H1 if present
      if (idx === 0) {
        const h1Match = content.match(/^#\s+.+$/m)
        if (h1Match) {
          h1Html = DOMPurify.sanitize(marked.parse(h1Match[0]) as string)
          content = content.replace(/^#\s+.+$/m, '').trim()
        }
      }

      const rawHtml = marked.parse(content) as string
      return {
        heading,
        headingText,
        content,
        html: DOMPurify.sanitize(rawHtml, {
          ADD_ATTR: ['src'],
          ALLOWED_URI_REGEXP: /^(?:(?:(?:f|ht)tps?|mailto|tel|callto|cid|xmpp|data):|[^a-z]|[a-z+.\-]+(?:[^a-z+.\-:]|$))/i
        }).replace(/<details>/g, '<details open>'),
        isRegenerating: _sectionRegen.value[idx] ?? false,
        h1Html
      }
    })
})

const articleBlocks = computed<MarkdownBlock[]>(() => {
  const md = generatedMarkdown.value
  if (!md) return []

  // Split into blocks. We want to identify:
  // - H1 (# ...)
  // - H2 (## ...)
  // - H3 (### ...)
  // - Images (![...](...))
  // - Lists (Starting with - or * or digit.)
  // - Paragraphs (everything else)
  
  // First, let's normalize line endings
  const normalized = md.replace(/\r\n/g, '\n')
  
  // We'll split by double newline as a starting point, but we need to be careful with lists and headers
  // A better way is to split while preserving markers
  const blocks: MarkdownBlock[] = []
  
  // regex that matches:
  // 1. Headings: ^#{1,3}\s+.+$
  // 2. Images: ^!\[.*?\]\(.*?\)$
  // 3. Everything else (paragraphs, lists)
  
  // We will split by lines and group them
  const lines = normalized.split('\n')
  let currentParagraphLines: string[] = []

  const flushParagraph = () => {
    if (currentParagraphLines.length > 0) {
      const content = currentParagraphLines.join('\n').trim()
      if (content) {
        blocks.push({
          id: `block-${blocks.length}-${Math.random().toString(36).substr(2, 5)}`,
          type: content.startsWith('- ') || content.startsWith('* ') || /^\d+\.\s/.test(content) ? 'list' : 'paragraph',
          content,
          html: DOMPurify.sanitize(marked.parse(content) as string, {
            ADD_ATTR: ['src'],
            ALLOWED_URI_REGEXP: /^(?:(?:(?:f|ht)tps?|mailto|tel|callto|cid|xmpp|data):|[^a-z]|[a-z+.\-]+(?:[^a-z+.\-:]|$))/i
          }).replace(/<details>/g, '<details open>')
        })
      }
      currentParagraphLines = []
    }
  }

  for (let i = 0; i < lines.length; i++) {
    const rawLine = lines[i] || ''
    const line = rawLine.trim()
    
    if (line.startsWith('# ')) {
      flushParagraph()
      blocks.push({
        id: `block-${blocks.length}-${Math.random().toString(36).substr(2, 5)}`,
        type: 'h1',
        content: line,
        html: DOMPurify.sanitize(marked.parse(line) as string)
      })
    } else if (line.startsWith('## ')) {
      flushParagraph()
      blocks.push({
        id: `block-${blocks.length}-${Math.random().toString(36).substr(2, 5)}`,
        type: 'h2',
        content: line,
        html: DOMPurify.sanitize(marked.parse(line) as string)
      })
    } else if (line.startsWith('### ')) {
      flushParagraph()
      blocks.push({
        id: `block-${blocks.length}-${Math.random().toString(36).substr(2, 5)}`,
        type: 'h3',
        content: line,
        html: DOMPurify.sanitize(marked.parse(line) as string, {
          ADD_ATTR: ['src'],
          ALLOWED_URI_REGEXP: /^(?:(?:(?:f|ht)tps?|mailto|tel|callto|cid|xmpp|data):|[^a-z]|[a-z+.\-]+(?:[^a-z+.\-:]|$))/i
        })
      })
    } else if (/^!\[.*?\]\(.*?\)$/.test(line)) {
      flushParagraph()
      blocks.push({
        id: `block-${blocks.length}-${Math.random().toString(36).substr(2, 5)}`,
        type: 'image',
        content: line,
        html: DOMPurify.sanitize(marked.parse(line) as string, {
          ADD_ATTR: ['src'],
          ALLOWED_URI_REGEXP: /^(?:(?:(?:f|ht)tps?|mailto|tel|callto|cid|xmpp|data):|[^a-z]|[a-z+.\-]+(?:[^a-z+.\-:]|$))/i
        })
      })
    } else if (line === '') {
      flushParagraph()
    } else {
      currentParagraphLines.push(lines[i] as string)
    }
  }
  flushParagraph()

  return blocks
})

const draggedBlockIndex = ref<number | null>(null)

function onBlockDragStart(index: number) {
  draggedBlockIndex.value = index
}

function onBlockDrop(event: DragEvent, index: number) {
  if (draggedBlockIndex.value !== null) {
    const blocks = [...articleBlocks.value]
    const [removed] = blocks.splice(draggedBlockIndex.value, 1)
    if (removed) {
      blocks.splice(index, 0, removed)
      // Update generatedMarkdown by joining block contents
      generatedMarkdown.value = blocks.map(b => b.content).join('\n\n')
    }
    draggedBlockIndex.value = null
  } else {
    // Check for gallery image drop
    const data = event.dataTransfer?.getData('text/plain')
    if (data && data.startsWith('![')) {
      const blocks = [...articleBlocks.value]
      const newContents = blocks.map(b => b.content)
      newContents.splice(index, 0, data)
      generatedMarkdown.value = newContents.join('\n\n')
    }
  }
}

function removeBlock(index: number) {
  const blocks = [...articleBlocks.value]
  blocks.splice(index, 1)
  generatedMarkdown.value = blocks.map(b => b.content).join('\n\n')
}

function replaceSectionContent(sectionIdx: number, newContent: string) {
  const sections = parsedSections.value
  const updated = sections.map((s, i) => {
    if (i !== sectionIdx) return s.heading ? `${s.heading}\n${s.content}` : s.content
    return s.heading ? `${s.heading}\n${newContent}` : newContent
  })
  generatedMarkdown.value = updated.join('\n\n')
}

async function handleRegenerateSection(sectionIdx: number) {
  const section = parsedSections.value[sectionIdx]
  if (!section || _sectionRegen.value[sectionIdx]) return
  _sectionRegen.value = { ..._sectionRegen.value, [sectionIdx]: true }
  try {
    const result = await regenerateSection({
      articleTitle: blogTitle.value,
      sectionHeading: section.headingText,
      currentContent: section.content,
      context: additionalContext.value,
      guidelines: regenGuidelines.value[sectionIdx] || '',
    })
    replaceSectionContent(sectionIdx, result.content)
  } catch (e: any) {
    if (e?.message?.includes('429')) {
      errorMessage.value = "⚠️ Límite de peticiones alcanzado. Espera un momento e inténtalo de nuevo."
      setTimeout(() => { errorMessage.value = null }, 6000)
    } else {
      errorMessage.value = "❌ Error al regenerar la sección. Intenta de nuevo."
      setTimeout(() => { errorMessage.value = null }, 4000)
    }
  } finally {
    _sectionRegen.value = { ..._sectionRegen.value, [sectionIdx]: false }
  }
}

const renderedMarkdown = computed(() => {
  const rawHtml = marked.parse(generatedMarkdown.value) as string
  return DOMPurify.sanitize(rawHtml).replace(/<details>/g, '<details open>')
})

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

function handleFileDrop(event: DragEvent) {
  event.preventDefault()
  if (event.dataTransfer?.files) {
    Array.from(event.dataTransfer.files).forEach(file => {
      if (file.type.startsWith('image/')) {
        processImageFile(file)
      }
    })
  }
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

function onImageDragStart(event: DragEvent, img: { name: string, data: string }) {
  if (event.dataTransfer) {
    event.dataTransfer.setData('text/plain', `![${img.name}](${img.data})`)
    event.dataTransfer.dropEffect = 'copy'
  }
}

function onEditorDrop(event: DragEvent) {
  // If it's a drag from our gallery, the data is already set in onDragStart
  // If it's a file from outside, we could handle it too, but let's keep it simple
  // The default behavior for text/plain data is to insert it at the cursor which is what we want
}

</script>

<template>
  <div class="min-h-screen bg-background text-text pb-20 font-sans">
    <!-- Top Navigation -->
    <nav class="bg-white sticky top-0 z-40 shadow-sm border-b border-transparent">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
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
                      v-model="newTag" 
                      @keydown.enter.prevent="addTag"
                      type="text" 
                      class="block w-full rounded-xl border-0 py-3 px-4 text-text shadow-sm ring-1 ring-inset ring-secondary/20 placeholder:text-secondary/40 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary sm:text-sm"
                      placeholder="Ej. Requisitos físicos... (Presiona Enter)" 
                    />
                    <button @click="addTag" class="px-4 py-2 bg-secondary/10 hover:bg-secondary/20 text-text font-semibold rounded-xl text-sm transition-colors border border-secondary/20 shadow-sm shrink-0">
                      Añadir
                    </button>
                  </div>
                  
                  <!-- Tags List Inline -->
                  <div class="flex flex-wrap gap-2" v-if="tags.length > 0">
                    <span v-for="(tag, index) in tags" :key="index" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium bg-primary/10 text-primary border border-primary/20">
                      {{ tag }}
                      <button @click="removeTag(tag)" class="text-primary/60 hover:text-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary rounded-full p-0.5" title="Eliminar punto clave">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                      </button>
                    </span>
                  </div>
                </div>

                <!-- URL Scraper -->
                <div class="pt-6 border-t border-secondary/10">
                  <label for="reference-url" class="block text-xs font-bold text-secondary uppercase tracking-widest mb-3">URLs de Referencia</label>
                  <input
                    id="reference-url"
                    v-model="referenceUrl"
                    @keydown.enter.prevent="handleScrape"
                    type="text"
                    class="block w-full rounded-2xl border-0 py-4 px-5 text-text shadow-sm ring-1 ring-inset ring-secondary/20 placeholder:text-secondary/40 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary sm:text-sm sm:leading-relaxed transition-shadow"
                    placeholder="Pega un enlace y pulsa Enter..."
                  />
                  
                  <!-- Smart URL Scraper Feedback -->
                  <div v-if="isScraping" class="mt-4 flex items-center gap-2 text-sm text-primary font-medium bg-primary/10 p-3 rounded-lg w-fit transition-all duration-300">
                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Analizando URLs...
                  </div>
                  <div v-else-if="scrapedReferences.length > 0" class="mt-4 space-y-2 transition-all duration-300">
                    <p class="text-xs font-semibold text-secondary uppercase tracking-wider">Lectura de fuentes completada:</p>
                    <div class="flex flex-wrap gap-2">
                      <div v-for="url in scrapedReferences" :key="url.url" class="inline-flex items-center gap-2 rounded-md bg-accent/10 px-2 py-1 text-xs font-medium text-accent ring-1 ring-inset ring-accent/20">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" /></svg>
                        {{ url.title }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <div class="p-6 border-t border-secondary/10 bg-secondary/5 flex justify-end">
                <button 
                  @click="handleProceedToArchitect" 
                  :disabled="architectLoading"
                  class="group inline-flex items-center justify-center rounded-xl bg-primary px-8 py-3.5 text-sm font-semibold text-white shadow-sm hover:bg-primary/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary transition-all duration-200 disabled:opacity-75 disabled:cursor-not-allowed"
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
              <div class="bg-white rounded-2xl shadow-sm border border-secondary/10 p-6 lg:sticky lg:top-24 h-full flex flex-col">
                <div class="flex items-center gap-2 pb-4 border-b border-secondary/10 shrink-0">
                  <svg class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                  </svg>
                  <h3 class="font-bold text-text">Preferencias</h3>
                </div>

                <div class="space-y-6 pt-6">
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

                <div class="mt-6 pt-6 border-t border-secondary/10">
                  <label for="additional-context" class="block text-xs font-bold text-secondary uppercase tracking-widest mb-1">Contexto y Directrices <span class="normal-case font-medium text-secondary/60">(Opcional)</span></label>
                  <p class="text-xs text-secondary mb-3">Añade indicaciones específicas para la IA: estilo, enfoque, datos a incluir o excluir, etc.</p>
                  <textarea
                    id="additional-context"
                    v-model="additionalContext"
                    rows="4"
                    class="block w-full rounded-xl border-0 py-3 px-4 text-text shadow-sm ring-1 ring-inset ring-secondary/10 placeholder:text-secondary/40 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary text-xs resize-none transition-shadow"
                    placeholder="Ej: Tono cercano y optimista. No mencionar requisitos de edad..."
                  ></textarea>
                </div>

                <div class="mt-auto pt-6">
                  <div class="bg-primary/5 rounded-2xl p-4 border border-primary/10">
                    <p class="text-[10px] text-secondary leading-relaxed font-medium">
                      <span class="text-primary font-bold uppercase tracking-tighter mr-1">Pro Tip:</span> 
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
            <h1 class="text-4xl font-extrabold tracking-tight text-text sm:text-5xl mb-4">Diseño del Artículo</h1>
            <p class="text-lg text-secondary">Ajusta la estructura y configuración antes de generar el borrador.</p>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            
            <!-- Left col: Interactive Outline and Links -->
            <div class="lg:col-span-3 flex flex-col gap-6">
              
              <!-- Outline Card -->
              <div class="bg-white rounded-2xl shadow-sm border border-secondary/10 p-6 flex flex-col items-stretch shrink-0 ring-1 ring-text/5">
              <h2 class="text-lg font-bold text-text mb-1 flex items-center gap-2">
                <svg class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" /></svg>
                Esquema Interactivo
              </h2>
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
                
                <!-- Keywords -->
                <div>
                  <label class="block text-xs font-bold text-secondary uppercase tracking-wider mb-2">Etiquetas / Keywords</label>
                  <div class="flex flex-wrap gap-2 mb-3">
                    <span v-for="tag in tags" :key="tag" class="inline-flex items-center gap-1.5 rounded-md bg-secondary/10 px-2.5 py-1 text-xs font-semibold text-text shadow-sm border border-secondary/20">
                      {{ tag }}
                      <button @click="removeTag(tag)" class="-mr-1 text-secondary/40 hover:text-red-500 rounded-full hover:bg-secondary/20 transition-colors">
                         <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
                      </button>
                    </span>
                  </div>
                  <input
                    v-model="newTag"
                    @keydown.enter.prevent="addTag"
                    type="text"
                    class="block w-full rounded-xl border-0 py-2.5 px-3 text-text shadow-sm ring-1 ring-inset ring-secondary/20 placeholder:text-secondary/40 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary sm:text-sm"
                    placeholder="Añadir nueva y pulsar Enter..."
                  />
                </div>

                <!-- Tone Slider -->
                <div>
                  <label class="block text-xs font-bold text-secondary uppercase tracking-wider mb-3">
                    Tono: 
                    <span class="text-primary">
                      {{ toneValue < 33 ? 'Profesional' : toneValue < 66 ? 'Cercano' : 'Viral/Audaz' }}
                    </span>
                  </label>
                  <input type="range" v-model="toneValue" min="0" max="100" class="w-full h-2 bg-secondary/20 rounded-lg appearance-none cursor-pointer mb-2 focus:outline-none focus:ring-2 focus:ring-primary [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:w-4 [&::-webkit-slider-thumb]:h-4 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-primary [&::-moz-range-thumb]:w-4 [&::-moz-range-thumb]:h-4 [&::-moz-range-thumb]:border-0 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:bg-primary" />
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
        <div v-else-if="currentStep === 3" class="space-y-6 mb-8" key="step3">
          
          <!-- Top Tool Bar -->
          <div class="bg-white rounded-2xl shadow-sm border border-secondary/20 p-4 flex flex-col md:flex-row justify-between items-center gap-4">
            
            <div class="flex items-center gap-3">
              <button @click="goNext(2)" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-semibold text-secondary bg-white border border-secondary/20 rounded-xl hover:bg-secondary/5 transition-colors focus:outline-none focus:ring-2 focus:ring-primary">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                Volver
              </button>
            </div>

            <!-- Toggle Switch -->
            <div class="flex bg-secondary/10 p-1 rounded-xl w-full max-w-xs relative isolate">
              <button @click="viewMode = 'editor'" class="flex-1 py-1.5 text-sm font-semibold rounded-lg z-10 transition-colors" :class="viewMode === 'editor' ? 'text-primary' : 'text-secondary/60 hover:text-text'">Editor</button>
              <button @click="viewMode = 'demo'" class="flex-1 py-1.5 text-sm font-semibold rounded-lg z-10 transition-colors" :class="viewMode === 'demo' ? 'text-primary' : 'text-secondary/60 hover:text-text'">Vista Previa</button>
              <div class="absolute inset-y-1 left-1 w-[calc(50%-4px)] bg-white rounded-lg shadow-sm transition-transform duration-300 ease-out z-0" :class="viewMode === 'demo' ? 'translate-x-[100%]' : 'translate-x-0'"></div>
            </div>

            <!-- Actions (Version / Export) -->
            <div class="flex items-center gap-2">
              <button class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-semibold text-secondary bg-white border border-secondary/20 rounded-xl hover:bg-secondary/5 transition-colors focus:outline-none focus:ring-2 focus:ring-primary">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                Historial
              </button>
              <button class="inline-flex items-center gap-2 px-4 py-2 text-sm font-bold text-white bg-primary border border-transparent rounded-xl shadow-sm hover:bg-primary/90 transition-colors focus:ring-2 focus:ring-offset-2 focus:ring-primary">
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
              <div v-show="viewMode === 'editor'" class="flex-1 bg-[#1e1e1e] rounded-2xl shadow-xl border border-secondary/20 flex flex-col overflow-hidden animate-fade-in h-full">
                <div class="px-4 py-2 bg-secondary/10 border-b border-secondary/20 flex items-center justify-between">
                  <div class="flex items-center gap-4">
                    <span class="text-xs font-mono text-secondary/60">borrador.md</span>
                    <div class="h-4 w-px bg-secondary/20"></div>
                    <div class="flex items-center gap-2">
                    </div>
                  </div>
                  <span class="text-[10px] bg-primary/20 text-primary px-2 py-0.5 rounded uppercase font-bold tracking-widest flex items-center gap-1">
                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14.5v-9l6 4.5-6 4.5z"/></svg>
                    Live Engine
                  </span>
                </div>
                <textarea
                  v-model="generatedMarkdown"
                  @dragover.prevent
                  @drop="onEditorDrop"
                  class="w-full h-[600px] p-6 text-sm font-mono border-0 focus:ring-0 resize-none bg-slate-50 text-slate-800"
                  placeholder="Escribe tu artículo aquí..."
                ></textarea>
              </div>

              <!-- DEMO VIEW -->
              <div v-show="viewMode === 'demo'" class="flex-1 bg-white rounded-2xl shadow-xl border border-secondary/10 overflow-y-auto animate-fade-in h-full">
                <div class="p-8 sm:p-12 space-y-4">
                  <div
                    v-for="(block, bIdx) in articleBlocks"
                    :key="block.id"
                    draggable="true"
                    @dragstart="onBlockDragStart(bIdx)"
                    @dragover.prevent
                    @drop="onBlockDrop($event, bIdx)"
                    class="relative group/block transition-all duration-200 rounded-xl"
                    :class="[
                      draggedBlockIndex === bIdx ? 'opacity-20' : 'opacity-100',
                      block.type === 'image' ? 'cursor-move ring-2 ring-transparent hover:ring-primary/30 p-1' : 'cursor-default'
                    ]"
                  >
                    <!-- Visual drag handle for images (always visible on hover) -->
                    <div v-if="block.type === 'image'" class="absolute -left-8 top-1/2 -translate-y-1/2 opacity-0 group-hover/block:opacity-100 transition-opacity p-2 cursor-move text-secondary/40 hover:text-primary">
                      <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                    </div>

                    <!-- Delete button for images in preview -->
                    <button 
                      v-if="block.type === 'image'"
                      @click="removeBlock(bIdx)"
                      class="absolute -right-2 -top-2 p-1.5 bg-red-500 text-white rounded-full opacity-0 group-hover/block:opacity-100 transition-opacity hover:bg-red-600 shadow-md z-10"
                      title="Eliminar del preview"
                    >
                      <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>

                    <div 
                      v-html="block.html" 
                      class="prose prose-slate prose-indigo max-w-none prose-p:text-base prose-p:leading-relaxed prose-li:text-base prose-img:rounded-2xl shadow-none"
                    ></div>
                    
                    <!-- Drop indicator after block -->
                    <div v-if="draggedBlockIndex !== null && draggedBlockIndex !== bIdx" class="h-1 w-full bg-primary/10 rounded-full mt-2 opacity-0 hover:opacity-100 transition-opacity"></div>
                  </div>
                </div>
              </div>
            </div>

            <!-- SEO & Snippet Sidebar -->
            <div class="w-full lg:w-80 flex flex-col gap-6 shrink-0 h-full overflow-y-auto pr-1">
              
              <!-- Google Snippet Simulator -->
              <div class="bg-white rounded-2xl shadow-sm border border-secondary/20 overflow-hidden">
                <div class="p-4 border-b border-secondary/10 bg-background flex items-center gap-2">
                  <svg class="h-4 w-4 text-secondary" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14.5v-9l6 4.5-6 4.5z"/></svg>
                  <h3 class="text-xs font-bold text-secondary uppercase tracking-widest">Vista previa Google</h3>
                </div>
                <div class="p-5 font-sans space-y-1">
                  <div class="text-[14px] text-secondary/60 line-clamp-1">https://tusitio.es › noticias</div>
                  <div class="text-[20px] text-primary hover:underline cursor-pointer leading-tight line-clamp-2">{{ googleTitle }}</div>
                  <div class="text-[14px] text-text/80 leading-relaxed line-clamp-3">
                    <span class="text-secondary/60">hace 2 horas — </span>{{ googleSnippet }}
                  </div>
                </div>
              </div>

              <!-- SEO Verification Checklist -->
              <div class="bg-white rounded-2xl shadow-sm border border-secondary/20 overflow-hidden shrink-0">
                <button 
                  @click="showSeoDetails = !showSeoDetails"
                  class="w-full p-4 border-b border-secondary/10 bg-background flex items-center justify-between hover:bg-secondary/5 transition-colors group/seo"
                >
                  <div class="flex items-center gap-2">
                    <h3 class="text-xs font-bold text-secondary uppercase tracking-widest group-hover/seo:text-primary transition-colors">Verificación SEO</h3>
                    <svg 
                      class="h-4 w-4 text-secondary/40 group-hover/seo:text-primary transition-all duration-300"
                      :class="{ 'rotate-180': showSeoDetails }"
                      viewBox="0 0 20 20" 
                      fill="currentColor"
                    >
                      <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                  </div>
                  <span class="text-xs font-bold text-primary">{{ seoScore }}%</span>
                </button>
                
                <transition
                  enter-active-class="transition duration-300 ease-out"
                  enter-from-class="transform -translate-y-2 opacity-0"
                  enter-to-class="transform translate-y-0 opacity-100"
                  leave-active-class="transition duration-200 ease-in"
                  leave-from-class="transform translate-y-0 opacity-100"
                  leave-to-class="transform -translate-y-2 opacity-0"
                >
                  <div v-if="showSeoDetails" class="p-5 space-y-4">
                    <div v-for="item in seoAnalysis" :key="item.label" class="flex items-start gap-3">
                      <div class="mt-0.5 shrink-0">
                        <svg v-if="item.value" class="h-5 w-5 text-accent" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                        <svg v-else class="h-5 w-5 text-secondary/30" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" /></svg>
                      </div>
                      <span class="text-xs font-medium" :class="item.value ? 'text-text' : 'text-secondary/40'">{{ item.label }}</span>
                    </div>
                    
                    <!-- Smart Tips -->
                    <div class="mt-6 pt-6 border-t border-secondary/10">
                      <div class="text-[10px] font-bold text-secondary/40 uppercase tracking-widest mb-3">Consejo de IA</div>
                      <div class="bg-primary/5 rounded-xl p-3 border border-primary/10">
                        <p class="text-[11px] text-primary/80 leading-relaxed font-medium">
                          {{ seoScore < 90 ? 'Añade más palabras clave secundarias para mejorar la autoridad temática.' : '¡Excelente trabajo! El artículo cumple con todos los estándares SEO recomendados.' }}
                        </p>
                      </div>
                    </div>
                  </div>
                </transition>
              </div>
              
              <!-- Media Library (Same as step 2 for consistency) -->
              <div class="bg-white rounded-2xl shadow-sm border border-secondary/20 overflow-hidden flex flex-col min-h-[300px]">
                <div class="p-4 border-b border-secondary/10 bg-background flex items-center gap-2 shrink-0">
                  <svg class="h-4 w-4 text-secondary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                  <h3 class="text-xs font-bold text-secondary uppercase tracking-widest">Galería de Imágenes</h3>
                </div>
                
                <div class="p-5 flex-1 overflow-y-auto">
                  <div 
                    @click="triggerImageUpload"
                    @dragover.prevent
                    @drop="handleFileDrop"
                    class="border-2 border-dashed border-secondary/20 bg-background rounded-xl p-4 text-center hover:bg-secondary/5 hover:border-secondary/30 transition-colors cursor-pointer mb-4"
                  >
                    <svg class="mx-auto h-6 w-6 text-secondary/40 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    <span class="text-[10px] text-primary font-bold block uppercase tracking-tight">Subir o Arrastrar</span>
                  </div>

                  <div class="grid grid-cols-2 gap-3" v-if="uploadedImages.length > 0">
                    <div 
                      v-for="img in uploadedImages" 
                      :key="img.id"
                      draggable="true"
                      @dragstart="onImageDragStart($event, img)"
                      class="aspect-square bg-secondary/10 rounded-lg flex items-center justify-center overflow-hidden border border-secondary/20 cursor-pointer hover:shadow-md transition-all group relative"
                      :title="'Insertar/Arrastrar ' + img.name"
                    >
                      <img :src="img.data" @click="insertImage(img)" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300" />
                      <div class="absolute inset-0 bg-primary/20 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center pointer-events-none">
                        <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                      </div>
                      <button 
                        @click.stop="deleteImage(img.id)"
                        class="absolute top-1 right-1 p-1 bg-red-500 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-600 shadow-sm z-10"
                        title="Eliminar imagen"
                      >
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                      </button>
                    </div>
                  </div>
                  
                  <div class="text-center py-8" v-else>
                    <svg class="mx-auto h-8 w-8 text-secondary/20 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    <p class="text-[10px] text-secondary/40 font-bold uppercase">Sin imágenes</p>
                  </div>
                  <p class="text-[9px] text-secondary/40 mt-4 text-center leading-relaxed">Arrastra miniaturas al preview para moverlas.</p>
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
  content: '→';
  @apply text-primary transition-transform duration-300 font-mono;
}

:deep(details[open] summary::before) {
  @apply rotate-90;
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