import { geminiClient } from '@/lib/geminiClient'

export const TestAPI = {
  askGemini: async (prompt: string): Promise<string> => {
    const response = await geminiClient.models.generateContent({
      model: 'gemini-3-flash-preview',
      contents: prompt,
    })

    return response.text ?? ''
  },
}
