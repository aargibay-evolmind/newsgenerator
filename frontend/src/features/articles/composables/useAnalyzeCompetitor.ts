import { useMutation } from '@tanstack/vue-query'
import { apiClient } from '@/lib/apiClient'
import type { AnalyzeCompetitorRequest, AnalyzeCompetitorResponse } from '../types'

export function useAnalyzeCompetitor() {
  return useMutation({
    mutationFn: async (payload: AnalyzeCompetitorRequest) => {
      return apiClient<AnalyzeCompetitorResponse>('/articles/analyze-competitor', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
      })
    }
  })
}
