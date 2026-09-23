import { Badge } from '@inmediam/ui'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import axios from 'axios'

import InMediamShield from '@/assets/inmediam-shield.svg'
import MediamLogo from '@/assets/mediam.svg'

interface Billing {
  id: number
  status: string
  plan: {
    name: string
  }
  customer: {
    name: string
  }
}

export function Home() {
  // Busca as cobranças da API para exibir os dados atualizados na tela inicial.
  const { data, isLoading, isError } = useQuery({
    queryKey: ['billings'],
    queryFn: async () => {
      const response = await axios
        .create()
        .get(`${import.meta.env.VITE_API_URL}/api/billing`)

      return response.data.data
    },
  })
  return (
    <div className="mx-auto flex min-h-screen w-full flex-col items-center justify-center gap-8 bg-muted p-6">
      <div className="w-1/3 rounded-lg border border-border bg-card p-6 shadow-sm">
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

        {isLoading && (
          <p className="mt-6 text-sm text-muted-foreground">
            Carregando cobranças...
          </p>
        )}

        {isError && (
          <p className="mt-6 text-sm text-error-500">
            Não foi possível carregar as cobranças.
          </p>
        )}
        <div className="mt-6 space-y-3">
          {/* Exibe cada cobrança retornada pela API com seu plano, cliente e status atual. */}
          {data?.map((billing: Billing) => (
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
              <Badge variant={billing.status === 'paid' ? 'success' : 'warning'}>
                {billing.status === 'paid' ? 'Pago' : 'Pendente'}
              </Badge>
            </Link>
          ))}
        </div>
      </div>
    </div>
  )
}
