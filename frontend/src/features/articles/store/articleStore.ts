import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { OutlineItem, ReferenceLink } from '../types';

export const useArticleStore = defineStore('article', () => {
  // Step 1 State
  const blogTitle = ref('');
  const keywords = ref<string[]>([]);
  const referenceUrls = ref<string[]>([]);
  const scrapedReferences = ref<{url: string, title: string}[]>([]);

  // Step 2 State (Config)
  const toneValue = ref(50);
  const articleLength = ref('medium');
  const includeLists = ref(true);
  const includeTables = ref(false);
  
  // Step 2 State (Plan)
  const outlineList = ref<OutlineItem[]>([]);
  const suggestedLinks = ref<ReferenceLink[]>([]);

  // Actions to conveniently get payload data
  function getGenerateOutlinePayload() {
    return {
      title: blogTitle.value,
      keywords: keywords.value,
      referenceUrls: scrapedReferences.value.map(r => r.url).concat(referenceUrls.value)
    };
  }

  function getGenerateArticlePayload() {
    return {
      title: blogTitle.value,
      keywords: keywords.value,
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
    referenceUrls,
    scrapedReferences,
    toneValue,
    articleLength,
    includeLists,
    includeTables,
    outlineList,
    suggestedLinks,
    // Actions
    getGenerateOutlinePayload,
    getGenerateArticlePayload
  };
});
