export const CARD_NUMBER_MIN_LENGTH = 13
export const CARD_NUMBER_MAX_LENGTH = 16
export const CVV_LENGTH = 3

export function stripDigits(value: string): string {
  return value.replace(/\D/g, '')
}

export function formatCardNumber(value: string): string {
  const digits = stripDigits(value).slice(0, CARD_NUMBER_MAX_LENGTH)

  return digits.replace(/(\d{4})(?=\d)/g, '$1 ').trim()
}

export function formatExpiryDate(value: string): string {
  const digits = stripDigits(value).slice(0, 4)

  if (digits.length <= 2) return digits

  return `${digits.slice(0, 2)}/${digits.slice(2)}`
}

export function formatCvv(value: string): string {
  return stripDigits(value).slice(0, CVV_LENGTH)
}

export function isExpiryValid(expiryDate: string): boolean {
  const match = expiryDate.match(/^(\d{2})\/(\d{2})$/)

  if (!match) return false

  const month = Number(match[1])
  const year = 2000 + Number(match[2])

  if (month < 1 || month > 12) return false

  const now = new Date()
  const expiry = new Date(year, month, 0)

  return expiry >= new Date(now.getFullYear(), now.getMonth(), 1)
}
