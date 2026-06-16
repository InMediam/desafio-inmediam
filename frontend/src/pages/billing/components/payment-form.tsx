import 'react-credit-cards-2/dist/es/styles-compiled.css'

import { zodResolver } from '@hookform/resolvers/zod'
import { Button, Input, Label } from '@inmediam/ui'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { isAxiosError } from 'axios'
import { Loader2 } from 'lucide-react'
import { useState } from 'react'
import Cards, { Focused } from 'react-credit-cards-2'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { z } from 'zod'

import { api } from '@/lib/api'
import type { PaymentFormValues } from '@/types/billing'

const paymentSchema = z.object({
  cardNumber: z.string().refine((value) => {
    const digitsOnly = value.replace(/\D+/g, '')
    return digitsOnly.length >= 13 && digitsOnly.length <= 19
  }, 'Informe um número de cartão válido.'),
  holderName: z
    .string()
    .trim()
    .min(3, 'Informe o nome do titular.')
    .max(255, 'Informe um nome de titular válido.'),
  expiryDate: z
    .string()
    .regex(/^(0[1-9]|1[0-2])\/\d{2}$/, 'Use o formato MM/AA.'),
  cvv: z.string().regex(/^\d{3,4}$/, 'Informe um CVV válido.'),
})

interface PaymentFormProps {
  billingId: string
}

export function PaymentForm({ billingId }: PaymentFormProps) {
  const queryClient = useQueryClient()
  const [focused, setFocused] = useState<Focused>('')
  const {
    register,
    handleSubmit,
    watch,
    formState: { errors },
  } = useForm<PaymentFormValues>({
    resolver: zodResolver(paymentSchema),
    defaultValues: {
      cardNumber: '',
      holderName: '',
      expiryDate: '',
      cvv: '',
    },
  })

  const watchedValues = watch()

  const cardNumberField = register('cardNumber')
  const holderNameField = register('holderName')
  const expiryDateField = register('expiryDate')
  const cvvField = register('cvv')

  const { mutateAsync: submitPayment, isPending } = useMutation({
    mutationFn: async (data: PaymentFormValues) =>
      api.post(`/billing/${billingId}/pay`, {
        card_number: data.cardNumber.replace(/\D+/g, ''),
        card_holder_name: data.holderName,
        expiry_date: data.expiryDate,
        cvv: data.cvv.replace(/\D+/g, ''),
      }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ['billing', billingId],
      })

      toast.success('Pagamento realizado com sucesso!')
    },
    onError: (error) => {
      const message = isAxiosError(error)
        ? (error.response?.data?.message as string | undefined)
        : undefined

      toast.error(message || 'Não foi possível processar o pagamento.')
    },
  })

  function handlePayment(data: PaymentFormValues) {
    return submitPayment(data)
  }

  return (
    <div className="w-full rounded-lg border border-border bg-card p-6 shadow-sm lg:max-w-xl">
      <h2 className="mb-6 text-lg font-semibold text-foreground">
        Dados do cartão
      </h2>

      <div className="mb-6">
        <Cards
          number={watchedValues.cardNumber}
          name={watchedValues.holderName}
          expiry={watchedValues.expiryDate}
          cvc={watchedValues.cvv}
          focused={focused}
        />
      </div>

      <form onSubmit={handleSubmit(handlePayment)} className="space-y-4">
        <div className="space-y-2">
          <Label htmlFor="cardNumber">Número do cartão</Label>
          <Input
            id="cardNumber"
            inputMode="numeric"
            autoComplete="cc-number"
            placeholder="0000 0000 0000 0000"
            {...cardNumberField}
            onFocus={() => setFocused('number')}
          />
          {errors.cardNumber && (
            <p className="text-sm text-error-500">
              {errors.cardNumber.message}
            </p>
          )}
        </div>

        <div className="space-y-2">
          <Label htmlFor="holderName">Nome do titular</Label>
          <Input
            id="holderName"
            autoComplete="cc-name"
            placeholder="JOÃO M A SILVA"
            {...holderNameField}
            onFocus={() => setFocused('name')}
          />
          {errors.holderName && (
            <p className="text-sm text-error-500">
              {errors.holderName.message}
            </p>
          )}
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
            <Label htmlFor="expiryDate">Validade</Label>
            <Input
              id="expiryDate"
              autoComplete="cc-exp"
              placeholder="MM/AA"
              {...expiryDateField}
              onFocus={() => setFocused('expiry')}
            />
            {errors.expiryDate && (
              <p className="text-sm text-error-500">
                {errors.expiryDate.message}
              </p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="cvv">CVV</Label>
            <Input
              id="cvv"
              inputMode="numeric"
              autoComplete="cc-csc"
              placeholder="123"
              {...cvvField}
              onFocus={() => setFocused('cvc')}
            />
            {errors.cvv && (
              <p className="text-sm text-error-500">{errors.cvv.message}</p>
            )}
          </div>
        </div>

        <Button type="submit" className="w-full" disabled={isPending}>
          {isPending ? (
            <>
              <Loader2 className="mr-2 h-4 w-4 animate-spin" />
              Processando...
            </>
          ) : (
            'Pagar'
          )}
        </Button>
      </form>
    </div>
  )
}
