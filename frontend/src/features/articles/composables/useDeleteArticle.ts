import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { ArticleAPI } from '../api';

export function useDeleteArticle() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: string) => ArticleAPI.deleteArticle(id),
    onSuccess: () => {
      // Invalidate both the list and any specific article query
      queryClient.invalidateQueries({ queryKey: ['saved-articles'] });
    },
  });
}
