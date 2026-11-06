import { Badge, Button, Skeleton } from '@inmediam/ui'
import { isAxiosError } from 'axios'
import { useParams } from 'react-router-dom'

import InMediamShield from '@/assets/inmediam-shield.svg'
import MediamLogo from '@/assets/mediam.svg'
import { useBilling } from '@/hooks/use-billing'
import { getApiErrorMessage } from '@/lib/api'
import { formatCurrency, formatDate } from '@/utils/formatter'

import { PaymentConcluded } from './components/payment-concluded'
import { PaymentForm } from './components/payment-form'

export function Billing() {
  const { id } = useParams<{ id: string }>()

  const { data, isLoading, isError, error, refetch } = useBilling(id!)

  const isNotFound = isAxiosError(error) && error.response?.status === 404

  return (
    <div className="flex h-screen w-full flex-row items-start gap-8 bg-muted px-20 py-20">
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
          <div className="mt-6 space-y-3">
            <Skeleton className="h-5 w-full" />
            <Skeleton className="h-4 w-full" />
            <Skeleton className="h-5 w-full" />
            <Skeleton className="h-5 w-full" />
          </div>
        )}

        {isError && isNotFound && (
          <div className="mt-6 text-center">
            <p className="font-semibold text-foreground">
              Cobrança não encontrada
            </p>
            <p className="mt-2 text-sm text-muted-foreground">
              Verifique o link acessado e tente novamente.
            </p>
          </div>
        )}

        {isError && !isNotFound && (
          <div className="mt-6 text-center">
            <p className="font-semibold text-foreground">
              Não foi possível carregar a cobrança
            </p>
            <p className="mt-2 text-sm text-muted-foreground">
              {getApiErrorMessage(error)}
            </p>
            <Button className="mt-4" onClick={() => refetch()}>
              Tentar de novo
            </Button>
          </div>
        )}

        {data && (
          <div className="mt-6 space-y-2">
            <div className="flex items-center justify-between">
              <span className="text-sm text-muted-foreground">Cliente</span>
              <span className="font-semibold text-foreground">
                {data.customer.name}
              </span>
            </div>

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
              <Badge variant={data.status === 'paid' ? 'success' : 'warning'}>
                {data.status === 'paid' ? 'Pago' : 'Pendente'}
              </Badge>
            </div>
          </div>
        )}
      </div>

      {data && data.status === 'pending' && <PaymentForm billingId={id!} />}

      {data && data.status === 'paid' && data.payments[0] && (
        <PaymentConcluded payment={data.payments[0]} />
      )}
    </div>
  )
}
