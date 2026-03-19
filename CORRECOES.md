# Correções e melhorias – Desafio InMediam

Este documento descreve os problemas identificados e as correções aplicadas no projeto.

---

## 1. Backend (Laravel)

### 1.1 Segurança

| Problema | Correção | Motivo |
|----------|----------|--------|
| **Chave da API Asaas hardcoded** no `BillingController` | Chave e base URL movidas para `config/services.php` e variáveis de ambiente (`.env`). Uso de `ASAAS_API_KEY` e `ASAAS_BASE_URL`. | Credenciais não devem estar no código; risco de vazamento no repositório. |
| **Valor do pagamento vindo do cliente** (`$request->amount`) | Valor do pagamento passou a ser obtido apenas do banco (`$billing->amount`). O frontend não envia mais `amount`. | Evita que o usuário altere o valor pago via requisição (ex.: pagar R$ 0,01 em vez do valor da cobrança). |
| **Ausência de validação dos dados de entrada** no `pay` | Criado `PayBillingRequest` com regras para `card_number`, `card_holder_name`, `expiry_date` e `cvv`. Resposta 422 com erros de validação. | Garante formato e presença dos dados antes de chamar o gateway e evita dados malformados. |
| **Mass assignment sem proteção** nos models | Definido `$fillable` em `Billing`, `Payment` e `CreditCard`. Removido `$guarded = []`. | Impede atribuição em massa de campos não permitidos. |

### 1.2 Bugs

| Problema | Correção | Motivo |
|----------|----------|--------|
| **Condição redundante/incorreta** em `pay`: `if (!Billing::where('id', $id)->first())` e uso de `$billing` que poderia ser null | Uso de `$billing = Billing::find($id)` e checagem `if (!$billing)`. Verificação de `$billing->status === 'paid'` antes de processar. | Evita uso de variável null e dupla consulta; garante que cobrança existe e ainda está pendente. |
| **ID da cobrança sem validação** em `show` e `pay` | Validação de `$id` com `ctype_digit($id)` e `(int) $id > 0`. Retorno 404 para ID inválido. | Evita IDs não numéricos ou negativos e melhora resposta da API. |
| **Resposta do Asaas tratada como sucesso sempre** | Verificação de `status` (CONFIRMED/RECEIVED) e existência de `creditCard` na resposta. Só persiste cartão e pagamento em caso de sucesso. | Evita gravar pagamento quando o gateway recusou ou retornou erro. |
| **Falha ao criar cartão** quando `creditCardNumber` vem mascarado ou token vazio | Uso de `substr(..., -4)` para últimos 4 dígitos e fallback para `card_token` quando vazio (`local_` + uniqid). | Evita erro de constraint (unique) e garante último dígitos do cartão. |

### 1.3 Boas práticas e resiliência

| Problema | Correção | Motivo |
|----------|----------|--------|
| **Lógica de integração com Asaas no controller** | Criado `App\Services\AsaasService` com métodos `createCustomer`, `createCharge` e `payWithCreditCard`. Timeout de 15s. | Separação de responsabilidades; controller fica mais enxuto; facilita testes e manutenção. |
| **Fluxo não escalável e erros interrompendo com exceção** | `AsaasService` passou a retornar resultado estruturado (`success`, `data`, `errors`, `message`) sem lançar exceção em falhas da API. Erros do Asaas são parseados (array `errors` com `code` e `description`), logados e devolvidos para o chamador. | A criação de cliente (e demais etapas) pode falhar sem “quebrar” o fluxo; a mensagem do gateway é repassada ao usuário e o fluxo fica preparado para fallback (ex.: outro gateway) no futuro. |
| **Orquestração no controller** | Criado `App\Services\BillingPaymentService`, que orquestra: criar cliente no Asaas → criar cobrança → pagar com cartão. Em cada etapa, se o resultado não for sucesso, o serviço faz log (com contexto: billing_id, errors) e retorna `ok: false` com `httpStatus` e `message`. O controller apenas delega e devolve JSON. | Fluxo escalável, fácil de estender (ex.: tentar outro gateway após falha no Asaas) e de testar; erros de criação de customer não param o fluxo com exceção. |
| **Sem tratamento de erro do Asaas** | Cada chamada ao Asaas no serviço retorna `errors` (lista com `code`/`description`). A primeira descrição é usada como `message` para o usuário; o restante fica em log. | Usuário recebe feedback claro do gateway; falhas são registradas em log para análise. |
| **Cobrança já paga podia ser paga de novo** | Verificação `$billing->status === 'paid'` com retorno 409 e mensagem “Esta cobrança já foi paga.”. | Evita operação duplicada e inconsistência. |
| **Respostas de erro não padronizadas** | Uso de `PayBillingRequest::failedValidation` para 422 com `message` e `errors`. Controller retorna `message` em 409, 422 e 502. | Contrato da API previsível para o frontend. |
| **Cliente Asaas removido/deletado interrompia o fluxo** | Tratamento igual ao do SiteController: verificação de status do cliente (`getCustomerStatus`: active, removed, deleted, error) e restauração quando removido/deletado (`restoreCustomer`). Persistência do `asaas_customer_id` no model `Customer` para reutilizar o cliente e só criar novo quando não existir ou quando a restauração falhar. | O fluxo não quebra quando o cliente foi removido no Asaas; tenta restaurar e segue o pagamento. |

### 1.4 Configuração

- **`.env.example`**: Incluídas variáveis `ASAAS_API_KEY` e `ASAAS_BASE_URL`.
- **`config/services.php`**: Adicionada seção `asaas` com `api_key` e `base_url` a partir do `.env`.
- **Tabela `customer_gateways`** (e migration): Armazena `customer_id`, `gateway`, `external_id` para reutilizar e verificar/restaurar cliente por gateway (ex.: Asaas). Model `CustomerGateway`; `Customer` com relação `gateways()`; `AsaasService` usa essa tabela em get/setStoredCustomerId. (Substitui o antigo `asaas_customer_id` em customers para suportar múltiplos gateways.)
- **Alinhamento com a API Asaas**: Em `payWithCreditCard` passamos `remoteIp` (IP do cliente, vindo do request) para antifraude; `creditCardHolderInfo` inclui `mobilePhone` além de `phone`, conforme documentação. Resposta do pagamento é usada para persistir `asaas_payment_id` no model `Payment` (migration `add_asaas_payment_id_to_payments_table`), permitindo reconciliação com webhooks.
- **GatewayTransacao**: Tabela `gateway_transacoes` e model para rastrear cada tentativa de transação (sucesso ou falha). Campos: billing_id, customer_id (nullable), payment_id, external_id, credit_card_id, card_legenda, payload (json), response (json), message, amount, status (success/failed/pending), gateway. Uso de `GatewayTransacao::registrar(...)` no `BillingPaymentService` em todos os pontos de falha e no sucesso, para auditoria e suporte.
- **Listagem de cobranças na Home**: Criado endpoint `GET /api/billing` no `BillingController::index()` que retorna todas as cobranças com `plan` e `customer`, ordenadas por `due_date`. Permite que a página inicial exiba dados reais em vez de lista fixa.
- **Webhook com Strategy**: Contrato `WebhookHandlerInterface` (`getName`, `validate`, `handle`); rota única `POST /api/webhooks/{gateway}` (ex: `/api/webhooks/asaas`). O `WebhookController` resolve o handler pelo nome do gateway em `config/payment.webhook_handlers` e delega validação e processamento. `AsaasWebhookHandler` implementa a interface (valida `asaas-access-token`, processa `PAYMENT_CONFIRMED`/`PAYMENT_RECEIVED`). Para outra adquirente (ex: Stripe), basta criar `StripeWebhookHandler`, implementar a interface e registrar em `payment.webhook_handlers`; a mesma rota serve (`/api/webhooks/stripe`).
- **Gateway de pagamento flexível**: Contrato `App\Contracts\PaymentGatewayInterface` define os métodos que qualquer adquirente deve implementar (`getName`, `getStoredCustomerId`, `setStoredCustomerId`, `createCustomer`, `getCustomerStatus`, `restoreCustomer`, `createCharge`, `payWithCreditCard`). `AsaasService` implementa essa interface; `BillingPaymentService` depende da interface, não do Asaas. Em `config/payment.php` e `.env` (`PAYMENT_GATEWAY=asaas`) define-se qual gateway usar; no `AppServiceProvider` o container resolve a implementação. Para adicionar Stripe, Pagar.me etc., basta criar uma nova classe que implemente a interface e registrá-la no `AppServiceProvider`.

---

## 2. Frontend (React)

### 2.1 Bugs

| Problema | Correção | Motivo |
|----------|----------|--------|
| **`onError` da mutation exibindo “Dados salvos com sucesso!”** | Troca para `toast.error(...)` com mensagem da API (`error.response?.data?.message`) ou mensagem genérica. | Erro de lógica; em falha o usuário deve ver mensagem de erro, não de sucesso. |
| **Validação do formulário de pagamento fraca** (apenas `z.string()`) | Schema Zod com regras: número do cartão 13–19 dígitos, nome obrigatório e máx. 255, validade MM/AA, CVV 3 ou 4 dígitos. Mensagens em português. | Garante dados válidos antes do envio e melhora UX. |
| **Número do cartão com espaços** enviado ao backend | Uso de `data.cardNumber.replace(/\s/g, '')` no payload. Backend valida apenas dígitos. | Backend espera só dígitos; evita 422 por formato. |
| **Classe CSS incorreta em `home.tsx`** (`max-` sozinho) | Alterado para `max-w-4xl` no container da página inicial. | Corrige layout da página. |

### 2.2 Experiência do usuário

| Problema | Correção | Motivo |
|----------|----------|--------|
| **Página de cobrança sem estado de carregamento** | Uso de `isLoading` da `useQuery`: exibição de “Carregando...” enquanto busca a cobrança. | Usuário sabe que a página está carregando. |
| **Sem tratamento de erro na tela de cobrança** | Uso de `isError` e `error`: exibição de mensagem de erro (incluindo `error` vindo da API quando disponível). | Feedback claro quando a cobrança não carrega. |
| **ID inválido na URL** (ex.: `/billing/abc`) | Checagem `enabled: !!id && /^\d+$/.test(id)` e tela “Cobrança inválida.” quando o ID não é numérico. | Evita requisição desnecessária e mensagem confusa. |
| **Após pagamento, tela não atualizava** | `queryClient.invalidateQueries({ queryKey: ['billing', billingId] })` no `onSuccess` da mutation. | Lista/detalhe da cobrança atualiza e mostra estado “Pago” e componente de conclusão. |
| **Datas em formato bruto (ISO)** na tela de cobrança e no resumo pago | Criados `formatDate` e `formatDateTime` em `utils/formatter.ts`; uso em `billing.tsx` (Vencimento) e em `payment-concluded.tsx` (Data do pagamento). Exibição em pt-BR (DD/MM/AAAA e DD/MM/AAAA HH:mm). | Melhora legibilidade e experiência do usuário. |
| **Falta de navegação de volta** na página de cobrança | Link “← Voltar para cobranças” na coluna do resumo, apontando para `/`. | Usuário consegue voltar à lista sem usar o botão do navegador. |
| **Resposta da API com `credit_card` (snake_case) ou `creditCard` (camelCase)** | Interface e uso com `payment.credit_card ?? payment.creditCard`; exibição de Titular/Cartão apenas quando o objeto existe. | Compatível com backend Laravel (snake_case) e evita quebra quando cartão não vem na resposta. |
| **Página de erro (Error boundary)** podia quebrar com `useRouteError()` não sendo `Error` | Função `getErrorMessage(error)` que trata `AxiosError`, `Error`, objeto com `message` e valores primitivos; exibição segura da mensagem. | Evita tela em branco ou crash quando o erro não é uma instância de `Error`. |
| **Página inicial com dados fixos e pouca informação** | Backend: endpoint `GET /api/billing` retornando cobranças com plano e cliente. Frontend: `useQuery` para buscar a lista; exibição de valor e vencimento em cada item; estado de carregamento (spinner + texto); estado de erro com mensagem; lista vazia com mensagem; cards com hover e ícone de seta; layout responsivo (`max-w-2xl`), foco visível para acessibilidade. | Lista dinâmica, mais informação (valor e data) e feedback claro (loading/erro/vazio). |
| **Lista sem indicação clara de ação** | Ícone `ChevronRight` em cada item e estados de hover/focus nos links. | Deixa claro que os itens são clicáveis e melhoram a acessibilidade. |

### 2.3 Boas práticas

| Problema | Correção | Motivo |
|----------|----------|--------|
| **URL da API hardcoded em mais de um arquivo** | Criado `src/lib/api.ts` com cliente Axios usando `import.meta.env.VITE_API_URL` (fallback `http://localhost:8000`). Formulário e página de billing passaram a usar `api`. | Configuração centralizada; fácil mudar ambiente (dev/staging/prod). |
| **Payload de pagamento incluía `amount`** | Removido `amount` do payload; backend usa apenas o valor da cobrança no banco. | Alinhado à correção de segurança no backend. |

### 2.4 Segurança e boas práticas (revisão frontend)

| Item | Correção | Motivo |
|------|----------|--------|
| **Cliente HTTP sem timeout** | `timeout: 30_000` (30s) no Axios em `api.ts`. | Evita requisições travadas indefinidamente; falha controlada. |
| **CVV visível em tela** | Campo CVV com `type="password"`, `inputMode="numeric"`, `autoComplete="cc-csc"`, `maxLength={4}`. | Reduz risco de exposição ao digitar (ombro, câmera); alinhado a PCI. |
| **Dados de cartão na memória após sucesso** | No `onSuccess` da mutation de pagamento: `reset()`, `setCardNumber('')`, `setCvv('')`. | Limpa estado sensível após envio bem-sucedido. |
| **Campos de cartão sem autocomplete** | `autoComplete="cc-number"`, `cc-name`, `cc-exp`, `cc-csc` nos inputs; `inputMode="numeric"` e `maxLength` onde aplicável. | Navegador não preenche em campos errados; teclado numérico em mobile; limite de caracteres. |
| **React sem StrictMode** | `main.tsx`: raiz envolvida em `<StrictMode>`. | Ajuda a detectar efeitos colaterais e APIs deprecadas em desenvolvimento. |
| **Imports não utilizados** | Removidos `Cards` e o CSS de `react-credit-cards-2` de `payment-concluded.tsx` (componente não usa preview de cartão). | Menos bundle e código mais limpo. |

---

## 3. Resumo

- **Segurança**: Chave Asaas em env, valor do pagamento só do banco, validação de entrada, `$fillable` nos models.
- **Bugs**: Ajuste de lógica de cobrança e ID, tratamento correto da resposta do Asaas, token/últimos dígitos do cartão, toast de erro, validação Zod e classe CSS.
- **Boas práticas**: Service de pagamento, Form Request, cliente API centralizado, respostas de erro padronizadas.
- **UX**: Loading e erro na tela de cobrança, ID inválido, invalidação da query após pagamento e mensagens claras.
- **Resiliência**: Verificação de cobrança já paga (409); tratamento de falhas do Asaas sem exceção (retorno estruturado), com log em cada etapa e mensagem do gateway ao usuário; fluxo escalável via `BillingPaymentService` (pronto para fallback de gateway no futuro).

Para rodar o projeto, configure no `.env` do backend as variáveis `ASAAS_API_KEY` e, se quiser, `ASAAS_BASE_URL`. No frontend, opcionalmente use `VITE_API_URL` no `.env` para apontar para a API.

---

## 4. Revisão geral do backend (models e arquivos)

- **Billing**: Inclusão de `$casts` para `amount` (decimal:2) e `due_date` (date); tipos de retorno `BelongsTo`/`HasMany` nas relações `plan()`, `customer()`, `payments()`.
- **Customer**: Relações `billings()` e `creditCards()` com tipo `HasMany` para consistência e uso em consultas.
- **Payment**: `$casts` para `amount_paid` (decimal:2) e `paid_at` (datetime); tipos de retorno `BelongsTo` em `billing()` e `creditCard()`; foreign key explícita em `creditCard()`.
- **CreditCard**: Tipo de retorno `BelongsTo` em `customer()`.
- **Plan** e **GatewayTransacao**: Já estavam alinhados (fillable, casts, tipos de retorno).
- **AsaasService**: Propriedades `$apiKey` e `$baseUrl` movidas para o topo da classe; métodos da interface mantidos em bloco lógico após o construtor.
- **PayBillingRequest**: Ordem dos `use` (Contracts antes de Foundation) para padrão do projeto.

---

## 5. Alterações posteriores (UUID, múltiplos gateways, endereço)

| Item | Correção | Motivo |
|------|----------|--------|
| **ID numérico na URL da cobrança** | Cobranças passaram a usar **UUID** na URL (`/billing/{uuid}`). Migration adiciona coluna `uuid` em `billings`; model gera UUID no `creating`; BillingSeeder preenche UUID; controller valida com `Str::isUuid($id)` e busca por `where('uuid', $id)`. Frontend usa `billing.uuid` nos links e valida parâmetro com regex de UUID. | Evita expor IDs sequenciais e facilita integrações. |
| **ID do cliente por gateway no model Customer** | Tabela **`customer_gateways`** (customer_id, gateway, external_id) e model `CustomerGateway`. Migration migra dados de `asaas_customer_id` (se existir) e remove a coluna. `AsaasService::getStoredCustomerId` / `setStoredCustomerId` passam a ler/escrever nessa tabela por nome do gateway. Customer remove `asaas_customer_id` do `$fillable` e ganha relação `gateways()`. | Escalável para várias adquirentes (Stripe, etc.) sem novas colunas em `customers`. |
| **Asaas exige CEP/endereço no pagamento** | Fallback com CEP válido quando cliente sem endereço; depois: campos de endereço em `customers` (migration), **formulário de endereço** no frontend quando `customer.postal_code` está vazio; integração **ViaCEP** para preencher logradouro, bairro, cidade, UF; **PATCH /api/billing/{id}/customer-address** e `UpdateCustomerAddressRequest` para salvar endereço na conta. | Asaas valida CEP; endereço salvo para não pedir de novo. |

---

## 6. Revisão dos requisitos do desafio (README)

Conferência com a seção **"O que esperamos"** do README:

| Requisito | Atendido | Observação |
|-----------|----------|-------------|
| **1. Identificar e corrigir bugs** | Sim | Lógica de cobrança/ID, resposta Asaas, token do cartão, toast de erro, validação Zod, classe CSS, ID inválido (agora UUID), máscaras (cartão, validade, CVV só dígitos). |
| **2. Melhorar a segurança** | Sim | Chave Asaas em env; valor só do banco; validação (PayBillingRequest, UpdateCustomerAddressRequest); `$fillable` nos models; front: timeout, CVV mascarado, limpeza pós-pagamento, autocomplete. |
| **3. Aplicar boas práticas** | Sim | Service layer (AsaasService, BillingPaymentService); Form Requests; tratamento de erros; API centralizada (api.ts, config); gateway flexível (interface + customer_gateways); webhooks com strategy. |
| **4. Melhorar a experiência do usuário** | Sim | Loading/erro em billing e home; feedback correto (toast); datas formatadas; link voltar; abas (Pendentes/Pagos); formulário de endereço com ViaCEP quando faltar; máscaras e limites nos campos de cartão; UI moderna. |
| **5. Melhorar a resiliência** | Sim | Asaas sem exceção (retorno estruturado); cobrança já paga (409); verificação/restauração de cliente no gateway; GatewayTransacao para auditoria; webhooks. |

**Entrega:** `CORRECOES.md` na raiz documentando o que foi encontrado e corrigido (este arquivo).
