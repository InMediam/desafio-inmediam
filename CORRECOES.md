# Correções e Melhorias

## Backend

### 1. Lógica de pagamento no controller com API Key hardcoded

- **Problema:** O controller `BillingController` continha toda a lógica de pagamento, incluindo a chave da API do Asaas hardcoded e chamadas HTTP diretas.
- **Correção:** A lógica foi extraída para camadas apropriadas: `AsaasService` (comunicação com Asaas), `BillingService` (orquestração do fluxo), Repositories (acesso a dados), e `PayBillingRequest` (validação).
- **Causa do Problema:** Viola o Princípio da Responsabilidade Única (SRP), expõe credenciais no código fonte e impossibilita testes unitários.

### 2. API Key do Asaas exposta no código

- **Problema:** A chave de API estava hardcoded como string no controller (`$aact_YourSandboxKeyHere`).
- **Correção:** Movida para `.env` e acessada via `config('asaas.apiKey')`.
- **Causa do Problema:** Vazamento de credenciais em repositórios Git, risco de segurança grave.

### 3. Mass Assignment sem proteção (`$guarded = []`)

- **Problema:** Todos os modelos usavam `protected $guarded = []`, permitindo que qualquer campo fosse preenchido em massa.
- **Correção:** Substituído por `protected $fillable` com lista explícita de campos permitidos.
- **Causa do Problema:** Vulnerabilidade de Mass Assignment — um usuário malicioso pode enviar campos não esperados na request.

### 4. Ausência de validação no pagamento

- **Problema:** O endpoint de pagamento usava `Request` genérico, sem validação de campos.
- **Correção:** Criado `PayBillingRequest` com regras de validação para todos os campos (card_holder_name, card_number, expiry_date, cvv, phone, postal_code, address_number) com mensagens de erro em português.
- **Causa do Problema:** Dados inválidos chegavam ao backend e ao gateway Asaas sem qualquer filtro.

### 5. Dados fixos do holder enviados ao Asaas

- **Problema:** `phone`, `postalCode` e `addressNumber` eram enviados como `'0000000000'`, `'00000000'`, `'0'`.
- **Correção:** Agora recebidos do frontend, validados e enviados corretamente.
- **Causa do Problema:** Dados incorretos sendo registrados no gateway de pagamento, podendo causar rejeição de transações.

### 6. Ausência de transação no fluxo de pagamento

- **Problema:** As operações de criação de registros locais e chamadas ao Asaas eram feitas sem transação.
- **Correção:** Todo o fluxo foi envolvido em `DB::transaction()` com rollback automático em caso de falha, marcando o pagamento como `failed`.
- **Causa do Problema:** Inconsistência de dados — se o Asaas falhasse após a criação local, o banco ficaria em estado inválido.

### 7. Modelos sem Factory, casts e type hints

- **Problema:** Modelos não possuíam `HasFactory`, casts de tipos, type hints nas relações ou `$hidden`.
- **Correção:** Adicionado `HasFactory`, casts para `BillingStatus`/`PaymentStatus`, `datetime`, type hints (e.g., `BelongsTo`, `HasMany`), e `$hidden` em campos sensíveis.
- **Causa do Problema:** Sem factories não era possível gerar dados para testes. Sem type hints, não há autocomplete e nem segurança de tipos.

### 8. Status como string pura

- **Problema:** `Billing::status` e `Payment::status` eram strings mágicas espalhadas pelo código.
- **Correção:** Criados enums `BillingStatus` (`PENDING`, `PAID`, `CANCELED`, `OVERDUE`), `PaymentStatus` (`PENDING`, `APPROVED`, `CONFIRMED`, `FAILED`, `REFUNDED`) e `BillingType` (`CREDIT_CARD`, `BOLETO`, `PIX`).
- **Causa do Problema:** Magic strings são propensas a erros de digitação e dificultam manutenção.

### 9. `credit_card_id` não nullable na migration

- **Problema:** A migration de `payments` definia `credit_card_id` como obrigatório.
- **Correção:** Alterado para `nullable()`.
- **Causa do Problema:** Um pagamento pode ser criado antes de ter um cartão associado (fluxo de tentativa e falha).

### 10. PostgreSQL sem versão fixa no Docker

- **Problema:** `docker-compose.yml` usava `image: postgres` sem tag.
- **Correção:** Especificado `image: postgres:16-alpine`.
- **Causa do Problema:** Garantir que todos os ambientes usem a mesma versão do banco, evitando problemas de compatibilidade.

### 11. Rota `GET /api/billings` inexistente

- **Problema:** O frontend não tinha uma rota para listar cobranças.
- **Correção:** Adicionada rota `GET /billings` e método `index()` no controller.
- **Causa do Problema:** O frontend consome dados dinâmicos, sem uma rota de listagem não é possível carregar as cobranças do banco.

### 12. `Pdo\Mysql::ATTR_SSL_CA` sem verificação de existência

- **Problema:** Em `config/database.php`, o código tentava acessar `Mysql::ATTR_SSL_CA` diretamente.
- **Correção:** Adicionado `defined('Pdo\\Mysql::ATTR_SSL_CA')` para verificar se a constante existe.
- **Causa do Problema:** Causava erro em runtime quando a extensão `pdo_mysql` não estava carregada.

### 13. Seeders sem `firstOrFail()` e com strings para status

- **Problema:** `first()` retornava null silenciosamente se o registro não existisse, e status eram strings puras.
- **Correção:** Usado `firstOrFail()` e enums `BillingStatus::PENDING->value` / `BillingStatus::PAID->value` / `PaymentStatus::CONFIRMED->value`.
- **Causa do Problema:** Falhas silenciosas ocultavam problemas no seed, e magic strings causavam inconsistência.

### 14. `TestCase` sem `RefreshDatabase`

- **Problema:** A classe base `TestCase` não aplicava `RefreshDatabase`, então os testes não limpavam o banco entre execuções.
- **Correção:** Adicionado `use RefreshDatabase`.
- **Causa do Problema:** Testes poluíam o banco de dados, causando falhas aleatórias dependendo da ordem de execução.

### 15. Ausência de Factories

- **Problema:** Não existiam factories para gerar dados de teste.
- **Correção:** Criadas `PlanFactory`, `CustomerFactory`, `BillingFactory`, `CreditCardFactory` e `PaymentFactory`.
- **Causa do Problema:** Factories são essenciais para gerar dados consistentes e evitar repetição em testes.

### 16. Sem testes automatizados

- **Problema:** Não havia nenhum teste no projeto.
- **Correção:** Adicionados testes Unit (services + repository), Feature (HTTP do controller) e Integration (Asaas sandbox real).
- **Causa do Problema:** Sem testes não é possível garantir que correções não quebram funcionalidades existentes.
---

## Frontend

### 1. Chamadas Axios sem baseURL configurada

- **Problema:** Cada chamada usava `axios.create().get('http://localhost:8000/api/...')`, repetindo a URL base e expondo o host.
- **Correção:** Criado `lib/api.ts` com `axios.create({ baseURL: import.meta.env.VITE_API_URL })` e reutilizado em todas as páginas.
- **Causa do Problema:** DRY, facilidade de manutenção (mudar URL em um só lugar), segurança (URL não fica hardcoded).

### 2. Formulário de pagamento sem validação

- **Problema:** O schema Zod validava apenas a existência dos campos (`z.string()`), sem regras.
- **Correção:** Adicionadas validações: `cardNumber` (16 dígitos), `cvv` (3 dígitos), `expiryDate` (formato MM/AA), `holderName` (mín. 3 caracteres), e campos obrigatórios para endereço.
- **Causa do Problema:** Dados inválidos enviados ao backend, UX pobre (sem feedback de erro).

### 3. Toast de erro com mensagem incorreta

- **Problema:** `onError` exibia `toast.success('Dados salvos com sucesso!')`.
- **Correção:** Alterado para `toast.error('Erro ao realizar o pagamento.')`.
- **Causa do Problema:** Mensagem totalmente enganosa — o usuário pensava que deu certo quando na verdade houve erro.

### 4. Página não recarregava após pagamento

- **Problema:** Após pagamento bem-sucedido, a página continuava exibindo o formulário.
- **Correção:** Adicionado `queryClient.invalidateQueries({ queryKey: ['billing', billingId] })` no `onSuccess`.
- **Causa do Problema:** A query precisa ser refetchada para refletir o novo status `paid` e trocar o formulário pelo comprovante.

### 5. Campos de cartão sem máscara/limpeza

- **Problema:** Os inputs aceitavam qualquer caractere sem formatação, e o preview do cartão (`react-credit-cards-2`) não funcionava corretamente.
- **Correção:** Adicionadas funções `formatCardNumber()` (agrupa de 4 em 4), `formatExpiryDate()` (limita mês a 12, adiciona `/` automático), limpeza de dígitos no CVV, e filtro de números no nome do titular.
- **Causa do Problema:** UX ruim, dados não normalizados enviados ao backend, preview do cartão quebrado.

### 6. Ausência de campos de endereço

- **Problema:** O formulário não coletava telefone, CEP e número do endereço — dados obrigatórios para o Asaas.
- **Correção:** Criado `BillingAddressForm` com máscaras brasileiras `(XX) XXXXX-XXXX` e `XXXXX-XXX`, validação de CEP via `viacep.com.br`.
- **Causa do Problema:** Sem esses dados, a API do Asaas rejeita a transação ou registra informações inválidas.

### 7. Sem validação de CEP

- **Problema:** CEP era enviado sem qualquer verificação.
- **Correção:** Criado `lib/cep.ts` com função `validateCep()` que consulta a API ViaCEP e retorna se o CEP é válido.
- **Causa do Problema:** Endereço inválido sendo enviado ao gateway de pagamento.

### 8. Home page com dados fixos

- **Problema:** A lista de cobranças era hardcoded (`/billing/1` e `/billing/2` com nomes fixos).
- **Correção:** Substituído por chamada `GET /billings` via `useQuery` e renderização dinâmica com `map()`.
- **Causa do Problema:** Dados fixos não refletem o banco real e quebram se os IDs mudarem.

### 9. Datas sem formatação

- **Problema:** `data?.due_date` e `payment.paid_at` eram exibidos no formato ISO (`YYYY-MM-DD`).
- **Correção:** Adicionadas funções `formatDate()` e `formatDateTime()` e aplicadas nos templates.
- **Causa do Problema:** Formato ISO é pouco legível para o usuário final no Brasil.

### 10. Falta de navegação de volta

- **Problema:** Na página de cobrança não havia link para voltar à home.
- **Correção:** Adicionado `Link` "Voltar pra Home" no topo da página.
- **Causa do Problema:** Usuário precisava usar o botão de voltar do navegador, prejudicando a navegação.

### 11. Botão submit habilitado durante validação de CEP

- **Problema:** O botão "Pagar" ficava habilitado mesmo enquanto o CEP estava sendo validado ou era inválido.
- **Correção:** Desabilitado quando `cepStatus === 'checking'` ou `cepStatus === 'invalid'`, e exibido "Validando CEP..." durante verificação.
- **Causa do Problema:** Usuário podia submeter o formulário com CEP inválido, causando erro no backend.

### 12. `RouterProvider` sem `future` flags

- **Problema:** O `RouterProvider` não usava `future={{ v7_startTransition: true }}`.
- **Correção:** Adicionada a flag de future conforme recomendação do React Router v6.4+.
- **Causa do Problema:** Preparação para React Router v7 e performance em transições de rota.

### 13. Flash de fundo branco ao carregar

- **Problema:** O `<body>` no `index.html` não tinha classe `bg-muted`, causando um flash de fundo branco antes do React carregar.
- **Correção:** Adicionado `class="bg-muted"` na tag `<body>`.
- **Causa do Problema:** Experiência visual ruim durante o carregamento da página.

### 14. Nome do titular aceitando números

- **Problema:** O campo "Nome do titular" aceitava caracteres numéricos.
- **Correção:** Adicionado filtro `replace(/[0-9]/g, '')` no onChange.
- **Causa do Problema:** Nome de pessoa não deve conter dígitos.

### 15. Preview do cartão não sincronizado

- **Problema:** O componente `react-credit-cards-2` não refletia a digitação porque os campos usavam `register()` do react-hook-form sem controle de estado local.
- **Correção:** Campos controlados via `useState` com `setValue()` do react-hook-form para manter ambas as fontes sincronizadas.
- **Causa do Problema:** Preview visual do cartão não funcionava, experiência do usuário prejudicada.
