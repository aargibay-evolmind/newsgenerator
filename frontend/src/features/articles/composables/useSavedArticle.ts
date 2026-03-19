import { useQuery } from '@tanstack/vue-query';
import { ArticleAPI } from '../api';

export function useSavedArticle(id: string) {
  return useQuery({
    queryKey: ['saved-article', id],
    queryFn: () => ArticleAPI.getSavedArticle(id),
    enabled: !!id,
  });
}
