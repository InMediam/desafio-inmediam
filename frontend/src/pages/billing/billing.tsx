import { Badge } from '@inmediam/ui'
import { useQuery } from '@tanstack/react-query'
import {
  ArrowLeft,
  Calendar,
  CreditCard,
  FileText,
  Loader2,
  ShieldCheck,
} from 'lucide-react'
import { Link, useParams } from 'react-router-dom'

import InMediamShield from '@/assets/inmediam-shield.svg'
import MediamLogo from '@/assets/mediam.svg'
import { api } from '@/lib/api'
import { currencyFormatter, formatDate } from '@/utils/formatter'

import { AddressForm } from './components/address-form'
import { PaymentConcluded } from './components/payment-concluded'
import { PaymentForm } from './components/payment-form'

const UUID_REGEX =
  /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i

function isValidUuid(value: string): boolean {
  return UUID_REGEX.test(value)
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
    queryFn: async () => {
      const response = await api.get(`/api/billing/${id}`)
      return response.data
    },
    enabled: !!id && isValidUuid(id),
  })

  if (!id || !isValidUuid(id)) {
    return (
      <div className="flex min-h-screen w-full flex-col items-center justify-center gap-6 bg-gradient-to-br from-muted/60 via-muted to-muted/80 p-6">
        <div className="rounded-3xl border border-border/80 bg-card/95 px-8 py-10 text-center shadow-xl shadow-black/5 backdrop-blur-sm">
          <FileText className="mx-auto h-12 w-12 text-muted-foreground/60" />
          <h1 className="mt-4 text-xl font-semibold text-foreground">
            Cobrança inválida
          </h1>
          <p className="mt-2 text-sm text-muted-foreground">
            O link que você acessou não corresponde a uma cobrança válida.
          </p>
          <Link
            to="/"
            className="mt-6 inline-flex items-center gap-2 text-sm font-medium text-primary hover:underline"
          >
            <ArrowLeft className="h-4 w-4" />
            Voltar para cobranças
          </Link>
        </div>
      </div>
    )
  }

  if (isLoading) {
    return (
      <div className="flex min-h-screen w-full flex-col items-center justify-center gap-6 bg-gradient-to-br from-muted/60 via-muted to-muted/80 p-6">
        <div className="flex flex-col items-center gap-5">
          <div className="rounded-2xl bg-card/80 p-5 shadow-lg backdrop-blur-sm">
            <Loader2 className="h-8 w-8 animate-spin text-primary" />
          </div>
          <div className="text-center">
            <p className="text-sm font-medium text-foreground">
              Carregando cobrança...
            </p>
            <p className="mt-1 text-xs text-muted-foreground">
              Aguarde um momento
            </p>
          </div>
        </div>
      </div>
    )
  }

  if (isError || !data) {
    const errorMessage =
      error &&
      typeof error === 'object' &&
      'response' in error &&
      error.response &&
      typeof error.response === 'object' &&
      'data' in error.response &&
      error.response.data &&
      typeof error.response.data === 'object' &&
      'error' in error.response.data
        ? String((error.response.data as { error?: string }).error)
        : 'Não foi possível carregar a cobrança.'
    return (
      <div className="flex min-h-screen w-full flex-col items-center justify-center gap-6 bg-gradient-to-br from-muted/60 via-muted to-muted/80 p-6">
        <div className="rounded-3xl border border-border/80 bg-card/95 px-8 py-10 text-center shadow-xl shadow-black/5 backdrop-blur-sm max-w-md">
          <p className="text-destructive font-medium">{errorMessage}</p>
          <Link
            to="/"
            className="mt-6 inline-flex items-center gap-2 text-sm font-medium text-primary hover:underline"
          >
            <ArrowLeft className="h-4 w-4" />
            Voltar para cobranças
          </Link>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen w-full bg-gradient-to-br from-muted/60 via-muted to-muted/80 p-6 md:p-10 lg:p-12">
      <div className="mx-auto max-w-4xl">
        {/* Header com logo */}
        <header className="mb-10 flex items-center justify-between">
          <div className="flex items-center gap-2.5">
            <img
              src={InMediamShield}
              className="h-9 w-9 drop-shadow-sm"
              alt=""
              aria-hidden
            />
            <img src={MediamLogo} alt="In Mediam" className="h-6 drop-shadow-sm" />
          </div>
          <Link
            to="/"
            className="inline-flex items-center gap-2 rounded-full border border-border/80 bg-card/90 px-5 py-2.5 text-sm font-medium text-foreground shadow-lg shadow-black/5 backdrop-blur-sm transition-all hover:scale-[1.02] hover:shadow-xl hover:border-primary/30"
          >
            <ArrowLeft className="h-4 w-4" />
            Voltar
          </Link>
        </header>

        <div className="mb-10">
          <h1 className="text-2xl font-bold tracking-tight text-foreground md:text-3xl lg:text-4xl">
            Pagamento de assinatura
          </h1>
          <p className="mt-2 text-muted-foreground md:text-base">
            Confira os dados da cobrança e conclua o pagamento de forma segura.
          </p>
        </div>

        <div className="grid gap-8 lg:grid-cols-[1fr,1.1fr]">
          {/* Card: Resumo da cobrança */}
          <section
            className="rounded-3xl border border-border/80 bg-card/95 shadow-xl shadow-black/5 backdrop-blur-sm overflow-hidden transition-shadow hover:shadow-2xl hover:shadow-black/5"
            aria-label="Resumo da cobrança"
          >
            <div className="bg-gradient-to-r from-primary/5 to-transparent px-6 py-4 md:px-8 border-b border-border/60">
              <h2 className="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                Resumo da cobrança
              </h2>
            </div>
            <div className="space-y-6 p-6 md:p-8">
              <div>
                <div className="flex items-center gap-2 text-muted-foreground">
                  <div className="rounded-lg bg-primary/10 p-1.5">
                    <CreditCard className="h-4 w-4 text-primary" />
                  </div>
                  <span className="text-xs font-semibold uppercase tracking-wider">
                    Plano
                  </span>
                </div>
                <p className="mt-2 text-xl font-semibold text-foreground">
                  {data.plan?.name}
                </p>
                {data.plan?.description && (
                  <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                    {data.plan.description}
                  </p>
                )}
              </div>

              <div className="flex items-center justify-between rounded-xl bg-muted/40 px-4 py-4">
                <span className="text-sm font-medium text-muted-foreground">Valor</span>
                <span className="text-2xl font-bold tabular-nums text-foreground">
                  {currencyFormatter.format(Number(data.amount))}
                </span>
              </div>

              <div className="flex items-center justify-between py-2">
                <span className="flex items-center gap-2 text-sm text-muted-foreground">
                  <Calendar className="h-4 w-4 text-muted-foreground/80" />
                  Vencimento
                </span>
                <span className="font-semibold text-foreground">
                  {formatDate(data.due_date)}
                </span>
              </div>

              <div className="flex items-center justify-between border-t border-border/60 pt-5">
                <span className="text-sm text-muted-foreground">Status</span>
                <Badge
                  variant={data.status === 'paid' ? 'success' : 'warning'}
                  className="font-semibold px-3 py-1"
                >
                  {data.status === 'paid' ? 'Pago' : 'Pendente'}
                </Badge>
              </div>

              <div className="flex items-center gap-3 rounded-xl bg-light border border-border px-4 py-3.5 text-sm text-foreground">
                <ShieldCheck className="h-5 w-5 shrink-0 text-muted-foreground" />
                <span className="font-medium">Ambiente seguro para pagamento.</span>
              </div>
            </div>
          </section>

          {/* Coluna direita: endereço (se faltar), formulário de pagamento ou confirmação */}
          <div className="flex flex-col gap-6">
            {data.status === 'pending' && data.customer && !data.customer.postal_code && (
              <AddressForm billingUuid={id!} onSuccess={() => {}} />
            )}
            {data.status === 'pending' && (!data.customer || data.customer.postal_code) && (
              <PaymentForm billingId={id!} amount={Number(data.amount)} />
            )}
            {data.status === 'paid' && data.payments?.[0] && (
              <PaymentConcluded payment={data.payments[0]} />
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
