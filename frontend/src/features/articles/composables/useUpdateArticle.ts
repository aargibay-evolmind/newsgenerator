import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { ArticleAPI } from '../api';
import type { SaveArticleRequest, SavedArticle } from '../types';

export function useUpdateArticle() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, data }: { id: string; data: SaveArticleRequest }) => 
      ArticleAPI.updateArticle(id, data),
    onSuccess: (data: SavedArticle) => {
      queryClient.invalidateQueries({ queryKey: ['saved-articles'] });
      queryClient.setQueryData(['saved-article', data.id], data);
    },
  });
}
