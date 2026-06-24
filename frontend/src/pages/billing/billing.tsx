import { Badge } from '@inmediam/ui'
import { useQuery } from '@tanstack/react-query'
import { Link, useParams } from 'react-router-dom'

import InMediamShield from '@/assets/inmediam-shield.svg'
import MediamLogo from '@/assets/mediam.svg'
import { api } from '@/lib/api'
import { currencyFormatter, formatDate } from '@/utils/formatter'

import { PaymentConcluded } from './components/payment-concluded'
import { PaymentForm } from './components/payment-form'

export function Billing() {
  const { id } = useParams<{ id: string }>()

  const { data } = useQuery({
    queryKey: ['billing', id],
    queryFn: async () => {
      const response = await api.get(`/billing/${id}`)
      return response.data
    },
  })

  return (
    <div className="flex h-screen w-full flex-row items-start gap-8 bg-muted px-20 py-20 pb-40">
      <div className="flex w-full flex-col">
        <Link
          to="/"
          className="mb-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
        >
          Voltar pra Home
        </Link>

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

          <div className="mt-6 space-y-2">
            <div className="flex items-center justify-between">
              <span className="text-sm text-muted-foreground">Plano</span>
              <span className="font-semibold text-foreground">
                {data?.plan.name}
              </span>
            </div>

            <p className="text-sm text-muted-foreground">
              {data?.plan.description}
            </p>

            <div className="flex items-center justify-between border-t border-border pt-4">
              <span className="text-sm text-muted-foreground">Valor</span>
              <span className="font-bold text-foreground">
                {currencyFormatter.format(data?.amount)}
              </span>
            </div>

            <div className="flex items-center justify-between">
              <span className="text-sm text-muted-foreground">Vencimento</span>
              <span className="text-normal text-foreground">
                {data?.due_date && formatDate(data.due_date)}
              </span>
            </div>

            <div className="flex items-center justify-between">
              <span className="text-sm text-muted-foreground">Status</span>
              <Badge variant={data?.status === 'paid' ? 'success' : 'warning'}>
                {data?.status === 'paid' ? 'Pago' : 'Pendente'}
              </Badge>
            </div>
          </div>
        </div>
      </div>

      {data && data.status === 'pending' && <PaymentForm billingId={id!} />}

      {data && data.status === 'paid' && data.payments?.[0] && (
        <PaymentConcluded payment={data.payments[0]} />
      )}
    </div>
  )
}
