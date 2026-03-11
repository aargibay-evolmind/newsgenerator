import { useMutation } from '@tanstack/vue-query';
import { ArticleAPI } from '../api';
import type { GenerateOutlineRequest } from '../types';

export function useGenerateOutline() {
  return useMutation({
    mutationFn: (data: GenerateOutlineRequest) => ArticleAPI.generateOutline(data),
  });
}
