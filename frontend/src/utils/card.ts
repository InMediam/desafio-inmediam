export function maskCardNumber(value: string): string {
  const digits = value.replace(/\D/g, '').slice(0, 19)
  return digits.replace(/(\d{4})(?=\d)/g, '$1 ')
}

export function maskExpiryDate(value: string): string {
  const digits = value.replace(/\D/g, '').slice(0, 4)
  return digits.length <= 2
    ? digits
    : `${digits.slice(0, 2)}/${digits.slice(2)}`
}

export function maskCvv(value: string): string {
  return value.replace(/\D/g, '').slice(0, 4)
}
