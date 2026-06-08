# Correções — Desafio InMediam

Documentação das correções realizadas no desafio técnico.

---

## Resumo

| Área | Principais mudanças |
|------|---------------------|
| Setup | PostgreSQL fixado em `16` no Docker |
| Segurança | API key no `.env`, validação server-side, dados sensíveis ocultos |
| Pagamento | Formulário com máscaras e Zod, CEP/endereço no customer, integração Asaas |
| Frontend | Home dinâmica, datas em pt-BR, API centralizada, loading e tratamento de erro |
| Backend | Logs estruturados, validação de validade do cartão, `.gitignore` de arquivos sensíveis |

---

## 1. Docker / PostgreSQL

**Problema:** `docker compose up` falhava — a imagem `postgres` sem tag puxava a versão 18+, incompatível com volumes de versões anteriores.

**Correção:** Tag `postgres:16` no `docker-compose.yml`, alinhada ao README.

---

## 2. Chave do Asaas no código

**Problema:** Chave placeholder hardcoded no `BillingController`.

**Correção:** Variáveis `ASAAS_API_KEY` e `ASAAS_BASE_URL` no `.env`, lidas via `config/services.php`.

---

## 3. Formulário de pagamento

**Problema:** Schema vazio, sem máscaras, toast de sucesso exibido em caso de erro, tela não atualizava após pagamento, número do cartão enviado com espaços.

**Correção:**
- `schemas/payment-schema.ts` e `utils/card.ts` — máscaras, validação de campos e validade
- React Hook Form com `Controller`
- `toast.error` no erro e `invalidateQueries` no sucesso
- Cartão enviado apenas com dígitos (`stripDigits`)
- Validação por comprimento (13–16 dígitos), sem algoritmo de Luhn — compatível com cartões de teste do sandbox Asaas

---

## 4. CEP inválido no Asaas

**Problema:** `postalCode: 00000000` hardcoded no controller; Asaas rejeitava o pagamento.

**Correção:** Campos `phone`, `postal_code` e `address_number` na tabela `customers`, populados no seeder e lidos pelo controller.

---

## 5. Datas em formato errado

**Problema:** Datas exibidas em formato ISO (`2026-06-09`, timestamp com `Z`).

**Correção:** Funções `formatDate` e `formatDateTime` em `utils/formatter.ts`, usando `Intl.DateTimeFormat('pt-BR')`.

---

## 6. Horário do pagamento com +3 horas

**Problema:** `paid_at` retornado sem indicador de fuso; o JavaScript interpretava como horário local.

**Correção:** Cast `datetime` no model `Payment` e `timeZone: 'America/Sao_Paulo'` no formatter.

---

## 7. Home com dados hardcoded

**Problema:** Lista de cobranças fixa no JSX; status não refletia pagamentos realizados.

**Correção:** Endpoint `GET /api/billing` e consumo via React Query na home.

---

## 8. Status pendente/pago inconsistente

**Problema:** Badge e backend tratavam status de formas diferentes; cobrança já paga podia ser paga novamente.

**Correção:** Constantes no model `Billing`, validação em `pay()` e helper `billing-status.ts` no frontend.

---

## 9. Número do cartão com dígitos a mais

**Problema:** Campo aceitava até 19 dígitos.

**Correção:** Limite de 16 dígitos no schema Zod, na máscara e no input.

---

## 10. CVV com 4 dígitos

**Problema:** Campo aceitava 3 ou 4 dígitos; o sandbox Asaas utiliza 3.

**Correção:** CVV fixo em 3 dígitos no schema, na máscara e no input.

---

## 11. Warnings no console

**Problema:** Import do barrel `@inmediam/ui` carregava componente com bug (`Carousel`); warning de future flag do React Router.

**Correção:** Shim em `lib/inmediam-ui.ts` com alias no Vite; flag `v7_startTransition` no `RouterProvider`.

---

## 12. Segurança no pagamento

**Problema:** Valor manipulável no POST, token e CPF expostos na API, race condition em pagamentos simultâneos, ausência de rate limit.

**Correção:**
- `PayBillingRequest` com validação server-side, sem campo `amount`
- Valor cobrado sempre de `$billing->amount`
- `$hidden` em `CreditCard` (`card_token`) e `Customer` (`document`, `email`)
- `$fillable` explícito nos models
- `lockForUpdate()` com transação de banco
- Cobrança marcada como paga apenas com status `CONFIRMED` ou `RECEIVED` do Asaas
- Rate limit `throttle:10,1` no endpoint de pagamento

---

## 13. UX e URL da API

**Problema:** Valor exibido como `R$ NaN` durante loading, URL da API hardcoded, erros mal tratados.

**Correção:** Cliente centralizado em `lib/api.ts` com `VITE_API_URL`, estados de loading/erro/retry, botão de voltar e `formatCurrency`.

---

## 14. Logs no backend

**Problema:** Falhas na integração com o Asaas não eram registradas no servidor.

**Correção:** Logs estruturados (`billing.payment.*`) no `BillingController`, sem dados sensíveis (cartão, CVV, CPF, e-mail ou token).

---

## 15. Tema dos toasts

**Problema:** `sonner.tsx` utilizava `useTheme()` sem `ThemeProvider` configurado na aplicação.

**Correção:** `ThemeProvider` adicionado no `App.tsx`.

---

## 16. Validade do cartão no backend

**Problema:** Backend validava apenas o formato `MM/AA`; cartões expirados passavam e falhavam apenas no Asaas.

**Correção:** Validação de expiração no `PayBillingRequest` (mês entre 01–12, data não expirada).

---

## 17. Dados de teste — plano Empresarial

**Contexto:** O seeder original cobria apenas dois clientes. Foi adicionado um terceiro registro para permitir teste do plano Empresarial.

| Campo | Valor |
|-------|-------|
| Cliente | Carlos Mendes |
| E-mail | carlos@email.com |
| Plano | Empresarial (R$ 199,90) |
| Cobrança | ID 3, status pendente |

**Arquivos:** `CustomerSeeder.php`, `BillingSeeder.php`.

---


