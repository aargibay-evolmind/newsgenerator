import { useMutation } from '@tanstack/vue-query';
import { ArticleAPI } from '../api';
import type { GenerateArticleRequest } from '../types';

export function useGenerateArticle() {
  return useMutation({
    mutationFn: (data: GenerateArticleRequest) => ArticleAPI.generateArticle(data),
  });
}
