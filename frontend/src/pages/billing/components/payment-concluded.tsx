import { Badge } from '@inmediam/ui'
import { CircleCheck } from 'lucide-react'

import type { PaymentResponse } from '@/types/billing'
import { currencyFormatter } from '@/utils/formatter'

interface PaymentConcludedProps {
  payment: PaymentResponse
}

const paymentDateFormatter = new Intl.DateTimeFormat('pt-BR', {
  dateStyle: 'medium',
  timeStyle: 'short',
})

export function PaymentConcluded({ payment }: PaymentConcludedProps) {
  const paymentDate = new Date(payment.paid_at)

  return (
    <div className="w-full rounded-lg border border-border bg-card p-6 shadow-sm lg:max-w-xl">
      <div className="mb-6 flex items-center gap-2">
        <CircleCheck className="h-5 w-5 text-emerald-500" />
        <h2 className="text-lg font-semibold text-foreground">
          Pagamento confirmado
        </h2>
      </div>

      <div className="space-y-3">
        <div className="flex items-center justify-between gap-4">
          <span className="text-sm text-muted-foreground">Titular</span>
          <span className="text-right font-medium text-foreground">
            {payment.credit_card.card_holder_name}
          </span>
        </div>

        <div className="flex items-center justify-between gap-4">
          <span className="text-sm text-muted-foreground">Cartão</span>
          <span className="text-right font-medium text-foreground">
            {payment.credit_card.card_brand} ••••{' '}
            {payment.credit_card.card_last_four}
          </span>
        </div>

        <div className="flex items-center justify-between border-t border-border pt-3">
          <span className="text-sm text-muted-foreground">Valor pago</span>
          <span className="font-bold text-foreground">
            {currencyFormatter.format(payment.amount_paid)}
          </span>
        </div>

        <div className="flex items-center justify-between gap-4">
          <span className="text-sm text-muted-foreground">Data</span>
          <span className="text-right text-sm text-foreground">
            {paymentDateFormatter.format(paymentDate)}
          </span>
        </div>

        <div className="flex items-center justify-between gap-4">
          <span className="text-sm text-muted-foreground">Status</span>
          <Badge variant="success">Confirmado</Badge>
        </div>
      </div>
    </div>
  )
}
