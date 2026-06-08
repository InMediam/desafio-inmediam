import axios, { isAxiosError } from 'axios'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api',
})

export function getApiErrorMessage(
  error: unknown,
  fallback = 'Ocorreu um erro inesperado.',
): string {
  if (isAxiosError(error) && error.response?.data?.error) {
    return String(error.response.data.error)
  }

  if (isAxiosError(error) && error.response?.data?.message) {
    return String(error.response.data.message)
  }

  if (error instanceof Error) {
    return error.message
  }

  if (typeof error === 'string') {
    return error
  }

  return fallback
}
