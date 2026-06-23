# Relatório de Correções e Melhorias Arquiteturais

## 1. Backend (Laravel & Arquitetura)
### Segurança
* **Vazamento de Credenciais (Hardcoded):**
  * **Problema:** A chave de API do Asaas e a URL base estavam expostas diretamente no `BillingController`.
  * **Correção:** As credenciais foram extraídas para o arquivo `.env` e mapeadas em um arquivo de configuração `config/asaas.php`.
  * **Motivo:** Prevenir o vazamento de chaves sensíveis no versionamento do código (Git) e facilitar a transição entre ambientes (Local/HML/PRD).

* **Insecure Direct Object Reference (Manipulação de Valores):**
  * **Problema:** O backend confiava no valor (`$request->amount`) enviado pelo frontend para realizar a cobrança no gateway.
  * **Correção:** O valor da transação passou a ser resgatado estritamente do banco de dados através do relacionamento com o plano (`$billing->plan->price`).
  * **Motivo:** Impedir que um invasor intercepte a requisição HTTP e altere o valor pago por uma assinatura.

### Arquitetura e Boas Práticas
* **Princípio de Responsabilidade:**
  * **Problema:** O `BillingController` acumulava regras de negócio, formatação de dados e chamadas HTTP diretas.
  * **Correção:** Criação do `AsaasService` para isolar a comunicação externa e do `PaymentRequest` para isolar a validação de dados de entrada.
  * **Motivo:** Manter o Controller enxuto, focado apenas em orquestrar o fluxo e facilitar futuros testes unitários e manutenções no gateway.

* **Transações do Banco:**
  * **Problema:** A criação do cartão, do registro de pagamento e a atualização do status da fatura ocorriam soltas. Em caso de falha no meio do processo, o banco ficaria inconsistente.
  * **Correção:** Toda a lógica de persistência e integração externa foi encapsulada em um `DB::transaction()`.
  * **Motivo:** Garantir a consistência dos dados (Rollback automático) em caso de falhas de comunicação com a API do Asaas ou erros no banco.

* **Otimização de Banco de Dados:**
  * **Problema:** Ocorriam problemas de N+1 e queries redundantes para resgatar a mesma cobrança.
  * **Correção:** Unificação das buscas utilizando *Eager Loading* (`Billing::with(['plan', 'customer'])->find($id)`). Adição do campo `asaas_id` (via *migration*) na tabela `customers` com indexação.
  * **Motivo:** Reduzir o *overhead* do banco de dados e evitar a criação duplicada do mesmo cliente na API do Asaas a cada tentativa de pagamento.

### Testes Unitários
* **Criação de Factorys e Classe de Teste**
  * **Problema:** Dependência exclusiva do frontend para testes com dados diretamente do banco.
  * **Correção:** Automatização das funções de testes (*php artisan test*) com geração de dados aleatórios `faker` em memória.
  * **Motivo:** Possibilita criar massas de testes complexos com dados em memória, mantendo em segurança a integridade do banco de dados.

### Docker
* **Ajuste em postgres_data para versões ^18**
  * **Problema:** Versões mais recentes do PostgreSQL (18+) mudaram a estrutura padrão de diretórios internos para manter compatibilidade com o pg_ctlcluster.
  * **Correção:** Atualizado o diretório para `/var/lib/postgresql` em vez de `/var/lib/postgresql/data`
  * **Motivo:** O contêiner agora espera que o volume seja montado diretamente em /var/lib/postgresql em vez de /var/lib/postgresql/data *Ubuntu 24.04.4 LTS*

---

## 2. Frontend (React & UX/UI)

### Bugs Críticos e Validações
* **Falso Positivo em Tratamento de Erros:**
  * **Problema:** O callback `onError` do `useMutation` no `payment-form.tsx` exibia um *toast* de sucesso, enganando o usuário caso o pagamento falhasse.
  * **Correção:** Implementação do `toast.error` capturando a mensagem de rejeição vinda do backend.
  * **Motivo:** Fornecer um *feedback* preciso sobre o status da transação.

* **Validação Anêmica (Zod):**
  * **Problema:** O *schema* validava apenas se os campos do cartão eram strings, aceitando letras no número do cartão e formatos de data irreais.
  * **Correção:** O `paymentSchema` foi fortalecido com validações via RegEx para o tamanho do cartão (16 dígitos), validação básica no campo Nome, formato de validade (MM/AA) e CVV (3 dígitos).
  * **Motivo:** Barrar dados incorretos na origem, poupando requisições inúteis ao servidor (onde contém validação também) e melhorando a usabilidade (*fail-fast*).

* **Conflito de Estados no Formulário:**
  * **Problema:** Uso paralelo de `useState` e `React Hook Form` para gerenciar os mesmos *inputs*, causando dessincronização.
  * **Correção:** Centralização do estado através do `watch` e `register` do próprio React Hook Form.

### Experiência do Usuário (UX) e Integração
* **Ausência de Reatividade:**
  * **Problema:** Após um pagamento bem-sucedido, a tela continuava exibindo o status "Pendente" até que a página fosse atualizada manualmente.
  * **Correção:** Inserção do `queryClient.invalidateQueries` no callback de sucesso.
  * **Motivo:** Forçar o React Query a refazer o *fetch* em segundo plano, atualizando a *Badge* para "Pago" de forma fluida e automática.

* **Falta de Estados de Carregamento e Telas Estáticas:**
  * **Problema:** A tela `home.tsx` exibia dados fictícios.
  * **Correção:** A tela inicial foi refatorada para consumir uma nova rota de listagem (`GET /api/billings`).
  * **Motivo:** O sistema deve refletir o banco de dados.

* **Configuração de API e Console Warnings:**
  * **Problema:** Requisições Axios com URLs hardcoded.
  * **Correção:** Criação da instância global `api` em `src/lib/axios.ts.
  * **Motivo:** Preparar a aplicação para o ambiente de produção.