import { zodResolver } from '@hookform/resolvers/zod'
import { isAxiosError } from 'axios'
import { useRef } from 'react'
import { useForm, type UseFormReturn } from 'react-hook-form'
import { toast } from 'sonner'

import { usePayBilling } from '@/hooks/use-pay-billing'
import { getApiErrorMessage } from '@/lib/api'
import { type PaymentFormData, paymentSchema } from '@/schemas/payment.schema'
import { maskCardNumber, maskCvv, maskExpiryDate } from '@/utils/card'

const FIELD_NAME_MAP: Record<string, keyof PaymentFormData> = {
  card_number: 'cardNumber',
  card_holder_name: 'holderName',
  expiry_date: 'expiryDate',
  cvv: 'cvv',
}

function applyFieldErrors(
  form: UseFormReturn<PaymentFormData>,
  errors: Record<string, string[]> | undefined,
): void {
  for (const [field, messages] of Object.entries(errors ?? {})) {
    const formField = FIELD_NAME_MAP[field]

    if (formField && messages[0]) {
      form.setError(formField, { message: messages[0] })
    }
  }
}

interface UsePaymentFormParams {
  billingId: string
}

export function usePaymentForm({ billingId }: UsePaymentFormParams) {
  const form = useForm<PaymentFormData>({
    resolver: zodResolver(paymentSchema),
    defaultValues: { cardNumber: '', holderName: '', expiryDate: '', cvv: '' },
  })

  const { mutate, isPending } = usePayBilling(billingId)
  const lastAttemptRef = useRef<{ key: string; payload: string } | null>(null)

  function handleCardNumberChange(value: string): void {
    form.setValue('cardNumber', maskCardNumber(value))
  }

  function handleExpiryDateChange(value: string): void {
    form.setValue('expiryDate', maskExpiryDate(value))
  }

  function handleCvvChange(value: string): void {
    form.setValue('cvv', maskCvv(value))
  }

  function submit(data: PaymentFormData): void {
    const input = {
      card_number: data.cardNumber,
      card_holder_name: data.holderName.toUpperCase(),
      expiry_date: data.expiryDate,
      cvv: data.cvv,
    }

    const payload = JSON.stringify(input)
    const idempotencyKey =
      lastAttemptRef.current?.payload === payload
        ? lastAttemptRef.current.key
        : crypto.randomUUID()

    lastAttemptRef.current = { key: idempotencyKey, payload }

    mutate(
      { input, idempotencyKey },
      {
        onSuccess: () => {
          toast.success('Pagamento realizado com sucesso!')
        },
        onError: (error) => {
          if (isAxiosError(error) && error.response?.status === 422) {
            applyFieldErrors(form, error.response.data?.errors)
            return
          }

          toast.error(getApiErrorMessage(error))
        },
      },
    )
  }

  return {
    form,
    isPending,
    onSubmit: form.handleSubmit(submit),
    handleCardNumberChange,
    handleExpiryDateChange,
    handleCvvChange,
  }
}
