import '@testing-library/jest-dom/vitest'

import { useQuery } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import { useParams } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { Billing } from './billing'

vi.mock('@tanstack/react-query', () => ({
  useQuery: vi.fn(),
}))

vi.mock('react-router-dom', () => ({
  useParams: vi.fn(),
}))

vi.mock('@inmediam/ui', () => ({
  Badge: ({ children }: { children: React.ReactNode; variant?: string }) => (
    <span>{children}</span>
  ),
}))

vi.mock('@/assets/inmediam-shield.svg', () => ({
  default: 'inmediam-shield.svg',
}))

vi.mock('@/assets/mediam.svg', () => ({
  default: 'mediam.svg',
}))

vi.mock('./components/payment-form', () => ({
  PaymentForm: ({ billingId }: { billingId: string }) => (
    <div data-testid="payment-form">PaymentForm - {billingId}</div>
  ),
}))

vi.mock('./components/payment-concluded', () => ({
  PaymentConcluded: ({
    payment,
  }: {
    payment: {
      id: number
    }
  }) => (
    <div data-testid="payment-concluded">PaymentConcluded - {payment.id}</div>
  ),
}))

const mockedUseQuery = vi.mocked(useQuery)
const mockedUseParams = vi.mocked(useParams)

describe('Billing', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    mockedUseParams.mockReturnValue({
      id: '1',
    })
  })

  it('deve exibir o estado de carregamento', () => {
    mockedUseQuery.mockReturnValue({
      data: undefined,
      isLoading: true,
      isError: false,
    } as ReturnType<typeof useQuery>)

    render(<Billing />)

    expect(screen.getByText('Carregando cobrança...')).toBeInTheDocument()
  })

  it('deve exibir erro quando não conseguir carregar a cobrança', () => {
    mockedUseQuery.mockReturnValue({
      data: undefined,
      isLoading: false,
      isError: true,
    } as ReturnType<typeof useQuery>)

    render(<Billing />)

    expect(
      screen.getByText(
        'Não foi possível carregar a cobrança. Tente novamente.',
      ),
    ).toBeInTheDocument()
  })

  it('deve exibir o formulário para cobrança pendente', () => {
    mockedUseQuery.mockReturnValue({
      data: {
        id: 1,
        amount: '79.90',
        due_date: '2026-09-30',
        status: 'pending',
        plan: {
          name: 'Professional',
          description: 'Plano profissional',
        },
        payments: [],
      },
      isLoading: false,
      isError: false,
    } as ReturnType<typeof useQuery>)

    render(<Billing />)

    expect(screen.getByText('Professional')).toBeInTheDocument()
    expect(screen.getByText('Plano profissional')).toBeInTheDocument()
    expect(screen.getByText('Pendente')).toBeInTheDocument()

    expect(screen.getByTestId('payment-form')).toHaveTextContent(
      'PaymentForm - 1',
    )

    expect(screen.queryByTestId('payment-concluded')).not.toBeInTheDocument()
  })

  it('deve exibir o pagamento concluído para cobrança paga', () => {
    mockedUseQuery.mockReturnValue({
      data: {
        id: 1,
        amount: '79.90',
        due_date: '2026-09-30',
        status: 'paid',
        plan: {
          name: 'Professional',
          description: 'Plano profissional',
        },
        payments: [
          {
            id: 10,
          },
        ],
      },
      isLoading: false,
      isError: false,
    } as ReturnType<typeof useQuery>)

    render(<Billing />)

    expect(screen.getByText('Professional')).toBeInTheDocument()
    expect(screen.getByText('Pago')).toBeInTheDocument()

    expect(screen.getByTestId('payment-concluded')).toHaveTextContent(
      'PaymentConcluded - 10',
    )

    expect(screen.queryByTestId('payment-form')).not.toBeInTheDocument()
  })
})
