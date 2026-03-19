import { Badge } from '@inmediam/ui'
import { useQuery } from '@tanstack/react-query'
import {
  ArrowRight,
  Calendar,
  CheckCircle2,
  Clock,
  Loader2,
  Receipt,
  User,
} from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'

import { api } from '@/lib/api'
import { currencyFormatter, formatDate } from '@/utils/formatter'

import InMediamShield from '@/assets/inmediam-shield.svg'
import MediamLogo from '@/assets/mediam.svg'

interface Plan {
  name: string
  description?: string
  price: number
}

interface Customer {
  name: string
  email?: string
}

interface BillingItem {
  id: number
  uuid: string
  amount: number
  status: string
  due_date: string
  plan: Plan
  customer: Customer
}

type TabStatus = 'pending' | 'paid'

export function Home() {
  const [activeTab, setActiveTab] = useState<TabStatus>('pending')

  const {
    data: billings,
    isLoading,
    isError,
    error,
  } = useQuery<BillingItem[]>({
    queryKey: ['billings'],
    queryFn: async () => {
      const { data } = await api.get<BillingItem[]>('/api/billing')
      return data
    },
  })

  const pendingBillings = billings?.filter((b) => b.status !== 'paid') ?? []
  const paidBillings = billings?.filter((b) => b.status === 'paid') ?? []
  const displayedBillings = activeTab === 'pending' ? pendingBillings : paidBillings

  return (
    <div className="min-h-screen w-full bg-gradient-to-b from-muted/80 to-muted p-6 md:p-10">
      <main className="mx-auto max-w-3xl">
        {/* Header */}
        <header className="mb-10 text-center">
          <div className="mb-6 flex items-center justify-center gap-2">
            <img
              src={InMediamShield}
              className="h-10 w-10 drop-shadow-sm"
              alt=""
              aria-hidden
            />
            <img
              src={MediamLogo}
              alt="In Mediam"
              className="h-7 drop-shadow-sm"
            />
          </div>
          <h1 className="text-3xl font-bold tracking-tight text-foreground md:text-4xl">
            Pagamento de assinaturas
          </h1>
          <p className="mt-3 max-w-md mx-auto text-base text-muted-foreground leading-relaxed">
            Selecione uma cobrança para visualizar os detalhes e realizar o
            pagamento de forma segura.
          </p>
        </header>

        {/* Lista de cobranças */}
        <section
          className="rounded-2xl border border-border bg-card shadow-md shadow-black/5"
          aria-label="Cobranças disponíveis"
        >
          <div className="border-b border-border px-6 pt-4 md:px-8">
            <div
              className="flex gap-1 rounded-lg bg-muted p-1"
              role="tablist"
              aria-label="Filtrar por status"
            >
              <button
                type="button"
                role="tab"
                aria-selected={activeTab === 'pending'}
                aria-controls="panel-pending"
                id="tab-pending"
                onClick={() => setActiveTab('pending')}
                className={`flex-1 rounded-md px-4 py-2.5 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 ${
                  activeTab === 'pending'
                    ? 'bg-primary text-primary-foreground shadow-sm'
                    : 'text-muted-foreground hover:bg-muted/80 hover:text-foreground'
                }`}
              >
                Pagamentos pendentes
                {!isLoading && pendingBillings.length > 0 && (
                  <span
                    className={`ml-2 rounded-full px-2 py-0.5 text-xs font-semibold ${
                      activeTab === 'pending'
                        ? 'bg-primary-foreground/20 text-primary-foreground'
                        : 'bg-primary/10 text-primary'
                    }`}
                  >
                    {pendingBillings.length}
                  </span>
                )}
              </button>
              <button
                type="button"
                role="tab"
                aria-selected={activeTab === 'paid'}
                aria-controls="panel-paid"
                id="tab-paid"
                onClick={() => setActiveTab('paid')}
                className={`flex-1 rounded-md px-4 py-2.5 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 ${
                  activeTab === 'paid'
                    ? 'bg-primary text-primary-foreground shadow-sm'
                    : 'text-muted-foreground hover:bg-muted/80 hover:text-foreground'
                }`}
              >
                Pagos
                {!isLoading && paidBillings.length > 0 && (
                  <span
                    className={`ml-2 rounded-full px-2 py-0.5 text-xs font-semibold ${
                      activeTab === 'paid'
                        ? 'bg-primary-foreground/20 text-primary-foreground'
                        : 'bg-primary/10 text-primary'
                    }`}
                  >
                    {paidBillings.length}
                  </span>
                )}
              </button>
            </div>
          </div>

          <div className="p-6 md:p-8">
            {isLoading && (
              <div className="flex flex-col items-center justify-center gap-4 py-16">
                <div className="rounded-full bg-muted p-4">
                  <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
                </div>
                <div className="text-center">
                  <p className="text-sm font-medium text-foreground">
                    Carregando cobranças...
                  </p>
                  <p className="mt-1 text-xs text-muted-foreground">
                    Aguarde um momento
                  </p>
                </div>
              </div>
            )}

            {isError && (
              <div className="rounded-xl border border-border bg-muted/30 p-6 text-center">
                <p className="text-sm font-semibold text-destructive">
                  Não foi possível carregar as cobranças
                </p>
                <p className="mt-2 text-xs text-muted-foreground">
                  {error instanceof Error
                    ? error.message
                    : 'Verifique sua conexão e tente novamente.'}
                </p>
              </div>
            )}

            {!isLoading && !isError && billings && billings.length === 0 && (
              <div className="flex flex-col items-center py-16 text-center">
                <Receipt className="h-12 w-12 text-muted-foreground/50" />
                <p className="mt-4 text-sm font-medium text-foreground">
                  Nenhuma cobrança no momento
                </p>
                <p className="mt-1 text-xs text-muted-foreground">
                  Quando houver cobranças disponíveis, elas aparecerão aqui.
                </p>
              </div>
            )}

            {!isLoading && !isError && billings && billings.length > 0 && displayedBillings.length === 0 && (
              <div className="flex flex-col items-center py-16 text-center">
                <Receipt className="h-12 w-12 text-muted-foreground/50" />
                <p className="mt-4 text-sm font-medium text-foreground">
                  {activeTab === 'pending'
                    ? 'Nenhum pagamento pendente'
                    : 'Nenhum pagamento realizado'}
                </p>
                <p className="mt-1 text-xs text-muted-foreground">
                  {activeTab === 'pending'
                    ? 'Suas cobranças pagas aparecem na aba Pagos.'
                    : 'Cobranças pendentes aparecem na aba Pagamentos pendentes.'}
                </p>
              </div>
            )}

            {!isLoading && !isError && billings && displayedBillings.length > 0 && (
              <ul className="space-y-4 pt-2" role="list" id={activeTab === 'pending' ? 'panel-pending' : 'panel-paid'}>
                {displayedBillings.map((billing) => (
                  <li key={billing.id}>
                    <Link
                      to={`/billing/${billing.uuid}`}
                      className="group flex flex-col gap-4 rounded-xl border border-border bg-background/50 p-5 transition-all hover:border-primary/40 hover:bg-muted/30 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 sm:flex-row sm:items-center sm:justify-between"
                    >
                      <div className="min-w-0 flex-1 space-y-3">
                        <div>
                          <p className="font-semibold text-foreground">
                            {billing.plan?.name ?? 'Cobrança'}
                          </p>
                          {billing.plan?.description && (
                            <p className="mt-0.5 line-clamp-2 text-xs text-muted-foreground">
                              {billing.plan.description}
                            </p>
                          )}
                        </div>
                        <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                          <span className="flex items-center gap-1.5">
                            <User className="h-3.5 w-3.5 shrink-0" />
                            {billing.customer?.name ?? '—'}
                          </span>
                          <span className="flex items-center gap-1.5">
                            <Calendar className="h-3.5 w-3.5 shrink-0" />
                            Venc. {formatDate(billing.due_date)}
                          </span>
                        </div>
                      </div>
                      <div className="flex shrink-0 items-center justify-between gap-4 border-t border-border pt-4 sm:justify-end sm:border-t-0 sm:border-l sm:pl-6 sm:pt-0">
                        <div className="text-right">
                          <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                            Valor
                          </p>
                          <p className="text-lg font-bold tabular-nums text-foreground">
                            {currencyFormatter.format(Number(billing.amount))}
                          </p>
                        </div>
                        <div className="flex items-center gap-3">
                          <Badge
                            variant={
                              billing.status === 'paid' ? 'success' : 'warning'
                            }
                            className="shrink-0"
                          >
                            {billing.status === 'paid' ? (
                              <>
                                <CheckCircle2 className="mr-1 h-3.5 w-3.5" />
                                Pago
                              </>
                            ) : (
                              <>
                                <Clock className="mr-1 h-3.5 w-3.5" />
                                Pendente
                              </>
                            )}
                          </Badge>
                          <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                            <ArrowRight className="h-4 w-4" />
                          </span>
                        </div>
                      </div>
                    </Link>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </section>

        <p className="mt-8 text-center text-xs text-muted-foreground">
          Pagamentos processados de forma segura. Em caso de dúvidas, entre em
          contato com o suporte.
        </p>
      </main>
    </div>
  )
}
