CORREÇÕES DO PROJETO

Resumo

Foi realizada uma revisão nas camadas de backend Laravel e frontend React/TypeScript, com foco nos pontos solicitados no `README.md`: bugs, segurança, boas práticas, experiência do usuário e resiliência contra falhas/duplicidades.

Principais problemas encontrados

Alterada a porta do postgres no  arquivos .env para 5433, realizei essa alteração por já ter o postgres 13 rodando no meu pc.

### Backend

1. **Chave do Asaas fixa no código**
   - A chave estava hardcoded em `BillingController.php`.
   - Isso expõe credenciais e impede configuração segura por ambiente.

2. **Ausência de validação robusta no pagamento**
   - O endpoint aceitava número do cartão, validade, CVV e valor sem validação adequada.
   - O valor enviado pelo frontend poderia ser manipulado pelo usuário.

3. **Risco de pagamento duplicado**
   - Não havia proteção contra duas requisições simultâneas para a mesma cobrança pendente.

4. **Lógica redundante e insegura para buscar cobrança**
   - O código buscava a cobrança duas vezes e poderia tentar acessar relações mesmo em cenários inválidos.

5. **Tratamento frágil de erro na integração externa**
   - O retorno do Asaas era usado diretamente, sem verificar sucesso HTTP ou mensagens de erro.

6. **Exposição desnecessária de dados sensíveis**
   - O token do cartão poderia ser retornado pela API, pois o model não ocultava o campo.

7. **Mass assignment aberto**
   - Os models usavam `protected $guarded = []`, aceitando preenchimento em massa sem lista explícita de campos permitidos.

8. **Configurações sensíveis espalhadas**
   - URL, timeout e dados padrão do titular estavam no controller ou fixos no código.

### Frontend

1. **Mensagem incorreta em erro de pagamento**
   - O frontend exibia `Dados salvos com sucesso!` mesmo quando o pagamento falhava.

2. **Campos de cartão com risco de warning/controlado e não controlado**
   - Alguns estados iniciavam como `undefined` e eram usados como `value` nos inputs.

3. **Validação fraca no formulário**
   - O schema Zod aceitava qualquer string para cartão, validade e CVV.

4. **Ausência de feedback de carregamento/erro na tela de cobrança**
   - A tela tentava renderizar dados antes do carregamento completo e não exibia erro amigável.

5. **URL da API hardcoded**
   - As chamadas usavam `http://localhost:8000/api` diretamente nos componentes.

6. **Layout pouco responsivo**
   - A tela de pagamento usava `h-screen`, `flex-row` e espaçamentos fixos, prejudicando telas menores.

7. **Data e valores sem normalização completa**
   - Datas eram exibidas no formato bruto retornado pela API.

## Correções realizadas

### Backend

#### `backend/app/Http/Controllers/BillingController.php`

- Refatorei o controller para usar `PayBillingRequest` e `AsaasPaymentService`.
- Corrigi a busca duplicada da cobrança.
- Adicionei retorno padronizado com `message`.
- Adicionei validação para impedir pagamento de cobrança já paga.
- Adicionei comparação do valor enviado com o valor real da cobrança no banco.
- Adicionei transação com `lockForUpdate()` para reduzir risco de pagamento duplicado.
- Adicionei tratamento de exceções controladas e inesperadas com logs.
- Passei a retornar o pagamento criado com o cartão relacionado.

#### `backend/app/Http/Requests/PayBillingRequest.php`

- Criei uma Form Request para validar:
  - número do cartão;
  - nome do titular;
  - validade no formato `MM/AA`;
  - CVV;
  - valor do pagamento.
- Adicionei mensagens amigáveis em português.

#### `backend/app/Services/AsaasPaymentService.php`

- Criei uma camada de serviço para isolar a integração com o Asaas.
- Centralizei base URL, chave, timeout e dados padrão em configuração.
- Adicionei `timeout`, `retry`, `acceptJson()` e `asJson()`.
- Adicionei verificação de sucesso nas respostas HTTP.
- Extraí mensagens de erro retornadas pelo Asaas quando disponíveis.

#### `backend/config/services.php`

- Adicionei configuração `asaas` usando variáveis de ambiente:
  - `ASAAS_API_KEY`
  - `ASAAS_BASE_URL`
  - `ASAAS_TIMEOUT`
  - dados padrão do titular do cartão.

#### `backend/.env`

- Adicionei as variáveis necessárias para configurar o Asaas sem expor segredo real.

#### `backend/app/Models/*.php`

- Substituí `guarded = []` por `$fillable` explícito.
- Adicionei casts de valores monetários, datas e booleanos.
- Tipifiquei os relacionamentos com `BelongsTo` e `HasMany`.
- Adicionei constantes de status em `Billing` e `Payment`.
- Ocultei `card_token` no model `CreditCard`.

#### `backend/database/migrations/0001_01_01_000004_create_credit_cards_table.php`

- Ajustei `card_token` para aceitar `nullable`, evitando falha caso a API não retorne token em algum cenário de sandbox.

#### `backend/routes/api.php`

- Adicionei `whereNumber('id')` nas rotas de cobrança para restringir IDs numéricos.

### Frontend

#### `frontend/src/lib/api.ts`

- Criei um cliente Axios centralizado.
- Removi URLs hardcoded dos componentes.
- Adicionei função para extrair mensagens de erro da API, inclusive erros de validação Laravel.

#### `frontend/.env`

- Adicionei `VITE_API_BASE_URL=http://localhost:8000/api`.

#### `frontend/src/pages/billing/billing.tsx`

- Adicionei tipagem para o retorno da API.
- Adicionei estado de carregamento.
- Adicionei tela amigável para cobrança não encontrada ou erro de API.
- Corrigi renderização segura dos dados.
- Formatei valor e data.
- Ajustei o layout para ser responsivo.

#### `frontend/src/pages/billing/components/payment-form.tsx`

- Reescrevi o schema Zod com validações reais:
  - cartão entre 13 e 19 dígitos;
  - nome do titular obrigatório;
  - validade no formato correto e não vencida;
  - CVV de 3 ou 4 dígitos.
- Adicionei máscara simples para cartão e validade.
- Removi estados `undefined` usados como valor dos inputs.
- Corrigi o feedback de erro para `toast.error(...)`.
- Invalidei a query da cobrança após pagamento com sucesso para atualizar a tela.
- Desabilitei campos durante processamento.
- Adicionei `autocomplete`, `inputMode` e `noValidate`.

#### `frontend/src/pages/billing/components/payment-concluded.tsx`

- Removi import desnecessário do componente visual de cartão.
- Formatei data e valor pagos.
- Tratei status exibido de forma mais segura.

#### `frontend/src/pages/home.tsx`

- Corrigi classe inválida `max-`.
- Ajustei largura para `max-w-lg`, melhorando responsividade.

#### `frontend/src/utils/formatter.ts`

- Mantive formatação monetária em BRL.
- Adicionei formatação de data em `pt-BR`.

#### `frontend/src/types/lucide-react.d.ts`

- Adicionei declaração mínima para evitar erro de tipagem relacionado ao pacote `lucide-react` em combinação com a biblioteca `@inmediam/ui`.

## Vulnerabilidades e riscos corrigidos

- Remoção de credencial hardcoded.
- Validação server-side dos dados do cartão.
- Validação do valor real no backend, impedindo manipulação pelo frontend.
- Proteção contra pagamento duplicado com transação e bloqueio pessimista.
- Não retorno do `card_token` na resposta JSON.
- Tratamento seguro de falhas da API externa.
- Controle de status da cobrança antes de processar pagamento.
- Redução de mass assignment aberto nos models.

## Arquivos criados

- `CORRECOES.md`
- `backend/app/Http/Requests/PayBillingRequest.php`
- `backend/app/Services/AsaasPaymentService.php`
- `frontend/src/lib/api.ts`
- `frontend/.env.example`
- `frontend/src/types/lucide-react.d.ts`

## Arquivos editados

- `backend/app/Http/Controllers/BillingController.php`
- `backend/app/Models/Billing.php`
- `backend/app/Models/CreditCard.php`
- `backend/app/Models/Customer.php`
- `backend/app/Models/Payment.php`
- `backend/app/Models/Plan.php`
- `backend/config/services.php`
- `backend/.env.example`
- `backend/database/migrations/0001_01_01_000004_create_credit_cards_table.php`
- `backend/routes/api.php`
- `frontend/src/pages/billing/billing.tsx`
- `frontend/src/pages/billing/components/payment-form.tsx`
- `frontend/src/pages/billing/components/payment-concluded.tsx`
- `frontend/src/pages/home.tsx`
- `frontend/src/utils/formatter.ts`
