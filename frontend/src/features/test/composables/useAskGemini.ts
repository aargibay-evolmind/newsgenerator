import { useMutation } from '@tanstack/vue-query'
import { TestAPI } from '../api'

export function useAskGemini() {
  return useMutation({
    mutationFn: (prompt: string) => TestAPI.askGemini(prompt),
  })
}
