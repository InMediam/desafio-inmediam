import { Button } from '@inmediam/ui'
import { isAxiosError } from 'axios'
import { NavLink, useRouteError } from 'react-router-dom'

import { getApiErrorMessage } from '@/lib/api'

import NotFoundImage from '../assets/not-found.svg'

export function ErrorPage() {
  const error = useRouteError()
  const axiosError = isAxiosError(error)
  const errorMessage = axiosError
    ? getApiErrorMessage(error)
    : (error as Error)?.message || JSON.stringify(error)

  return (
    <div className="flex h-screen flex-col items-center justify-center gap-2 bg-background">
      <img
        src={NotFoundImage}
        alt="Ilustração indicando página não encontrada"
        className="mx-auto -mb-80 -mt-[19rem] w-[56rem]"
      />
      <h1 className="text-4xl/10 font-bold text-gray-900 dark:text-gray-100">
        Whoops, algo aconteceu...
      </h1>
      <p className="mt-4 max-w-3xl text-center text-lg/7 font-normal text-gray-600 dark:text-gray-200">
        Um erro aconteceu na aplicação, abaixo você encontra mais detalhes:
      </p>
      <pre className="mt-4 text-gray-900 data-[axios-error=true]:hidden dark:text-gray-100">
        {errorMessage}
      </pre>
      <NavLink to="/" className="mt-4">
        <Button size="lg" className="px-4 text-base/6 font-semibold">
          Voltar para a página inicial
        </Button>
      </NavLink>
    </div>
  )
}
