import { Badge } from '@inmediam/ui'
import { Calendar, CreditCard, ShieldCheck, User } from 'lucide-react'
import { Link } from 'react-router-dom'

import { currencyFormatter, formatDateTime } from '@/utils/formatter'

interface PaymentCreditCard {
  card_holder_name: string
  card_last_four: string
  card_brand: string
}

interface Payment {
  amount_paid: number
  status: string
  paid_at: string
  credit_card?: PaymentCreditCard
  creditCard?: PaymentCreditCard
}

interface PaymentConcludedProps {
  payment: Payment
}

export function PaymentConcluded({ payment }: PaymentConcludedProps) {
  const creditCard = payment.credit_card ?? payment.creditCard

  return (
    <div className="rounded-3xl border border-border/80 bg-card/95 shadow-xl shadow-black/5 backdrop-blur-sm overflow-hidden transition-shadow hover:shadow-2xl">
      {/* Header de sucesso */}
      <div className="relative overflow-hidden border-b border-border/60 bg-gradient-to-br from-emerald-500/15 via-emerald-500/10 to-transparent px-6 py-6 md:px-8">
        <div className="absolute right-0 top-0 h-24 w-24 -translate-y-1/2 translate-x-1/2 rounded-full bg-emerald-400/20 blur-2xl" />
        <div className="relative flex items-center gap-4">
          <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-500 text-white shadow-lg shadow-emerald-500/30">
            <ShieldCheck className="h-6 w-6" />
          </div>
          <div>
            <h2 className="text-lg font-bold text-foreground">
              Pagamento confirmado
            </h2>
            <p className="text-sm text-muted-foreground mt-0.5">
              Sua transação foi processada com sucesso.
            </p>
          </div>
        </div>
      </div>

      <div className="space-y-0 p-6 md:p-8">
        {creditCard && (
          <div className="space-y-5 pb-6 border-b border-border/60">
            <div className="flex items-center gap-2 text-muted-foreground">
              <div className="rounded-lg bg-muted p-1.5">
                <User className="h-4 w-4" />
              </div>
              <span className="text-xs font-semibold uppercase tracking-wider">
                Titular do cartão
              </span>
            </div>
            <p className="font-semibold text-foreground">
              {creditCard.card_holder_name}
            </p>

            <div className="flex items-center gap-2 text-muted-foreground pt-1">
              <div className="rounded-lg bg-muted p-1.5">
                <CreditCard className="h-4 w-4" />
              </div>
              <span className="text-xs font-semibold uppercase tracking-wider">
                Cartão utilizado
              </span>
            </div>
            <p className="font-semibold text-foreground tabular-nums">
              {creditCard.card_brand} •••• {creditCard.card_last_four}
            </p>
          </div>
        )}

        <div className="space-y-5 pt-6">
          <div className="flex items-center justify-between rounded-xl bg-muted/40 px-4 py-4">
            <span className="text-sm font-medium text-muted-foreground">Valor pago</span>
            <span className="text-2xl font-bold tabular-nums text-foreground">
              {currencyFormatter.format(payment.amount_paid)}
            </span>
          </div>

          <div className="flex items-center justify-between py-2">
            <span className="flex items-center gap-2 text-sm text-muted-foreground">
              <Calendar className="h-4 w-4 text-muted-foreground/80" />
              Data do pagamento
            </span>
            <span className="text-sm font-semibold text-foreground">
              {formatDateTime(payment.paid_at)}
            </span>
          </div>

          <div className="flex items-center justify-between border-t border-border/60 pt-5">
            <span className="text-sm text-muted-foreground">Status</span>
            <Badge variant="success" className="font-semibold px-3 py-1">
              Confirmado
            </Badge>
          </div>
        </div>

        <div className="mt-8 pt-6 border-t border-border/60">
          <Link
            to="/"
            className="inline-flex items-center gap-2 rounded-full bg-primary/10 px-5 py-2.5 text-sm font-semibold text-primary transition-colors hover:bg-primary/20"
          >
            Ver outras cobranças
          </Link>
        </div>
      </div>
    </div>
  )
}
