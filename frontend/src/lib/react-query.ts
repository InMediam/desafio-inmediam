import { QueryClient } from '@tanstack/react-query'
import { isAxiosError } from 'axios'

const MAX_QUERY_RETRY_ATTEMPTS = 1

function shouldRetryQuery(failureCount: number, error: unknown): boolean {
  if (failureCount >= MAX_QUERY_RETRY_ATTEMPTS) {
    return false
  }

  if (!isAxiosError(error)) {
    return true
  }

  const status = error.response?.status

  if (status === undefined) {
    return true
  }

  return status >= 500
}

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: shouldRetryQuery,
    },
    mutations: {
      retry: false,
    },
  },
})
