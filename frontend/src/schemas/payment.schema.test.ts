import { describe, expect, it } from 'vitest'

import { paymentSchema } from './payment.schema'

function futureExpiryDate(): string {
  const year = new Date().getFullYear() + 5
  return `12/${String(year).slice(-2)}`
}

const validPayment = {
  cardNumber: '4539656048392220',
  holderName: 'JOAO DA SILVA',
  expiryDate: futureExpiryDate(),
  cvv: '123',
}

describe('paymentSchema', () => {
  it('accepts a valid card using the official asaas sandbox test number', () => {
    const result = paymentSchema.safeParse(validPayment)

    expect(result.success).toBe(true)
  })

  it('rejects a card number that fails the luhn check', () => {
    const result = paymentSchema.safeParse({
      ...validPayment,
      cardNumber: '4539656048392221',
    })

    expect(result.success).toBe(false)
  })

  it('rejects an expired expiry date', () => {
    const result = paymentSchema.safeParse({
      ...validPayment,
      expiryDate: '01/20',
    })

    expect(result.success).toBe(false)
  })

  it('rejects an invalid month', () => {
    const result = paymentSchema.safeParse({
      ...validPayment,
      expiryDate: '13/30',
    })

    expect(result.success).toBe(false)
  })

  it('rejects a cvv with 2 digits', () => {
    const result = paymentSchema.safeParse({
      ...validPayment,
      cvv: '12',
    })

    expect(result.success).toBe(false)
  })
})
