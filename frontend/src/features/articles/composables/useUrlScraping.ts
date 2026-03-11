import { useMutation } from '@tanstack/vue-query';
import { ArticleAPI } from '../api';
import type { ScrapeUrlRequest } from '../types';

export function useUrlScraping() {
  return useMutation({
    mutationFn: (data: ScrapeUrlRequest) => ArticleAPI.scrapeUrl(data),
  });
}
