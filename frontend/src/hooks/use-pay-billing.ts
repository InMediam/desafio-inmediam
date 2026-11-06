import { useMutation, useQueryClient } from '@tanstack/react-query'

import { payBilling } from '@/services/billing.service'
import type { PayBillingInput } from '@/types/billing'

interface PayBillingVariables {
  input: PayBillingInput
  idempotencyKey?: string
}

export function usePayBilling(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ input, idempotencyKey }: PayBillingVariables) =>
      payBilling(id, input, idempotencyKey),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['billing', id] })
    },
  })
}
