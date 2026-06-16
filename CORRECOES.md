# Correções realizadas

## Backend laravel

### 1. Remoção de credencial hardcoded do Asaas
- **Problema encontrado:** a chave da API estava fixa no controller.
- **Correção:** movi a configuração para `config/services.php` e para as variáveis de ambiente.
- **Motivo:** evita vazamento de segredo e facilita troca de ambiente.

### 2. Validação de entrada no pagamento
- **Problema encontrado:** o endpoint de pagamento aceitava dados sem validação.
- **Correção:** criei `PayBillingRequest` com validações e sanitização dos campos do cartão.
- **Motivo:** reduz risco de payload inválido e deixa o controller mais limpo.

### 3. Proteção contra pagamento duplicado
- **Problema encontrado:** o fluxo podia ser executado várias vezes para a mesma cobrança.
- **Correção:** o controller agora marca a cobrança como `processing` antes da integração externa e bloqueia reprocessamentos.
- **Motivo:** evita operações duplicadas e melhora a resiliência.

### 4. Separação de responsabilidades
- **Problema encontrado:** o controller concentrava lógica de integração externa, persistência e tratamento de erro.
- **Correção:** extraí a integração com o Asaas para `AsaasService`.
- **Motivo:** melhora manutenção, testabilidade e legibilidade.

### 5. Segurança na resposta JSON
- **Problema encontrado:** modelos expunham mais campos do que o necessário.
- **Correção:** escondi campos sensíveis como `card_token` e chaves internas de relacionamento.
- **Motivo:** reduz exposição de dados internos e sensíveis.

### 6. Testes do Laravel
- **Problema encontrado:** a suíte não conseguia criar a aplicação corretamente.
- **Correção:** adicionei `tests/CreatesApplication.php` e registrei o trait em `tests/TestCase.php`.
- **Motivo:** habilita a execução dos testes de Feature.

### 7. Ajuste do ambiente de teste
- **Problema encontrado:** o ambiente de teste não tinha `APP_KEY` nem `.env`.
- **Correção:** configurei uma chave válida no `phpunit.xml` e criei um `.env` local seguro.
- **Motivo:** elimina warnings e permite executar a suíte sem ruído.

## Front

### 1. Base URL centralizada
- **Problema encontrado:** requisições estavam hardcoded para `http://localhost:8000`.
- **Correção:** criei um client Axios centralizado e usei `VITE_API_BASE_URL`.
- **Motivo:** facilita dev local, testes e deploy.

### 2. Fluxo de pagamento reativo
- **Problema encontrado:** após pagar, a tela não refletia o novo status.
- **Correção:** invalidei a query da cobrança após o sucesso.
- **Motivo:** a tela atualiza automaticamente para o comprovante.

### 3. Formulário do cartão
- **Problema encontrado:** havia estado duplicado, toast incorreto no erro e validação fraca.
- **Correção:** reescrevi o formulário com React Hook Form + Zod, corrigi o toast de erro e removi o envio de valor controlado pelo cliente.
- **Motivo:** melhora UX, reduz bugs de estado e evita confiar em valor vindo do frontend.

### 4. Estados de loading e erro
- **Problema encontrado:** a página de cobrança não tratava carregamento e falha de API.
- **Correção:** adicionei skeleton, mensagem de erro e botão de retry.
- **Motivo:** dá feedback claro para o usuário.

### 5. Ajustes de rota e UI
- **Problema encontrado:** havia classes inválidas, rota index inconsistente e uma dependência de tema sem provider.
- **Correção:** normalizei as rotas, corrigi layout responsivo e removi a dependência de `next-themes` do toaster.
- **Motivo:** evita bugs silenciosos e melhora estabilidade visual.

## Verificações executadas

- `npm run lint`
- `npm run build`
- `php artisan test`

## Observação

- O arquivo `.env` local foi criado apenas para o ambiente de desenvolvimento/teste e permanece ignorado pelo Git.
