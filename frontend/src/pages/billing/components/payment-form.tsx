import 'react-credit-cards-2/dist/es/styles-compiled.css'

import { zodResolver } from '@hookform/resolvers/zod'
import { Button, Input, Label } from '@inmediam/ui'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Loader2 } from 'lucide-react'
import { useState } from 'react'
import Cards, { Focused } from 'react-credit-cards-2'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { z } from 'zod'
import { api } from '@/lib/axios'

const paymentSchema = z.object({
  cardNumber: z.string().regex(/^\d{16}$/, 'O cartão deve ter 16 dígitos numéricos.'),
  holderName: z.string().min(3, 'Nome inválido.'),
  expiryDate: z.string().regex(/^(0[1-9]|1[0-2])\/\d{2}$/, 'Formato inválido. Use MM/AA.'),
  cvv: z.string().regex(/^\d{3}$/, 'O CVV deve ter 3 dígitos.'),
})

type PaymentFormData = z.infer<typeof paymentSchema>

interface PaymentFormProps {
  billingId: string
} // Removido o 'amount' das props, pois o frontend não deve mandar isso

export function PaymentForm({ billingId }: PaymentFormProps) {
  const queryClient = useQueryClient()
  const [focused, setFocused] = useState<Focused>('')

  const {
    register,
    handleSubmit,
    watch,
    reset,
    formState: { errors },
  } = useForm<PaymentFormData>({
    resolver: zodResolver(paymentSchema),
  })

  const watchedValues = watch()

  const { mutateAsync: submitPayment, isPending } = useMutation({
    mutationFn: async (data: PaymentFormData) => {
      // Usando a instância global do axios e removendo o 'amount' do payload
      const response = await api.post(`/billing/${billingId}/pay`, {
        card_number: data.cardNumber,
        card_holder_name: data.holderName,
        expiry_date: data.expiryDate,
        cvv: data.cvv,
      })
      return response.data
    },
    onSuccess: () => {
      toast.success('Pagamento aprovado com sucesso!')
      reset() // Limpa o formulário
      // 3. Invalidação de Cache:
      queryClient.invalidateQueries({ queryKey: ['billing', billingId] })
    },
    onError: (error: any) => {
      // 4. Correção do Falso Positivo
      const errorMessage = error.response?.data?.error || 'Erro ao processar o pagamento.'
      toast.error(errorMessage)
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
          number={watchedValues.cardNumber || ''}
          name={watchedValues.holderName || ''}
          expiry={watchedValues.expiryDate || ''}
          cvc={watchedValues.cvv || ''}
          focused={focused}
        />
      </div>

      <form onSubmit={handleSubmit(handlePayment)} className="space-y-4">
        <div className="space-y-2">
          <Label htmlFor="cardNumber">Número do cartão (Apenas números)</Label>
          <Input
            id="cardNumber"
            maxLength={16}
            placeholder="0000000000000000"
            {...register('cardNumber')}
            onFocus={() => setFocused('number')}
          />
          {errors.cardNumber && <p className="text-sm text-error-500">{errors.cardNumber.message}</p>}
        </div>

        <div className="space-y-2">
          <Label htmlFor="holderName">Nome do titular</Label>
          <Input
            id="holderName"
            placeholder="JOÃO M A SILVA"
            {...register('holderName')}
            onFocus={() => setFocused('name')}
          />
          {errors.holderName && <p className="text-sm text-error-500">{errors.holderName.message}</p>}
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
            <Label htmlFor="expiryDate">Validade (MM/AA)</Label>
            <Input
              id="expiryDate"
              placeholder="12/29"
              {...register('expiryDate')}
              onFocus={() => setFocused('expiry')}
            />
            {errors.expiryDate && <p className="text-sm text-error-500">{errors.expiryDate.message}</p>}
          </div>

          <div className="space-y-2">
            <Label htmlFor="cvv">CVV</Label>
            <Input
              id="cvv"
              placeholder="123"
              {...register('cvv')}
              onFocus={() => setFocused('cvc')}
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