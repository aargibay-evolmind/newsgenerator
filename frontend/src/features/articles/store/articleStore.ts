import { defineStore } from 'pinia';
import { ref, watch, computed } from 'vue';
import type { OutlineItem, ReferenceLink, LeadItem } from '../types';
import { BLOGS, type Blog } from '../constants/blogs';

export type ContentMode = 'quick-guide' | 'news-brief' | 'deep-dive' | 'storytelling' | null;

const MODE_PRESETS: Record<Exclude<ContentMode, null>, { sectionCount: number; toneValue: number; audienceValue: number; searchIntent: string }> = {
  'quick-guide':  { sectionCount: 5, toneValue: 50, audienceValue: 0,  searchIntent: 'Tutorial' },
  'news-brief':   { sectionCount: 5, toneValue: 25, audienceValue: 0,  searchIntent: 'Informativo' },
  'deep-dive':    { sectionCount: 9, toneValue: 50, audienceValue: 50, searchIntent: 'Informativo' },
  'storytelling': { sectionCount: 7, toneValue: 75, audienceValue: 0,  searchIntent: 'Informativo' },
};

const INTENT_PROMPTS: Record<string, string> = {
  'Informativo': 'Objetivo: Informar de manera neutral y objetiva. Responde a las 5W (quién, qué, cuándo, dónde, por qué) con claridad. Estructura la información de lo más importante a lo menos relevante.',
  'Tutorial': 'Objetivo: Instruir paso a paso. Utiliza un lenguaje directo y accionable (\'Haz clic\', \'Envía\', \'Prepara\'). Asegúrate de enumerar los requisitos y seguir una secuencia lógica.',
  'Transaccional': 'Objetivo: Persuadir e impulsar la acción. Destaca los beneficios de la formación y el crecimiento profesional. Incluye llamadas a la acción directas y alentadoras para captar el interés del alumno.',
  'Comparativo': 'Objetivo: Analizar y comparar. Establece paralelismos claros entre las distintas opciones, destacando ventajas, inconvenientes y requisitos de cada camino para ayudar en la decisión.'
};

const MODE_PROMPTS: Record<string, string> = {
  'quick-guide':  'Estilo Guía Rápida: Párrafos de máximo 3 frases. Prioriza bullet points y listas. Sin rodeos, cada sección responde una pregunta concreta.',
  'news-brief':   'Estilo Noticia Breve: Estructura de pirámide invertida. Párrafos de 2-4 frases, directos y factuales. Tono periodístico neutral.',
  'deep-dive':    'Estilo Inmersivo: Párrafos ricos y detallados (5-8 frases). Incluye análisis profundo, contexto histórico y comparativas.',
  'storytelling': 'Estilo Crónica: Narrativo y envolvente. Comienza secciones con ganchos emocionales o anécdotas. Ritmo variable y transiciones fluidas.'
};

export const useArticleStore = defineStore('article', () => {
  // Step 1 State
  const blogTitle = ref('');
  const keywords = ref<string[]>([]);
  const keyPoints = ref<string[]>([]);
  const masterDLeads = ref<LeadItem[]>([]);
  const exampleUrls = ref<string[]>([]);
  const authorityUrls = ref<string[]>([]);
  const scrapedExampleUrls = ref<{url: string, title: string, content?: string}[]>([]);
  const scrapedAuthorityUrls = ref<{url: string, title: string, content?: string}[]>([]);
  const additionalContext = ref('');

  // Advanced Context
  const audienceValue = ref(0); // Default to 'General'
  const searchIntent = ref('Informativo');
  const lastAppliedIntentPrompt = ref('');

  // Content Mode
  const contentMode = ref<ContentMode>(null);
  
  // Custom Blogs Management
  const customBlogs = ref<Blog[]>([]);
  
  // Load custom blogs from localStorage on initialization
  try {
    const stored = localStorage.getItem('newsgenerator_custom_blogs');
    if (stored) {
      customBlogs.value = JSON.parse(stored);
    }
  } catch (e) {
    console.error('Failed to load custom blogs from localStorage', e);
  }

  // Merged available blogs
  const availableBlogs = computed(() => {
    return [...BLOGS, ...customBlogs.value];
  });

  const selectedBlogId = ref<number | null>(null);

  function addCustomBlog(name: string, url: string) {
    const newId = Date.now(); // Generate a unique ID (timestamp-based to avoid collision with standard 1-2 digit IDs)
    const newBlog: Blog = { id: newId, name, url };
    
    customBlogs.value.push(newBlog);
    
    // Persist to localStorage
    try {
      localStorage.setItem('newsgenerator_custom_blogs', JSON.stringify(customBlogs.value));
    } catch (e) {
      console.error('Failed to save custom blog to localStorage', e);
    }
    
    return newId;
  }

  // Step 2 State (Config)
  const toneValue = ref(0);
  const includeLists = ref(true);
  const includeTables = ref(false);
  const sectionCount = ref(7);
  
  // Step 2 State (Plan)
  const outlineList = ref<OutlineItem[]>([]);
  const suggestedLinks = ref<ReferenceLink[]>([]);
  const uploadedImages = ref<{id: string, name: string, data: string}[]>([]);

  function getAudienceLabel(val: number): string {
    if (val < 33) return 'General';
    if (val < 66) return 'Profesional';
    return 'Especializado';
  }

  function getToneLabel(val: number): string {
    if (val < 33) return 'Profesional';
    if (val < 66) return 'Cercano';
    return 'Viral/Audaz';
  }

  function applyModePresets(mode: ContentMode) {
    contentMode.value = mode;
    if (mode && MODE_PRESETS[mode]) {
      const preset = MODE_PRESETS[mode];
      sectionCount.value = preset.sectionCount;
      toneValue.value = preset.toneValue;
      audienceValue.value = preset.audienceValue;
      searchIntent.value = preset.searchIntent;
      // applyIntentPrompt will be triggered by the watch(searchIntent)
    }
  }

  function applyIntentPrompt(intent: string, mode: ContentMode = null) {
    const intentPrompt = INTENT_PROMPTS[intent] || '';
    const modePrompt = mode ? (MODE_PROMPTS[mode] || '') : '';
    
    // Build combined prompt: Mode specific style + Intent objective
    let newPrompt = '';
    if (modePrompt && intentPrompt) {
      newPrompt = `${modePrompt} ${intentPrompt}`;
    } else {
      newPrompt = modePrompt || intentPrompt;
    }

    if (!newPrompt) return;

    const currentContext = additionalContext.value.trim();

    if (!currentContext) {
      // If empty, just set it
      additionalContext.value = newPrompt;
    } else if (lastAppliedIntentPrompt.value && additionalContext.value.includes(lastAppliedIntentPrompt.value)) {
      // Smart replace: swap the old preset prompt with the new one
      additionalContext.value = additionalContext.value.replace(lastAppliedIntentPrompt.value, newPrompt);
    } else {
      // If the new prompt is already part of the content (maybe appended before), don't add it again
      if (!additionalContext.value.includes(newPrompt)) {
        // Append at the top but separate from existing content
        additionalContext.value = newPrompt + '\n\n' + additionalContext.value;
      }
    }
    
    lastAppliedIntentPrompt.value = newPrompt;
  }

  // Reactive watcher to ensure prompts stay in sync when either the intent or the mode changes
  watch([searchIntent, contentMode], ([newIntent, newMode]) => {
    applyIntentPrompt(newIntent, newMode as ContentMode);
  }, { immediate: false });

  function getGenerateOutlinePayload() {
    return {
      title: blogTitle.value,
      keywords: keywords.value,
      keyPoints: keyPoints.value,
      masterDLeads: masterDLeads.value.map(l => l.text),
      audience: getAudienceLabel(audienceValue.value),
      searchIntent: searchIntent.value,
      additionalContext: additionalContext.value,
      tone: toneValue.value,
      sectionCount: sectionCount.value,
      contentMode: contentMode.value,
      blogId: selectedBlogId.value,
      exampleUrls: scrapedExampleUrls.value.map(r => ({ url: r.url, title: r.title, content: r.content })),
      authorityUrls: scrapedAuthorityUrls.value.map(r => ({ url: r.url, title: r.title, content: r.content }))
    };
  }

  function getGenerateArticlePayload() {
    return {
      title: blogTitle.value,
      keywords: keywords.value,
      keyPoints: keyPoints.value,
      masterDLeads: masterDLeads.value.filter(l => l.included).map(l => l.text),
      audience: getAudienceLabel(audienceValue.value),
      searchIntent: searchIntent.value,
      additionalContext: additionalContext.value,
      tone: toneValue.value,
      contentMode: contentMode.value,
      blogId: selectedBlogId.value,
      includeLists: includeLists.value,
      includeTables: includeTables.value,
      exampleUrls: scrapedExampleUrls.value.map(r => ({ url: r.url, title: r.title, content: r.content })),
      authorityUrls: scrapedAuthorityUrls.value.map(r => ({ url: r.url, title: r.title, content: r.content })),
      // Only send included items
      outline: outlineList.value
        .filter(item => item.included)
        .map(item => ({
          ...item,
          infographic: !!item.infographic
        })),
      references: suggestedLinks.value.filter(link => link.included)
    };
  }

  const resetTrigger = ref(0);

  function reset() {
    blogTitle.value = '';
    keywords.value = [];
    keyPoints.value = [];
    masterDLeads.value = [];
    exampleUrls.value = [];
    authorityUrls.value = [];
    scrapedExampleUrls.value = [];
    scrapedAuthorityUrls.value = [];
    additionalContext.value = '';
    audienceValue.value = 0;
    searchIntent.value = 'Informativo';
    lastAppliedIntentPrompt.value = '';
    contentMode.value = null;
    selectedBlogId.value = null;
    toneValue.value = 0;
    includeLists.value = true;
    includeTables.value = false;
    sectionCount.value = 7;
    outlineList.value = [];
    suggestedLinks.value = [];
    uploadedImages.value = [];
    
    resetTrigger.value++;
  }

  return {
    // State
    blogTitle,
    keywords,
    keyPoints,
    masterDLeads,
    exampleUrls,
    authorityUrls,
    scrapedExampleUrls,
    scrapedAuthorityUrls,
    additionalContext,
    audienceValue,
    searchIntent,
    contentMode,
    customBlogs,
    availableBlogs,
    selectedBlogId,
    toneValue,
    includeLists,
    includeTables,
    sectionCount,
    outlineList,
    suggestedLinks,
    uploadedImages,
    // Actions
    addCustomBlog,
    getAudienceLabel,
    getToneLabel,
    applyModePresets,
    applyIntentPrompt,
    getGenerateOutlinePayload,
    getGenerateArticlePayload,
    resetTrigger,
    reset
  };
});
