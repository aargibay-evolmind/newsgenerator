import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { ArticleAPI } from '../api';

export function useSaveArticle() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ArticleAPI.saveArticle,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['saved-articles'] });
    },
  });
}
