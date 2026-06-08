import { z } from 'zod'

import {
  isExpiryValid,
  stripDigits,
  CARD_NUMBER_MIN_LENGTH,
  CARD_NUMBER_MAX_LENGTH,
  CVV_LENGTH,
} from '@/utils/card'

export const paymentSchema = z.object({
  cardNumber: z
    .string()
    .min(1, 'Número do cartão é obrigatório')
    .refine(
      (value) => {
        const digits = stripDigits(value)
        return (
          digits.length >= CARD_NUMBER_MIN_LENGTH &&
          digits.length <= CARD_NUMBER_MAX_LENGTH
        )
      },
      {
        message: `Número do cartão deve ter entre ${CARD_NUMBER_MIN_LENGTH} e ${CARD_NUMBER_MAX_LENGTH} dígitos`,
      },
    ),
  holderName: z
    .string()
    .trim()
    .min(3, 'Nome do titular deve ter no mínimo 3 caracteres')
    .max(50, 'Nome do titular deve ter no máximo 50 caracteres')
    .regex(/^[A-Za-zÀ-ÿ\s]+$/, 'Nome deve conter apenas letras'),
  expiryDate: z
    .string()
    .regex(/^\d{2}\/\d{2}$/, 'Validade deve estar no formato MM/AA')
    .refine((value) => isExpiryValid(value), {
      message: 'Cartão expirado ou data inválida',
    }),
  cvv: z
    .string()
    .length(CVV_LENGTH, `CVV deve ter ${CVV_LENGTH} dígitos`)
    .regex(/^\d{3}$/, 'CVV deve conter apenas números'),
})

export type PaymentFormData = z.infer<typeof paymentSchema>
