import '@testing-library/jest-dom/vitest'

import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import {
  type ButtonHTMLAttributes,
  forwardRef,
  type InputHTMLAttributes,
  type LabelHTMLAttributes,
} from 'react'
import { toast } from 'sonner'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { api } from '../../../lib/api'
import { PaymentForm } from './payment-form'

vi.mock('@inmediam/ui', () => ({
  Button: ({ children, ...props }: ButtonHTMLAttributes<HTMLButtonElement>) => (
    <button {...props}>{children}</button>
  ),

  Input: forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement>>(
    function Input(props, ref) {
      return <input ref={ref} {...props} />
    },
  ),

  Label: ({ children, ...props }: LabelHTMLAttributes<HTMLLabelElement>) => (
    <label {...props}>{children}</label>
  ),
}))

vi.mock('react-credit-cards-2', () => ({
  default: () => <div data-testid="credit-card-preview" />,
}))

vi.mock('lucide-react', () => ({
  Loader2: () => <span data-testid="loader" />,
}))

vi.mock('sonner', () => ({
  toast: {
    success: vi.fn(),
    error: vi.fn(),
  },
}))

vi.mock('../../../lib/api', () => ({
  api: {
    post: vi.fn(),
  },
}))

const mockedPost = vi.mocked(api.post)
const mockedToast = vi.mocked(toast)

function renderPaymentForm() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
      mutations: {
        retry: false,
      },
    },
  })

  const invalidateQueries = vi.spyOn(queryClient, 'invalidateQueries')

  render(
    <QueryClientProvider client={queryClient}>
      <PaymentForm billingId="1" />
    </QueryClientProvider>,
  )

  return {
    queryClient,
    invalidateQueries,
  }
}

function fillValidForm() {
  fireEvent.change(screen.getByPlaceholderText('0000 0000 0000 0000'), {
    target: {
      value: '4111 1111 1111 1111',
    },
  })

  fireEvent.change(screen.getByPlaceholderText('JOÃO M A SILVA'), {
    target: {
      value: 'JOAO DA SILVA',
    },
  })

  fireEvent.change(screen.getByPlaceholderText('MM/AA'), {
    target: {
      value: '12/30',
    },
  })

  fireEvent.change(screen.getByPlaceholderText('123'), {
    target: {
      value: '123',
    },
  })
}

describe('PaymentForm', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('deve validar os campos com dados inválidos', async () => {
    renderPaymentForm()

    fireEvent.change(screen.getByPlaceholderText('0000 0000 0000 0000'), {
      target: {
        value: '123',
      },
    })

    fireEvent.change(screen.getByPlaceholderText('JOÃO M A SILVA'), {
      target: {
        value: 'A',
      },
    })

    fireEvent.change(screen.getByPlaceholderText('MM/AA'), {
      target: {
        value: '13/30',
      },
    })

    fireEvent.change(screen.getByPlaceholderText('123'), {
      target: {
        value: '12',
      },
    })

    fireEvent.click(
      screen.getByRole('button', {
        name: 'Pagar',
      }),
    )

    expect(
      await screen.findByText('Informe um número de cartão válido.'),
    ).toBeInTheDocument()

    expect(
      await screen.findByText('Informe o nome do titular.'),
    ).toBeInTheDocument()

    expect(await screen.findByText('Use o formato MM/AA.')).toBeInTheDocument()

    expect(
      await screen.findByText('O CVV deve ter 3 ou 4 dígitos.'),
    ).toBeInTheDocument()

    expect(mockedPost).not.toHaveBeenCalled()
  })

  it('deve enviar somente os dados necessários para o pagamento', async () => {
    mockedPost.mockResolvedValueOnce({
      data: {
        id: 1,
      },
    })

    renderPaymentForm()

    fillValidForm()

    fireEvent.click(
      screen.getByRole('button', {
        name: 'Pagar',
      }),
    )

    await waitFor(() => {
      expect(mockedPost).toHaveBeenCalledWith('/billing/1/pay', {
        card_number: '4111111111111111',
        card_holder_name: 'JOAO DA SILVA',
        expiry_date: '12/30',
        cvv: '123',
      })
    })

    expect(mockedPost).not.toHaveBeenCalledWith(
      expect.anything(),
      expect.objectContaining({
        amount: expect.anything(),
      }),
    )
  })

  it('deve exibir estado de processamento e impedir envio duplicado', async () => {
    let resolvePayment!: (value: unknown) => void

    mockedPost.mockImplementationOnce(
      () =>
        new Promise((resolve) => {
          resolvePayment = resolve
        }),
    )

    renderPaymentForm()

    fillValidForm()

    const button = screen.getByRole('button', {
      name: 'Pagar',
    })

    fireEvent.click(button)

    const processingButton = await screen.findByRole('button', {
      name: /Processando/,
    })

    expect(processingButton).toBeDisabled()

    fireEvent.click(processingButton)

    expect(mockedPost).toHaveBeenCalledTimes(1)

    resolvePayment({
      data: {
        id: 1,
      },
    })

    await waitFor(() => {
      expect(mockedToast.success).toHaveBeenCalledWith(
        'Pagamento realizado com sucesso!',
      )
    })
  })

  it('deve invalidar a cobrança após pagamento realizado com sucesso', async () => {
    mockedPost.mockResolvedValueOnce({
      data: {
        id: 1,
      },
    })

    const { invalidateQueries } = renderPaymentForm()

    fillValidForm()

    fireEvent.click(
      screen.getByRole('button', {
        name: 'Pagar',
      }),
    )

    await waitFor(() => {
      expect(invalidateQueries).toHaveBeenCalledWith({
        queryKey: ['billing', '1'],
      })
    })

    expect(mockedToast.success).toHaveBeenCalledWith(
      'Pagamento realizado com sucesso!',
    )
  })

  it('deve exibir erro quando a API rejeitar o pagamento', async () => {
    mockedPost.mockRejectedValueOnce({
      response: {
        data: {
          message: 'Cartão recusado.',
        },
      },
    })

    renderPaymentForm()

    fillValidForm()

    fireEvent.click(
      screen.getByRole('button', {
        name: 'Pagar',
      }),
    )

    await waitFor(() => {
      expect(mockedToast.error).toHaveBeenCalledWith('Cartão recusado.')
    })

    expect(mockedToast.success).not.toHaveBeenCalled()
  })
})
