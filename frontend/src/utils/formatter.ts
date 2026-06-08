export const currencyFormatter = new Intl.NumberFormat('pt-BR', {
  style: 'currency',
  currency: 'BRL',
})

const dateFormatter = new Intl.DateTimeFormat('pt-BR', {
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
})

const dateTimeFormatter = new Intl.DateTimeFormat('pt-BR', {
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
  timeZone: 'America/Sao_Paulo',
})

function parseApiDateTime(value: string): Date {
  const hasTimezone = /(?:Z|[+-]\d{2}:\d{2})$/i.test(value)

  if (hasTimezone) {
    return new Date(value)
  }

  // Laravel sem cast retorna "YYYY-MM-DD HH:mm:ss" em UTC
  return new Date(value.trim().replace(' ', 'T') + 'Z')
}

export function formatDate(value?: string | null): string {
  if (!value) return '-'

  const [year, month, day] = value.split('T')[0].split('-').map(Number)

  return dateFormatter.format(new Date(year, month - 1, day))
}

export function formatDateTime(value?: string | null): string {
  if (!value) return '-'

  return dateTimeFormatter.format(parseApiDateTime(value))
}

export function formatCurrency(value?: number | string | null): string {
  if (value == null || value === '') return '-'

  const amount = typeof value === 'string' ? Number(value) : value

  if (Number.isNaN(amount)) return '-'

  return currencyFormatter.format(amount)
}
