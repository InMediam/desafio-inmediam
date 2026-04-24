# Correções — Desafio InMediam

Documento de todos os problemas identificados e corrigidos no código entregue.  
Os bugs foram categorizados por camada (backend / frontend) e classificados por gravidade.

---

## Backend (Laravel 12)

### B1 — Chave de API hardcoded `CRÍTICO · Segurança`

**Problema:** A chave da API Asaas estava escrita diretamente no `BillingController`, expondo credencial de produção no código-fonte versionado.

**Correção:** Chave movida para `.env` (`ASAAS_API_KEY`) e lida via `config('services.asaas.key')`, com entrada correspondente em `config/services.php` e `.env.example` documentando a variável sem o valor real.

> **Nota:** chaves que começam com `$` devem ser envoltas em aspas duplas no `.env` (`ASAAS_API_KEY="$aact_..."`) para evitar que o parser do Laravel interprete o `$` como variável de shell.

---

### B2 — Dupla consulta ao banco `Média · Performance`

**Problema:** O controller buscava a cobrança duas vezes — um `find()` para verificar existência e depois um `findOrFail()` — gerando query desnecessária e lógica redundante.

**Correção:** Substituído por uma única chamada `Billing::with('customer')->find($id)` com verificação de nulo e retorno 404 explícito.

---

### B3 — Cobrança paga podia ser processada novamente `Alta · Integridade`

**Problema:** Não havia verificação do status da cobrança antes de processar o pagamento. Uma cobrança já paga podia ser cobrada uma segunda vez no gateway.

**Correção:** Adicionada verificação `if ($billing->status === 'paid')` com retorno 422 antes de qualquer chamada à Asaas.

---

### B4 — Ausência de validação de entrada `Alta · Segurança / Integridade`

**Problema:** O método `pay()` recebia um `Request` genérico sem validar nenhum campo. Dados inválidos (cartão malformado, CVV ausente, validade no formato errado) chegavam até a API do gateway.

**Correção:** Criado `PayBillingRequest` com regras explícitas para todos os campos:

| Campo | Regra |
|---|---|
| `card_holder_name` | `required, string, min:3` |
| `card_number` | `required, string, digits_between:13,19` |
| `expiry_date` | `required, regex:/^(0[1-9]\|1[0-2])\/\d{2}$/` |
| `cvv` | `required, string, digits_between:3,4` |
| `phone` | `required, string, digits_between:10,11` |
| `postal_code` | `required, string, digits:8` |
| `address_number` | `required, string, max:20` |

A regex da validade rejeita meses inválidos (ex: `00`, `13`) — a versão original não tinha essa restrição.

---

### B5 — Dados hardcoded na requisição ao gateway `Alta · Integridade`

**Problema:** Os campos `phone`, `postalCode` e `addressNumber` enviados à Asaas eram valores fictícios fixos no código (`"11999999999"`, `"01310100"`, `"123"`). O campo `remoteIp`, obrigatório pela Asaas para pagamentos com cartão, estava ausente.

**Correção:** Os campos foram adicionados ao formulário do frontend e coletados via `PayBillingRequest`. O `remoteIp` é preenchido automaticamente com `$request->ip()`.

---

### B6 — Armazenamento incorreto dos últimos 4 dígitos `Média · Integridade`

**Problema:** O campo `card_last_four` era preenchido com `substr($cardNumber, 0, 4)`, gravando os primeiros quatro dígitos em vez dos últimos.

**Correção:** Corrigido para `substr($response->creditCard['creditCardNumber'] ?? '', -4)`, usando o número retornado pelo gateway (mascarado) para garantir precisão.

---

### B7 — Sem tratamento de erros nas chamadas HTTP `Alta · Resiliência`

**Problema:** As três chamadas à API Asaas (criar cliente, criar cobrança, pagar com cartão) não tinham `try/catch` nem verificação da resposta. Qualquer falha de rede ou rejeição do gateway causava exceção não tratada com stack trace exposto.

**Correção:** Adicionado bloco `try/catch` envolvendo as três chamadas. Cada resposta é verificada com `->failed()` antes de prosseguir, retornando JSON com mensagem amigável e código HTTP adequado (502 para falhas no gateway, 422 para pagamento recusado).

---

### B8 — Ausência de transação de banco `Alta · Integridade`

**Problema:** As três operações de escrita (`CreditCard::create`, `Payment::create`, `$billing->save()`) ocorriam sequencialmente sem transação. Uma falha na segunda ou terceira operação deixaria o banco em estado inconsistente (cartão gravado sem pagamento, ou pagamento gravado sem atualização do status).

**Correção:** As três operações foram envolvidas em `DB::transaction()`, garantindo atomicidade.

---

### B9 — Número do cartão com espaços quebrava validação `Baixa · Integridade`

**Problema:** O frontend enviava o número do cartão formatado com espaços (`"4444 4444 4444 4444"`), fazendo a regra `digits_between:13,19` falhar no backend.

**Correção:** Adicionado `prepareForValidation()` no `PayBillingRequest` que faz strip dos espaços antes da validação: `preg_replace('/\s+/', '', $this->card_number)`.

---

### B10 — Valor do pagamento vinha do cliente `CRÍTICO · Segurança`

**Problema:** O valor enviado ao gateway era `$request->amount`, permitindo que o cliente manipulasse o corpo da requisição e pagasse um valor arbitrário (ex: R$ 0,01 por qualquer assinatura).

**Correção:** Substituído por `$billing->amount`, lido diretamente do banco de dados após autenticação da cobrança. O campo `amount` continua sendo validado no `PayBillingRequest` por exigência da Asaas, mas o valor efetivamente cobrado ignora o que o cliente enviou ou pode enviar.

---

### B11 — Mass assignment aberta nos models `Alta · Segurança`

**Problema:** Os models `Billing`, `Payment`, `CreditCard` e `Customer` usavam `protected $guarded = []`, desativando completamente a proteção contra mass assignment. Qualquer campo poderia ser preenchido em massa, incluindo campos sensíveis como `status`.

**Correção:** Substituído por `protected $fillable` com lista explícita dos campos permitidos em cada model.

---

### B12 — Endpoint de listagem ausente `Média · Funcionalidade`

**Problema:** Não existia endpoint `GET /api/billing` para listar cobranças, tornando impossível construir uma home page dinâmica.

**Correção:** Adicionado método `index()` no `BillingController` com eager load de `plan` e `customer`, ordenado por `created_at desc`. Rota registrada em `api.php`.

---

## Frontend (React 18 + TypeScript)

### F1 — Toast de erro exibia mensagem de sucesso `Alta · UX`

**Problema:** O callback `onError` da mutação de pagamento chamava `toast.success('Pagamento realizado com sucesso!')` mesmo em caso de falha, enganando o usuário.

**Correção:** Corrigido para `toast.error('Erro ao processar pagamento. Verifique os dados e tente novamente.')`.

---

### F2 — Schema Zod sem validação de formato `Média · UX / Integridade`

**Problema:** O schema Zod original não validava o formato dos campos do cartão — apenas marcava campos como `required`. Dados claramente inválidos passavam para a requisição.

**Correção:** Adicionadas regexes para todos os campos (`cardNumber`, `expiryDate`, `cvv`, `phone`, `postalCode`) com mensagens de erro em português.

---

### F3 — URLs da API hardcoded `Alta · Manutenibilidade`

**Problema:** A URL base da API estava escrita como string literal no código (`"http://localhost:8000"`), impossibilitando a troca de ambiente sem alterar o código-fonte.

**Correção:** Substituído por `${import.meta.env.VITE_API_URL}` em todos os pontos de uso. Criados `.env` e `.env.example` com a variável documentada.

---

### F4 — `axios.create()` desnecessário `Baixa · Qualidade`

**Problema:** O axios era instanciado via `axios.create()` sem nenhuma configuração adicional (sem `baseURL`, sem headers padrão), gerando complexidade sem benefício.

**Correção:** Removido; `axios` utilizado diretamente.

---

### F5 — Estados de loading e erro ignorados `Média · UX`

**Problema:** O `useQuery` na página de cobrança não tratava `isLoading` nem `isError`. O usuário via uma tela em branco durante o carregamento ou em caso de falha de rede.

**Correção:** Adicionados estados com retornos antecipados e mensagens visuais adequadas para cada situação.

---

### F6 — Classe Tailwind incompleta `Baixa · Layout`

**Problema:** A classe `max-` na página home estava truncada (sem o breakpoint), gerando classe CSS inválida e sem efeito.

**Correção:** Corrigida para `max-w-screen-lg`.

---

### F7 — Lista de cobranças estática `Alta · Funcionalidade`

**Problema:** A home page exibia uma lista hardcoded com IDs fixos e dados fictícios, sem consumir dados reais do backend.

**Correção:** Substituída por `useQuery` que consome o endpoint `GET /api/billing`, com exibição de nome do plano, nome do cliente e badge de status (Pago / Pendente). Estados de loading e erro tratados.

---

### F8 — Máscaras de input ausentes `Média · UX`

**Problema:** Os campos de número do cartão, validade, telefone e CEP não tinham formatação visual. O usuário precisava digitar os dados sem guia de formato, aumentando a taxa de erro.

**Correção:** Adicionadas máscaras em tempo real sem dependências externas:

| Campo | Formato exibido |
|---|---|
| Número do cartão | `4444 4444 4444 4444` |
| Validade | `MM/AA` |
| Telefone | `(11) 99999-9999` |
| CEP | `01310-100` |

Os valores enviados à API são sempre enviados sem máscara (strip antes do envio).

---

### F9 — Cache do React Query não invalidado após pagamento `Alta · UX`

**Problema:** Após pagamento bem-sucedido, o cache não era invalidado. A página de cobrança continuava exibindo status "Pendente" e o formulário de pagamento permanecia visível, mesmo após confirmação.

**Correção:** Adicionados `queryClient.invalidateQueries` para as chaves `['billing', billingId]` e `['billings']` no `onSuccess` da mutação, forçando o recarregamento dos dados.

---

### F10 — Validação do cartão falhava com máscara ativa `Média · UX`

**Problema:** O schema Zod validava o número do cartão contra `/^\d{13,19}$/`, mas o `onBlur` nativo injetado pelo `register` do react-hook-form lia o valor diretamente do DOM (com espaços, ex: `"4444 4444 4444 4444"`), sobrescrevendo o valor limpo definido via `setValue`. Na submissão, o Zod recebia a string com espaços e rejeitava o campo.

**Correção:** Schema alterado para `z.string().refine(v => /^\d{13,19}$/.test(v.replace(/\s+/g, '')), ...)`, validando após strip de espaços independentemente da origem do valor.

---

## Observação 

Durante o processo de pagamento, um novo cliente é criado na Asaas a cada transação, mesmo que o cliente já exista. A solução ideal seria persistir o `asaas_customer_id` na tabela `customers` e reutilizá-lo nos pagamentos subsequentes, evitando duplicatas no gateway. Esta melhoria foi documentada mas não implementada por estar fora do escopo dos bugs originais.
