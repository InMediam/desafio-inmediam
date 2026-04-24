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

const paymentSchema = z.object({
  cardNumber: z.string().refine(v => /^\d{13,19}$/.test(v.replace(/\s+/g, '')), 'Número do cartão inválido'),
  holderName: z.string().min(3, 'Nome deve ter pelo menos 3 caracteres'),
  expiryDate: z.string().regex(/^(0[1-9]|1[0-2])\/\d{2}$/, 'Formato inválido (MM/AA)'),
  cvv: z.string().regex(/^\d{3,4}$/, 'CVV inválido'),
  phone: z.string().regex(/^\(\d{2}\) \d{4,5}-\d{4}$/, 'Telefone inválido'),
  postalCode: z.string().regex(/^\d{5}-\d{3}$/, 'CEP deve ter 8 dígitos'),
  addressNumber: z.string().min(1, 'Número do endereço é obrigatório'),
})

type PaymentFormData = z.infer<typeof paymentSchema>

interface PaymentFormProps {
  billingId: string
  amount: number
}

function formatCardNumber(value: string): string {
  const digits = value.replace(/\D/g, '').slice(0, 16)
  return digits.replace(/(\d{4})(?=\d)/g, '$1 ')
}

function formatCep(value: string): string {
  const digits = value.replace(/\D/g, '').slice(0, 8)
  return digits.length > 5 ? `${digits.slice(0, 5)}-${digits.slice(5)}` : digits
}

function formatPhone(value: string): string {
  const digits = value.replace(/\D/g, '').slice(0, 11)
  if (digits.length <= 2) return digits.length ? `(${digits}` : ''
  if (digits.length <= 6) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`
  if (digits.length <= 10) return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`
  return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`
}

function formatExpiry(value: string): string {
  const digits = value.replace(/\D/g, '').slice(0, 4)
  return digits.length > 2 ? `${digits.slice(0, 2)}/${digits.slice(2)}` : digits
}

export function PaymentForm({ billingId, amount }: PaymentFormProps) {
  const queryClient = useQueryClient()
  const [cardNumber, setCardNumber] = useState('')
  const [holderName, setHolderName] = useState('')
  const [expiryDate, setExpiryDate] = useState('')
  const [cvv, setCvv] = useState('')
  const [phone, setPhone] = useState('')
  const [postalCode, setPostalCode] = useState('')
  const [focused, setFocused] = useState<Focused>('')

  const {
    register,
    handleSubmit,
    setValue,
    formState: { errors },
  } = useForm<PaymentFormData>({
    resolver: zodResolver(paymentSchema),
  })

  const { mutateAsync: submitPayment, isPending } = useMutation({
    mutationFn: (data: PaymentFormData) =>
      axios.post(`${import.meta.env.VITE_API_URL}/api/billing/${billingId}/pay`, {
        card_number: data.cardNumber.replace(/\s+/g, ''),
        card_holder_name: data.holderName,
        expiry_date: data.expiryDate,
        cvv: data.cvv,
        amount,
        phone: data.phone.replace(/\D/g, ''),
        postal_code: data.postalCode.replace(/\D/g, ''),
        address_number: data.addressNumber,
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['billing', billingId] })
      queryClient.invalidateQueries({ queryKey: ['billings'] })
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
          number={cardNumber}
          name={holderName}
          expiry={expiryDate}
          cvc={cvv}
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
              const formatted = formatCardNumber(e.target.value)
              setCardNumber(formatted)
              setValue('cardNumber', formatted, { shouldValidate: true })
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
            value={holderName}
            {...register('holderName')}
            onChange={(e) => {
              setHolderName(e.target.value)
              setValue('holderName', e.target.value, { shouldValidate: true })
            }}
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
              value={expiryDate}
              {...register('expiryDate')}
              onChange={(e) => {
                const formatted = formatExpiry(e.target.value)
                setExpiryDate(formatted)
                setValue('expiryDate', formatted, { shouldValidate: true })
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
              value={cvv}
              {...register('cvv')}
              onChange={(e) => {
                const digits = e.target.value.replace(/\D/g, '').slice(0, 4)
                setCvv(digits)
                setValue('cvv', digits, { shouldValidate: true })
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
                placeholder="(11) 99999-9999"
                value={phone}
                {...register('phone')}
                onChange={(e) => {
                  const formatted = formatPhone(e.target.value)
                  setPhone(formatted)
                  setValue('phone', formatted, { shouldValidate: true })
                }}
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
                  placeholder="01310-100"
                  value={postalCode}
                  {...register('postalCode')}
                  onChange={(e) => {
                    const formatted = formatCep(e.target.value)
                    setPostalCode(formatted)
                    setValue('postalCode', formatted, { shouldValidate: true })
                  }}
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
