import { useMutation } from '@tanstack/vue-query';
import { ArticleAPI } from '../api';
import type { RegenerateSectionRequest } from '../types';

export function useRegenerateSection() {
  return useMutation({
    mutationFn: (data: RegenerateSectionRequest) => ArticleAPI.regenerateSection(data),
  });
}
