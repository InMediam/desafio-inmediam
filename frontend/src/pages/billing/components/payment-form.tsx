import 'react-credit-cards-2/dist/es/styles-compiled.css'

import { zodResolver } from '@hookform/resolvers/zod'
import { Button, Input, Label } from '@inmediam/ui'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { Loader2 } from 'lucide-react'
import { useState } from 'react'
import Cards, { Focused } from 'react-credit-cards-2'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { z } from 'zod'

import { api } from '../../../lib/api'

const paymentSchema = z.object({
  cardNumber: z
    .string()
    .transform((value) => value.replace(/\s/g, ''))
    .refine((value) => /^\d{13,19}$/.test(value), {
      message: 'Informe um número de cartão válido.',
    }),

  holderName: z
    .string()
    .trim()
    .min(2, 'Informe o nome do titular.')
    .max(100, 'O nome do titular é muito longo.'),

  expiryDate: z
    .string()
    .regex(/^(0[1-9]|1[0-2])\/\d{2}$/, 'Use o formato MM/AA.'),

  cvv: z.string().regex(/^\d{3,4}$/, 'O CVV deve ter 3 ou 4 dígitos.'),
})

type PaymentFormData = z.infer<typeof paymentSchema>

interface PaymentFormProps {
  billingId: string
}

export function PaymentForm({ billingId }: PaymentFormProps) {
  const queryClient = useQueryClient()
  const [cardNumber, setCardNumber] = useState<string>()
  const [cvv, setCvv] = useState<string>()
  const [focused, setFocused] = useState<Focused>('')

  const {
    register,
    handleSubmit,
    watch,
    setValue,
    formState: { errors },
  } = useForm<PaymentFormData>({
    resolver: zodResolver(paymentSchema),
  })

  const watchedValues = {
    cardNumber: watch('cardNumber', ''),
    holderName: watch('holderName', ''),
    expiryDate: watch('expiryDate', ''),
    cvv: watch('cvv', ''),
  }

  const { mutate: submitPayment, isPending } = useMutation({
    mutationFn: (data: PaymentFormData) =>
      api.post(`/billing/${billingId}/pay`, {
        card_number: data.cardNumber,
        card_holder_name: data.holderName,
        expiry_date: data.expiryDate,
        cvv: data.cvv,
      }),
    onSuccess: () => {
      toast.success('Pagamento realizado com sucesso!')

      queryClient.invalidateQueries({
        queryKey: ['billing', billingId],
      })
    },
    onError: (error) => {
      const axiosError = error as AxiosError<{ message?: string }>

      toast.error(
        axiosError.response?.data?.message ??
          'Não foi possível realizar o pagamento. Tente novamente.',
      )
    },
  })

  function handlePayment(data: PaymentFormData) {
    submitPayment(data)
  }

  return (
    <div className="w-full rounded-lg border border-border bg-card p-6 shadow-sm">
      <h2 className="mb-6 text-lg font-semibold text-foreground">
        Dados do cartão
      </h2>

      <div className="mb-6">
        <Cards
          number={cardNumber || watchedValues.cardNumber || ''}
          name={watchedValues.holderName}
          expiry={watchedValues.expiryDate}
          cvc={cvv || watchedValues.cvv || ''}
          focused={focused}
        />
      </div>

      <form onSubmit={handleSubmit(handlePayment)} className="space-y-4">
        <div className="space-y-2">
          <Input
            id="cardNumber"
            placeholder="0000 0000 0000 0000"
            value={cardNumber || ''}
            {...register('cardNumber')}
            onChange={(e) => {
              setCardNumber(e.target.value)
              setValue('cardNumber', e.target.value, {
                shouldValidate: true,
                shouldDirty: true,
              })
            }}
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
            placeholder="JOÃO M A SILVA"
            {...register('holderName')}
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
              placeholder="MM/AA"
              {...register('expiryDate')}
              onFocus={() => setFocused('expiry')}
            />
            {errors.expiryDate && (
              <p className="text-sm text-error-500">
                {errors.expiryDate.message}
              </p>
            )}
          </div>

          <div className="space-y-2">
            <Input
              id="cvv"
              placeholder="123"
              value={cvv || ''}
              {...register('cvv')}
              onChange={(e) => {
                setCvv(e.target.value)
                setValue('cvv', e.target.value, {
                  shouldValidate: true,
                  shouldDirty: true,
                })
              }}
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
