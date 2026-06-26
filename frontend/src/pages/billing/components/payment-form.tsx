import 'react-credit-cards-2/dist/es/styles-compiled.css'

import { zodResolver } from '@hookform/resolvers/zod'
import { Button, Input, Label } from '@inmediam/ui'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Loader2 } from 'lucide-react'
import Cards, { Focused } from 'react-credit-cards-2'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { z } from 'zod'

import { api, getApiErrorMessage } from '@/lib/api'

const onlyNumbers = (value: string) => value.replace(/\D/g, '')

const paymentSchema = z.object({
  cardNumber: z
    .string()
    .transform(onlyNumbers)
    .refine((value) => value.length >= 13 && value.length <= 19, 'Informe um cartão válido.'),
  holderName: z
    .string()
    .trim()
    .min(3, 'Informe o nome do titular.')
    .max(100, 'O nome do titular é muito longo.'),
  expiryDate: z
    .string()
    .regex(/^(0[1-9]|1[0-2])\/\d{2}$/, 'Informe a validade no formato MM/AA.')
    .refine((value) => {
      const [month, year] = value.split('/').map(Number)
      const now = new Date()
      const expiration = new Date(2000 + year, month)
      return expiration > new Date(now.getFullYear(), now.getMonth())
    }, 'O cartão está vencido.'),
  cvv: z
    .string()
    .transform(onlyNumbers)
    .refine((value) => value.length === 3 || value.length === 4, 'Informe um CVV válido.'),
})

type PaymentFormData = z.infer<typeof paymentSchema>

interface PaymentFormProps {
  billingId: string
  amount: number
}

function formatCardNumber(value: string) {
  return onlyNumbers(value).slice(0, 19).replace(/(.{4})/g, '$1 ').trim()
}

function formatExpiryDate(value: string) {
  const numbers = onlyNumbers(value).slice(0, 4)

  if (numbers.length <= 2) return numbers

  return `${numbers.slice(0, 2)}/${numbers.slice(2)}`
}

export function PaymentForm({ billingId, amount }: PaymentFormProps) {
  const queryClient = useQueryClient()
  const [focused, setFocused] = useState<Focused>('')

  const {
    register,
    handleSubmit,
    setValue,
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
  const cardNumberRegistration = register('cardNumber')
  const expiryDateRegistration = register('expiryDate')
  const cvvRegistration = register('cvv')

  const { mutateAsync: submitPayment, isPending } = useMutation({
    mutationFn: (data: PaymentFormData) =>
      api.post(`/billing/${billingId}/pay`, {
        card_number: data.cardNumber,
        card_holder_name: data.holderName,
        expiry_date: data.expiryDate,
        cvv: data.cvv,
        amount,
      }),
    onSuccess: async () => {
      toast.success('Pagamento realizado com sucesso!')
      await queryClient.invalidateQueries({ queryKey: ['billing', billingId] })
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error))
    },
  })

  async function handlePayment(data: PaymentFormData) {
    await submitPayment(data)
  }

  return (
    <div className="w-full rounded-lg border border-border bg-card p-6 shadow-sm">
      <h2 className="mb-6 text-lg font-semibold text-foreground">Dados do cartão</h2>

      <div className="mb-6">
        <Cards
          number={onlyNumbers(watchedValues.cardNumber)}
          name={watchedValues.holderName}
          expiry={watchedValues.expiryDate.replace('/', '')}
          cvc={onlyNumbers(watchedValues.cvv)}
          focused={focused}
        />
      </div>

      <form onSubmit={handleSubmit(handlePayment)} className="space-y-4" noValidate>
        <div className="space-y-2">
          <Label htmlFor="cardNumber">Número do cartão</Label>
          <Input
            id="cardNumber"
            placeholder="0000 0000 0000 0000"
            inputMode="numeric"
            autoComplete="cc-number"
            {...cardNumberRegistration}
            onChange={(event) => {
              setValue('cardNumber', formatCardNumber(event.target.value), { shouldValidate: true })
            }}
            onFocus={() => setFocused('number')}
            disabled={isPending}
          />
          {errors.cardNumber && <p className="text-sm text-error-500">{errors.cardNumber.message}</p>}
        </div>

        <div className="space-y-2">
          <Label htmlFor="holderName">Nome do titular</Label>
          <Input
            id="holderName"
            placeholder="JOÃO M A SILVA"
            autoComplete="cc-name"
            {...register('holderName')}
            onFocus={() => setFocused('name')}
            disabled={isPending}
          />
          {errors.holderName && <p className="text-sm text-error-500">{errors.holderName.message}</p>}
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
            <Label htmlFor="expiryDate">Validade</Label>
            <Input
              id="expiryDate"
              placeholder="MM/AA"
              inputMode="numeric"
              autoComplete="cc-exp"
              {...expiryDateRegistration}
              onChange={(event) => {
                setValue('expiryDate', formatExpiryDate(event.target.value), { shouldValidate: true })
              }}
              onFocus={() => setFocused('expiry')}
              disabled={isPending}
            />
            {errors.expiryDate && <p className="text-sm text-error-500">{errors.expiryDate.message}</p>}
          </div>

          <div className="space-y-2">
            <Label htmlFor="cvv">CVV</Label>
            <Input
              id="cvv"
              placeholder="123"
              inputMode="numeric"
              autoComplete="cc-csc"
              {...cvvRegistration}
              onChange={(event) => {
                setValue('cvv', onlyNumbers(event.target.value).slice(0, 4), { shouldValidate: true })
              }}
              onFocus={() => setFocused('cvc')}
              disabled={isPending}
            />
            {errors.cvv && <p className="text-sm text-error-500">{errors.cvv.message}</p>}
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
