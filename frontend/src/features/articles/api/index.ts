import { apiClient } from '@/lib/apiClient';
import type { 
  TopicSuggestionRequest, 
  TopicSuggestionResponse,
  ScrapeUrlRequest,
  ScrapeUrlResponse,
  GenerateOutlineRequest,
  GenerateOutlineResponse,
  GenerateArticleRequest,
  GenerateArticleResponse,
  RegenerateSectionRequest,
  RegenerateSectionResponse
} from '../types';

export const ArticleAPI = {
  suggestTopics: (data: TopicSuggestionRequest) => 
    apiClient<TopicSuggestionResponse>('/suggest-topics', { 
      method: 'POST', 
      body: JSON.stringify(data) 
    }),

  scrapeUrl: (data: ScrapeUrlRequest) => 
    apiClient<ScrapeUrlResponse>('/scrape-urls', { 
      method: 'POST', 
      body: JSON.stringify(data) 
    }),

  generateOutline: (data: GenerateOutlineRequest) => 
    apiClient<GenerateOutlineResponse>('/generate-outline', { 
      method: 'POST', 
      body: JSON.stringify(data) 
    }),

  generateArticle: (data: GenerateArticleRequest) => 
    apiClient<GenerateArticleResponse>('/generate-article', { 
      method: 'POST', 
      body: JSON.stringify(data) 
    }),

  regenerateSection: (data: RegenerateSectionRequest) =>
    apiClient<RegenerateSectionResponse>('/regenerate-section', {
      method: 'POST',
      body: JSON.stringify(data)
    }),
};
