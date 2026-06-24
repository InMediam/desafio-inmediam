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

import { api } from '@/lib/api'
import type { CepStatus } from '@/lib/cep'

import { BillingAddressForm } from './billing-address-form'

const paymentSchema = z.object({
  cardNumber: z
    .string()
    .regex(/^\d{16}$/, 'O cartão deve ter 16 dígitos numéricos.'),
  holderName: z.string().min(3, 'Nome inválido.'),
  expiryDate: z
    .string()
    .regex(/^(0[1-9]|1[0-2])\/\d{2}$/, 'Formato inválido. Use MM/AA.'),
  cvv: z.string().regex(/^\d{3}$/, 'O CVV deve ter 3 dígitos.'),
  phone: z.string().min(1, 'O telefone é obrigatório.'),
  postalCode: z.string().min(1, 'O CEP é obrigatório.'),
  addressNumber: z.string().min(1, 'O número do endereço é obrigatório.'),
})

type PaymentFormData = z.infer<typeof paymentSchema>

interface PaymentFormProps {
  billingId: string
}

function formatCardNumber(value: string) {
  const digits = value.replace(/\D/g, '').slice(0, 16)
  return digits.replace(/(\d{4})(?=\d)/g, '$1 ')
}

function formatExpiryDate(value: string) {
  const digits = value.replace(/\D/g, '').slice(0, 4)

  if (digits.length === 0) return ''

  if (digits.length === 1) {
    const d = parseInt(digits[0], 10)
    if (d > 1) return '0' + digits[0] + '/'
    return digits
  }

  let month = parseInt(digits.slice(0, 2), 10)
  if (month > 12) month = 12

  const monthStr = String(month).padStart(2, '0')

  if (digits.length <= 2) return monthStr

  return monthStr + '/' + digits.slice(2, 4)
}

export function PaymentForm({ billingId }: PaymentFormProps) {
  const [cardNumber, setCardNumber] = useState('')
  const [cvv, setCvv] = useState('')
  const [holderName, setHolderName] = useState('')
  const [expiryDate, setExpiryDate] = useState('')
  const [focused, setFocused] = useState<Focused>('')
  const [cepStatus, setCepStatus] = useState<CepStatus>('idle')

  const {
    setValue,
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
      phone: '',
      postalCode: '',
      addressNumber: '',
    },
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
      api.post(`/billing/${billingId}/pay`, {
        card_number: data.cardNumber,
        card_holder_name: data.holderName,
        expiry_date: data.expiryDate,
        cvv: data.cvv,
        phone: data.phone.replace(/\D/g, ''),
        postal_code: data.postalCode.replace(/\D/g, ''),
        address_number: data.addressNumber,
      }),
    onSuccess: () => {
      toast.success('Pagamento realizado com sucesso!')
      queryClient.invalidateQueries({ queryKey: ['billing', billingId] })
    },
    onError: () => {
      toast.error('Erro ao realizar o pagamento.')
    },
  })

  function handlePayment(data: PaymentFormData) {
    submitPayment(data)
  }

  return (
    <div className="mt-7 w-full rounded-lg border border-border bg-card p-6 shadow-sm">
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
          <Label htmlFor="cardNumber" required>
            Número do cartão
          </Label>
          <Input
            id="cardNumber"
            placeholder="0000 0000 0000 0000"
            value={cardNumber}
            onChange={(e) => {
              const raw = e.target.value.replace(/\D/g, '').slice(0, 16)
              setCardNumber(formatCardNumber(raw))
              setValue('cardNumber', raw)
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
          <Label htmlFor="holderName" required>
            Nome do titular
          </Label>
          <Input
            id="holderName"
            placeholder="JOÃO M A SILVA"
            value={holderName}
            onChange={(e) => {
              const cleaned = e.target.value.replace(/[0-9]/g, '')
              setHolderName(cleaned)
              setValue('holderName', cleaned)
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
            <Label htmlFor="expiryDate" required>
              Validade
            </Label>
            <Input
              id="expiryDate"
              placeholder="MM/AA"
              value={expiryDate}
              onChange={(e) => {
                const formatted = formatExpiryDate(e.target.value)
                setExpiryDate(formatted)
                setValue('expiryDate', formatted)
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
            <Label htmlFor="cvv" required>
              CVV
            </Label>
            <Input
              id="cvv"
              placeholder="123"
              value={cvv}
              onChange={(e) => {
                const cleaned = e.target.value.replace(/\D/g, '').slice(0, 3)
                setCvv(cleaned)
                setValue('cvv', cleaned)
              }}
              onFocus={() => setFocused('cvc')}
            />
            {errors.cvv && (
              <p className="text-sm text-error-500">{errors.cvv.message}</p>
            )}
          </div>
        </div>

        <BillingAddressForm
          onChange={(data) => {
            setValue('phone', data.phone)
            setValue('postalCode', data.postalCode)
            setValue('addressNumber', data.addressNumber)
          }}
          onCepStatus={setCepStatus}
          errors={errors}
        />

        <Button
          type="submit"
          className="w-full"
          disabled={
            isPending || cepStatus === 'checking' || cepStatus === 'invalid'
          }
        >
          {isPending ? (
            <>
              <Loader2 className="mr-2 h-4 w-4 animate-spin" />
              Processando...
            </>
          ) : cepStatus === 'checking' ? (
            <>
              <Loader2 className="mr-2 h-4 w-4 animate-spin" />
              Validando CEP...
            </>
          ) : (
            'Pagar'
          )}
        </Button>
      </form>
    </div>
  )
}
