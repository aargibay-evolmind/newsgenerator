import { useMutation } from '@tanstack/vue-query';
import { ArticleAPI } from '../api';
import type { TopicSuggestionRequest } from '../types';

export function useSuggestTopics() {
  return useMutation({
    mutationFn: (data: TopicSuggestionRequest) => ArticleAPI.suggestTopics(data),
  });
}
