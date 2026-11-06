import { createBrowserRouter } from 'react-router-dom'

import { NotFound } from '@/pages/404'
import { Billing } from '@/pages/billing/billing'
import { ErrorPage } from '@/pages/error'
import { Home } from '@/pages/home'

export const router = createBrowserRouter([
  {
    path: '/',
    errorElement: <ErrorPage />,
    children: [
      {
        path: '/billing/:id',
        element: <Billing />,
      },
      {
        path: '',
        element: <Home />,
      },
      {
        path: '*',
        element: <NotFound />,
      },
    ],
  },
])
