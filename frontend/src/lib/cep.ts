import axios from 'axios'

export type CepStatus = 'idle' | 'checking' | 'valid' | 'invalid'

export async function validateCep(cep: string): Promise<boolean> {
  const digits = cep.replace(/\D/g, '')
  if (digits.length !== 8) return false

  try {
    const response = await axios.get(`https://viacep.com.br/ws/${digits}/json/`)
    return !response.data.erro
  } catch {
    return false
  }
}
