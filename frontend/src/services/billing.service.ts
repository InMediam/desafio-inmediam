import { api } from '@/lib/api'
import type {
  Billing,
  BillingListItem,
  PayBillingInput,
  Payment,
} from '@/types/billing'

export async function listBillings(): Promise<BillingListItem[]> {
  const response = await api.get<BillingListItem[]>('/billings')
  return response.data
}

export async function getBilling(id: string): Promise<Billing> {
  const response = await api.get<Billing>(`/billing/${id}`)
  return response.data
}

export async function payBilling(
  id: string,
  input: PayBillingInput,
  idempotencyKey?: string,
): Promise<Payment> {
  const response = await api.post<Payment>(
    `/billing/${id}/pay`,
    input,
    idempotencyKey
      ? { headers: { 'Idempotency-Key': idempotencyKey } }
      : undefined,
  )
  return response.data
}
