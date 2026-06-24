import { QueryClientProvider } from '@tanstack/react-query'
import { RouterProvider } from 'react-router-dom'

import { Toaster } from '@/components/sonner'
import { queryClient } from '@/lib/react-query'
import { router } from '@/routes'

export function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <Toaster />
      <RouterProvider router={router} future={{ v7_startTransition: true }} />
    </QueryClientProvider>
  )
}
