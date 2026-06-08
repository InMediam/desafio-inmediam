import { QueryClientProvider } from '@tanstack/react-query'
import { ThemeProvider } from 'next-themes'
import { RouterProvider } from 'react-router-dom'

import { Toaster } from '@/components/sonner'
import { queryClient } from '@/lib/react-query'
import { router } from '@/routes'

export function App() {
  return (
    <ThemeProvider attribute="class" defaultTheme="light" enableSystem>
      <QueryClientProvider client={queryClient}>
        <Toaster />
        <RouterProvider
          router={router}
          future={{ v7_startTransition: true }}
        />
      </QueryClientProvider>
    </ThemeProvider>
  )
}
