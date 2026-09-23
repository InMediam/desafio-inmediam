import 'react-credit-cards-2/dist/es/styles-compiled.css'

import { zodResolver } from '@hookform/resolvers/zod'
import { Button, Input, Label } from '@inmediam/ui'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { Loader2 } from 'lucide-react'
import { useState } from 'react'
import Cards, { Focused } from 'react-credit-cards-2'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { z } from 'zod'

// Valida os dados no frontend antes de enviar o pagamento para o backend.
const paymentSchema = z.object({
  cardNumber: z
    .string()
    .transform((value) => value.replace(/\D/g, ''))
    .pipe(z.string().min(13).max(19)),
  holderName: z.string().min(2),
  expiryDate: z.string().regex(/^(0[1-9]|1[0-2])\/\d{2}$/),
  cvv: z.string().min(3).max(4),
})

type PaymentFormData = z.infer<typeof paymentSchema>

interface PaymentFormProps {
  billingId: string
}

export function PaymentForm({ billingId }: PaymentFormProps) {
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

  const queryClient = useQueryClient()
  const { mutateAsync: submitPayment, isPending } = useMutation({
    mutationFn: (data: PaymentFormData) =>
      axios
        .create()
        .post(`${import.meta.env.VITE_API_URL}/api/billing/${billingId}/pay`, {
          // Remove a formatação antes de enviar o número do cartão ao backend.
          card_number: data.cardNumber.replace(/\D/g, ''),
          card_holder_name: data.holderName,
          expiry_date: data.expiryDate,
          cvv: data.cvv,
        }),
    onSuccess: () => {
      toast.success('Pagamento realizado com sucesso!')
      // Atualiza a cobrança atual e a lista da Home após o pagamento.
      queryClient.invalidateQueries({ queryKey: ['billing', billingId] })
      queryClient.invalidateQueries({ queryKey: ['billings'] })
    },
    // Exibe uma mensagem de erro quando o pagamento não é processado.
    onError: (error) => {
      if (axios.isAxiosError<{ message?: string }>(error)) {
        toast.error(
          error.response?.data?.message ?? 'Erro ao processar o pagamento!',
        )
        return
      }

      toast.error('Erro ao processar o pagamento!')
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
          number={watchedValues.cardNumber.match(/.{1,4}/g)?.join(' ') || ''}
          name={watchedValues.holderName}
          expiry={watchedValues.expiryDate}
          cvc={watchedValues.cvv}
          focused={focused}
        />
      </div>

      <form onSubmit={handleSubmit(handlePayment)} className="space-y-4">
        <div className="space-y-2">
          <Label htmlFor="cardNumber">Número do cartão</Label>
          {/* Aplica máscara visual ao cartão, mantendo apenas os números no valor enviado à API. */}
          <Input
            id="cardNumber"
            placeholder="0000 0000 0000 0000"
            value={watchedValues.cardNumber.match(/.{1,4}/g)?.join(' ') || ''}
            {...register('cardNumber')}
            onChange={(e) => {
              const value = e.target.value.replace(/\D/g, '').slice(0, 19)
              setValue('cardNumber', value, { shouldValidate: true })
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
            {/* Formata a validade automaticamente no padrão MM/AA. */}
            <Input
              id="expiryDate"
              placeholder="MM/AA"
              value={watchedValues.expiryDate}
              {...register('expiryDate')}
              onChange={(e) => {
                const value = e.target.value.replace(/\D/g, '').slice(0, 4)
                const formattedValue =
                  value.length > 2
                    ? `${value.slice(0, 2)}/${value.slice(2)}`
                    : value
                setValue('expiryDate', formattedValue, { shouldValidate: true })
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
              placeholder="123"
              value={watchedValues.cvv}
              {...register('cvv')}
              onChange={(e) => {
                // Permite somente números e limita o CVV a quatro dígitos.
                const value = e.target.value.replace(/\D/g, '').slice(0, 4)
                setValue('cvv', value, { shouldValidate: true })
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
