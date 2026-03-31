import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { OutlineItem, ReferenceLink, LeadItem } from '../types';

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

export const useArticleStore = defineStore('article', () => {
  // Step 1 State
  const blogTitle = ref('');
  const keywords = ref<string[]>([]);
  const keyPoints = ref<string[]>([]);
  const masterDLeads = ref<LeadItem[]>([]);
  const referenceUrls = ref<string[]>([]);
  const scrapedReferences = ref<{url: string, title: string}[]>([]);
  const additionalContext = ref('');

  // Advanced Context
  const audienceValue = ref(0); // Default to 'General'
  const searchIntent = ref('Informativo');
  const lastAppliedIntentPrompt = ref('');

  // Content Mode
  const contentMode = ref<ContentMode>(null);

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
      applyIntentPrompt(preset.searchIntent);
    }
  }

  function applyIntentPrompt(intent: string) {
    const newPrompt = INTENT_PROMPTS[intent] || '';
    if (!newPrompt) return;

    if (!additionalContext.value.trim()) {
      // If empty, just set it
      additionalContext.value = newPrompt;
    } else if (lastAppliedIntentPrompt.value && additionalContext.value.includes(lastAppliedIntentPrompt.value)) {
      // Smart replace: swap the old preset prompt with the new one
      additionalContext.value = additionalContext.value.replace(lastAppliedIntentPrompt.value, newPrompt);
    } else {
      // Append if it's not already there and we can't safely replace
      if (!additionalContext.value.includes(newPrompt)) {
        additionalContext.value = newPrompt + '\n\n' + additionalContext.value;
      }
    }
    
    lastAppliedIntentPrompt.value = newPrompt;
  }

  // Actions to conveniently get payload data
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
      referenceUrls: scrapedReferences.value.map(r => r.url).concat(referenceUrls.value)
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
      includeLists: includeLists.value,
      includeTables: includeTables.value,
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

  return {
    // State
    blogTitle,
    keywords,
    keyPoints,
    masterDLeads,
    referenceUrls,
    scrapedReferences,
    additionalContext,
    audienceValue,
    searchIntent,
    contentMode,
    toneValue,
    includeLists,
    includeTables,
    sectionCount,
    outlineList,
    suggestedLinks,
    uploadedImages,
    // Actions
    getAudienceLabel,
    getToneLabel,
    applyModePresets,
    applyIntentPrompt,
    getGenerateOutlinePayload,
    getGenerateArticlePayload
  };
});
