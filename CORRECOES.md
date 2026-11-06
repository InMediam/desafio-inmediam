# Correções — Desafio InMediam

## Resumo

O projeto recebido tinha problemas em cinco frentes: segurança, integridade do pagamento, resiliência da integração com o Asaas, organização do código e experiência do usuário. Este documento lista o que foi encontrado, o que foi corrigido e por quê, além da arquitetura resultante, das decisões técnicas e de como executar e validar o projeto.

- As correções de comportamento foram feitas com TDD: para cada problema, o teste foi escrito antes da correção e confirmado falhando (commits `test:` seguidos de `fix:`/`refactor:`).
- A refatoração da arquitetura foi feita com os testes de comportamento já existentes, sem alterar suas asserções.
- O fluxo completo foi validado manualmente no sandbox do Asaas.
- Cobertura automatizada: 41 testes no backend (feature e unidade) e 5 casos no frontend (validação do cartão).
- Lint, testes e build rodam no GitHub Actions a cada push e pull request.

## Como executar e testar

### Pré-requisitos

Docker, PHP 8.2+, Composer, Node.js 18+ e uma conta no [sandbox do Asaas](https://sandbox.asaas.com).

### 1. Banco de dados

```bash
docker compose up -d
```

Sobe o Postgres 16 e cria dois bancos: `desafio` (aplicação) e `desafio_test` (testes automatizados), este pelo script `docker/postgres/init-test-db.sql`.

> O script de inicialização só roda quando o volume do banco é criado. Se o volume já existia antes desta versão, recrie-o: `docker compose down -v && docker compose up -d`.

### 2. Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

No `backend/.env`, preencha a chave do sandbox:

| Variável | Descrição |
| -------- | --------- |
| `ASAAS_API_KEY` | Chave de API do sandbox (Configurações > Integrações > API). Obrigatória para pagar. |
| `ASAAS_BASE_URL` | URL da API do Asaas. Padrão: `https://sandbox.asaas.com/api/v3`. |

```bash
php artisan migrate --seed
php artisan serve
```

A API fica em `http://localhost:8000`.

### 3. Frontend

```bash
cd frontend
npm install
cp .env.example .env
npm run dev
```

O `frontend/.env` define `VITE_API_URL` (padrão: `http://localhost:8000/api`). A aplicação fica em `http://localhost:3000`.

### 4. Testes automatizados

São os mesmos comandos executados no CI.

| Onde | Comando | O que verifica |
| ---- | ------- | -------------- |
| Backend | `composer test` | PHPUnit, no banco `desafio_test`. O Asaas é simulado com `Http::fake()` e qualquer chamada HTTP não simulada falha o teste, então nenhum teste acessa o Asaas real. |
| Backend | `composer lint` | Padrão de código com Laravel Pint. |
| Frontend | `npm run lint` | ESLint e Prettier. |
| Frontend | `npx tsc --noEmit` | Tipagem TypeScript. |
| Frontend | `npm test` | Vitest: regras de validação do cartão. |
| Frontend | `npm run build` | Build de produção. |

### 5. Teste manual no sandbox

Com backend e frontend rodando e a chave do Asaas configurada:

1. Restaure os dados iniciais: `php artisan migrate:fresh --seed`.
2. Acesse `http://localhost:3000`. A Home lista três cobranças, com status vindo da API:

   | Cobrança | Cliente | Plano | Status |
   | -------- | ------- | ----- | ------ |
   | 1 | João da Silva (CPF) | Profissional — R$ 79,90 | Pendente |
   | 2 | Maria Oliveira (CPF) | Básico — R$ 29,90 | Pago |
   | 3 | Empresa Exemplo Ltda (CNPJ) | Empresarial — R$ 199,90 | Pendente |

3. **Validação no formulário** (cobrança 1): estes casos mostram erro no próprio campo, sem enviar requisição:
   - número `4539 6560 4839 2221` (falha na verificação de Luhn);
   - validade vencida ou com mês `13`;
   - CVV com 2 dígitos.
4. **Pagamento aprovado** (cobrança 1), com o cartão de teste do Asaas:

   | Campo | Valor |
   | ----- | ----- |
   | Número | `4539 6560 4839 2220` |
   | Titular | qualquer nome |
   | Validade | qualquer data futura (ex.: `12/30`) |
   | CVV | `123` |

   Resultado esperado: mensagem de sucesso, a tela passa a mostrar o pagamento confirmado sem recarregar a página e, no painel do Asaas, existe um cliente e uma cobrança de R$ 79,90 confirmada.
5. **Pagamento duplicado bloqueado:** com a cobrança 1 já paga, uma nova tentativa pela API retorna `409`:

   ```bash
   curl -i -X POST http://localhost:8000/api/billing/1/pay \
     -H "Content-Type: application/json" -H "Accept: application/json" \
     -d '{"card_number":"4539656048392220","card_holder_name":"TESTE","expiry_date":"12/30","cvv":"123"}'
   ```

6. **Cliente pessoa jurídica:** pague a cobrança 3. No painel do Asaas, o cliente aparece como pessoa jurídica e a cobrança é de R$ 199,90.
7. **Cobrança inexistente:** `http://localhost:3000/billing/99` mostra a tela de "não encontrada", com uma única requisição `404`.
8. **Logs:** cada tentativa gera eventos em `backend/storage/logs/payments-AAAA-MM-DD.log` (ver [Observabilidade](#observabilidade)).

## Arquitetura

### Backend

```
Requisição HTTP
  └─ PayBillingRequest        valida o payload e monta o CreditCardData
      └─ BillingController    recebe, delega e responde
          └─ BillingPaymentService   regra de negócio do pagamento
              ├─ PaymentGateway (interface)
              │    └─ AsaasGateway   única classe que se comunica com o Asaas
              └─ Models (Eloquent)   persistência

Respostas: API Resources → JSON
Erros de negócio: exceções de domínio → convertidas em HTTP em bootstrap/app.php
```

| Componente | Responsabilidade |
| ---------- | ---------------- |
| `Http/Requests/PayBillingRequest` | Valida os dados do cartão e converte a validade `MM/AA` em mês e ano com quatro dígitos. |
| `Http/Controllers/BillingController` | Recebe a requisição, chama o service e devolve o Resource. Não contém regra de negócio. |
| `Services/Billing/BillingPaymentService` | Orquestra o pagamento: idempotência, lock da cobrança, reaproveitamento de cliente e cobrança no Asaas, chamada ao gateway, persistência e logs. Depende só da interface do gateway. |
| `Services/Billing/CreditCardData` | Objeto com os dados do cartão, trafegando entre a request e o service. |
| `Contracts/PaymentGateway` | Contrato com as três operações de que o fluxo precisa: criar cliente, criar cobrança e pagar com cartão. |
| `Services/Asaas/AsaasGateway` | Implementação do contrato para o Asaas: autenticação, URL, timeout e conversão das respostas, com valor padrão para campos ausentes. |
| `Exceptions/*` | Erros de negócio com status HTTP próprio: cobrança não encontrada (404), já paga (409), pagamento recusado (422) e falha do gateway (502). |
| `Http/Resources/*` | Definem exatamente quais campos saem na resposta da API. |
| `Enums/BillingStatus` | Status da cobrança (`pending`, `paid`) usados pelo código. |
| `Providers/AppServiceProvider` | Liga a interface `PaymentGateway` ao `AsaasGateway` com os valores da configuração. |

**Fluxo de um pagamento (`POST /api/billing/{id}/pay`):**

1. O `PayBillingRequest` valida o payload; dados inválidos retornam `422` sem acessar o banco nem o Asaas.
2. Se o header `Idempotency-Key` já corresponde a um pagamento registrado, esse pagamento é devolvido sem nova cobrança.
3. Em uma transação, a cobrança é lida com `lockForUpdate()`. Inexistente ou já paga encerra o fluxo com `404` ou `409`.
4. O cliente e a cobrança no Asaas são reaproveitados se já existirem; caso contrário, são criados e seus ids gravados.
5. O pagamento com cartão é enviado ao Asaas com os dados do titular cadastrados no cliente.
6. Cartão, pagamento e status da cobrança são gravados. A cobrança só passa a `paid` com status `CONFIRMED` ou `RECEIVED`.
7. Recusa e falha de comunicação não desfazem os ids do Asaas já gravados; o resultado é convertido em `422` ou `502` depois da transação.

### Frontend

```
Página / componente     renderização
  └─ hook               estado do formulário e React Query
      └─ service        chamadas da API
          └─ lib/api    instância única do axios (VITE_API_URL)
```

| Local | Conteúdo |
| ----- | -------- |
| `types/billing.ts` | Tipos do contrato da API. |
| `lib/api.ts` | Cliente HTTP e extração da mensagem de erro do backend. |
| `lib/react-query.ts` | Política de novas tentativas das consultas. |
| `services/billing.service.ts` | `listBillings`, `getBilling` e `payBilling`. |
| `hooks/` | `use-billings`, `use-billing` e `use-pay-billing` (atualiza a cobrança após o pagamento). |
| `schemas/payment.schema.ts` | Regras de validação do cartão, equivalentes às do backend. |
| `utils/` | Máscaras de cartão e formatação de moeda e datas. |
| `pages/billing/hooks/use-payment-form.ts` | Lógica do formulário: validação, máscaras, envio, chave de idempotência e tratamento de erros. |
| `pages/billing/components/` | Componentes de apresentação. |

### Contrato da API

| Método | Rota | Respostas |
| ------ | ---- | --------- |
| GET | `/api/billings` | `200` com a lista de cobranças (`id`, `status`, `plan`, `customer.name`). |
| GET | `/api/billing/{id}` | `200` com a cobrança (`id`, `amount`, `status`, `due_date`, `plan`, `customer.name`, `payments`); `404`. |
| POST | `/api/billing/{id}/pay` | `200` com o pagamento; `404`; `409` já paga; `422` dados inválidos ou cartão recusado; `429` limite de tentativas; `502` falha no Asaas. |

O `POST` recebe `card_number`, `card_holder_name`, `expiry_date` (`MM/AA`) e `cvv`, e aceita o header opcional `Idempotency-Key`. O valor cobrado é sempre o da cobrança; um `amount` enviado no corpo é ignorado.

## Observabilidade

Os eventos do pagamento são gravados no canal `payments`, em arquivos diários (`storage/logs/payments-AAAA-MM-DD.log`), em JSON, uma linha por evento.

| Evento | Nível | Quando |
| ------ | ----- | ------ |
| `billing.payment.started` | info | Início de toda tentativa. |
| `billing.payment.confirmed` | info | Asaas retornou `CONFIRMED` ou `RECEIVED`. |
| `billing.payment.pending` | info | Asaas aceitou, mas com outro status. |
| `billing.payment.idempotent_replay` | info | `Idempotency-Key` repetida; o pagamento anterior foi devolvido. |
| `billing.payment.rejected` | warning | Cobrança inexistente ou já paga (`reason`: `not_found` ou `already_paid`). |
| `billing.payment.declined` | warning | Cartão recusado pelo Asaas. |
| `billing.payment.failed` | error | Erro ou timeout na comunicação com o Asaas, ou chave não configurada. |

Todo evento traz `billing_id` e um `correlation_id`, comum a todos os eventos da mesma tentativa. Conforme o caso, trazem também o id da cobrança no Asaas, o status retornado, o status HTTP, a duração da chamada ao Asaas (`duration_ms`), o código e a descrição do erro do Asaas e a classe da exceção.

Nunca são registrados: número do cartão, CVV, nome do titular, token do cartão, CPF/CNPJ, e-mail, a chave da API, a mensagem bruta de exceções ou o corpo das requisições e respostas.

## Problemas encontrados e correções

### Segurança

| # | Problema | Correção | Motivo |
| - | -------- | -------- | ------ |
| 1 | Chave de API e URL do Asaas fixas no código do controller. | Lidas do ambiente (`ASAAS_API_KEY`, `ASAAS_BASE_URL`) via `config/services.php`, com as variáveis documentadas no `.env.example`. Sem chave configurada, o pagamento falha de forma controlada (`502`) antes de qualquer chamada externa. | Credencial em código versionado fica exposta a quem tem acesso ao repositório e ao histórico, e trocar de ambiente exigiria alterar código. |
| 2 | Nenhuma validação dos dados do cartão; payloads malformados chegavam ao Asaas ou causavam erro `500`. | `PayBillingRequest`: número com 13 a 19 dígitos e verificação de Luhn, titular obrigatório, validade `MM/AA` não vencida e CVV com 3 ou 4 dígitos, com mensagens em português. | Dados inválidos devem ser barrados na entrada, sem consumir a integração nem expor erros internos. |
| 3 | `GET /api/billing/{id}` devolvia o model serializado, incluindo o token do cartão e campos internos (ids de relacionamento, ids do Asaas, chave de idempotência, datas de controle). | Respostas montadas por API Resources, que listam explicitamente os campos expostos. `$hidden` nos models permanece como segunda barreira. | Uma lista do que pode sair é mais segura que uma lista do que deve ser escondido: um campo novo no banco não vaza por omissão. |
| 4 | Todos os models com `$guarded = []`. | `$fillable` explícito, apenas com os campos gravados pelo código. | Evita atribuição em massa de campos não previstos. |
| 5 | Endpoint de pagamento sem limite de requisições. | Limite de 10 requisições por minuto por IP (`429` acima disso). | Reduz tentativas automatizadas de cartões e abuso da integração. |

### Pagamento e resiliência

| # | Problema | Correção | Motivo |
| - | -------- | -------- | ------ |
| 1 | O valor cobrado e registrado vinha do `amount` enviado pelo cliente; era possível pagar uma cobrança de R$ 79,90 com R$ 0,01. | O valor vem sempre da cobrança gravada no banco. | O preço não pode ser definido por quem paga. |
| 2 | Id inexistente ou não numérico gerava erro de banco não tratado (`500`). | Retorna `404` antes de consultar o banco ou o Asaas, no detalhe e no pagamento. | Erro interno não deve chegar ao cliente; id inválido equivale a cobrança inexistente. |
| 3 | Uma cobrança já paga podia ser paga de novo. | Retorna `409` sem chamar o Asaas. | Evita cobrar o cliente duas vezes. |
| 4 | Cartão recusado quebrava o fluxo com `500` e podia deixar registros parciais. | Retorna `422` com mensagem amigável; nenhum cartão ou pagamento é gravado e a cobrança continua pendente. | Recusa é um resultado esperado do negócio, não um erro do sistema. |
| 5 | Chamadas ao Asaas sem timeout; erro `5xx` ou falha de conexão derrubava a requisição. | Timeout de 10 segundos e resposta `502` com mensagem genérica; a cobrança continua pendente. | Instabilidade externa não pode travar a aplicação nem expor detalhes internos. |
| 6 | A cobrança era marcada como paga em qualquer resposta HTTP de sucesso, mesmo com status `PENDING`. | Só passa a paga com `CONFIRMED` ou `RECEIVED`; nos demais casos o pagamento é registrado com o status real e a cobrança segue pendente. | Sucesso HTTP não significa pagamento efetivado. |
| 7 | Cartão, pagamento e status da cobrança gravados em operações independentes. | Gravação em uma única transação. | Uma falha no meio deixaria dados parciais e inconsistentes. |
| 8 | Requisições simultâneas para a mesma cobrança podiam cobrar duas vezes. | A cobrança é lida com `lockForUpdate()` dentro de uma transação que cobre checagem, chamada ao Asaas e gravação. | A segunda requisição aguarda a primeira terminar e encontra a cobrança já paga. |
| 9 | Sem idempotência: o reenvio da mesma requisição (por exemplo, após um timeout no cliente) era uma nova tentativa. | Header opcional `Idempotency-Key`, armazenado com restrição de unicidade; a mesma chave devolve o pagamento já registrado. O frontend gera uma chave por tentativa. | Reenvios por instabilidade de rede não podem gerar nova cobrança. |
| 10 | Cada tentativa criava um novo cliente no Asaas. | O id do cliente no Asaas é gravado e reaproveitado. | Evita cadastros duplicados no provedor. |
| 11 | Cada tentativa criava uma nova cobrança no Asaas, inclusive após uma recusa. | O id da cobrança no Asaas é gravado na cobrança e reaproveitado nas tentativas seguintes; também é gravado no pagamento. | Evita cobranças pendentes órfãs e permite rastrear o pagamento no provedor. |
| 12 | Telefone, CEP e número do endereço do titular eram valores fixos (`0000000000`, `00000000`, `0`). No sandbox, isso fazia o Asaas recusar todo pagamento por CEP inválido. | Campos adicionados ao cadastro do cliente (nova migration, factories e seeders) e enviados ao Asaas. | Com os dados fixos, nenhum pagamento real seria aprovado. |
| 13 | A coluna `document` do cliente tinha 11 caracteres, insuficiente para CNPJ. | Nova migration ampliando para 14, sem editar a migration original. | O Asaas aceita CPF e CNPJ; clientes pessoa jurídica não podiam ser cadastrados. |

### Organização do backend

| # | Problema | Correção | Motivo |
| - | -------- | -------- | ------ |
| 1 | O controller concentrava validação de estado, três chamadas HTTP, persistência e montagem das respostas. | Separação em request, controller, service, interface de gateway e implementação para o Asaas (ver [Arquitetura](#arquitetura)). | Cada parte pode ser entendida, alterada e testada isoladamente; o service é testado com um gateway falso, sem HTTP. |
| 2 | Respostas do Asaas lidas como objetos dinâmicos, sem tratar campos ausentes (por exemplo, `creditCardBrand`). | O `AsaasGateway` converte as respostas e aplica valor padrão aos campos opcionais. | Uma variação na resposta do provedor não pode derrubar o pagamento. |
| 3 | Erros de negócio sinalizados por retornos manuais espalhados no controller. | Exceções de domínio, convertidas em resposta HTTP em um único ponto (`bootstrap/app.php`), com as mesmas mensagens. | Tratamento de erro consistente e centralizado. |
| 4 | Status da cobrança como textos soltos no código. | Enum `BillingStatus`, sem alterar os valores gravados. | Evita erros de digitação e documenta os estados possíveis. |
| 5 | Nenhum registro do fluxo de pagamento. | Logs estruturados (ver [Observabilidade](#observabilidade)). | Permite investigar uma tentativa de ponta a ponta sem expor dados sensíveis. |
| 6 | Não havia como listar cobranças; a Home tinha clientes, planos e status escritos à mão. | Endpoint `GET /api/billings`, que expõe apenas id, status, plano e nome do cliente. | A Home passa a refletir o estado real das cobranças. |

### Frontend e experiência do usuário

| # | Problema | Correção | Motivo |
| - | -------- | -------- | ------ |
| 1 | Chamadas HTTP feitas dentro dos componentes, com URL fixa, um cliente axios novo a cada chamada e respostas sem tipo. | Tipos do contrato, cliente HTTP único com `VITE_API_URL`, service e hooks de consulta. | Componentes ficam só com a renderização, a URL é configurável por ambiente e o TypeScript aponta divergências com a API. |
| 2 | O schema do formulário não validava nada. | Validação com Zod equivalente à do backend (Luhn, validade, CVV, titular) e máscaras nos campos. | Erros óbvios são apontados antes de qualquer requisição. |
| 3 | Campos com estado duplicado (`useState` e React Hook Form), gerando warning de componente controlado/não controlado. | Campos controlados apenas pelo React Hook Form, com a lógica isolada no hook `use-payment-form`. | Uma única fonte de verdade por campo. |
| 4 | Em caso de erro, o formulário exibia "Dados salvos com sucesso!" e o erro aparecia como não tratado no console. | Mensagem de sucesso só no sucesso; nos erros, a mensagem do backend; erros de validação `422` exibidos no campo correspondente. | O usuário precisa saber se o pagamento foi feito e o que corrigir. |
| 5 | Após pagar, a tela continuava mostrando o formulário. | A consulta da cobrança é atualizada após o pagamento. | A tela reflete o pagamento sem recarregar a página. |
| 6 | O formulário enviava o valor da cobrança na requisição. | Campo removido do envio; o titular é enviado em maiúsculas, como impresso no cartão. | O valor é responsabilidade do backend. |
| 7 | Tela da cobrança sem estados de carregamento, erro ou cobrança inexistente. | Carregamento, erro com opção de tentar de novo e tela de cobrança não encontrada; o nome do cliente passou a ser exibido. | Feedback claro em todos os estados da tela. |
| 8 | Valores podiam aparecer como "R$ NaN" e as datas eram exibidas sem formatação. | Formatação de moeda segura e datas em pt-BR, com o horário do pagamento no fuso de São Paulo. | Informação correta e legível. |
| 9 | A página de erro lia o campo `message`, mas a API responde com `error`; o componente se chamava `Error`, sombreando o `Error` nativo do JavaScript. | Componente renomeado para `ErrorPage`, lendo a mensagem com o mesmo utilitário do restante da aplicação. | A mensagem real do erro passa a ser exibida e o nome não conflita com o global. |
| 10 | Uma cobrança inexistente gerava quatro requisições (novas tentativas automáticas do React Query). | Erros `4xx` não são repetidos; falhas de rede e `5xx` são repetidas uma vez; o pagamento nunca é repetido automaticamente. | Repetir uma resposta definitiva só atrasa o feedback. |
| 11 | O componente de notificações usava o tema sem `ThemeProvider`. | Provider adicionado com tema claro fixo. | A aplicação não tem troca de tema; o visual fica estável. |
| 12 | `npm run lint` falhava por import não utilizado e formatação fora do padrão. | Corrigido. | O lint faz parte do CI. |

### Ambiente, testes e CI

| # | Problema | Correção | Motivo |
| - | -------- | -------- | ------ |
| 1 | `docker-compose.yml` usava `postgres` sem versão; a imagem mais recente mudou o diretório de dados e o container não subia. | Imagem fixada em `postgres:16`. | O ambiente não subia seguindo o README. |
| 2 | Testes configurados com SQLite em memória. | Testes no Postgres, em um banco separado (`desafio_test`) criado automaticamente pelo Docker Compose. | O SQLite não reproduz o `lockForUpdate` nem o comportamento de tipos do Postgres. |
| 3 | Sem factories, sem forma de simular o Asaas e apenas os testes de exemplo do Laravel. | Factories dos cinco models, simulação reutilizável do Asaas (sucesso, recusa, erro e timeout) e bloqueio de qualquer chamada HTTP não simulada em todos os testes. | Testes rápidos, determinísticos e sem risco de acessar o Asaas real. |
| 4 | O Pint já era dependência, mas não era usado e o código estava fora do padrão. | Script `composer lint` e código formatado. | Padrão de código verificado automaticamente. |
| 5 | Frontend sem executor de testes. | Vitest 2 (compatível com o Vite 5 do projeto), em ambiente Node. | Testar as regras puras do frontend. |
| 6 | Sem integração contínua. | GitHub Actions com jobs paralelos para backend (Postgres, lint, testes e auditoria de dependências) e frontend (lint, tipos, testes, build e auditoria), do check mais rápido ao mais lento. | Toda alteração é verificada automaticamente. |
| 7 | Os dados de exemplo não tinham cliente pessoa jurídica nem cobrança do plano Empresarial. | Cobrança 3: cliente com CNPJ válido e plano Empresarial, sem alterar as cobranças existentes. | Permite validar o fluxo com CNPJ de ponta a ponta. |

## Decisões técnicas e trade-offs

| Decisão | Alternativa considerada | Motivo da escolha |
| ------- | ----------------------- | ----------------- |
| Testes no Postgres | SQLite em memória, mais rápido e sem dependências. | Testar concorrência e transações exige o mesmo banco de produção. O custo é precisar do Docker rodando. |
| Lock durante todo o pagamento | Lock curto, com um status intermediário de "processando". | Mais simples e suficiente para o volume do projeto. A linha fica bloqueada enquanto o Asaas responde (até 10 segundos); em alto volume, o status intermediário seria preferível. |
| Concorrência validada por teste sequencial | Teste com requisições realmente simultâneas. | O PHPUnit executa em um único processo; simular paralelismo real tornaria o teste lento e instável. O teste cobre a segunda requisição após a primeira. |
| Resultado da transação devolvido antes de virar exceção | Lançar a exceção de domínio dentro da transação. | Lançar dentro da transação desfaria a gravação dos ids do Asaas, que precisam ser mantidos após uma recusa. |
| Interface apenas para o gateway | Repositórios para os models. | O gateway é a única dependência externa substituível. Os models já são abstraídos pelo Eloquent; repositórios seriam abstração sem uso. |
| Entrada e saída do gateway como arrays com chaves fixas | Um objeto tipado para cada payload e resposta. | Cobre a necessidade atual com menos código. Vale rever se o gateway ganhar regras sobre esses dados. |
| Idempotência em uma coluna única de `payments` | Tabela dedicada a chaves de idempotência. | Mais simples. A chave fica registrada junto ao pagamento gravado; uma tentativa recusada não é registrada e pode ser refeita com a mesma chave, que é o comportamento desejado após corrigir o cartão. |
| `id` exposto no pagamento | Omitir o `id`. | É o identificador público do pagamento e permite verificar que um reenvio idempotente devolveu o mesmo pagamento. |
| Resources sem envelope `data` | Formato padrão do Laravel, com envelope. | Mantém o formato de resposta que o frontend já consumia. |
| Endpoint de listagem novo | Uma requisição por cobrança na Home. | Uma única chamada e sem depender de ids conhecidos de antemão. É uma adição ao contrato, sem alterar os endpoints existentes. |
| `correlation_id` gerado no service | Middleware de request id. | Agrupa os eventos de uma tentativa sem código adicional de infraestrutura. |
| CORS não restringido | Restringir à origem do frontend. | A API não usa autenticação por cookie; o CORS não impediria chamadas feitas fora do navegador. Passa a ser necessário quando houver autenticação. |
| Não repetir erros `4xx` nas consultas | Repetir todos os erros. | `4xx` são respostas definitivas; o `429` também não é repetido, para não contornar o limite de tentativas. |
| Testes do frontend apenas no schema | Testes de componente com jsdom, Testing Library e MSW. | A regra que pode causar prejuízo (aceitar cartão inválido ou recusar um válido) está no schema; os componentes são finos e foram validados manualmente. |
| Auditoria de dependências em modo informativo no CI | Bloquear o build. | Os alertas estão em dependências transitivas; a atualização precisa ser feita de forma controlada e revalidada antes de bloquear o pipeline. |

## Limitações conhecidas e melhorias futuras

- **Dados do cartão passam pelo backend.** O número completo e o CVV trafegam pela API até o Asaas. O ideal é adotar tokenização do cartão, para que esses dados não passem pela aplicação, reduzindo o escopo de conformidade PCI.
- **Pagamentos pendentes não são atualizados.** Um pagamento com status `PENDING` no Asaas mantém a cobrança pendente; a atualização posterior exige receber os webhooks do Asaas.
- **Sem autenticação.** Quem conhece o id de uma cobrança pode consultá-la e tentar pagá-la. Para um link de pagamento avulso isso é aceitável, mas o ideal é um token assinado por cobrança, com expiração. Com autenticação por cookie, o CORS deve ser restringido à origem do frontend.
- **Dependências com alertas de segurança.** O CI executa `composer audit` e `npm audit`, que apontam alertas em dependências transitivas. A atualização deve ser feita dentro das versões compatíveis, com revalidação de testes e build, e então a auditoria passa a bloquear o pipeline.
- **Tipo de documento implícito.** CPF e CNPJ ficam na mesma coluna, distinguidos pelo tamanho. Se a interface precisar diferenciá-los, convém uma coluna de tipo explícita.
- **Teste de integração automatizado com o sandbox.** O fluxo real foi validado manualmente; um teste opcional contra o sandbox, fora do CI, daria a mesma garantia com um comando.
- **Testes de componente no frontend**, caso a lógica de interface cresça.
