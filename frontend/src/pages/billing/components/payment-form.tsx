import 'react-credit-cards-2/dist/es/styles-compiled.css'

import { Button, Input } from '@inmediam/ui'
import { Loader2 } from 'lucide-react'
import { useState } from 'react'
import Cards, { Focused } from 'react-credit-cards-2'

import { usePaymentForm } from '../hooks/use-payment-form'
import { FormField } from './form-field'

interface PaymentFormProps {
  billingId: string
}

export function PaymentForm({ billingId }: PaymentFormProps) {
  const [focused, setFocused] = useState<Focused>('')
  const {
    form,
    isPending,
    onSubmit,
    handleCardNumberChange,
    handleExpiryDateChange,
    handleCvvChange,
  } = usePaymentForm({ billingId })

  const {
    register,
    formState: { errors },
  } = form
  const values = form.watch()

  return (
    <div className="w-full rounded-lg border border-border bg-card p-6 shadow-sm">
      <h2 className="mb-6 text-lg font-semibold text-foreground">
        Dados do cartão
      </h2>

      <div className="mb-6">
        <Cards
          number={values.cardNumber}
          name={values.holderName}
          expiry={values.expiryDate}
          cvc={values.cvv}
          focused={focused}
        />
      </div>

      <form onSubmit={onSubmit} className="space-y-4">
        <FormField
          id="cardNumber"
          label="Número do cartão"
          error={errors.cardNumber?.message}
        >
          <Input
            id="cardNumber"
            placeholder="0000 0000 0000 0000"
            value={values.cardNumber}
            onChange={(e) => handleCardNumberChange(e.target.value)}
            onFocus={() => setFocused('number')}
          />
        </FormField>

        <FormField
          id="holderName"
          label="Nome do titular"
          error={errors.holderName?.message}
        >
          <Input
            id="holderName"
            placeholder="JOÃO DA SILVA"
            {...register('holderName')}
            onFocus={() => setFocused('name')}
          />
        </FormField>

        <div className="grid grid-cols-2 gap-4">
          <FormField
            id="expiryDate"
            label="Validade"
            error={errors.expiryDate?.message}
          >
            <Input
              id="expiryDate"
              placeholder="MM/AA"
              value={values.expiryDate}
              onChange={(e) => handleExpiryDateChange(e.target.value)}
              onFocus={() => setFocused('expiry')}
            />
          </FormField>

          <FormField id="cvv" label="CVV" error={errors.cvv?.message}>
            <Input
              id="cvv"
              placeholder="123"
              value={values.cvv}
              onChange={(e) => handleCvvChange(e.target.value)}
              onFocus={() => setFocused('cvc')}
            />
          </FormField>
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
