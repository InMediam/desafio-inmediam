import { useQuery } from '@tanstack/react-query'

import { getBilling } from '@/services/billing.service'

export function useBilling(id: string) {
  return useQuery({
    queryKey: ['billing', id],
    queryFn: () => getBilling(id),
  })
}
