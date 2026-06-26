import { Badge, Button } from '@inmediam/ui'
import { useQuery } from '@tanstack/react-query'
import { Loader2 } from 'lucide-react'
import { Link, useParams } from 'react-router-dom'

import InMediamShield from '@/assets/inmediam-shield.svg'
import MediamLogo from '@/assets/mediam.svg'
import { api, getApiErrorMessage } from '@/lib/api'
import { currencyFormatter, dateFormatter } from '@/utils/formatter'

import { PaymentConcluded } from './components/payment-concluded'
import { PaymentForm } from './components/payment-form'

interface BillingResponse {
  id: number
  amount: number | string
  status: 'pending' | 'paid' | string
  due_date: string
  plan: {
    name: string
    description: string
  }
  payments?: Array<{
    amount_paid: number | string
    status: string
    paid_at: string
    credit_card: {
      card_holder_name: string
      card_last_four: string
      card_brand: string
    }
  }>
}

export function Billing() {
  const { id } = useParams<{ id: string }>()

  const {
    data,
    isLoading,
    isError,
    error,
  } = useQuery({
    queryKey: ['billing', id],
    enabled: Boolean(id),
    queryFn: async () => {
      const response = await api.get<BillingResponse>(`/billing/${id}`)
      return response.data
    },
  })

  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-muted">
        <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
      </div>
    )
  }

  if (isError || !data) {
    return (
      <div className="flex min-h-screen flex-col items-center justify-center gap-4 bg-muted p-6 text-center">
        <h1 className="text-2xl font-bold text-foreground">Cobrança não encontrada</h1>
        <p className="max-w-md text-sm text-muted-foreground">{getApiErrorMessage(error)}</p>
        <Link to="/">
          <Button>Voltar para a página inicial</Button>
        </Link>
      </div>
    )
  }

  const amount = Number(data.amount)
  const lastPayment = data.payments?.[0]

  return (
    <div className="flex min-h-screen w-full flex-col items-start gap-8 bg-muted p-6 lg:flex-row lg:px-20 lg:py-20">
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
            <span className="font-semibold text-foreground">{data.plan.name}</span>
          </div>

          <p className="text-sm text-muted-foreground">{data.plan.description}</p>

          <div className="flex items-center justify-between border-t border-border pt-4">
            <span className="text-sm text-muted-foreground">Valor</span>
            <span className="font-bold text-foreground">{currencyFormatter.format(amount)}</span>
          </div>

          <div className="flex items-center justify-between">
            <span className="text-sm text-muted-foreground">Vencimento</span>
            <span className="text-sm text-foreground">{dateFormatter.format(new Date(`${data.due_date}T00:00:00`))}</span>
          </div>

          <div className="flex items-center justify-between">
            <span className="text-sm text-muted-foreground">Status</span>
            <Badge variant={data.status === 'paid' ? 'success' : 'warning'}>
              {data.status === 'paid' ? 'Pago' : 'Pendente'}
            </Badge>
          </div>
        </div>
      </div>

      {data.status === 'pending' && <PaymentForm billingId={String(data.id)} amount={amount} />}

      {data.status === 'paid' && lastPayment && <PaymentConcluded payment={lastPayment} />}
    </div>
  )
}
