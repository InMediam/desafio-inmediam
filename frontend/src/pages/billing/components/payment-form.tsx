import 'react-credit-cards-2/dist/es/styles-compiled.css'

import { zodResolver } from '@hookform/resolvers/zod'
import { Button, Input, Label } from '@inmediam/ui'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import axios from 'axios'

import { api } from '@/lib/api'
import { CreditCard, Loader2 } from 'lucide-react'
import { useState } from 'react'
import Cards, { Focused } from 'react-credit-cards-2'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { z } from 'zod'

const paymentSchema = z.object({
  cardNumber: z
    .string()
    .min(1, 'Número do cartão é obrigatório')
    .refine((val) => /^\d{13,16}$/.test(val.replace(/\s/g, '')), {
    message: 'Número do cartão deve ter entre 13 e 16 dígitos',
  }),
  holderName: z
    .string()
    .min(1, 'Nome do titular é obrigatório')
    .max(255, 'Nome muito longo'),
  expiryDate: z
    .string()
    .min(1, 'Validade é obrigatória')
    .regex(/^(0[1-9]|1[0-2])\/\d{2}$/, 'Use o formato MM/AA'),
  cvv: z
    .string()
    .min(1, 'CVV é obrigatório')
    .regex(/^\d{3,4}$/, 'CVV deve ter 3 ou 4 dígitos'),
})

type PaymentFormData = z.infer<typeof paymentSchema>

interface PaymentFormProps {
  billingId: string
  amount: number
}

function formatExpiryDate(value: string): string {
  const digits = value.replace(/\D/g, '').slice(0, 4)
  if (digits.length <= 2) return digits
  return `${digits.slice(0, 2)}/${digits.slice(2)}`
}

export function PaymentForm({ billingId }: PaymentFormProps) {
  const [cardNumber, setCardNumber] = useState<string>('')
  const [expiryDate, setExpiryDate] = useState<string>('')
  const [cvv, setCvv] = useState<string>('')
  const [focused, setFocused] = useState<Focused>('')
  const queryClient = useQueryClient()

  const {
    register,
    handleSubmit,
    watch,
    reset,
    setValue,
    formState: { errors },
  } = useForm<PaymentFormData>({
    resolver: zodResolver(paymentSchema),
  })

  const CARD_MAX_DIGITS = 16
  const CARD_MAX_LENGTH = CARD_MAX_DIGITS + Math.floor(CARD_MAX_DIGITS / 4) // 16 + 3 espaços = 19

  function formatCardNumber(value: string): string {
    const digits = value.replace(/\D/g, '').slice(0, CARD_MAX_DIGITS)
    return digits.replace(/(\d{4})(?=\d)/g, '$1 ')
  }

  function handleCardNumberChange(raw: string): void {
    const formatted = formatCardNumber(raw)
    setCardNumber(formatted)
    setValue('cardNumber', formatted, { shouldValidate: true })
  }

  function handleCardNumberKeyDown(e: React.KeyboardEvent<HTMLInputElement>): void {
    const digits = cardNumber.replace(/\D/g, '')
    if (digits.length >= CARD_MAX_DIGITS && /^\d$/.test(e.key) && !e.metaKey && !e.ctrlKey && !e.altKey) {
      e.preventDefault()
    }
  }

  const watchedValues = {
    cardNumber: watch('cardNumber', ''),
    holderName: watch('holderName', ''),
    expiryDate: expiryDate || watch('expiryDate', ''),
    cvv: watch('cvv', ''),
  }

  function handleExpiryDateChange(raw: string): void {
    const formatted = formatExpiryDate(raw)
    setExpiryDate(formatted)
    setValue('expiryDate', formatted, { shouldValidate: true })
  }

  function handleCvvChange(raw: string): void {
    const digits = raw.replace(/\D/g, '').slice(0, 4)
    setCvv(digits)
    setValue('cvv', digits, { shouldValidate: true })
  }

  const { mutateAsync: submitPayment, isPending } = useMutation({
    mutationFn: (data: PaymentFormData) =>
      api.post(`/api/billing/${billingId}/pay`, {
        card_number: data.cardNumber.replace(/\s/g, ''),
        card_holder_name: data.holderName.trim(),
        expiry_date: data.expiryDate,
        cvv: data.cvv,
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['billing', billingId] })
      reset(undefined, { keepValues: false })
      setCardNumber('')
      setExpiryDate('')
      setCvv('')
      toast.success('Pagamento realizado com sucesso!')
    },
    onError: (error: unknown) => {
      const message =
        axios.isAxiosError(error) && error.response?.data?.message
          ? error.response.data.message
          : 'Não foi possível processar o pagamento. Tente novamente.'
      toast.error(message)
    },
  })

  function handlePayment(data: PaymentFormData) {
    submitPayment(data)
  }

  return (
    <div className="w-full rounded-3xl border border-border/80 bg-card/95 p-6 shadow-xl shadow-black/5 backdrop-blur-sm transition-shadow hover:shadow-2xl md:p-8">
      <div className="mb-6 flex items-center gap-3">
        <div className="rounded-xl bg-primary/10 p-2">
          <CreditCard className="h-5 w-5 text-primary" />
        </div>
        <div>
          <h2 className="text-lg font-bold text-foreground">
            Dados do cartão
          </h2>
          <p className="text-xs text-muted-foreground mt-0.5">
            Preencha os dados para concluir o pagamento
          </p>
        </div>
      </div>

      <div className="mb-6">
        <Cards
          number={cardNumber || watchedValues.cardNumber || ''}
          name={watchedValues.holderName}
          expiry={watchedValues.expiryDate}
          cvc={cvv || watchedValues.cvv || ''}
          focused={focused}
          locale={{ valid: 'válido até' }}
          placeholders={{ name: 'SEU NOME AQUI' }}
        />
      </div>

      <form onSubmit={handleSubmit(handlePayment)} className="space-y-4">
        <div className="space-y-2">
          <Label htmlFor="cardNumber">Número do cartão</Label>
          <Input
            id="cardNumber"
            type="text"
            inputMode="numeric"
            autoComplete="cc-number"
            maxLength={CARD_MAX_LENGTH}
            placeholder="0000 0000 0000 0000"
            value={cardNumber}
            {...register('cardNumber')}
            onChange={(e) => handleCardNumberChange(e.target.value)}
            onKeyDown={handleCardNumberKeyDown}
            onPaste={(e) => {
              e.preventDefault()
              const pasted = (e.clipboardData?.getData('text') ?? '').replace(/\D/g, '').slice(0, CARD_MAX_DIGITS)
              handleCardNumberChange(pasted)
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
            type="text"
            autoComplete="cc-name"
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
              type="text"
              inputMode="numeric"
              autoComplete="cc-exp"
              placeholder="MM/AA"
              maxLength={5}
              value={expiryDate}
              {...register('expiryDate')}
              onChange={(e) => handleExpiryDateChange(e.target.value)}
              onPaste={(e) => {
                e.preventDefault()
                const pasted = (e.clipboardData?.getData('text') ?? '').replace(/\D/g, '').slice(0, 4)
                handleExpiryDateChange(pasted)
              }}
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
              type="password"
              inputMode="numeric"
              autoComplete="cc-csc"
              maxLength={4}
              placeholder="123"
              value={cvv}
              {...register('cvv')}
              onChange={(e) => handleCvvChange(e.target.value)}
              onPaste={(e) => {
                e.preventDefault()
                const pasted = (e.clipboardData?.getData('text') ?? '').replace(/\D/g, '').slice(0, 4)
                handleCvvChange(pasted)
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
