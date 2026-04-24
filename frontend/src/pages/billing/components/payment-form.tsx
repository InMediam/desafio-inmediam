import 'react-credit-cards-2/dist/es/styles-compiled.css'

import { zodResolver } from '@hookform/resolvers/zod'
import { Button, Input, Label } from '@inmediam/ui'
import { useMutation } from '@tanstack/react-query'
import axios from 'axios'
import { Loader2 } from 'lucide-react'
import { useState } from 'react'
import Cards, { Focused } from 'react-credit-cards-2'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { z } from 'zod'

const paymentSchema = z.object({
  cardNumber: z.string().regex(/^\d{13,19}$/, 'Número do cartão inválido'),
  holderName: z.string().min(3, 'Nome deve ter pelo menos 3 caracteres'),
  expiryDate: z.string().regex(/^\d{2}\/\d{2}$/, 'Formato inválido (MM/AA)'),
  cvv: z.string().regex(/^\d{3,4}$/, 'CVV inválido'),
  phone: z.string().regex(/^\d{10,11}$/, 'Telefone deve ter 10 ou 11 dígitos'),
  postalCode: z.string().regex(/^\d{8}$/, 'CEP deve ter 8 dígitos'),
  addressNumber: z.string().min(1, 'Número do endereço é obrigatório'),
})

type PaymentFormData = z.infer<typeof paymentSchema>

interface PaymentFormProps {
  billingId: string
  amount: number
}

export function PaymentForm({ billingId, amount }: PaymentFormProps) {
  const [cardNumber, setCardNumber] = useState<string>()
  const [cvv, setCvv] = useState<string>()
  const [focused, setFocused] = useState<Focused>('')

  const {
    register,
    handleSubmit,
    watch,
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

  const { mutateAsync: submitPayment, isPending } = useMutation({
    mutationFn: (data: PaymentFormData) =>
      axios.post(`${import.meta.env.VITE_API_URL}/api/billing/${billingId}/pay`, {
          card_number: data.cardNumber,
          card_holder_name: data.holderName,
          expiry_date: data.expiryDate,
          cvv: data.cvv,
          amount,
          phone: data.phone,
          postal_code: data.postalCode,
          address_number: data.addressNumber,
        }),
    onSuccess: () => {
      toast.success('Pagamento realizado com sucesso!')
    },
    onError: () => {
      toast.error('Erro ao processar pagamento. Verifique os dados e tente novamente.')
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
          <Label htmlFor="cardNumber">Número do cartão</Label>
          <Input
            id="cardNumber"
            placeholder="0000 0000 0000 0000"
            value={cardNumber}
            {...register('cardNumber')}
            onChange={(e) => {
              setCardNumber(e.target.value)
              register('cardNumber').onChange(e)
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
            <Label htmlFor="cvv">CVV</Label>
            <Input
              id="cvv"
              placeholder="123"
              value={cvv}
              {...register('cvv')}
              onChange={(e) => {
                setCvv(e.target.value)
                register('cvv').onChange(e)
              }}
              onFocus={() => setFocused('cvc')}
            />
            {errors.cvv && (
              <p className="text-sm text-error-500">{errors.cvv.message}</p>
            )}
          </div>
        </div>

        <div className="border-t border-border pt-4">
          <h3 className="mb-4 text-sm font-semibold text-foreground">
            Endereço de cobrança
          </h3>
          <div className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="phone">Telefone</Label>
              <Input
                id="phone"
                placeholder="11999999999"
                {...register('phone')}
              />
              {errors.phone && (
                <p className="text-sm text-error-500">{errors.phone.message}</p>
              )}
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="postalCode">CEP</Label>
                <Input
                  id="postalCode"
                  placeholder="01310100"
                  {...register('postalCode')}
                />
                {errors.postalCode && (
                  <p className="text-sm text-error-500">
                    {errors.postalCode.message}
                  </p>
                )}
              </div>

              <div className="space-y-2">
                <Label htmlFor="addressNumber">Número</Label>
                <Input
                  id="addressNumber"
                  placeholder="123"
                  {...register('addressNumber')}
                />
                {errors.addressNumber && (
                  <p className="text-sm text-error-500">
                    {errors.addressNumber.message}
                  </p>
                )}
              </div>
            </div>
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
