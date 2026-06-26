import axios from 'axios'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000/api',
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

export function getApiErrorMessage(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined

    if (data?.errors) {
      const firstError = Object.values(data.errors).flat()[0]
      if (firstError) return firstError
    }

    return data?.message ?? 'Não foi possível completar a operação.'
  }

  return 'Não foi possível completar a operação.'
}
