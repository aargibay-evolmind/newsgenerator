import { useQuery } from '@tanstack/vue-query';
import { ArticleAPI } from '../api';

export function useSavedArticles() {
  return useQuery({
    queryKey: ['saved-articles'],
    queryFn: ArticleAPI.getSavedArticles,
  });
}
