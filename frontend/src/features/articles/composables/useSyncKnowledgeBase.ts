import { useMutation } from '@tanstack/vue-query';
import { ArticleAPI } from '../api';

export function useSyncKnowledgeBase() {
  return useMutation({
    mutationFn: () => ArticleAPI.syncKnowledgeBase(),
  });
}
