export const currencyFormatter = new Intl.NumberFormat('pt-BR', {
  style: 'currency',
  currency: 'BRL',
})

/** Formata data para exibição (DD/MM/AAAA). Aceita ISO string ou Date. */
export function formatDate(value: string | Date | null | undefined): string {
  if (value == null) return '—'
  const date = typeof value === 'string' ? new Date(value) : value
  if (Number.isNaN(date.getTime())) return String(value)
  return date.toLocaleDateString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}

/** Formata data e hora para exibição (DD/MM/AAAA HH:mm). Aceita ISO string ou Date. */
export function formatDateTime(value: string | Date | null | undefined): string {
  if (value == null) return '—'
  const date = typeof value === 'string' ? new Date(value) : value
  if (Number.isNaN(date.getTime())) return String(value)
  return date.toLocaleDateString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
