import { Button, Input, Label } from '@inmediam/ui'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Loader2, MapPin } from 'lucide-react'
import { useState } from 'react'
import { toast } from 'sonner'

import { api } from '@/lib/api'

const VIA_CEP_URL = 'https://viacep.com.br/ws'

interface ViaCepResponse {
  cep: string
  logradouro: string
  bairro: string
  localidade: string
  uf: string
  erro?: boolean
}

function formatCep(value: string): string {
  const digits = value.replace(/\D/g, '').slice(0, 8)
  if (digits.length <= 5) return digits
  return `${digits.slice(0, 5)}-${digits.slice(5)}`
}

interface AddressFormProps {
  billingUuid: string
  onSuccess: () => void
}

export function AddressForm({ billingUuid, onSuccess }: AddressFormProps) {
  const queryClient = useQueryClient()
  const [postalCode, setPostalCode] = useState('')
  const [street, setStreet] = useState('')
  const [addressNumber, setAddressNumber] = useState('')
  const [addressComplement, setAddressComplement] = useState('')
  const [neighborhood, setNeighborhood] = useState('')
  const [city, setCity] = useState('')
  const [state, setState] = useState('')
  const [phone, setPhone] = useState('')
  const [loadingCep, setLoadingCep] = useState(false)

  const { mutateAsync: saveAddress, isPending } = useMutation({
    mutationFn: async () => {
      const cepDigits = postalCode.replace(/\D/g, '')
      if (cepDigits.length !== 8) throw new Error('CEP deve ter 8 dígitos.')
      await api.patch(`/api/billing/${billingUuid}/customer-address`, {
        postal_code: cepDigits,
        street,
        address_number: addressNumber,
        address_complement: addressComplement || undefined,
        neighborhood,
        city,
        state: state.toUpperCase(),
        phone: phone || undefined,
      })
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['billing', billingUuid] })
      toast.success('Endereço salvo. Você não precisará informar novamente.')
      onSuccess()
    },
    onError: (err: unknown) => {
      const message =
        err && typeof err === 'object' && 'response' in err
          ? (err as { response?: { data?: { message?: string } } }).response?.data?.message
          : 'Não foi possível salvar o endereço.'
      toast.error(message)
    },
  })

  async function fetchCep() {
    const cepDigits = postalCode.replace(/\D/g, '')
    if (cepDigits.length !== 8) {
      toast.error('Informe um CEP com 8 dígitos.')
      return
    }
    setLoadingCep(true)
    try {
      const res = await fetch(`${VIA_CEP_URL}/${cepDigits}/json/`)
      const data: ViaCepResponse = await res.json()
      if (data.erro) {
        toast.error('CEP não encontrado.')
        return
      }
      setStreet(data.logradouro ?? '')
      setNeighborhood(data.bairro ?? '')
      setCity(data.localidade ?? '')
      setState((data.uf ?? '').toUpperCase())
    } catch {
      toast.error('Não foi possível buscar o CEP.')
    } finally {
      setLoadingCep(false)
    }
  }

  function handleCepChange(value: string) {
    const formatted = formatCep(value)
    setPostalCode(formatted)
  }

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    saveAddress()
  }

  return (
    <div className="w-full rounded-3xl border border-border/80 bg-card/95 p-6 shadow-xl shadow-black/5 backdrop-blur-sm md:p-8">
      <div className="mb-6 flex items-center gap-3">
        <div className="rounded-xl bg-primary/10 p-2">
          <MapPin className="h-5 w-5 text-primary" />
        </div>
        <div>
          <h2 className="text-lg font-bold text-foreground">Endereço</h2>
          <p className="text-xs text-muted-foreground mt-0.5">
            Informe seu endereço para concluir o pagamento. Será salvo na sua conta.
          </p>
        </div>
      </div>

      <form onSubmit={handleSubmit} className="space-y-4">
        <div className="flex gap-2">
          <div className="flex-1 space-y-2">
            <Label htmlFor="postal_code">CEP</Label>
            <Input
              id="postal_code"
              type="text"
              inputMode="numeric"
              placeholder="00000-000"
              maxLength={9}
              value={postalCode}
              onChange={(e) => handleCepChange(e.target.value)}
            />
          </div>
          <div className="flex items-end">
            <Button
              type="button"
              variant="outline"
              onClick={fetchCep}
              disabled={loadingCep || postalCode.replace(/\D/g, '').length !== 8}
            >
              {loadingCep ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : (
                'Buscar'
              )}
            </Button>
          </div>
        </div>

        <div className="space-y-2">
          <Label htmlFor="street">Logradouro</Label>
          <Input
            id="street"
            type="text"
            placeholder="Rua, avenida..."
            value={street}
            onChange={(e) => setStreet(e.target.value)}
            required
          />
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
            <Label htmlFor="address_number">Número</Label>
            <Input
              id="address_number"
              type="text"
              placeholder="123"
              value={addressNumber}
              onChange={(e) => setAddressNumber(e.target.value)}
              required
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="address_complement">Complemento</Label>
            <Input
              id="address_complement"
              type="text"
              placeholder="Apto, bloco..."
              value={addressComplement}
              onChange={(e) => setAddressComplement(e.target.value)}
            />
          </div>
        </div>

        <div className="space-y-2">
          <Label htmlFor="neighborhood">Bairro</Label>
          <Input
            id="neighborhood"
            type="text"
            placeholder="Bairro"
            value={neighborhood}
            onChange={(e) => setNeighborhood(e.target.value)}
            required
          />
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
            <Label htmlFor="city">Cidade</Label>
            <Input
              id="city"
              type="text"
              placeholder="Cidade"
              value={city}
              onChange={(e) => setCity(e.target.value)}
              required
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="state">Estado (UF)</Label>
            <Input
              id="state"
              type="text"
              placeholder="SP"
              maxLength={2}
              value={state}
              onChange={(e) => setState(e.target.value.toUpperCase())}
              required
            />
          </div>
        </div>

        <div className="space-y-2">
          <Label htmlFor="phone">Telefone (opcional)</Label>
          <Input
            id="phone"
            type="tel"
            placeholder="(11) 99999-9999"
            value={phone}
            onChange={(e) => setPhone(e.target.value)}
          />
        </div>

        <Button type="submit" className="w-full" disabled={isPending}>
          {isPending ? (
            <>
              <Loader2 className="mr-2 h-4 w-4 animate-spin" />
              Salvando...
            </>
          ) : (
            'Salvar endereço e continuar'
          )}
        </Button>
      </form>
    </div>
  )
}
