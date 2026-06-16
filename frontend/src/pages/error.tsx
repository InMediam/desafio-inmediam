import { Button } from '@inmediam/ui'
import { isRouteErrorResponse, NavLink, useRouteError } from 'react-router-dom'

import NotFoundImage from '../assets/not-found.svg'

function getErrorMessage(error: unknown): string {
  if (isRouteErrorResponse(error)) {
    return `${error.status} ${error.statusText}`
  }

  if (error instanceof globalThis.Error) {
    return error.message
  }

  return 'Não foi possível carregar a página.'
}

function ErrorPage() {
  const error = useRouteError()
  const errorMessage = getErrorMessage(error)

  return (
    <div className="flex min-h-screen flex-col items-center justify-center gap-2 bg-background px-6 text-center">
      <img
        src={NotFoundImage}
        alt="Ilustração indicando página não encontrada"
        className="mx-auto w-full max-w-5xl"
      />
      <h1 className="text-4xl/10 font-bold text-gray-900 dark:text-gray-100">
        Whoops, algo aconteceu...
      </h1>
      <p className="mt-4 max-w-3xl text-lg/7 font-normal text-gray-600 dark:text-gray-200">
        Um erro aconteceu na aplicação, abaixo você encontra mais detalhes.
      </p>
      <pre className="mt-4 max-w-3xl whitespace-pre-wrap rounded-md bg-muted px-4 py-3 text-left text-sm text-gray-900 dark:text-gray-100">
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

export { ErrorPage as Error }
