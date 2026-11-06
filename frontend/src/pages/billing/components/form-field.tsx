import { Label } from '@inmediam/ui'
import type { ReactNode } from 'react'

interface FormFieldProps {
  id: string
  label: string
  error?: string
  children: ReactNode
}

export function FormField({ id, label, error, children }: FormFieldProps) {
  return (
    <div className="space-y-2">
      <Label htmlFor={id}>{label}</Label>
      {children}
      {error && <p className="text-sm text-error-500">{error}</p>}
    </div>
  )
}
