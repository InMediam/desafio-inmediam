import { Input, Label } from '@inmediam/ui'
import { Loader2 } from 'lucide-react'
import { useState } from 'react'
import type { FieldError } from 'react-hook-form'

import type { CepStatus } from '@/lib/cep'
import { validateCep } from '@/lib/cep'

export interface AddressData {
  phone: string
  postalCode: string
  addressNumber: string
}

interface BillingAddressFormProps {
  onChange: (data: AddressData) => void
  onCepStatus?: (status: CepStatus) => void
  errors?: {
    phone?: FieldError
    postalCode?: FieldError
    addressNumber?: FieldError
  }
}

function formatPhone(value: string): string {
  const digits = value.replace(/\D/g, '').slice(0, 11)
  if (digits.length <= 2) return `(${digits}`
  if (digits.length <= 7) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`
  return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`
}

function formatCep(value: string): string {
  const digits = value.replace(/\D/g, '').slice(0, 8)
  if (digits.length <= 5) return digits
  return digits.slice(0, 5) + '-' + digits.slice(5)
}

export function BillingAddressForm({
  onChange,
  onCepStatus,
  errors,
}: BillingAddressFormProps) {
  const [phone, setPhone] = useState('')
  const [postalCode, setPostalCode] = useState('')
  const [addressNumber, setAddressNumber] = useState('')
  const [cepStatus, setCepStatus] = useState<CepStatus>('idle')

  function handleFieldChange(field: keyof AddressData, value: string) {
    const newData = { phone, postalCode, addressNumber, [field]: value }
    onChange(newData)
  }

  async function handleCepBlur() {
    const digits = postalCode.replace(/\D/g, '')
    if (digits.length !== 8) return

    setCepStatus('checking')
    onCepStatus?.('checking')

    const valid = await validateCep(postalCode)
    const status = valid ? 'valid' : 'invalid'
    setCepStatus(status)
    onCepStatus?.(status)
  }

  function handleCepChange(e: React.ChangeEvent<HTMLInputElement>) {
    const formatted = formatCep(e.target.value)
    setPostalCode(formatted)
    handleFieldChange('postalCode', formatted)
    if (cepStatus !== 'idle') {
      setCepStatus('idle')
      onCepStatus?.('idle')
    }
  }

  return (
    <div className="space-y-4 border-t border-border pt-6">
      <h2 className="text-lg font-semibold text-foreground">
        Dados de cobrança
      </h2>

      <div className="space-y-4">
        <div className="space-y-2">
          <Label htmlFor="phone" required>
            Telefone
          </Label>
          <Input
            id="phone"
            placeholder="(11) 99999-9999"
            value={phone}
            onChange={(e) => {
              const formatted = formatPhone(e.target.value)
              setPhone(formatted)
              handleFieldChange('phone', formatted)
            }}
          />
          {errors?.phone?.message && (
            <p className="text-sm text-error-500">{errors.phone.message}</p>
          )}
        </div>

        <div className="space-y-2">
          <Label htmlFor="postalCode" required>
            CEP
          </Label>
          <Input
            id="postalCode"
            placeholder="12345-678"
            value={postalCode}
            onChange={handleCepChange}
            onBlur={handleCepBlur}
          />
          {cepStatus === 'checking' && (
            <p className="flex items-center gap-1 text-sm text-muted-foreground">
              <Loader2 className="h-3 w-3 animate-spin" />
              Verificando CEP...
            </p>
          )}
          {cepStatus === 'valid' && (
            <p className="text-sm text-emerald-500">✓ CEP válido</p>
          )}
          {cepStatus === 'invalid' && (
            <p className="text-sm text-error-500">CEP não encontrado</p>
          )}
          {errors?.postalCode?.message && cepStatus === 'idle' && (
            <p className="text-sm text-error-500">
              {errors.postalCode.message}
            </p>
          )}
        </div>

        <div className="space-y-2">
          <Label htmlFor="addressNumber" required>
            Número
          </Label>
          <Input
            id="addressNumber"
            placeholder="123"
            value={addressNumber}
            onChange={(e) => {
              const cleaned = e.target.value.replace(/\D/g, '')
              setAddressNumber(cleaned)
              handleFieldChange('addressNumber', cleaned)
            }}
          />
          {errors?.addressNumber?.message && (
            <p className="text-sm text-error-500">
              {errors.addressNumber.message}
            </p>
          )}
        </div>
      </div>
    </div>
  )
}
