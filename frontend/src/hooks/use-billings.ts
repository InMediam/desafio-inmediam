import { useQuery } from '@tanstack/react-query'

import { listBillings } from '@/services/billing.service'

export function useBillings() {
  return useQuery({
    queryKey: ['billings'],
    queryFn: listBillings,
  })
}
