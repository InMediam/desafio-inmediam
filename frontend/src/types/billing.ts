export interface CreditCard {
  card_holder_name: string
  card_last_four: string
  card_brand: string
}

export interface Payment {
  amount_paid: number
  status: string
  paid_at: string
  credit_card: CreditCard
}

export interface Plan {
  name: string
  description: string
}

export interface Billing {
  id: number
  amount: number
  status: 'pending' | 'processing' | 'paid'
  due_date: string
  plan: Plan
  payments: Payment[]
}

export interface PaymentFormValues {
  cardNumber: string
  holderName: string
  expiryDate: string
  cvv: string
}

export interface PaymentResponse {
  amount_paid: number
  status: string
  paid_at: string
  credit_card: CreditCard
}
