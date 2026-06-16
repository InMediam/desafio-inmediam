import { createBrowserRouter } from 'react-router-dom'

import { NotFound } from '@/pages/404'
import { Billing } from '@/pages/billing/billing'
import { Error } from '@/pages/error'
import { Home } from '@/pages/home'

export const router = createBrowserRouter([
  {
    path: '/',
    errorElement: <Error />,
    children: [
      {
        path: '/billing/:id',
        element: <Billing />,
      },
      {
        index: true,
        element: <Home />,
      },
      {
        path: '*',
        element: <NotFound />,
      },
    ],
  },
])
