export const BILLING_STATUS = {
  PENDING: 'pending',
  PAID: 'paid',
} as const

export type BillingStatus =
  (typeof BILLING_STATUS)[keyof typeof BILLING_STATUS]

interface BillingStatusDisplay {
  label: string
  variant: 'success' | 'warning'
}

export function getBillingStatusDisplay(
  status?: string | null,
): BillingStatusDisplay {
  switch (status) {
    case BILLING_STATUS.PAID:
      return { label: 'Pago', variant: 'success' }
    case BILLING_STATUS.PENDING:
      return { label: 'Pendente', variant: 'warning' }
    default:
      return { label: 'Desconhecido', variant: 'warning' }
  }
}

export function isBillingPending(status?: string | null): boolean {
  return status === BILLING_STATUS.PENDING
}

export function isBillingPaid(status?: string | null): boolean {
  return status === BILLING_STATUS.PAID
}
