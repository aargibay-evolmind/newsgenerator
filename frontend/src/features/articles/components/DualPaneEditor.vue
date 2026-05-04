<script setup lang="ts">
import { computed, ref, watch, onMounted, onUnmounted } from 'vue'
import { renderMarkdown, cleanMarkdown, revokeMarkdownBlobs } from '@/utils/markdown'
import { marked } from 'marked'
import MarkdownEditor from './MarkdownEditor.vue'
import ComparatorSidebar from './ComparatorSidebar.vue'
import CompetitorPane from './CompetitorPane.vue'
import { useRegenerateSection } from '../composables'

import type { ArticleMetadata } from '../types'

const props = defineProps<{
  modelValue: string
  title: string
  isSaving: boolean
  isSaved?: boolean
  savePrompt?: string
  backPrompt?: string
  hideBack?: boolean
  metadata?: ArticleMetadata
  competitorUrl?: string
  competitorMarkdown?: string
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
const viewMode = ref<'markdown' | 'visual' | 'html' | 'compare'>('visual')
const isCopied = ref(false)

// Regeneration State
const isRegenerating = ref(false)
const showRegenInput = ref(false)
const regenGuidelines = ref('')
const selectedText = ref('')
const selectionIndices = ref({ start: 0, end: 0 })
const newVersion = ref('')
const isReviewingRegen = ref(false)

const showDownloadDropdown = ref(false)
const markdownEditor = ref<HTMLTextAreaElement | null>(null)

const showMetadataSidebar = ref(true)
const activeSidebarTab = ref<'metadata' | 'comparator'>('metadata')

const copiedField = ref<string | null>(null)

function copyField(text: string, fieldName: string) {
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(text).then(() => {
      handleFieldCopySuccess(fieldName)
    }).catch(() => {
      fallbackCopyTextToClipboard(text)
      handleFieldCopySuccess(fieldName)
    })
  } else {
    fallbackCopyTextToClipboard(text)
    handleFieldCopySuccess(fieldName)
  }
}

function handleFieldCopySuccess(fieldName: string) {
  copiedField.value = fieldName
  setTimeout(() => {
    if (copiedField.value === fieldName) {
      copiedField.value = null
    }
  }, 2000)
}

function downloadArticle(format: 'markdown' | 'html') {
  let content = generatedMarkdown.value
  let filename = props.title.trim() || 'articulo-generado'
  
  filename = filename.toLowerCase()
    .normalize("NFD").replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9]/g, '-')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '')
  
  if (format === 'html') {
    const htmlContent = renderMarkdown(content, true)
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
    <h1>${props.title}</h1>
    ${htmlContent}
</body>
</html>`
  } else {
    // Markdown format
    content = `# ${props.title}\n\n${content}`
  }
  
  const blob = new Blob([content], { type: 'text/plain' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  const extension = format === 'html' ? 'html' : 'txt'
  link.download = `${filename}.${extension}`
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
  
  showDownloadDropdown.value = false
}


function copyToClipboard() {
  const htmlContent = renderMarkdown(generatedMarkdown.value, true)
  const fullContent = `<h1>${props.title}</h1>\n${htmlContent}`
  
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(fullContent).then(() => {
      handleCopySuccess()
    }).catch(err => {
      fallbackCopyTextToClipboard(fullContent)
    })
  } else {
    fallbackCopyTextToClipboard(fullContent)
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
    
    newVersion.value = cleanMarkdown(response.content)
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
  emit('save')
}

onMounted(() => {
  if (typeof window !== 'undefined') {
    document.body.style.overflow = 'hidden';
    document.body.style.height = '100%';
    document.documentElement.style.overflow = 'hidden';
    document.documentElement.style.height = '100%';
  }
  
  // Default to comparator if we have a URL
  if (props.competitorUrl) {
    activeSidebarTab.value = 'comparator'
  }
})

onUnmounted(() => {
  revokeMarkdownBlobs()
  if (typeof window !== 'undefined') {
    document.body.style.overflow = ''
    document.body.style.height = ''
    document.documentElement.style.overflow = ''
    document.documentElement.style.height = ''
  }
})
</script>

<template>
  <div class="flex-1 flex flex-col min-h-0 bg-background dark:bg-dark-background rounded-2xl shadow-xl border border-secondary/10 dark:border-dark-border overflow-hidden relative transition-colors duration-300">
    <transition
      enter-active-class="transform ease-out duration-300 transition"
      enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
      enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-active-class="transition ease-in duration-100"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="errorMessage" class="absolute top-16 right-4 z-50 max-w-sm bg-background dark:bg-dark-surface border-l-4 border-red-500 rounded-r-lg shadow-xl p-4 flex items-start gap-3 border border-secondary/10 dark:border-dark-border">
        <svg class="h-5 w-5 text-red-500 mt-0.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" /></svg>
        <div class="flex-1">
          <p class="text-sm font-medium text-text dark:text-dark-text">{{ errorMessage }}</p>
        </div>
        <button @click="errorMessage = null" class="text-secondary dark:text-dark-text/40 hover:text-text dark:hover:text-dark-text">
          <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
        </button>
      </div>
    </transition>
    <!-- Top Tool Bar -->
    <div class="bg-background dark:bg-dark-background border-b border-secondary/10 dark:border-dark-border p-4 flex justify-between items-center shrink-0">
      <div class="flex items-center gap-4">
        <button v-if="!hideBack" @click="$emit('back')" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-secondary dark:text-dark-text/60 bg-background dark:bg-dark-surface border border-secondary/20 dark:border-dark-border rounded-lg hover:bg-secondary/5 dark:hover:bg-dark-background transition-colors">
          <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
          {{ backPrompt || 'Volver' }}
        </button>
        <div class="relative flex items-center gap-1.5">
          <button 
            @click="handleSave"
            :disabled="isSaving || isSaved"
            :class="[
              'inline-flex items-center gap-1.5 px-4 py-1.5 text-xs font-bold rounded-lg transition-all shadow-sm border',
              isSaved 
                ? 'bg-slate-400 border-slate-400 text-white cursor-not-allowed' 
                : 'bg-primary border-primary text-white hover:bg-primary/90 disabled:opacity-50'
            ]"
          >
            <svg v-if="isSaving" class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            <svg v-else class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg>
            {{ isSaving ? 'Guardando...' : (isSaved ? 'Guardado' : (savePrompt || 'Guardar')) }}
          </button>

          <div class="relative">
            <button 
              @click="showDownloadDropdown = !showDownloadDropdown"
              class="inline-flex items-center justify-center p-1.5 text-secondary dark:text-dark-text/40 bg-secondary/5 border border-secondary/10 rounded-lg hover:bg-secondary/10 transition-colors"
              title="Opciones de descarga"
            >
              <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM18 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
            </button>
            
            <!-- Download Dropdown -->
            <div v-if="showDownloadDropdown" class="absolute left-0 mt-2 w-48 bg-background dark:bg-dark-surface rounded-xl shadow-2xl border border-secondary/10 dark:border-dark-border py-2 z-50 animate-in fade-in slide-in-from-top-1 duration-200">
              <div class="px-3 py-1 mb-1">
                <span class="text-[9px] font-black text-secondary/40 dark:text-dark-text/30 uppercase tracking-widest">Descargar como</span>
              </div>
              <button 
                @click="downloadArticle('markdown')"
                class="w-full text-left px-4 py-2 text-xs font-semibold text-text dark:text-dark-text hover:bg-primary/5 dark:hover:bg-primary/10 hover:text-primary transition-colors flex items-center gap-2"
              >
                <svg class="h-3.5 w-3.5 text-secondary/40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                Markdown (.txt)
              </button>
              <button 
                @click="downloadArticle('html')"
                class="w-full text-left px-4 py-2 text-xs font-semibold text-text dark:text-dark-text hover:bg-primary/5 dark:hover:bg-primary/10 hover:text-primary transition-colors flex items-center gap-2"
              >
                <svg class="h-3.5 w-3.5 text-secondary/40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" /></svg>
                HTML (.html)
              </button>
            </div>

            <!-- Backdrop for closing dropdown -->
            <div v-if="showDownloadDropdown" @click="showDownloadDropdown = false" class="fixed inset-0 z-40"></div>
          </div>
        </div>
      </div>

      <div class="flex items-center gap-3">
        <!-- Sidebar Toggles -->
        <div class="flex p-0.5 bg-secondary/5 dark:bg-dark-surface rounded-lg border border-secondary/10 dark:border-dark-border">
          <!-- Metadata Toggle -->
          <button 
            @click="activeSidebarTab = 'metadata'; showMetadataSidebar = true"
            :class="[
              'flex items-center gap-1.5 px-2.5 py-1 text-[9px] font-black uppercase tracking-wider rounded transition-all duration-200',
              showMetadataSidebar && activeSidebarTab === 'metadata'
                ? 'bg-primary text-white shadow-sm' 
                : 'text-secondary/60 dark:text-dark-text/40 hover:text-primary'
            ]"
          >
            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Metadatos
          </button>

          <!-- Comparator Toggle (Only if competitorUrl exists) -->
          <button 
            v-if="competitorUrl"
            @click="activeSidebarTab = 'comparator'; showMetadataSidebar = true"
            :class="[
              'flex items-center gap-1.5 px-2.5 py-1 text-[9px] font-black uppercase tracking-wider rounded transition-all duration-200',
              showMetadataSidebar && activeSidebarTab === 'comparator'
                ? 'bg-orange-500 text-white shadow-sm' 
                : 'text-secondary/60 dark:text-dark-text/40 hover:text-orange-500'
            ]"
          >
            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
            </svg>
            Análisis
          </button>

          <!-- Side-by-Side (Vista Dual) Toggle -->
          <button 
            v-if="competitorUrl"
            @click="viewMode = viewMode === 'compare' ? 'visual' : 'compare'; if(viewMode === 'compare') showMetadataSidebar = false"
            :class="[
              'flex items-center gap-1.5 px-2.5 py-1 text-[9px] font-black uppercase tracking-wider rounded transition-all duration-200 border border-transparent',
              viewMode === 'compare'
                ? 'bg-primary text-white border-primary shadow-sm' 
                : 'text-primary bg-primary/5 hover:bg-primary/10 border-primary/20'
            ]"
          >
            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7" />
            </svg>
            Vista Dual
          </button>
          
          <!-- Close toggle button -->
          <button 
            v-if="showMetadataSidebar"
            @click="showMetadataSidebar = false"
            class="px-1.5 text-secondary/40 hover:text-red-500 transition-colors"
            title="Cerrar barra lateral"
          >
            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </div>

        <!-- Copy HTML Button (Always Visible) -->
        <button 
          @click="copyToClipboard"
          :class="[
            'flex items-center gap-2 px-3 py-1.5 rounded-lg border transition-all duration-200',
            isCopied 
              ? 'bg-green-600/10 border-green-600/30 text-green-600 shadow-sm' 
              : 'bg-secondary/5 border-secondary/10 text-secondary hover:bg-secondary/10 dark:text-dark-text/60'
          ]"
        >
          <svg v-if="!isCopied" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" /></svg>
          <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
          <span class="text-[10px] font-black uppercase tracking-wider">{{ isCopied ? '¡Copiado!' : 'Copiar HTML' }}</span>
        </button>
      </div>


    </div>

    <!-- Workplace Area with Sidebar -->
    <div class="flex-1 flex overflow-hidden min-h-0 relative">
      
      <!-- MAIN CONTENT AREA -->
      <div class="flex-1 flex flex-col min-w-0 overflow-hidden bg-background dark:bg-dark-background transition-colors">
        
        <!-- Generated Article Title (H1) - Outside of editable Markdown -->
        <div class="px-6 pt-4 pb-3 shrink-0 max-w-6xl mx-auto w-full border-b border-secondary/5 dark:border-dark-border/30 mb-1">
          <h1 class="text-xl sm:text-2xl font-bold text-text dark:text-dark-text leading-tight">
            {{ title }}
          </h1>
        </div>

        <!-- Mode Switcher (Local to main content) -->
        <div class="h-9 px-4 bg-background dark:bg-dark-background border-b border-secondary/10 dark:border-dark-border flex items-center justify-between shrink-0">
          <div class="flex p-0.5 bg-secondary/5 dark:bg-dark-surface rounded-md border border-secondary/10 dark:border-dark-border">
            <button 
              v-for="mode in ['visual', 'markdown', 'html', 'compare']" 
              :key="mode"
              v-show="mode !== 'compare' || competitorUrl"
              @click="viewMode = mode as any"
              :class="[
                'px-2.5 py-0.5 text-[9px] font-black uppercase tracking-wider rounded transition-all duration-200 whitespace-nowrap',
                viewMode === mode 
                  ? 'bg-primary text-white shadow-sm' 
                  : 'text-secondary/60 dark:text-dark-text/40 hover:text-primary'
              ]"
            >
              {{ mode === 'visual' ? 'Editor' : mode === 'markdown' ? 'Markdown' : mode === 'html' ? 'HTML' : 'Comparar' }}
            </button>
          </div>
          <span class="text-[9px] font-mono text-secondary/40 dark:text-dark-text/30">
            {{ viewMode === 'visual' ? 'Editor Visual (AI)' : viewMode === 'markdown' ? 'Editor Markdown Raw' : viewMode === 'html' ? 'Código HTML' : 'Vista Comparativa (Lado a Lado)' }}
          </span>
        </div>

        <!-- EDITOR MODE (Markdown / Visual) -->
        <div v-if="(viewMode === 'markdown' || viewMode === 'visual') && !isReviewingRegen" class="flex-1 flex flex-col min-h-0 bg-slate-50 dark:bg-dark-surface/10 transition-colors">
          <div class="h-8 px-4 border-b border-secondary/5 dark:border-dark-border/30 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-4">
              <button 
                v-if="viewMode === 'markdown'"
                @click="handleRegenerateClick"
                class="flex items-center gap-1.5 px-2 py-0.5 text-[9px] font-bold text-primary bg-primary/5 border border-primary/20 rounded hover:bg-primary/10 transition-colors"
              >
                ✨ Regenerar Selección
              </button>
            </div>
            <span class="text-[9px] font-mono text-secondary/30 dark:text-dark-text/20 italic">Auto-guardado activo</span>
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
            <div v-if="showRegenInput" class="p-4 bg-primary/5 dark:bg-primary/10 border-b border-secondary/10 dark:border-dark-border flex flex-col gap-3">
              <div class="flex items-center justify-between">
                <label class="text-[10px] font-bold text-primary uppercase tracking-wider">Directrices para la IA</label>
                <button @click="showRegenInput = false" class="text-secondary/40 dark:text-dark-text/30 hover:text-secondary">
                  <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
              </div>
              <div class="flex gap-2">
                <input 
                  v-model="regenGuidelines"
                  @keydown.enter="confirmRegeneration"
                  type="text" 
                  placeholder="Ej: hazlo más profesional, resume este punto..." 
                  class="flex-1 bg-background dark:bg-dark-background border border-secondary/20 dark:border-dark-border rounded-lg px-3 py-2 text-xs text-text dark:text-dark-text focus:ring-1 focus:ring-primary focus:outline-none transition-colors"
                />
                <button 
                  @click="confirmRegeneration"
                  :disabled="isRegenerating"
                  class="px-4 py-2 bg-primary text-white text-xs font-bold rounded-lg hover:bg-primary/90 transition-colors disabled:opacity-50 flex items-center gap-2"
                >
                  <svg v-if="isRegenerating" class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                  {{ isRegenerating ? 'Generando...' : 'Confirmar' }}
                </button>
              </div>
            </div>
          </transition>

          <!-- Component Switcher -->
          <div v-if="viewMode === 'visual'" class="flex-1 min-h-0 flex flex-col">
            <MarkdownEditor v-model="generatedMarkdown" />
          </div>
          <textarea
            v-else-if="viewMode === 'markdown'"
            ref="markdownEditor"
            v-model="generatedMarkdown"
            class="flex-1 w-full p-8 sm:p-12 text-sm font-mono border-0 focus:ring-0 resize-none bg-transparent text-text dark:text-dark-text leading-relaxed overflow-y-auto custom-scrollbar transition-colors max-w-6xl mx-auto"
            placeholder="Escribe tu artículo aquí..."
          ></textarea>
        </div>

        <!-- HTML SOURCE MODE -->
        <div v-else-if="viewMode === 'html' && !isReviewingRegen" class="flex-1 flex flex-col min-h-0 bg-background dark:bg-dark-background transition-colors">
          <div class="h-8 px-4 bg-background dark:bg-dark-background border-b border-secondary/5 dark:border-dark-border/30 flex items-center justify-end shrink-0">
            <div class="flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-accent animate-pulse"></span>
              <span class="text-[9px] font-bold text-accent uppercase tracking-wider">Live</span>
            </div>
          </div>
          
          <div class="flex-1 overflow-y-auto overscroll-contain custom-scrollbar p-8 sm:p-12">
            <div class="h-full max-w-6xl mx-auto">
              <textarea
                readonly
                :value="renderMarkdown(generatedMarkdown, true)"
                class="w-full h-full p-4 border border-secondary/10 dark:border-dark-border rounded-xl bg-slate-50 dark:bg-dark-surface/30 text-xs font-mono text-secondary dark:text-dark-text/70 focus:ring-0 resize-none leading-relaxed transition-colors"
                placeholder="Código HTML generado..."
              ></textarea>
            </div>
          </div>
        </div>

        <!-- COMPARE MODE (Side-by-Side) -->
        <div v-else-if="viewMode === 'compare' && !isReviewingRegen" class="flex-1 flex overflow-hidden divide-x divide-secondary/10 dark:divide-dark-border transition-colors">
          <!-- Left: Our Editor -->
          <div class="flex-1 flex flex-col min-w-0 bg-background dark:bg-dark-background">
            <div class="h-8 px-4 border-b border-secondary/5 dark:border-dark-border/30 flex items-center justify-between shrink-0 bg-slate-50/50 dark:bg-dark-surface/50">
              <span class="text-[9px] font-bold text-primary uppercase tracking-widest">Nuestro Artículo</span>
            </div>
            <div class="flex-1 min-h-0">
               <MarkdownEditor v-model="generatedMarkdown" />
            </div>
          </div>
          <!-- Right: Competitor Article -->
          <div class="flex-1 flex flex-col min-w-0 bg-slate-50 dark:bg-dark-surface">
            <CompetitorPane :markdown="competitorMarkdown || ''" :url="competitorUrl || ''" />
          </div>
        </div>

        <!-- DIFF REVIEW OVERLAY (Full PANE) -->
        <div v-if="isReviewingRegen" class="flex-1 overflow-hidden flex flex-col bg-background dark:bg-dark-background">
          <div class="px-6 py-4 border-b border-secondary/10 dark:border-dark-border flex items-center justify-between bg-background dark:bg-dark-background shadow-sm z-10 transition-colors">
            <h3 class="text-xs font-bold text-text dark:text-dark-text uppercase tracking-widest">Revisar Cambios</h3>
            <div class="flex gap-2">
              <button @click="cancelRegen" class="px-3 py-1.5 text-xs font-bold text-secondary dark:text-dark-text/60 bg-background dark:bg-dark-surface border border-secondary/20 dark:border-dark-border rounded-lg hover:bg-secondary/5 dark:hover:bg-dark-background transition-colors">
                Descartar
              </button>
              <button @click="acceptRegen" class="px-3 py-1.5 text-xs font-bold text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors">
                Aceptar Cambios
              </button>
            </div>
          </div>
          <div class="flex-1 flex divide-x divide-secondary/10 dark:divide-dark-border overflow-hidden">
            <div class="flex-1 flex flex-col">
              <div class="px-4 py-2 bg-red-50/50 dark:bg-red-900/10 border-b border-secondary/10 dark:border-dark-border text-[10px] font-bold text-red-600 dark:text-red-400 uppercase">Original</div>
              <div class="flex-1 p-6 overflow-y-auto bg-red-50/30 dark:bg-red-900/5 text-sm font-mono text-red-800 dark:text-red-300 line-through decoration-red-400 dark:decoration-red-600 whitespace-pre-wrap leading-relaxed">{{ selectedText }}</div>
            </div>
            <div class="flex-1 flex flex-col">
              <div class="px-4 py-2 bg-green-50/50 dark:bg-green-900/10 border-b border-secondary/10 dark:border-dark-border text-[10px] font-bold text-green-600 dark:text-green-400 uppercase">Nueva Versión</div>
              <div class="flex-1 p-6 overflow-y-auto bg-green-50/30 dark:bg-green-900/5 text-sm font-mono text-green-800 dark:text-green-200 whitespace-pre-wrap leading-relaxed">{{ newVersion }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- METADATA SIDEBAR -->
      <transition
        enter-active-class="transform transition ease-out duration-300"
        enter-from-class="translate-x-full"
        enter-to-class="translate-x-0"
        leave-active-class="transform transition ease-in duration-200"
        leave-from-class="translate-x-0"
        leave-to-class="translate-x-full"
      >
        <div v-show="showMetadataSidebar" class="w-80 h-full shrink-0 z-10 shadow-2xl relative bg-slate-50 dark:bg-dark-surface border-l border-secondary/10 dark:border-dark-border">
          
          <!-- METADATA TAB -->
          <aside 
            v-if="metadata && activeSidebarTab === 'metadata'" 
            class="absolute inset-0 flex flex-col overflow-hidden transition-colors"
          >
            <div class="p-4 border-b border-secondary/10 dark:border-dark-border flex items-center justify-between bg-background dark:bg-dark-background shrink-0">
              <div class="flex items-center gap-2">
                <div class="p-1 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400">
                  <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                </div>
                <h3 class="text-xs font-black text-text dark:text-dark-text uppercase tracking-widest">Metadatos</h3>
              </div>
              <button @click="showMetadataSidebar = false" class="p-1 rounded-full hover:bg-secondary/10 transition-colors text-secondary dark:text-dark-text/40">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
              </button>
            </div>

            <div class="flex-1 overflow-y-auto p-5 space-y-8 custom-scrollbar">
              
              <!-- Generales Section -->
              <div class="space-y-4">
                <h4 class="text-[10px] font-black text-secondary/40 dark:text-dark-text/30 uppercase tracking-widest border-b border-secondary/10 dark:border-dark-border pb-2">Generales</h4>
                
                <div class="flex flex-col gap-1.5">
                  <div class="flex items-center justify-between">
                    <label class="text-[9px] font-bold text-secondary dark:text-dark-text/50 uppercase tracking-wider">Título del Artículo</label>
                    <button @click="copyField(title, 'main-title')" class="text-[9px] font-bold text-primary hover:underline">
                      {{ copiedField === 'main-title' ? '¡Hecho!' : 'Copiar' }}
                    </button>
                  </div>
                  <div class="px-2.5 py-2 bg-background dark:bg-dark-background border border-secondary/10 dark:border-dark-border rounded-lg text-[10px] font-bold text-text dark:text-dark-text transition-colors">{{ title }}</div>
                </div>

                <div v-if="metadata.leads" class="flex flex-col gap-1.5">
                  <div class="flex items-center justify-between">
                    <label class="text-[9px] font-bold text-secondary dark:text-dark-text/50 uppercase tracking-wider">Ganchos / Leads</label>
                    <button @click="copyField(metadata.leads, 'leads-main')" class="text-[9px] font-bold text-primary hover:underline">
                      {{ copiedField === 'leads-main' ? '¡Hecho!' : 'Copiar' }}
                    </button>
                  </div>
                  <div class="px-2.5 py-2 bg-background dark:bg-dark-background border border-secondary/10 dark:border-dark-border rounded-lg text-[10px] text-text dark:text-dark-text leading-relaxed whitespace-pre-wrap transition-colors max-h-32 overflow-y-auto custom-scrollbar">{{ metadata.leads }}</div>
                </div>
              </div>

              <!-- SEO Section -->
              <div class="space-y-4">
                <h4 class="text-[10px] font-black text-secondary/40 dark:text-dark-text/30 uppercase tracking-widest border-b border-secondary/10 dark:border-dark-border pb-2">SEO</h4>
                
                <div class="flex flex-col gap-1.5">
                  <div class="flex items-center justify-between">
                    <label class="text-[9px] font-bold text-secondary dark:text-dark-text/50 uppercase tracking-wider">Friendly URL</label>
                    <button @click="copyField(metadata.friendlyUrl, 'url')" class="text-[9px] font-bold text-primary hover:underline">
                      {{ copiedField === 'url' ? '¡Hecho!' : 'Copiar' }}
                    </button>
                  </div>
                  <div class="px-2.5 py-2 bg-background dark:bg-dark-background border border-secondary/10 dark:border-dark-border rounded-lg text-[10px] font-mono text-text dark:text-dark-text break-all transition-colors">{{ metadata.friendlyUrl }}</div>
                </div>

                <div class="flex flex-col gap-1.5">
                  <div class="flex items-center justify-between">
                    <label class="text-[9px] font-bold text-secondary dark:text-dark-text/50 uppercase tracking-wider">Meta Title ({{ metadata.metaTitle?.length || 0 }})</label>
                    <button @click="copyField(metadata.metaTitle, 'title')" class="text-[9px] font-bold text-primary hover:underline">
                      {{ copiedField === 'title' ? '¡Hecho!' : 'Copiar' }}
                    </button>
                  </div>
                  <div class="px-2.5 py-2 bg-background dark:bg-dark-background border border-secondary/10 dark:border-dark-border rounded-lg text-[10px] text-text dark:text-dark-text leading-relaxed italic transition-colors">{{ metadata.metaTitle }}</div>
                </div>

                <div class="flex flex-col gap-1.5">
                  <div class="flex items-center justify-between">
                    <label class="text-[9px] font-bold text-secondary dark:text-dark-text/50 uppercase tracking-wider">Keywords</label>
                    <button @click="copyField(metadata.metaKeywords, 'keywords')" class="text-[9px] font-bold text-primary hover:underline">
                      {{ copiedField === 'keywords' ? '¡Hecho!' : 'Copiar' }}
                    </button>
                  </div>
                  <div class="px-2.5 py-2 bg-background dark:bg-dark-background border border-secondary/10 dark:border-dark-border rounded-lg text-[10px] text-text dark:text-dark-text leading-relaxed transition-colors">{{ metadata.metaKeywords }}</div>
                </div>

                <div class="flex flex-col gap-1.5">
                  <div class="flex items-center justify-between">
                    <label class="text-[9px] font-bold text-secondary dark:text-dark-text/50 uppercase tracking-wider">Description ({{ metadata.metaDescription?.length || 0 }})</label>
                    <button @click="copyField(metadata.metaDescription, 'desc')" class="text-[9px] font-bold text-primary hover:underline">
                      {{ copiedField === 'desc' ? '¡Hecho!' : 'Copiar' }}
                    </button>
                  </div>
                  <div class="px-2.5 py-2 bg-background dark:bg-dark-background border border-secondary/10 dark:border-dark-border rounded-lg text-[10px] text-text dark:text-dark-text leading-relaxed italic transition-colors">{{ metadata.metaDescription }}</div>
                </div>
              </div>

              <!-- Leads Section (Individually copyable) -->
              <div class="space-y-4" v-if="metadata.leads">
                <div class="flex items-center justify-between border-b border-secondary/10 dark:border-dark-border pb-2">
                  <h4 class="text-[10px] font-black text-secondary/40 dark:text-dark-text/30 uppercase tracking-widest">Ganchos (Individuales)</h4>
                  <button 
                    @click="copyField(metadata.leads, 'leads-all')" 
                    class="text-[9px] font-bold text-primary hover:underline transition-all flex items-center gap-1"
                  >
                    <svg v-if="copiedField !== 'leads-all'" class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" /></svg>
                    <svg v-else class="h-2.5 w-2.5 text-green-500" viewBox="0 0 20 20" fill="currentColor"><path d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" /></svg>
                    {{ copiedField === 'leads-all' ? '¡Copiados!' : 'Copiar Todos' }}
                  </button>
                </div>
                <div class="space-y-3 pl-1">
                  <div 
                    v-for="(lead, idx) in metadata.leads.split(/\n\n+|(?<=\.)\s+(?=[¿¡A-Z])|(?<=\?)\s+(?=[¿¡A-Z])|(?<=!)\s+(?=[¿¡A-Z])/).filter(l => l.trim().length > 0)" 
                    :key="idx" 
                    class="group/lead relative flex gap-3 text-[11px] text-text dark:text-dark-text leading-relaxed p-2 rounded-lg hover:bg-secondary/5 dark:hover:bg-primary/5 transition-colors"
                  >
                    <span class="shrink-0 text-primary/40 font-mono mt-0.5">•</span>
                    <p class="flex-1">{{ lead.trim() }}</p>
                    <button 
                      @click="copyField(lead.trim(), `lead-${idx}`)"
                      class="shrink-0 p-1.5 rounded-md bg-secondary/5 dark:bg-dark-surface opacity-0 group-hover/lead:opacity-100 transition-all hover:bg-primary/10 text-secondary hover:text-primary"
                      title="Copiar este gancho"
                    >
                      <svg v-if="copiedField === `lead-${idx}`" class="h-3.5 w-3.5 text-green-500" viewBox="0 0 20 20" fill="currentColor"><path d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" /></svg>
                      <svg v-else class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                    </button>
                  </div>
                </div>
              </div>


              <!-- Newsletter Section -->
              <div class="space-y-4">
                <h4 class="text-[10px] font-black text-secondary/40 dark:text-dark-text/30 uppercase tracking-widest border-b border-secondary/10 dark:border-dark-border pb-2">Email Newsletter</h4>
                
                <div class="flex flex-col gap-1.5">
                  <div class="flex items-center justify-between">
                    <label class="text-[9px] font-bold text-indigo-500 uppercase tracking-wider">Asunto</label>
                    <button @click="copyField(metadata.emailTitle, 'em_title')" class="text-[9px] font-bold text-primary hover:underline">
                      {{ copiedField === 'em_title' ? '¡Hecho!' : 'Copiar' }}
                    </button>
                  </div>
                  <div class="px-2.5 py-2 bg-indigo-50/30 dark:bg-indigo-900/10 border border-indigo-100 dark:border-indigo-500/20 rounded-lg text-[10px] font-bold text-indigo-700 dark:text-indigo-400 transition-colors">{{ metadata.emailTitle }}</div>
                </div>

                <div class="flex flex-col gap-1.5">
                  <div class="flex items-center justify-between">
                    <label class="text-[9px] font-bold text-indigo-500 uppercase tracking-wider">Cuerpo</label>
                    <button @click="copyField(metadata.emailText, 'em_text')" class="text-[9px] font-bold text-primary hover:underline">
                      {{ copiedField === 'em_text' ? '¡Hecho!' : 'Copiar' }}
                    </button>
                  </div>
                  <div class="px-2.5 py-2 bg-indigo-50/30 dark:bg-indigo-900/10 border border-indigo-100 dark:border-indigo-500/20 rounded-lg text-[10px] text-indigo-700/80 dark:text-indigo-400/80 whitespace-pre-wrap transition-colors">{{ metadata.emailText }}</div>
                </div>
              </div>
            </div>
          </aside>

          <!-- COMPARATOR TAB -->
          <aside 
            v-if="competitorUrl && activeSidebarTab === 'comparator'" 
            class="absolute inset-0 flex flex-col overflow-hidden"
          >
            <ComparatorSidebar :competitor-url="competitorUrl" />
          </aside>

        </div>
      </transition>
    </div>
  </div>
</template>

<style scoped>
@reference "../../../assets/main.css";

/* Table Responsive Fix */
:deep(.prose table) {
  @apply block w-full overflow-x-auto border-collapse;
  -webkit-overflow-scrolling: touch;
}

:deep(.prose thead) {
  @apply bg-secondary/5 dark:bg-dark-surface;
}

:deep(.prose th), :deep(.prose td) {
  @apply border border-secondary/10 dark:border-dark-border px-4 py-2 min-w-[120px];
}
</style>
