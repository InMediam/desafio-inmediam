import { Badge, Button } from '@inmediam/ui'
import { useQuery } from '@tanstack/react-query'
import { ArrowLeft, Loader2 } from 'lucide-react'
import { Link, useParams } from 'react-router-dom'

import InMediamShield from '@/assets/inmediam-shield.svg'
import MediamLogo from '@/assets/mediam.svg'
import { api, getApiErrorMessage } from '@/lib/api'
import {
  getBillingStatusDisplay,
  isBillingPaid,
  isBillingPending,
} from '@/utils/billing-status'
import { formatCurrency, formatDate } from '@/utils/formatter'

import { PaymentConcluded } from './components/payment-concluded'
import { PaymentForm } from './components/payment-form'

export function Billing() {
  const { id } = useParams<{ id: string }>()

  const { data, isLoading, isError, error, refetch, isFetching } = useQuery({
    queryKey: ['billing', id],
    queryFn: async () => {
      const response = await api.get(`/billing/${id}`)
      return response.data
    },
    enabled: Boolean(id),
  })

  const statusDisplay = getBillingStatusDisplay(data?.status)

  return (
    <div className="min-h-screen bg-muted p-6 lg:px-20 lg:py-20">
      <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <Button variant="outline" size="sm" asChild className="w-fit">
          <Link to="/">
            <ArrowLeft className="h-4 w-4" />
            Voltar para cobranças
          </Link>
        </Button>

        <div className="flex w-full flex-col items-start gap-8 lg:flex-row">
          <div className="w-full rounded-lg border border-border bg-card p-6 shadow-sm">
            <div className="mb-6 flex items-center justify-center gap-1">
              <img
                src={InMediamShield}
                className="h-8 w-8"
                alt="Logo da empresa InMediam"
              />
              <img src={MediamLogo} alt="Logo da empresa InMediam" />
            </div>

            <h1 className="text-2xl font-bold text-foreground">
              Pagamento de assinatura
            </h1>

            {isLoading && (
              <div className="mt-8 flex items-center justify-center py-12">
                <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
              </div>
            )}

            {isError && (
              <div className="mt-8 space-y-3 text-center">
                <p className="text-sm text-error-500">
                  {getApiErrorMessage(
                    error,
                    'Não foi possível carregar os detalhes da cobrança.',
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

            {data && !isLoading && (
            <div className="mt-6 space-y-2">
              <div className="flex items-center justify-between">
                <span className="text-sm text-muted-foreground">Plano</span>
                <span className="font-semibold text-foreground">
                  {data.plan.name}
                </span>
              </div>

              <p className="text-sm text-muted-foreground">
                {data.plan.description}
              </p>

              <div className="flex items-center justify-between border-t border-border pt-4">
                <span className="text-sm text-muted-foreground">Valor</span>
                <span className="font-bold text-foreground">
                  {formatCurrency(data.amount)}
                </span>
              </div>

              <div className="flex items-center justify-between">
                <span className="text-sm text-muted-foreground">Vencimento</span>
                <span className="text-normal text-foreground">
                  {formatDate(data.due_date)}
                </span>
              </div>

              <div className="flex items-center justify-between">
                <span className="text-sm text-muted-foreground">Status</span>
                <Badge variant={statusDisplay.variant}>
                  {statusDisplay.label}
                </Badge>
              </div>
            </div>
          )}
          </div>

          {data && isBillingPending(data.status) && (
            <PaymentForm billingId={id!} />
          )}

          {data && isBillingPaid(data.status) && data.payments?.[0] && (
            <PaymentConcluded payment={data.payments[0]} />
          )}

          {data && isBillingPaid(data.status) && !data.payments?.[0] && (
            <div className="w-full rounded-lg border border-border bg-card p-6 shadow-sm">
              <p className="text-sm text-muted-foreground">
                Pagamento registrado, mas os detalhes não estão disponíveis.
              </p>
            </div>
          )}

          {data &&
            !isBillingPending(data.status) &&
            !isBillingPaid(data.status) && (
              <div className="w-full rounded-lg border border-border bg-card p-6 shadow-sm">
                <p className="text-sm text-muted-foreground">
                  Esta cobrança não está disponível para pagamento.
                </p>
              </div>
            )}
        </div>
      </div>
    </div>
  )
}
