export type BillingStatus = 'pending' | 'paid'

export interface Plan {
  name: string
  description: string
  price: string
}

export interface CreditCard {
  card_holder_name: string
  card_brand: string
  card_last_four: string
}

export interface Payment {
  id: number
  amount_paid: string
  status: string
  paid_at: string
  credit_card: CreditCard
}

export interface Customer {
  name: string
}

export interface Billing {
  id: number
  amount: string
  status: BillingStatus
  due_date: string
  plan: Plan
  customer: Customer
  payments: Payment[]
}

export interface PayBillingInput {
  card_number: string
  card_holder_name: string
  expiry_date: string
  cvv: string
}

export interface BillingListItem {
  id: number
  status: BillingStatus
  plan: Plan
  customer: Customer
}
