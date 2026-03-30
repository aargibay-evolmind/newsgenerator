import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { OutlineItem, ReferenceLink } from '../types';

export type ContentMode = 'quick-guide' | 'news-brief' | 'deep-dive' | 'storytelling' | null;

const MODE_PRESETS: Record<Exclude<ContentMode, null>, { sectionCount: number; toneValue: number; audienceValue: number; searchIntent: string }> = {
  'quick-guide':  { sectionCount: 5, toneValue: 50, audienceValue: 0,  searchIntent: 'Tutorial' },
  'news-brief':   { sectionCount: 5, toneValue: 25, audienceValue: 0,  searchIntent: 'Informativo' },
  'deep-dive':    { sectionCount: 9, toneValue: 50, audienceValue: 50, searchIntent: 'Informativo' },
  'storytelling': { sectionCount: 7, toneValue: 75, audienceValue: 0,  searchIntent: 'Informativo' },
};

export const useArticleStore = defineStore('article', () => {
  // Step 1 State
  const blogTitle = ref('');
  const keywords = ref<string[]>([]);
  const keyPoints = ref<string[]>([]);
  const referenceUrls = ref<string[]>([]);
  const scrapedReferences = ref<{url: string, title: string}[]>([]);
  const additionalContext = ref('');

  // Advanced Context
  const audienceValue = ref(0); // Default to 'General'
  const searchIntent = ref('Informativo');

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
    }
  }

  // Actions to conveniently get payload data
  function getGenerateOutlinePayload() {
    return {
      title: blogTitle.value,
      keywords: keywords.value,
      keyPoints: keyPoints.value,
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
    getGenerateOutlinePayload,
    getGenerateArticlePayload
  };
});
