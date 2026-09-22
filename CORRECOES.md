Correções — Desafio InMediam

1. Validação dos dados de pagamento

Problema

O endpoint de pagamento recebia os dados do cartão diretamente através do Request, sem validação específica dos campos.

Correção

Foi criado o PayBillingRequest para validar:

nome do titular;

número do cartão;

validade;

CVV.

O número do cartão também passa por validação de formato e algoritmo de Luhn.

A validade do cartão é validada no formato MM/AA e também é verificado se o cartão não está expirado em relação ao mês e ano atuais.

Motivo

Separar a validação da regra de negócio e impedir que dados inválidos cheguem ao fluxo de pagamento.

2. Valor da cobrança controlado pelo backend

Problema

O backend utilizava o valor amount enviado pelo frontend para criar a cobrança e registrar o pagamento.

Correção

O valor passou a ser obtido diretamente da cobrança armazenada no banco, utilizando $billing->amount.

O frontend também deixou de enviar o campo amount.

Motivo

Impedir que o cliente manipule o valor enviado ao backend durante o pagamento.

3. Validação da existência e situação da cobrança

Problema

O fluxo de pagamento precisava garantir que a cobrança realmente existisse e estivesse disponível para pagamento.

Correção

A cobrança passou a ser buscada diretamente no backend, com carregamento do cliente relacionado e bloqueio para atualização durante o processamento.

O backend verifica se a cobrança está com status pending antes de iniciar o pagamento.

Caso a cobrança não exista, o backend retorna 404.

Caso a cobrança já esteja em outro estado, o backend retorna 409.

Motivo

Garantir que somente cobranças válidas e ainda pendentes possam entrar no fluxo de pagamento.

4. Chave da API do Asaas em variável de ambiente

Problema

A chave de acesso à API do Asaas não deveria ficar diretamente no código da aplicação.

Correção

A chave da API passou a ser configurada através de variável de ambiente.

Como foi feito

No arquivo backend/config/services.php, foi configurado:

'asaas' => [
    'key' => env('ASAAS_API_KEY'),
    'base_url' => env('ASAAS_BASE_URL'),
],

No arquivo .env.example:

ASAAS_API_KEY=
ASAAS_BASE_URL=https://api-sandbox.asaas.com/v3

Motivo

Separar as credenciais da aplicação e permitir que cada ambiente utilize sua própria configuração sem alterar o código-fonte.

5. Centralização da integração com o Asaas

Problema

A comunicação com a API do Asaas ficava diretamente relacionada ao fluxo do controller.

Correção

Foi criado um serviço específico para centralizar a comunicação com a API do Asaas.

Como foi feito

Foi criado o arquivo:

backend/app/Services/AsaasService.php

O serviço passou a concentrar as operações de:

criação do cliente no Asaas;

criação da cobrança;

pagamento da cobrança com cartão de crédito.

Também foram centralizadas as configurações das requisições HTTP, incluindo:

base_url;

access_token;

connect timeout;

request timeout;

headers JSON.

Foram configurados timeout de conexão de 10 segundos e timeout da requisição de 60 segundos.

Motivo

Separar a integração com o serviço externo da lógica do controller, melhorando a organização, manutenção e testabilidade do código.

6. Tratamento de erros da API do Asaas

Problema

Falhas de conexão com o Asaas e erros retornados pela API externa não possuíam um tratamento específico no fluxo de pagamento.

Correção

Foram adicionados tratamentos específicos para falhas de conexão, timeout e erros HTTP retornados pelo Asaas.

Como foi feito

No BillingController, foram tratados cenários de:

ConnectionException;

RequestException.

Quando ocorre uma falha de conexão ou timeout com o serviço de pagamento, o backend retorna HTTP 503.

Quando o Asaas retorna um erro HTTP durante o processamento, o backend retorna HTTP 502.

Os detalhes técnicos permanecem no log da aplicação sem serem expostos desnecessariamente ao usuário.

Motivo

Melhorar o tratamento de falhas externas e retornar respostas HTTP adequadas para que o frontend consiga apresentar uma mensagem apropriada ao usuário.

7. Verificação do status do pagamento no Asaas

Problema

O pagamento poderia ser registrado localmente sem confirmação adequada do Asaas.

Correção

O backend passou a verificar explicitamente o status retornado pelo Asaas.

O pagamento somente é considerado concluído quando o Asaas retorna:

CONFIRMED

Somente após essa confirmação o sistema:

registra o pagamento local;

altera o status da cobrança para paid;

registra a data do pagamento;

associa o cartão utilizado.

Caso o Asaas retorne qualquer outro status, o pagamento local não é marcado como confirmado.

Motivo

Garantir que o pagamento local somente seja concluído após confirmação explícita do provedor.

8. Proteção contra pagamento duplicado

Problema

O fluxo de pagamento precisava impedir que a mesma cobrança fosse processada novamente depois de já ter sido paga.

Correção

Foram adicionadas proteções tanto no fluxo da aplicação quanto no banco de dados.

Como foi feito

A cobrança é bloqueada para atualização durante o processamento através de lockForUpdate().

O processamento ocorre dentro de DB::transaction().

Antes de iniciar o pagamento, o sistema verifica se a cobrança ainda possui status pending.

Caso a cobrança já tenha outro status, o backend retorna HTTP 409.

Também existe uma restrição única para billing_id na tabela payments, impedindo que a mesma cobrança possua mais de um pagamento registrado.

Motivo

Reduzir o risco de processamento duplicado e proteger a integridade financeira dos registros.

9. Proteção do token do cartão

Problema

O card_token não deveria ficar disponível de forma exposta nas respostas JSON e também precisava possuir proteção no armazenamento.

Correção

O token passou a:

ser armazenado de forma criptografada;

possuir hash HMAC-SHA256 para identificação segura;

permanecer oculto nas respostas da API;

ter o hash também ocultado nas respostas.

Como foi feito

No arquivo:

backend/app/Models/CreditCard.php

foram configurados como ocultos:

protected $hidden = [
    'card_token',
    'card_token_hash',
];

O campo card_token utiliza cast criptografado.

Também foi criada uma migration específica para adequar a estrutura do banco, adicionar card_token_hash e aplicar a proteção no armazenamento.

Motivo

Evitar a exposição desnecessária de dados sensíveis e reduzir o risco associado ao armazenamento do token do cartão.

10. Correção de Mass Assignment nos models

Problema

Alguns models utilizavam protected $guarded = [];, permitindo que qualquer atributo pudesse ser preenchido através de atribuição em massa.

Correção

Os models passaram a utilizar protected $fillable, definindo explicitamente quais campos podem ser preenchidos.

Como foi feito

A alteração foi aplicada nos models:

Billing;

CreditCard;

Customer;

Payment;

Plan.

Cada model passou a possuir uma lista específica de atributos permitidos.

Motivo

Reduzir o risco de alteração indevida de campos através de atribuição em massa e deixar explícitos os atributos permitidos em cada model.

11. Suporte e validação de CPF e CNPJ

Problema

O campo document possuía suporte estrutural somente para documentos de até 11 caracteres e não realizava validação completa do documento.

Correção

O campo passou a aceitar documentos com até 14 caracteres e foi implementada validação para CPF e CNPJ.

Como foi feito

Foi criada a migration:

backend/database/migrations/2026_09_21_233541_change_document_length_on_customers_table.php

A coluna passou de tamanho 11 para tamanho 14.

Também foi criado o DocumentValidator, responsável por:

remover caracteres não numéricos;

validar CPF;

validar CNPJ;

validar os dígitos verificadores;

aceitar documentos de 11 ou 14 dígitos.

Motivo

Permitir o uso de CPF e CNPJ no fluxo e evitar o armazenamento de documentos inválidos.

12. Enums para status

Problema

Os status de cobrança e pagamento poderiam ser tratados como strings espalhadas pela aplicação.

Correção

Foram criados Enums para centralizar os estados permitidos.

Como foi feito

Foram implementados:

BillingStatus;

PaymentStatus.

O BillingStatus utiliza, entre outros, os estados:

PENDING = pending
PAID = paid

O PaymentStatus utiliza:

CONFIRMED = CONFIRMED

Os models passaram a realizar casts dos campos de status para os respectivos Enums.

Motivo

Centralizar os valores permitidos, reduzir inconsistências e tornar as comparações de status mais seguras e legíveis.

13. Seeders idempotentes

Problema

Os seeders poderiam criar registros duplicados quando executados novamente.

Correção

Os seeders foram ajustados para reutilizar registros existentes e evitar duplicidades durante novas execuções.

Como foi feito

Foram ajustados os seeders de:

Plan;

Customer;

Billing;

Payment.

Foi utilizado updateOrCreate() nos casos apropriados.

Também foram utilizados métodos como firstOrFail() para garantir que registros necessários existam antes da criação dos relacionamentos.

Os status utilizados nos seeders também passaram a utilizar os Enums correspondentes.

Validação

O comando php artisan migrate:fresh --seed foi executado com sucesso, recriando a estrutura e os dados de teste.

Motivo

Permitir que o banco seja populado novamente com segurança durante o desenvolvimento e testes.

14. API Resources para respostas da API

Problema

As respostas da API poderiam expor diretamente atributos dos models, dificultando o controle sobre quais dados seriam enviados ao frontend.

Correção

Foram criados API Resources para padronizar as respostas.

Como foi feito

Foram utilizados:

BillingResource;

PaymentResource.

O BillingResource controla os dados retornados da cobrança e seus relacionamentos carregados.

O PaymentResource controla os dados retornados do pagamento e do cartão relacionado.

Quando o cartão relacionado é retornado, somente informações não sensíveis são disponibilizadas, como:

nome do titular;

últimos quatro dígitos;

bandeira.

O token do cartão não é incluído na resposta.

Motivo

Padronizar as respostas da API e evitar exposição acidental de atributos internos ou dados sensíveis.

15. Centralização das chamadas HTTP no frontend

Problema

As chamadas para a API utilizavam URLs diretamente dentro dos componentes do frontend.

Correção

Foi criada uma instância Axios centralizada para concentrar a configuração da API.

Como foi feito

Foi criado o arquivo:

frontend/src/lib/api.ts

Com:

import axios from 'axios'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
})

A URL da API passou a ser definida através da variável de ambiente:

VITE_API_URL=http://localhost:8000/api

As páginas e componentes que realizam requisições passaram a utilizar a instância api.

Motivo

Centralizar a configuração das requisições HTTP e facilitar a alteração da URL da API entre diferentes ambientes.

16. Remoção do valor da cobrança enviado pelo frontend

Problema

O formulário de pagamento enviava o valor da cobrança junto com os dados do cartão.

Correção

O campo amount foi removido da requisição enviada pelo frontend.

Como foi feito

O PaymentForm passou a utilizar somente o identificador da cobrança.

Na chamada para a API são enviados apenas os dados necessários para o pagamento:

card_holder_name
card_number
expiry_date
cvv

O valor da cobrança continua sendo obtido e controlado exclusivamente pelo backend.

Motivo

Evitar que o cliente consiga alterar o valor da cobrança enviada para o processamento do pagamento.

17. Validação do formulário de pagamento com Zod

Problema

A validação dos dados do cartão no frontend era insuficiente.

Correção

Foi implementado um schema com Zod para validar os dados antes do envio para a API.

Como foi feito

O formulário passou a validar:

número do cartão;

nome do titular;

validade;

CVV.

O número do cartão passa por uma transformação para remover espaços antes da validação.

A validade deve seguir o formato MM/AA e o CVV deve possuir 3 ou 4 dígitos.

Motivo

Validar os dados ainda no frontend, fornecer feedback imediato ao usuário e reduzir o envio de dados claramente inválidos para o backend.

18. Atualização da cobrança após o pagamento

Problema

Após a realização do pagamento, os dados da cobrança poderiam permanecer desatualizados no cache do React Query.

Correção

A query da cobrança passou a ser invalidada após o pagamento realizado com sucesso.

Como foi feito

No PaymentForm, o QueryClient invalida a query correspondente à cobrança:

queryClient.invalidateQueries({
  queryKey: ['billing', billingId],
})

Com isso, os dados da cobrança são buscados novamente após a alteração.

Motivo

Garantir que a interface apresente os dados atualizados da cobrança após o pagamento.

19. Tratamento de erros no pagamento

Problema

Quando o pagamento falhava, o frontend não apresentava corretamente a mensagem retornada pelo backend.

Correção

O tratamento de erro do React Query foi ajustado para ler a mensagem retornada pela API e apresentá-la através do toast.

Como foi feito

Foi utilizado AxiosError para acessar a resposta do backend.

Caso o backend não forneça uma mensagem específica, o frontend utiliza uma mensagem padrão.

Motivo

Garantir que o usuário receba uma informação clara quando o pagamento não puder ser processado.

20. Estados de carregamento, erro e sucesso da cobrança

Problema

A página de cobrança não possuía tratamento visual específico enquanto os dados estavam sendo carregados ou quando ocorria uma falha na requisição.

Correção

Foram adicionados estados específicos para carregamento, erro e conclusão do pagamento.

Como foi feito

Enquanto os dados estão sendo carregados, a interface apresenta uma mensagem de carregamento.

Quando ocorre erro ao buscar a cobrança, a interface apresenta uma mensagem específica de erro.

Quando a cobrança está pendente, o formulário de pagamento é exibido.

Quando a cobrança está paga, a tela de conclusão é apresentada utilizando os dados relacionados retornados pela API.

O formulário também possui estado de processamento para impedir múltiplos envios simultâneos.

Motivo

Fornecer feedback adequado ao usuário durante operações assíncronas e representar corretamente o estado atual da cobrança.

21. Acesso seguro aos dados do plano

Problema

A página de cobrança acessava diretamente propriedades do objeto data, sem garantir que os dados estivessem disponíveis.

Correção

Foi utilizado acesso opcional.

Como foi feito

Os acessos passaram a utilizar:

data?.plan?.name
data?.plan?.description

Motivo

Evitar erros de execução enquanto os dados ainda não estiverem disponíveis ou quando a API não retornar algum relacionamento esperado.

22. Correção de classe Tailwind inválida

Problema

Foi identificada uma classe Tailwind inválida contendo o trecho max-.

Correção

A classe foi corrigida para utilizar uma classe válida do Tailwind, mantendo o layout centralizado.

Motivo

Evitar classes CSS inválidas e garantir que o layout seja interpretado corretamente pelo Tailwind.

23. Ajuste da configuração do TypeScript

Problema

O tsconfig.json utilizava a configuração baseUrl, marcada como depreciada na configuração utilizada pelo projeto.

Correção

A configuração baseUrl foi removida e o alias de importação @/* foi mantido através de paths.

Como foi feito

O alias continua configurado através de:

"paths": {
  "@/*": ["./src/*"]
}

A configuração do Vitest também foi ajustada para resolver corretamente o alias durante os testes.

Motivo

Manter os imports utilizando @/ funcionando e remover uma configuração depreciada.

24. Proteção do histórico financeiro contra exclusões em cascata

Problema

Relacionamentos financeiros utilizando cascade delete poderiam permitir a exclusão de registros históricos relacionados.

Correção

As relações financeiras passaram a utilizar comportamento restritivo para impedir exclusões que comprometam o histórico.

Como foi feito

Foram ajustados os relacionamentos de:

billings.plan_id;

billings.customer_id;

credit_cards.customer_id;

payments.billing_id;

payments.credit_card_id.

O comportamento foi alterado de exclusão em cascata para restrição.

Motivo

Preservar o histórico financeiro e impedir que a exclusão de um registro relacionado apague cobranças ou pagamentos existentes.

25. PostgreSQL em Docker

Problema

O projeto dependia de uma configuração local do PostgreSQL e havia conflito com uma instalação PostgreSQL existente durante o desenvolvimento.

Correção

O PostgreSQL passou a ser executado através do Docker Compose utilizando a imagem postgres:16.

Como foi feito

No docker-compose.yml foi configurado um serviço PostgreSQL com healthcheck utilizando pg_isready.

A porta do PostgreSQL no container permanece 5432.

No ambiente local utilizado durante o desenvolvimento, a porta do host foi configurada como 5433 para evitar conflito com o PostgreSQL já instalado na máquina.

Motivo

Padronizar o ambiente do banco de dados e facilitar a execução do projeto em um ambiente controlado pelo Docker.

26. Configuração através de variáveis de ambiente

Problema

Algumas configurações do projeto dependiam de valores específicos do ambiente de desenvolvimento e não deveriam ficar fixas no código-fonte.

Correção

As principais configurações passaram a utilizar variáveis de ambiente, com arquivos .env.example para orientar a configuração de cada ambiente.

Como foi feito

No backend:

ASAAS_API_KEY=
ASAAS_BASE_URL=https://api-sandbox.asaas.com/v3

No frontend:

VITE_API_URL=http://localhost:8000/api

As configurações do banco de dados também utilizam variáveis de ambiente.

Também foi configurado o .gitignore para impedir que arquivos .env locais sejam versionados.

Motivo

Separar configurações específicas do ambiente do código da aplicação, evitando o versionamento de credenciais e facilitando a execução do projeto em diferentes ambientes.

27. Atualização das dependências do backend

Problema

A análise inicial das dependências identificou vulnerabilidades conhecidas em pacotes utilizados pelo backend.

Correção

As dependências foram atualizadas dentro da versão principal do Laravel utilizada pelo projeto.

O Laravel foi atualizado dentro da série 12, juntamente com dependências relacionadas.

Também foi atualizado o symfony/yaml para uma versão que não apresenta os avisos de segurança identificados anteriormente.

Validação

Foram executados:

composer validate
composer audit

O composer.json foi validado com sucesso e o composer audit finalizado sem vulnerabilidades reportadas.

Motivo

Reduzir riscos conhecidos nas dependências utilizadas pelo backend sem realizar uma migração desnecessária para uma nova versão principal do framework.

28. Testes automatizados do backend

Problema

O fluxo principal de pagamento precisava ser validado após as alterações realizadas.

Correção

Foram ampliados os testes automatizados do backend para cobrir os principais cenários de pagamento.

Como foi feito

Foram cobertos cenários como:

utilização do valor da cobrança armazenado no backend;

pagamento não confirmado pelo Asaas;

erro HTTP retornado pelo Asaas;

falha de conexão com o Asaas;

timeout;

token do cartão não exposto;

tentativa de pagamento de cobrança já paga;

cobrança inexistente;

dados inválidos;

validade de cartão expirada;

número de cartão inválido;

resposta inválida de criação de cliente no Asaas;

CPF válido;

CNPJ válido;

CPF inválido;

CNPJ inválido;

rollback da transação;

proteção contra dois pagamentos para a mesma cobrança.

Foram utilizados mocks do HTTP client para simular as respostas do Asaas.

Validação

Foi executado:

php artisan test

Resultado atual:

19 testes passaram
65 assertions
0 falhas

Motivo

Validar o comportamento do backend nos cenários de sucesso, falha, segurança e concorrência do fluxo de pagamento.

29. Testes automatizados do frontend

Problema

O fluxo de pagamento do frontend precisava validar comportamento de sucesso, erro, validação e prevenção de requisições duplicadas.

Correção

Foram adicionados testes com Vitest e React Testing Library.

Como foi feito

Foram cobertos cenários como:

validação de dados inválidos;

envio dos dados necessários sem amount;

remoção dos espaços do número do cartão;

estado de processamento;

prevenção de envio duplicado;

invalidação da query da cobrança após sucesso;

apresentação de erro retornado pela API;

estado de carregamento da cobrança;

estado de erro da cobrança;

exibição do formulário para cobrança pendente;

exibição da confirmação para cobrança paga.

Validação

Foi executado:

npm test

Resultado atual:

2 arquivos de teste
9 testes passaram
0 falhas

Motivo

Reduzir o risco de regressões no comportamento da interface e validar os principais estados do fluxo de pagamento.

30. Validação do TypeScript, lint e build

Problema

As alterações realizadas no frontend poderiam introduzir erros de tipagem, lint ou build.

Correção

Foram executadas as validações do projeto.

Como foi feito

TypeScript:

npm run tsc

Lint:

npm run lint

Build:

npm run build

Os três comandos foram concluídos com sucesso.

O build do Vite foi concluído com 4177 módulos transformados.

O build também apresentou apenas avisos não bloqueantes relacionados ao Browserslist desatualizado e ao tamanho de um chunk gerado pelo Vite.

Motivo

Garantir que o frontend continue compilando, tipado corretamente e sem erros de lint após as alterações.

31. Sincronização dos campos do formulário

Problema

Alguns campos do formulário de cartão precisavam manter sincronização correta entre os valores registrados pelo React Hook Form e os componentes controlados utilizados na interface.

Correção

Os campos necessários passaram a sincronizar seus valores através do formulário, utilizando setValue quando necessário.

Motivo

Evitar divergências entre o valor apresentado na interface e o valor efetivamente enviado para a API.

32. Exibição do resultado do pagamento

Problema

Após o pagamento, a tela de conclusão precisava receber os dados relacionados ao pagamento sem depender de informações não disponíveis no frontend.

Correção

A cobrança passou a carregar o relacionamento com os pagamentos, e o PaymentResource controla os dados disponibilizados para a tela de conclusão.

A tela PaymentConcluded utiliza os dados retornados pela API.

Motivo

Centralizar no backend o carregamento dos dados relacionados e manter a resposta da API consistente com o estado real da cobrança.

33. Pontos identificados que permanecem como melhorias futuras

Durante a análise do projeto, também foram identificados pontos que não fazem parte da implementação concluída nesta etapa.

Entre eles:

autenticação e autorização das rotas;

webhook e reconciliação com o Asaas;

utilização de mecanismo de idempotência específico disponibilizado pelo Asaas;

substituição dos dados fictícios de telefone e endereço utilizados na integração;

testes de integração reais contra o ambiente Sandbox do Asaas;

melhorias na divisão dos chunks do frontend para reduzir o tamanho dos arquivos gerados.

Esses pontos permanecem registrados como melhorias futuras e não são considerados implementados nesta versão.

34. Resumo da validação realizada

Backend

Validações executadas:

composer validate
composer audit
php artisan config:clear
php artisan migrate:fresh --seed
php artisan test
vendor/bin/pint --test

Resultados registrados:

composer.json válido
composer audit sem vulnerabilidades reportadas
migrations executadas com sucesso
seeders executados com sucesso
19 testes aprovados
65 assertions
Pint aprovado

Frontend

Validações executadas:

npm test
npm run tsc
npm run lint
npm run build

Resultados registrados:

9 testes aprovados
TypeScript aprovado
Lint aprovado
Build de produção aprovado

Banco de dados

O ambiente utiliza:

PostgreSQL 16
Docker Compose
porta do host: 5433
porta do container: 5432

Integração com Asaas

A integração utiliza o ambiente Sandbox configurado por variável de ambiente:

https://api-sandbox.asaas.com/v3

As credenciais não são armazenadas no código-fonte.

35. Considerações finais

As correções realizadas concentram-se principalmente em:

validação dos dados recebidos;

proteção contra manipulação do valor da cobrança;

controle de status do pagamento;

tratamento de erros de integração;

proteção contra pagamentos duplicados;

proteção de dados sensíveis do cartão;

suporte e validação de CPF e CNPJ;

uso de Enums;

uso de API Resources;

proteção do histórico financeiro;

centralização da integração com o Asaas;

centralização das chamadas HTTP do frontend;

tratamento de estados de carregamento, erro e conclusão;

validação do formulário com Zod;

testes automatizados de backend e frontend;

padronização do ambiente através do Docker;

configuração por variáveis de ambiente;

atualização das dependências do backend;

validação de TypeScript, lint e build.

O projeto fica documentado com as correções efetivamente implementadas nesta etapa e com os pontos que permanecem como melhorias futuras.