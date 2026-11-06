import { z } from 'zod'

function passesLuhnCheck(value: string): boolean {
  let sum = 0
  let alternate = false

  for (let i = value.length - 1; i >= 0; i--) {
    let digit = Number(value[i])

    if (alternate) {
      digit *= 2
      if (digit > 9) {
        digit -= 9
      }
    }

    sum += digit
    alternate = !alternate
  }

  return sum % 10 === 0
}

function isExpiryDateInFuture(value: string): boolean {
  const [month, year] = value.split('/')
  const lastDayOfMonth = new Date(Number(`20${year}`), Number(month), 0)

  return lastDayOfMonth.getTime() >= Date.now()
}

export const paymentSchema = z.object({
  cardNumber: z
    .string()
    .transform((value) => value.replace(/\s+/g, ''))
    .refine(
      (value) => /^\d{13,19}$/.test(value),
      'O número do cartão deve conter entre 13 e 19 dígitos.',
    )
    .refine(passesLuhnCheck, 'O número do cartão é inválido.'),
  holderName: z.string().min(1, 'O nome do titular é obrigatório.'),
  expiryDate: z
    .string()
    .regex(
      /^(0[1-9]|1[0-2])\/\d{2}$/,
      'A validade do cartão deve estar no formato MM/AA, com mês entre 01 e 12.',
    )
    .refine(isExpiryDateInFuture, 'O cartão informado está vencido.'),
  cvv: z
    .string()
    .regex(/^\d{3,4}$/, 'O código de segurança (CVV) deve ter 3 ou 4 dígitos.'),
})

export type PaymentFormData = z.infer<typeof paymentSchema>
