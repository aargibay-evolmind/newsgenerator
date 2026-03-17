import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { OutlineItem, ReferenceLink } from '../types';

export const useArticleStore = defineStore('article', () => {
  // Step 1 State
  const blogTitle = ref('');
  const keywords = ref<string[]>([]);
  const keyPoints = ref<string[]>([]);
  const referenceUrls = ref<string[]>([]);
  const scrapedReferences = ref<{url: string, title: string}[]>([]);
  const additionalContext = ref('');

  // Advanced Context
  const audience = ref('General');
  const searchIntent = ref('Informativo');

  // Step 2 State (Config)
  const toneValue = ref(0);
  const articleLength = ref('medium');
  const includeLists = ref(true);
  const includeTables = ref(false);
  
  // Step 2 State (Plan)
  const outlineList = ref<OutlineItem[]>([]);
  const suggestedLinks = ref<ReferenceLink[]>([]);
  const uploadedImages = ref<{id: string, name: string, data: string}[]>([]);

  // Actions to conveniently get payload data
  function getGenerateOutlinePayload() {
    return {
      title: blogTitle.value,
      keywords: keywords.value,
      keyPoints: keyPoints.value,
      audience: audience.value,
      searchIntent: searchIntent.value,
      additionalContext: additionalContext.value,
      referenceUrls: scrapedReferences.value.map(r => r.url).concat(referenceUrls.value)
    };
  }

  function getGenerateArticlePayload() {
    return {
      title: blogTitle.value,
      keywords: keywords.value,
      keyPoints: keyPoints.value,
      audience: audience.value,
      searchIntent: searchIntent.value,
      additionalContext: additionalContext.value,
      tone: toneValue.value,
      articleLength: articleLength.value,
      includeLists: includeLists.value,
      includeTables: includeTables.value,
      // Only send included items
      outline: outlineList.value.filter(item => item.included),
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
    audience,
    searchIntent,
    toneValue,
    articleLength,
    includeLists,
    includeTables,
    outlineList,
    suggestedLinks,
    uploadedImages,
    // Actions
    getGenerateOutlinePayload,
    getGenerateArticlePayload
  };
});
