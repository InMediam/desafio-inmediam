import { Badge } from '@inmediam/ui'
import { useQuery } from '@tanstack/react-query'
import { Loader2 } from 'lucide-react'
import { Link } from 'react-router-dom'

import InMediamShield from '@/assets/inmediam-shield.svg'
import MediamLogo from '@/assets/mediam.svg'
import { api, getApiErrorMessage } from '@/lib/api'
import { getBillingStatusDisplay } from '@/utils/billing-status'

interface BillingListItem {
  id: number
  status: string
  plan: { name: string }
  customer: { name: string }
}

export function Home() {
  const { data: billings, isLoading, isError, error, refetch, isFetching } =
    useQuery({
      queryKey: ['billings'],
      queryFn: async () => {
        const response = await api.get<BillingListItem[]>('/billing')
        return response.data
      },
    })

  return (
    <div className="mx-auto flex min-h-screen w-full flex-col items-center justify-center gap-8 bg-muted p-6">
      <div className="w-full max-w-md rounded-lg border border-border bg-card p-6 shadow-sm">
        <div className="mb-6 flex items-center justify-center gap-1">
          <img
            src={InMediamShield}
            className="h-8 w-8"
            alt="Logo da empresa InMediam"
          />
          <img src={MediamLogo} alt="Logo da empresa InMediam" />
        </div>

        <h1 className="text-2xl font-bold text-foreground">
          Pagamento de assinaturas
        </h1>
        <p className="mt-2 text-sm text-muted-foreground">
          Selecione uma cobrança para visualizar os detalhes e realizar o
          pagamento.
        </p>

        <div className="mt-6 space-y-3">
          {isLoading && (
            <div className="flex items-center justify-center py-8">
              <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
            </div>
          )}

          {isError && (
            <div className="space-y-3 text-center">
              <p className="text-sm text-error-500">
                {getApiErrorMessage(
                  error,
                  'Não foi possível carregar as cobranças.',
                )}
              </p>
              <button
                type="button"
                onClick={() => refetch()}
                disabled={isFetching}
                className="text-sm font-medium text-foreground underline-offset-4 hover:underline disabled:opacity-50"
              >
                Tentar novamente
              </button>
            </div>
          )}

          {!isLoading && !isError && billings?.length === 0 && (
            <p className="py-8 text-center text-sm text-muted-foreground">
              Nenhuma cobrança disponível no momento.
            </p>
          )}

          {billings?.map((billing) => {
            const statusDisplay = getBillingStatusDisplay(billing.status)

            return (
              <Link
                key={billing.id}
                to={`/billing/${billing.id}`}
                className="flex items-center justify-between rounded-md border border-border p-4 transition-colors hover:bg-muted"
              >
                <div>
                  <p className="font-semibold text-foreground">
                    {billing.plan.name}
                  </p>
                  <p className="text-sm text-muted-foreground">
                    {billing.customer.name}
                  </p>
                </div>
                <Badge variant={statusDisplay.variant}>
                  {statusDisplay.label}
                </Badge>
              </Link>
            )
          })}
        </div>
      </div>
    </div>
  )
}
