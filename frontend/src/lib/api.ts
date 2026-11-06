import axios, { isAxiosError } from 'axios'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
})

const DEFAULT_ERROR_MESSAGE = 'Ocorreu um erro inesperado. Tente novamente.'

export function getApiErrorMessage(error: unknown): string {
  if (!isAxiosError(error)) {
    return DEFAULT_ERROR_MESSAGE
  }

  const data = error.response?.data

  return data?.error ?? data?.message ?? DEFAULT_ERROR_MESSAGE
}
