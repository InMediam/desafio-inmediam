# Correções realizadas

## Backend

### 1. Validação dos dados do cartão

**Problema:**
O endpoint de pagamento não possuía validações suficientes para os dados enviados pelo cliente.

**Correção:**
Foi adicionada validação para nome do titular, número do cartão, validade e CVV antes das chamadas à API da Asaas.

**Motivo:**
Evitar o processamento de dados inválidos e impedir chamadas desnecessárias à API externa.

### 2. Validação da validade do cartão

**Problema:**
Não havia uma verificação para impedir o uso de cartões expirados.

**Correção:**
A validade informada é convertida para uma data e o pagamento é interrompido quando o cartão está expirado.

**Motivo:**
Evitar o envio de uma tentativa de pagamento com um cartão que já está vencido.

### 3. Valor da cobrança controlado pelo backend

**Problema:**
O valor da cobrança era recebido pelo frontend e utilizado na criação da cobrança na Asaas.

**Correção:**
O valor utilizado na integração passou a ser o valor armazenado na cobrança no banco de dados (`$billing->amount`).

Além disso, o `amount` foi removido do formulário e da requisição enviada pelo frontend.

**Motivo:**
O frontend não deve ser considerado uma fonte confiável para definir o valor de uma cobrança.

### 4. Centralização das configurações da Asaas

**Problema:**
A API Key e a URL da Asaas estavam sendo obtidas diretamente no Controller, com a URL da API definida no código.

**Correção:**
As configurações foram centralizadas em `config/services.php` e os valores são definidos pelo `.env`.

As variáveis foram adicionadas também ao `.env.example`:

- `ASAAS_API_KEY`
- `ASAAS_BASE_URL`

**Motivo:**
Separar configuração de código e facilitar a alteração entre ambientes, como Sandbox e Produção.

### 5. Identificação do cliente na Asaas

**Problema:**
O modelo de clientes não possuía um campo para armazenar o identificador do cliente criado na Asaas.

**Correção:**
Foi criada uma migration para adicionar o campo `asaas_customer_id` à tabela `customers`.

O sistema utiliza esse identificador para reutilizar o cliente já cadastrado na Asaas e somente criar um novo quando necessário.

**Motivo:**
Evitar a criação desnecessária de clientes duplicados na Asaas e manter a relação entre o cliente local e o cliente da plataforma de pagamentos.

### 6. Dados adicionais do cliente

**Problema:**
Os dados necessários para `creditCardHolderInfo` não existiam na estrutura original de clientes.

**Correção:**
Foram adicionados os campos `phone`, `postal_code` e `address_number` por meio de migration.

**Motivo:**
Permitir que a integração utilize os dados de contato do cliente armazenados no banco, evitando valores fixos no código.

### 7. Tratamento de erros da integração com a Asaas

**Problema:**
Respostas diferentes da API da Asaas eram tratadas indiscriminadamente e detalhes da resposta externa poderiam ser expostos ao cliente.

**Correção:**
As respostas da Asaas passaram a ser verificadas antes da continuidade do fluxo.

Os detalhes retornados pela Asaas são registrados nos logs, enquanto o cliente recebe mensagens controladas pela aplicação.

Falhas de conexão com a API externa continuam sendo tratadas como `502`.

**Motivo:**
Diferenciar falhas de comunicação de erros retornados pela API e evitar a exposição de informações internas da integração.

### 8. Confirmação do pagamento antes da atualização local

**Problema:**
O pagamento precisava ser confirmado pela Asaas antes de ser registrado como pago no sistema local.

**Correção:**
A cobrança local só é marcada como `paid` quando a resposta da Asaas possui status `CONFIRMED`.

**Motivo:**
Evitar que uma cobrança seja marcada como paga localmente sem confirmação do pagamento externo.

### 9. Reutilização de cartão pelo token

**Problema:**
Um novo registro de cartão era criado a cada pagamento, sem verificar se o cartão já estava cadastrado para aquele cliente.

**Correção:**
O sistema utiliza `customer_id` e `card_token` para localizar um cartão já cadastrado antes de criar um novo registro.

**Motivo:**
Evitar registros duplicados do mesmo cartão para o mesmo cliente.

### 10. Correção dos dados do cartão retornados pela Asaas

**Problema:**
Os dados utilizados para armazenar os últimos quatro dígitos e a bandeira do cartão precisavam ser compatíveis com a estrutura retornada pela Asaas.

**Correção:**
O sistema passou a utilizar os dados `creditCardNumber` e `creditCardBrand` retornados pela API da Asaas.

**Motivo:**
Garantir que os dados persistidos no cartão local correspondam à resposta da integração.

### 11. Endpoint para listagem de cobranças

**Problema:**
A tela inicial utilizava dados fixos de clientes, planos e status.

**Correção:**
Foi criado o endpoint `GET /api/billing` para retornar as cobranças necessárias para a listagem.

A consulta utiliza paginação e carrega somente os relacionamentos necessários para a Home.

**Motivo:**
Permitir que a interface utilize dados reais do backend e evitar informações fixas no frontend.

### 12. Limitação de tentativas no endpoint de pagamento

**Problema:**
O endpoint de pagamento não possuía limitação de requisições.

**Correção:**
Foi adicionado o middleware `throttle:5,1` ao endpoint:

`POST /api/billing/{billing}/pay`

**Motivo:**
Reduzir tentativas excessivas de pagamento e diminuir o risco de abuso do endpoint.

### 13. Separação da lógica de pagamento em Services

**Problema:**
O Controller concentrava responsabilidades relacionadas ao processamento do pagamento e à integração com a Asaas.

**Correção:**
A lógica foi separada em Services:

- `AsaasService`: responsável pela comunicação com a API da Asaas.
- `BillingPaymentService`: responsável pelo fluxo de pagamento da cobrança.

**Motivo:**
Reduzir a responsabilidade do Controller e facilitar a manutenção e os testes da lógica de negócio.

### 14. Separação da consulta de cobranças

**Problema:**
A consulta utilizada pela listagem de cobranças estava diretamente no Controller.

**Correção:**
A consulta da Home foi extraída para o `BillingService`.

**Motivo:**
Separar a responsabilidade de consulta da camada HTTP e manter o Controller mais enxuto.

### 15. Uso de transação no registro do pagamento

**Problema:**
O registro do pagamento e a atualização do status da cobrança envolvem duas operações no banco de dados.

**Correção:**
Essas operações passaram a ser realizadas dentro de uma `DB::transaction()`.

**Motivo:**
Garantir que o pagamento e a atualização da cobrança sejam persistidos de forma atômica no banco local.

## 16. Configuração de debug

### Problema

O modo debug deve ser desabilitado em ambientes de produção para evitar a exposição de informações internas da aplicação.

### Correção

Foi verificado/configurado `APP_DEBUG=false` no ambiente de execução utilizado para os testes.

### Motivo

Evitar a exposição de informações internas da aplicação em ambientes de produção.

### 17. Atualização da versão do PostgreSQL na documentação

**Problema:**
A documentação indicava PostgreSQL 16, enquanto o `docker-compose.yml` estava configurado para PostgreSQL 17.

**Correção:**
O README foi atualizado para refletir a versão PostgreSQL 17 utilizada pelo ambiente Docker.

**Motivo:**
Manter a documentação consistente com a configuração real do projeto.

---

## Frontend

### 1. Mensagem de erro no pagamento

**Problema:**
O callback `onError` do formulário exibia uma mensagem genérica mesmo quando o pagamento falhava.

**Correção:**
O tratamento de erro passou a utilizar a mensagem retornada pela API quando disponível.

**Motivo:**
Fornecer um feedback mais preciso ao usuário sem expor detalhes internos da aplicação.

### 2. Validação dos dados do formulário

**Problema:**
Os campos do formulário possuíam pouca validação no frontend.

**Correção:**
Foram adicionadas validações utilizando Zod para número do cartão, nome do titular, validade e CVV.

**Motivo:**
Fornecer feedback imediato ao usuário e evitar o envio de dados claramente inválidos ao backend.

### 3. Correção da formatação do valor da cobrança

**Problema:**
O valor da cobrança recebido pela API era utilizado diretamente na formatação monetária, resultando em `R$ NaN` na interface.

**Correção:**
O valor recebido da API passou a ser convertido para número antes da formatação.

**Motivo:**
Garantir que o valor da cobrança seja exibido corretamente na interface.

### 4. Máscara do número do cartão

**Problema:**
O número do cartão era enviado ao backend com espaços quando informado com formatação, causando falha na validação.

**Correção:**
Foi adicionada uma máscara visual para o número do cartão e os caracteres não numéricos são removidos antes do envio à API.

**Motivo:**
Permitir uma experiência de preenchimento mais amigável sem alterar o formato esperado pelo backend.

### 5. Máscara da validade do cartão

**Problema:**
O usuário poderia informar a validade sem o formato esperado.

**Correção:**
Foi adicionada uma máscara que formata automaticamente a entrada para `MM/AA`.

**Motivo:**
Padronizar a entrada do usuário e facilitar o preenchimento do campo.

### 6. Máscara do CVV

**Problema:**
O campo permitia caracteres que não faziam parte do CVV.

**Correção:**
O campo passou a aceitar somente números e foi limitado a quatro dígitos.

**Motivo:**
Garantir uma entrada mais adequada ao formato esperado pelo backend.

### 7. Remoção da formatação do cartão antes do envio

**Problema:**
O cartão formatado com espaços estava sendo enviado ao backend, causando falha na validação.

**Correção:**
Os caracteres não numéricos são removidos antes do envio para a API.

**Motivo:**
Permitir que o usuário utilize a máscara visual sem alterar o formato esperado pelo backend.

### 8. Lista de cobranças na tela inicial

**Problema:**
A tela inicial utilizava dados fixos de clientes, planos e status.

**Correção:**
A Home passou a consultar `GET /api/billing` e montar a lista dinamicamente com os dados retornados pela API.

**Motivo:**
Garantir que a tela inicial apresente o estado atual das cobranças.

### 9. Configuração da URL da API por ambiente

**Problema:**
A URL do backend estava definida diretamente em diferentes arquivos do frontend.

**Correção:**
A URL passou a ser obtida por meio da variável `VITE_API_URL`.

O arquivo `.env` do frontend também foi incluído no `.gitignore`.

**Motivo:**
Evitar valores fixos no código, facilitar a execução em diferentes ambientes e impedir o versionamento de configurações locais.

### 10. Tipagem da listagem de cobranças

**Problema:**
A listagem de cobranças utilizava `any` para os dados recebidos da API.

**Correção:**
Foi adicionada uma tipagem específica para os dados utilizados pela Home.

**Motivo:**
Melhorar a segurança de tipos e facilitar a manutenção do código.

### 11. Centralização do estado dos campos do formulário

**Problema:**
O formulário utilizava estados locais separados em conjunto com o estado do React Hook Form.

**Correção:**
Os campos de número do cartão, validade e CVV passaram a utilizar o estado do React Hook Form.

**Motivo:**
Evitar duplicação de estado e manter uma única fonte de verdade para os dados do formulário.

### 12. Estados de carregamento e erro

**Problema:**
As consultas da Home e da tela de cobrança não apresentavam feedback específico durante carregamento ou em caso de erro.

**Correção:**
Foram adicionadas mensagens específicas para os estados `isLoading` e `isError`.

**Motivo:**
Fornecer feedback adequado ao usuário durante as operações de consulta.

### 13. Atualização da cobrança após o pagamento

**Problema:**
Após um pagamento bem-sucedido, a tela permanecia com os dados anteriores até uma nova consulta.

**Correção:**
Após o pagamento, a query da cobrança atual e a query da lista de cobranças são invalidadas.

**Motivo:**
Garantir que tanto a tela atual quanto a Home reflitam o novo status da cobrança.

### 14. Remoção de configuração depreciada do TypeScript

**Problema:**
O `tsconfig.json` utilizava a opção `baseUrl`, que estava marcada como depreciada pelo TypeScript.

**Correção:**
A opção `baseUrl` foi removida, mantendo a configuração de `paths` utilizada pelos imports do projeto.

**Motivo:**
Eliminar o warning de depreciação e manter a configuração do TypeScript compatível com as versões atuais.

---

## Testes realizados

### Testes automatizados

A suíte automatizada foi executada com sucesso:

- `php artisan test` — suíte completa executada com sucesso.
- `php artisan test --filter=BillingTest` — 4 testes e 7 assertions executados com sucesso.
- `npm.cmd run build` — build de produção do frontend executado com sucesso.

O `BillingTest` cobre os seguintes cenários:

- Cobrança inexistente retorna `404`.
- Tentativa de pagamento de cobrança já paga retorna `409`.
- Cartão expirado é rejeitado com `422`.
- O valor utilizado no pagamento corresponde ao valor da cobrança armazenado no backend, independentemente do `amount` enviado pelo cliente.
- Após um pagamento confirmado, a cobrança é atualizada para o status `paid`.
- Após um pagamento confirmado, um registro de pagamento é criado com o valor correto e status `CONFIRMED`.

O teste de valor da cobrança utiliza `Http::fake()` para simular a comunicação com a Asaas sem realizar uma chamada externa real.

Além de validar o valor enviado à Asaas, o teste também verifica o estado final dos dados no banco de dados após o pagamento.

### Testes manuais

Foram realizados testes dos principais cenários do fluxo de pagamento:

- Consulta de cobrança inexistente, retornando `404`.
- Tentativa de pagamento de cobrança já paga, retornando `409`.
- Validação de dados inválidos do cartão, retornando `422`.
- Validação de cartão expirado.
- Criação e reutilização de cliente na Asaas.
- Processamento de pagamento utilizando cartão de teste no ambiente Sandbox da Asaas.
- Confirmação do pagamento no backend após retorno `CONFIRMED`.
- Funcionamento do fluxo completo pelo frontend.
- Máscaras de número do cartão, validade e CVV.
- Verificação da lista de cobranças na Home utilizando dados reais da API.
- Atualização automática do status da cobrança após pagamento realizado com sucesso.
- Exibição da mensagem de erro retornada pelo backend no frontend.
- Tratamento de cobrança inexistente.
- Verificação dos estados de carregamento e erro das consultas.

---

# Melhorias previstas para produção

Durante o code review foram identificados alguns pontos que seriam tratados em uma versão de produção da aplicação.

Essas alterações não foram implementadas neste desafio para manter o escopo controlado, mas ficam registradas como próximos passos técnicos.

## 1. Autenticação e autorização

Implementar autenticação e autorização na API, garantindo que somente usuários autenticados e autorizados possam consultar ou realizar operações sobre as cobranças.

Também seria implementada uma regra de autorização específica no `PayBillingRequest`, garantindo que o usuário tenha permissão para realizar o pagamento da cobrança solicitada.

---

## 2. Validação Luhn

Adicionar a validação do algoritmo de Luhn para verificar a consistência do número do cartão antes de enviá-lo para a Asaas.

A validação atual verifica o formato e a quantidade de dígitos, mas não realiza essa validação matemática.

---

## 3. Idempotência e concorrência no pagamento

Implementar um mecanismo de idempotência e controle de concorrência para impedir que duas requisições simultâneas processem a mesma cobrança.

O objetivo é garantir que uma cobrança não seja paga ou registrada duas vezes em situações de requisições concorrentes.

---

## 4. Persistência do ID da cobrança na Asaas

Persistir no banco de dados o identificador da cobrança criada na Asaas.

Isso permitiria rastrear a cobrança externa, facilitar conciliação e possibilitar tratamentos de falhas ou reprocessamentos.

---

## 5. Tratamento de estados intermediários da Asaas

Implementar o tratamento dos diferentes estados possíveis de um pagamento na Asaas.

Atualmente o fluxo considera `CONFIRMED` como condição para concluir o pagamento localmente.

Em produção, estados intermediários, como `AUTHORIZED` ou outros estados pendentes de confirmação, deverão ser tratados adequadamente.

---

## 6. Webhooks da Asaas

Implementar webhooks para receber atualizações de status dos pagamentos diretamente da Asaas.

Isso permitirá que a aplicação atualize o status da cobrança local quando houver alterações assíncronas no pagamento.

Também deverá ser implementada a validação das notificações recebidas para garantir a autenticidade das informações.

---

## 7. Timeout e retry nas requisições externas

Configurar timeout explícito nas requisições para a Asaas e implementar uma estratégia controlada de retry para falhas transitórias.

Os retries deverão ser utilizados com cuidado para evitar a criação duplicada de cobranças.

---

## 8. Concorrência na criação do cliente Asaas

Tratar possíveis condições de corrida durante a criação de clientes na Asaas.

Duas requisições simultâneas podem tentar criar o mesmo cliente antes que o `asaas_customer_id` seja persistido localmente.

Em produção, essa situação deverá ser tratada para evitar clientes duplicados ou conflitos na persistência do identificador.

---

## 9. Desacoplamento das exceções HTTP dos Services

Remover o acoplamento direto dos Services com exceções HTTP, como `HttpException`.

Uma evolução possível seria utilizar exceções específicas do domínio ou da integração e deixar uma camada superior responsável por convertê-las em respostas HTTP.

Isso mantém a camada de negócio independente do protocolo HTTP.

---

## 10. Refinamento dos tipos de retorno do Controller

Refinar os tipos de retorno dos métodos do `BillingController`, tornando-os mais precisos e consistentes com todas as respostas possíveis.

Também poderia ser adotada uma estratégia mais padronizada para o tratamento das exceções e respostas HTTP.

---

## 11. Data real de confirmação do pagamento

Ajustar o preenchimento do campo `paid_at`.

Atualmente é utilizado:

`'paid_at' => now()`

Em produção, quando disponível, deverá ser considerada a data/hora efetiva de confirmação informada pela Asaas, permitindo maior precisão no histórico do pagamento.

## 12. Evolução das camadas de arquitetura

Conforme a aplicação cresça, a arquitetura poderá evoluir para separar ainda mais as responsabilidades de:

- Regras de domínio;
- Integração com serviços externos;
- Persistência;
- Tratamento de exceções;
- Casos de uso.


# Considerações Finais

As correções implementadas priorizaram:

- Segurança;
- Integridade dos valores;
- Validação dos dados;
- Tratamento de erros;
- Integração com a Asaas;
- Organização do código;
- Separação de responsabilidades;
- Experiência do usuário;
- Testes automatizados;
- Documentação.

As melhorias acima foram identificadas durante a análise técnica e ficam registradas como próximos passos para uma evolução do projeto em ambiente de produção.