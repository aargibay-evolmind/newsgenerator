import { useMutation } from '@tanstack/vue-query'
import { apiClient } from '@/lib/apiClient'

export interface SuggestHeadingRequest {
  title: string;
  currentOutline: string[];
}

export interface SuggestHeadingResponse {
  heading: string;
}

export function useSuggestHeading() {
  return useMutation({
    mutationFn: (payload: SuggestHeadingRequest) => apiClient<SuggestHeadingResponse>('/articles/suggest-heading', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(payload)
    })
  })
}
