const currencyFormatter = new Intl.NumberFormat('pt-BR', {
  style: 'currency',
  currency: 'BRL',
})

export function formatCurrency(value: string | number): string {
  const amount = Number(value)
  return currencyFormatter.format(Number.isNaN(amount) ? 0 : amount)
}

export function formatDate(value: string): string {
  const [year, month, day] = value.split('-')
  return `${day}/${month}/${year}`
}

const paymentDateTimeFormatter = new Intl.DateTimeFormat('pt-BR', {
  timeZone: 'America/Sao_Paulo',
  dateStyle: 'short',
  timeStyle: 'short',
})

export function formatPaymentDate(value: string): string {
  const date = new Date(`${value.replace(' ', 'T')}-03:00`)
  return paymentDateTimeFormatter.format(date)
}
