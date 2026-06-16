import { Badge } from '@inmediam/ui'
import { useQuery } from '@tanstack/react-query'
import { useParams } from 'react-router-dom'

import InMediamShield from '@/assets/inmediam-shield.svg'
import MediamLogo from '@/assets/mediam.svg'
import { api } from '@/lib/api'
import type { Billing as BillingDetails } from '@/types/billing'
import { currencyFormatter } from '@/utils/formatter'

import { PaymentConcluded } from './components/payment-concluded'
import { PaymentForm } from './components/payment-form'

function getStatusLabel(status: BillingDetails['status']): string {
  if (status === 'paid') {
    return 'Pago'
  }

  if (status === 'processing') {
    return 'Processando'
  }

  return 'Pendente'
}

function getStatusVariant(
  status: BillingDetails['status'],
): 'warning' | 'success' {
  return status === 'paid' ? 'success' : 'warning'
}

export function Billing() {
  const { id } = useParams<{ id: string }>()

  const { data, isLoading, isError, error, refetch } = useQuery<BillingDetails>(
    {
      queryKey: ['billing', id],
      enabled: Boolean(id),
      queryFn: async () => {
        const response = await api.get<BillingDetails>(`/billing/${id}`)
        return response.data
      },
    },
  )

  const statusLabel = data ? getStatusLabel(data.status) : 'Pendente'
  const statusVariant = data ? getStatusVariant(data.status) : 'warning'

  return (
    <div className="flex min-h-screen w-full flex-col gap-8 bg-muted px-6 py-10 lg:flex-row lg:px-20 lg:py-20">
      <div className="w-full rounded-lg border border-border bg-card p-6 shadow-sm lg:max-w-xl">
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
          <div className="mt-6 space-y-4">
            <div className="h-5 w-40 rounded bg-muted" />
            <div className="h-14 rounded bg-muted" />
            <div className="h-5 w-28 rounded bg-muted" />
            <div className="h-5 w-24 rounded bg-muted" />
          </div>
        )}

        {isError && (
          <div className="mt-6 space-y-4 rounded-md border border-destructive/30 bg-destructive/10 p-4">
            <p className="text-sm font-medium text-destructive">
              Não conseguimos carregar esta cobrança.
            </p>
            <p className="text-sm text-muted-foreground">
              {error instanceof globalThis.Error
                ? error.message
                : 'Tente novamente em instantes.'}
            </p>
            <button
              type="button"
              onClick={() => refetch()}
              className="text-sm font-semibold text-primary underline-offset-4 hover:underline"
            >
              Tentar novamente
            </button>
          </div>
        )}

        {data && (
          <div className="mt-6 space-y-4">
            <div className="flex items-center justify-between gap-4">
              <span className="text-sm text-muted-foreground">Plano</span>
              <span className="text-right font-semibold text-foreground">
                {data.plan.name}
              </span>
            </div>

            <p className="text-sm leading-6 text-muted-foreground">
              {data.plan.description}
            </p>

            <div className="flex items-center justify-between border-t border-border pt-4">
              <span className="text-sm text-muted-foreground">Valor</span>
              <span className="font-bold text-foreground">
                {currencyFormatter.format(Number(data.amount))}
              </span>
            </div>

            <div className="flex items-center justify-between">
              <span className="text-sm text-muted-foreground">Vencimento</span>
              <span className="text-normal text-foreground">
                {new Date(`${data.due_date}T00:00:00`).toLocaleDateString(
                  'pt-BR',
                )}
              </span>
            </div>

            <div className="flex items-center justify-between">
              <span className="text-sm text-muted-foreground">Status</span>
              <Badge variant={statusVariant}>{statusLabel}</Badge>
            </div>
          </div>
        )}
      </div>

      {data && data.status === 'pending' && <PaymentForm billingId={id!} />}

      {data && data.status === 'processing' && (
        <div className="w-full rounded-lg border border-border bg-card p-6 shadow-sm lg:max-w-xl">
          <h2 className="text-lg font-semibold text-foreground">
            Processando pagamento
          </h2>
          <p className="mt-3 text-sm leading-6 text-muted-foreground">
            Recebemos sua solicitação e estamos finalizando a confirmação com a
            operadora do cartão. Esta tela será atualizada assim que o pagamento
            for concluído.
          </p>
        </div>
      )}

      {data && data.status === 'paid' && data.payments?.[0] && (
        <PaymentConcluded payment={data.payments[0]} />
      )}
    </div>
  )
}
