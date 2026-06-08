import 'react-credit-cards-2/dist/es/styles-compiled.css'

import { zodResolver } from '@hookform/resolvers/zod'
import { Button, Input, Label } from '@inmediam/ui'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Loader2 } from 'lucide-react'
import { useState } from 'react'
import Cards, { Focused } from 'react-credit-cards-2'
import { Controller, useForm } from 'react-hook-form'
import { toast } from 'sonner'

import { api, getApiErrorMessage } from '@/lib/api'
import {
  type PaymentFormData,
  paymentSchema,
} from '@/schemas/payment-schema'
import {
  formatCardNumber,
  formatCvv,
  formatExpiryDate,
  stripDigits,
  CARD_NUMBER_MAX_LENGTH,
  CVV_LENGTH,
} from '@/utils/card'

interface PaymentFormProps {
  billingId: string
}

export function PaymentForm({ billingId }: PaymentFormProps) {
  const queryClient = useQueryClient()
  const [focused, setFocused] = useState<Focused>('')

  const {
    control,
    handleSubmit,
    watch,
    formState: { errors },
  } = useForm<PaymentFormData>({
    resolver: zodResolver(paymentSchema),
    defaultValues: {
      cardNumber: '',
      holderName: '',
      expiryDate: '',
      cvv: '',
    },
  })

  const watchedValues = watch()

  const { mutate: submitPayment, isPending } = useMutation({
    mutationFn: (data: PaymentFormData) =>
      api.post(`/billing/${billingId}/pay`, {
        card_number: stripDigits(data.cardNumber),
        card_holder_name: data.holderName.toUpperCase(),
        expiry_date: data.expiryDate,
        cvv: data.cvv,
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['billing', billingId] })
      queryClient.invalidateQueries({ queryKey: ['billings'] })
      toast.success('Pagamento realizado com sucesso!')
    },
    onError: (error) => {
      toast.error(
        getApiErrorMessage(
          error,
          'Não foi possível processar o pagamento. Tente novamente.',
        ),
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
          <Controller
            name="cardNumber"
            control={control}
            render={({ field }) => (
              <Input
                id="cardNumber"
                placeholder="0000 0000 0000 0000"
                inputMode="numeric"
                autoComplete="cc-number"
                maxLength={CARD_NUMBER_MAX_LENGTH + 3}
                value={field.value}
                onChange={(e) => field.onChange(formatCardNumber(e.target.value))}
                onBlur={field.onBlur}
                onFocus={() => setFocused('number')}
              />
            )}
          />
          {errors.cardNumber && (
            <p className="text-sm text-error-500">
              {errors.cardNumber.message}
            </p>
          )}
        </div>

        <div className="space-y-2">
          <Label htmlFor="holderName">Nome do titular</Label>
          <Controller
            name="holderName"
            control={control}
            render={({ field }) => (
              <Input
                id="holderName"
                placeholder="JOÃO M A SILVA"
                autoComplete="cc-name"
                maxLength={50}
                value={field.value}
                onChange={(e) => field.onChange(e.target.value.toUpperCase())}
                onBlur={field.onBlur}
                onFocus={() => setFocused('name')}
              />
            )}
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
            <Controller
              name="expiryDate"
              control={control}
              render={({ field }) => (
                <Input
                  id="expiryDate"
                  placeholder="MM/AA"
                  inputMode="numeric"
                  autoComplete="cc-exp"
                  maxLength={5}
                  value={field.value}
                  onChange={(e) =>
                    field.onChange(formatExpiryDate(e.target.value))
                  }
                  onBlur={field.onBlur}
                  onFocus={() => setFocused('expiry')}
                />
              )}
            />
            {errors.expiryDate && (
              <p className="text-sm text-error-500">
                {errors.expiryDate.message}
              </p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="cvv">CVV</Label>
            <Controller
              name="cvv"
              control={control}
              render={({ field }) => (
                <Input
                  id="cvv"
                  placeholder="123"
                  inputMode="numeric"
                  autoComplete="cc-csc"
                  maxLength={CVV_LENGTH}
                  value={field.value}
                  onChange={(e) => field.onChange(formatCvv(e.target.value))}
                  onBlur={field.onBlur}
                  onFocus={() => setFocused('cvc')}
                />
              )}
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
