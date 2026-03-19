<script lang="ts" setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { marked } from 'marked'
import DOMPurify from 'dompurify'
import MarkdownEditor from './MarkdownEditor.vue'
import { useRegenerateSection } from '../composables'

const props = defineProps<{
  modelValue: string
  title: string
  isSaving: boolean
  savePrompt?: string
  backPrompt?: string
  hideBack?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
  (e: 'save'): void
  (e: 'back'): void
}>()

const generatedMarkdown = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val)
})

const { mutateAsync: regenerateSection } = useRegenerateSection()

const errorMessage = ref<string | null>(null)

// View & Editor state
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
const markdownEditor = ref<HTMLTextAreaElement | null>(null)
const editorType = ref<'markdown' | 'visual'>('markdown')

function downloadArticle(format: 'markdown' | 'html') {
  let content = generatedMarkdown.value
  let filename = props.title.trim() || 'articulo-generado'
  
  filename = filename.toLowerCase()
    .normalize("NFD").replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9]/g, '-')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '')
  
  if (format === 'html') {
    const htmlContent = marked.parse(content)
    content = `<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>${props.title}</title>
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

const renderedHtml = computed(() => {
  const rawHtml = marked.parse(generatedMarkdown.value) as string
  return DOMPurify.sanitize(rawHtml, {
    ADD_ATTR: ['src'],
    ALLOWED_URI_REGEXP: /^(?:(?:(?:f|ht)tps?|mailto|tel|callto|cid|xmpp|data):|[^a-z]|[a-z+.\-]+(?:[^a-z+.\-:]|$))/i
  }).replace(/<details>/g, '<details open>')
})

function copyToClipboard() {
  const content = renderedHtml.value
  
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(content).then(() => {
      handleCopySuccess()
    }).catch(err => {
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
    }
  } catch (err) {}
  document.body.removeChild(textArea)
}

function handleRegenerateClick() {
  if (!markdownEditor.value) return

  const start = markdownEditor.value.selectionStart
  const end = markdownEditor.value.selectionEnd
  const text = generatedMarkdown.value.substring(start, end).trim()

  if (!text) {
    errorMessage.value = "⚠️ selecciona el texto que deseas regenerar en el editor."
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
      articleTitle: props.title,
      sectionHeading: "Sección seleccionada",
      currentContent: selectedText.value,
      guidelines: regenGuidelines.value
    })
    
    newVersion.value = response.content
    isReviewingRegen.value = true
    showRegenInput.value = false
    regenGuidelines.value = ''
  } catch (error: any) {
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

function handleSave() {
  showSaveDropdown.value = false
  emit('save')
}

onMounted(() => {
  if (typeof window !== 'undefined') {
    document.body.style.overflow = 'hidden';
    document.body.style.height = '100%';
    document.documentElement.style.overflow = 'hidden';
    document.documentElement.style.height = '100%';
  }
})

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
  <div class="flex-1 flex flex-col min-h-0 bg-white rounded-2xl shadow-xl border border-secondary/10 overflow-hidden relative">
    <transition
      enter-active-class="transform ease-out duration-300 transition"
      enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
      enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-active-class="transition ease-in duration-100"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="errorMessage" class="absolute top-16 right-4 z-50 max-w-sm bg-white border-l-4 border-red-500 rounded-r-lg shadow-xl p-4 flex items-start gap-3">
        <svg class="h-5 w-5 text-red-500 mt-0.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" /></svg>
        <div class="flex-1">
          <p class="text-sm font-medium text-slate-800">{{ errorMessage }}</p>
        </div>
        <button @click="errorMessage = null" class="text-slate-400 hover:text-slate-600">
          <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
        </button>
      </div>
    </transition>
    <!-- Top Tool Bar -->
    <div class="bg-background border-b border-secondary/10 p-4 flex justify-between items-center shrink-0">
      <div class="flex items-center gap-4">
        <button v-if="!hideBack" @click="$emit('back')" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-secondary bg-white border border-secondary/20 rounded-lg hover:bg-secondary/5 transition-colors">
          <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
          {{ backPrompt || 'Volver' }}
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
            <button 
              @click="handleSave"
              :disabled="isSaving"
              class="w-full text-left px-4 py-2 text-xs font-semibold text-accent hover:bg-accent/5 transition-colors flex items-center gap-2 mb-1 disabled:opacity-50"
            >
              <svg v-if="isSaving" class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
              <svg v-else class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg>
              {{ isSaving ? 'Guardando...' : (savePrompt || 'Guardar Noticia') }}
            </button>
            <div class="px-3 py-2 border-b border-t border-secondary/5 mb-1 mt-1">
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
        <router-link to="/mis-noticias" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-secondary bg-white border border-secondary/20 rounded-lg hover:bg-secondary/5 transition-colors">
          <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          Historial
        </router-link>
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
              {{ isCopied ? '¡Copiado!' : 'Copiar HTML' }}
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
