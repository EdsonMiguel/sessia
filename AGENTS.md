# AGENTS.md — Sessia

> Instruções para agentes de IA (incluindo Codex) que trabalham neste repositório.
> Este arquivo é a referência de contexto do produto e de convenções de implementação. Se houver divergência entre este documento e uma decisão explícita mais recente do responsável pelo projeto, siga a decisão mais recente e proponha a atualização deste arquivo.

## 1. Visão do produto

**Sessia** é um SaaS para gestão de atendimentos de profissionais de estética, com foco inicial em profissionais autônomas que atendem **em domicílio**.

O sistema deve permitir:

- Gerenciar clientes e seus endereços de atendimento.
- Cadastrar procedimentos com duração e preço de referência.
- Configurar preços personalizados de procedimentos por cliente.
- Agendar, confirmar, reagendar, cancelar e concluir atendimentos.
- Controlar atendimentos avulsos, planos recorrentes e pacotes com múltiplos procedimentos.
- Acompanhar sessões contratadas, reservadas, realizadas e disponíveis.
- Gerar cobranças, parcelas, pagamentos e instruções de Pix.
- Operar como SaaS multi-tenant, mantendo os dados de cada negócio isolados.

**Prioridade do produto:** uma experiência simples, rápida e responsiva no celular da profissional. Evitar funcionalidades genéricas que não resolvam fluxos reais de operação.

## 2. Stack e decisões técnicas

- **Backend:** PHP + Laravel.
- **Interface:** Livewire + Blade + Tailwind CSS.
- **Banco de dados:** MySQL, com tabelas InnoDB.
- **ORM:** Eloquent.
- **Testes:** Pest, usando os recursos de teste do Laravel.
- **Desenvolvimento local:** Docker Compose, quando aplicável ao repositório.
- **Arquitetura:** monólito modular, sem microsserviços e sem API REST separada apenas para a interface interna.
- **Multi-tenancy:** banco compartilhado com coluna `tenant_id` nas tabelas de negócio.

Respeitar as versões e dependências efetivamente declaradas em `composer.json`, `package.json` e arquivos de configuração. **Não atualizar versões ou instalar pacotes sem necessidade justificada.**

## 3. Princípios de implementação

1. **Simplicidade primeiro:** utilizar os recursos nativos do Laravel antes de introduzir bibliotecas ou abstrações.
2. **Regras de negócio explícitas:** regras complexas devem ficar em Actions/Services de aplicação e classes de domínio apropriadas, não em controllers, componentes Livewire ou observers com efeitos ocultos.
3. **CRUD sem excesso de camadas:** Eloquent pode ser utilizado diretamente nas operações simples. Não criar Repository, interface ou DTO para cada operação por padrão.
4. **Responsabilidades separadas:** contratação, agendamento, execução do atendimento e cobrança são conceitos distintos.
5. **Consistência:** operações que alteram saldo de sessões, obrigações financeiras ou pagamentos devem usar transações e proteção contra concorrência e duplicidade.
6. **Sem suposições comerciais:** quando faltar uma política de cancelamento, reposição, prazo ou cobrança, identificar a lacuna e propor uma decisão; não embutir uma regra arbitrária silenciosamente.
7. **Evolução incremental:** entregar fluxos verticais pequenos, completos e testados; não criar infraestrutura para um cenário hipotético distante.
8. **Nomenclatura consistente:** preferir nomes técnicos em inglês para classes, tabelas, métodos e variáveis; textos de interface em português (pt-BR).

## 4. Multi-tenancy e segurança

### 4.1. Modelo de contas

- `Tenant` representa um **negócio de estética**, não uma pessoa usuária.
- `User` representa uma identidade que acessa a plataforma.
- A associação entre usuários e negócios deve permitir evolução para múltiplos usuários por tenant (ex.: `tenant_user`), mas o MVP considera **uma profissional por negócio**.
- Os clientes, procedimentos, contratos, agendamentos, atendimentos, cobranças e configurações operacionais pertencem a um tenant.
- Dados centrais da plataforma, como planos comerciais do SaaS, podem existir sem `tenant_id`, quando conceitualmente globais.

### 4.2. Isolamento obrigatório

- Nunca aceitar um `tenant_id` fornecido pelo frontend como autoridade para definir o tenant atual.
- Resolver o tenant no backend a partir da sessão/autenticação e de vínculo autorizado com o usuário.
- Toda leitura, criação, atualização e exclusão de dados de negócio deve estar restrita ao tenant atual.
- Aplicar escopos ou mecanismos centralizados do Eloquent **sem depender exclusivamente deles**: revisar também queries diretas, joins, relacionamentos, validações, exports e relatórios.
- Verificar que entidades associadas pertencem ao **mesmo tenant** (ex.: cliente, procedimento, contrato e agendamento).
- Aplicar Policies/Gates e validações de autorização quando adequado.
- Jobs, comandos agendados, notificações e webhooks devem receber/resolver explicitamente um contexto de tenant confiável e não podem herdar contexto incorreto de uma execução anterior.
- Projetar índices compostos e, quando viável, chaves estrangeiras compostas que reforcem a integridade entre registros do mesmo tenant.
- Manter arquivos, fotografias, caches e dados exportados separados logicamente por tenant.
- Implementar testes que tentem acessar, alterar e vincular registros entre tenants distintos.

**Atenção:** MySQL não oferece o mesmo mecanismo de Row-Level Security do PostgreSQL; o isolamento deve ser garantido pela aplicação, pelo desenho relacional e pelos testes.

## 5. Módulos de negócio

A organização física pode ser ajustada ao código existente. Separar responsabilidades por funcionalidades, sem impor uma arquitetura complexa artificialmente.

| Módulo | Responsabilidade |
| --- | --- |
| `Platform` | Tenants, configuração da plataforma e, futuramente, assinatura comercial do SaaS |
| `Identity` | Usuários, autenticação, vínculos de tenant e autorização |
| `Customer` | Clientes e endereços de atendimento |
| `Catalog` | Procedimentos e preços de referência/personalizados |
| `Contract` | Contratação avulsa, plano recorrente e pacote de sessões |
| `Scheduling` | Agenda, recorrências de horários, bloqueios e deslocamentos |
| `Attendance` | Execução efetiva de procedimentos e consumo de sessões |
| `Billing` | Valores a receber, parcelas, pagamentos e Pix das clientes |
| `Integration` | Integrações externas, se e quando necessárias |

**Não confundir `Billing` (recebimentos da profissional) com `PlatformBilling` (assinaturas cobradas pela empresa Sessia).** São fluxos, receitas e destinatários de pagamento distintos.

## 6. Regras de negócio consolidadas

### 6.1. Procedimentos e preços

- Um `Procedure` pertence a um tenant e possui, no mínimo, nome, duração e preço **padrão**.
- O preço de um mesmo procedimento pode variar entre clientes.
- Uma cliente pode ter um preço habitual personalizado para um procedimento (`CustomerProcedurePrice`).
- Ao montar uma contratação, sugerir primeiro o preço personalizado da cliente (se existir) e, na ausência dele, o preço padrão.
- A profissional pode negociar um preço diferente em **cada contratação**, sem alterar automaticamente o preço habitual.
- O item contratado deve guardar um **snapshot do preço negociado**. Alterações posteriores no catálogo não podem modificar retroativamente contratos e cobranças existentes.
- O valor total negociado de um pacote ou de uma mensalidade pode ser diferente da soma dos preços de referência das sessões que o compõem.
- Usar `decimal`/`DECIMAL` para valores financeiros; jamais usar ponto flutuante para cálculos monetários.

### 6.2. Formas de contratação

A aplicação deve distinguir:

1. **Avulso:** atendimento ou conjunto pontual de serviços, cobrado conforme o acordo.
2. **Plano recorrente:** contratação com quantidade fixa de sessões por competência mensal, normalmente com frequência semanal e pagamento mensal. Pode ter data de término ou prazo indeterminado.
3. **Pacote:** quantidade determinada de sessões, possivelmente de **procedimentos diferentes**, com cobrança normalmente liberada ao concluir a execução do pacote; o pagamento final pode ser à vista ou parcelado conforme negociação.

Uma abstração comum de contrato (ex.: `ServiceContract`) é aceitável, mas as regras próprias de planos e pacotes devem continuar claras. Não forçar heranças ou tabelas genéricas excessivas sem benefício concreto.

### 6.3. Planos recorrentes

- Diferenciar **recorrência do agendamento**, **competência de sessões** e **recorrência financeira**.
- O plano possui um número **fixo de sessões por mês**, mesmo quando determinado dia da semana ocorre cinco vezes no mês.
- Exemplo: quatro sessões de drenagem por mês, todas as terças, com mensalidade definida. A quinta terça do mês **não cria uma quinta sessão gratuita**.
- Modelar a competência mensal (ex.: `PlanPeriod`) de forma independente das cobranças e dos agendamentos.
- A mensalidade pode estar paga com sessões ainda não realizadas; sessões podem ter sido realizadas com mensalidade ainda pendente.
- Gerar competências e agendamentos futuros com horizonte limitado e execução **idempotente**; não criar ocorrências infinitas.
- Planos podem ser pausados ou encerrados com preservação do histórico.
- Políticas de faltas, reposições, transferência de saldo, suspensão de mensalidade e vencimento de sessão **ainda precisam ser definidas**. Não assumir transferência automática nem consumo automático por falta.

### 6.4. Pacotes

- Um pacote inclui um ou mais itens, cada qual apontando para um procedimento e uma quantidade contratada.
- Permitir vários tipos de procedimento no mesmo pacote.
- **Agendar não consome sessão.** O consumo ocorre apenas ao registrar a execução efetiva, conforme a regra aplicável.
- A reserva de um horário pode reservar saldo operacionalmente para impedir agendamento acima da quantidade disponível.
- A conclusão da execução acontece quando todas as sessões obrigatórias forem realizadas, ressalvadas regras futuras de encerramento antecipado.
- A conclusão operacional **não implica quitação financeira**.
- A cobrança final deve respeitar o preço total acordado e as condições de vencimento/parcelamento.
- Correções e estornos de execução precisam restaurar saldos de modo controlado e auditável, sem exclusões silenciosas.
- Desistência, troca de procedimento, prazo de validade e ajustes financeiros ainda precisam de políticas comerciais explícitas.

### 6.5. Agenda domiciliar

- Um `Appointment` representa a **visita/compromisso** e pode conter mais de um procedimento (`AppointmentItem`).
- Um agendamento não é o mesmo que uma execução (`ServiceExecution`).
- Considerar endereço, duração dos procedimentos, preparação e deslocamento entre clientes ao avaliar conflitos.
- Preservar os dados relevantes do local de atendimento como snapshot quando necessário, para não modificar o histórico ao editar o cadastro do endereço.
- Prever estados como agendado, confirmado, realizado, cancelado e falta.
- Reagendamentos devem preservar histórico de alterações, sem consumir sessões automaticamente.
- Manter registro de quem cancelou e do motivo, quando informado.
- Considerar a agenda de **uma profissional por tenant** no MVP; não desenvolver gestão avançada de equipes agora.
- Preferir salvar instantes em UTC e converter para o fuso configurado do tenant na interface e nas regras de calendário; cálculos de competência e recorrência devem respeitar esse fuso.

### 6.6. Financeiro e Pix

- Separar **obrigação a receber** (`Receivable`), **parcelas** (`Installment`), **pagamentos** (`Payment`) e alocação dos pagamentos às parcelas (`PaymentAllocation`), conforme a necessidade real do fluxo.
- Pagamentos parciais, pagamentos em mais de uma parcela e quitações devem manter valores consistentes.
- Um atendimento concluído não significa pagamento realizado.
- Uma cobrança vencida não deve automaticamente cancelar agendamentos sem política explícita.
- Para o MVP, permitir gerar informações de **Pix estático/Copia e Cola**, quando tecnicamente suportado, e registrar a baixa **manual** do recebimento.
- **Chave Pix não é gerada a cada cobrança**: ela pertence à conta de recebimento da profissional. QR Code dinâmico, identificação automática de pagamento e conciliação dependem de integração com provedor bancário/de pagamentos.
- Cada tenant configura seus próprios dados de recebimento; a plataforma não deve centralizar o dinheiro das profissionais por padrão.
- Integrações futuras devem receber eventos de webhook de forma segura, validada e **idempotente**, sem duplicar pagamento.
- Não alterar retroativamente parcelas pagas; utilizar ajustes/estornos auditáveis quando necessário.

## 7. Estrutura de código (diretriz, não obrigação de reescrever tudo)

Organização sugerida:

```text
app/
  Models/
  Modules/
    Customer/
    Catalog/
    Contract/
    Scheduling/
    Attendance/
    Billing/
    Platform/
    Identity/
  Http/
  Policies/
  Jobs/
resources/
  views/
database/
  migrations/
  factories/
  seeders/
tests/
  Feature/
  Unit/
```

Dentro dos módulos, usar `Actions`, `Services`, `DTOs` ou classes de domínio **somente quando houver uma responsabilidade clara**. Para CRUD simples, usar Request/validação + Eloquent + componente Livewire ou controller, conforme o padrão já adotado no projeto.

### Convenções

- Seguir PSR-12 e o estilo oficial do Laravel; formatar com Laravel Pint, quando instalado.
- Usar migrations para toda alteração estrutural; não depender de mudanças manuais no banco.
- Validar entradas no servidor e autorizar cada operação.
- Evitar N+1 com eager loading quando adequado, sem carregar relacionamentos desnecessários.
- Não colocar segredo, token, chave de API ou credencial no repositório; documentar variáveis no `.env.example`.
- Não adicionar dependências ou integrações externas sem explicar o ganho e a necessidade.
- Textos e mensagens para usuárias finais devem ser claros, acessíveis e em pt-BR.
- Interfaces devem ser responsivas, priorizando uso por celular.

## 8. Testes obrigatórios para fluxos críticos

Usar Pest e testes de integração com o banco para cobrir, no mínimo:

1. Tenant A não acessa, altera ou relaciona dados do tenant B.
2. Um procedimento pode ter preço padrão, preço personalizado e preço negociado por contrato.
3. Alterar preço de catálogo **não altera contrato anterior**.
4. Plano de quatro sessões mensais não concede quinta sessão automática em mês com cinco ocorrências do dia habitual.
5. Gerar competências e cobranças duas vezes não cria registros duplicados.
6. Agendar sessão de pacote não consome saldo; concluir execução consome exatamente uma vez.
7. Duas tentativas concorrentes não podem consumir saldo indisponível.
8. Um pacote com múltiplos procedimentos acompanha os saldos por item corretamente.
9. Encerrar a execução de um pacote não marca pagamento como recebido.
10. Parcelamentos preservam o total devido e pagamentos parciais atualizam saldo corretamente.
11. Webhook de pagamento repetido, quando houver integração, não duplica recebimentos.
12. Alterações e cancelamentos relevantes preservam o histórico necessário.

Criar factories/fixtures com dados fictícios. Nunca usar informações reais de clientes em testes ou seeders públicos.

## 9. Privacidade e dados sensíveis

- Tratar informações pessoais de clientes como dados protegidos; coletar apenas o necessário.
- Dados de anamnese, condições de saúde e fotografias de acompanhamento exigem cuidados adicionais de segurança, controle de acesso, retenção e fundamento legal adequado.
- Não registrar senhas, chaves Pix sensíveis de integração, tokens, documentos pessoais completos ou dados de saúde nos logs.
- Preferir operações auditáveis para exclusão, correção financeira e alteração contratual.
- Planejar exportação e exclusão/anonimização de dados conforme obrigações legais e necessidades operacionais, sem apagar automaticamente histórico financeiro obrigatório.

## 10. Fluxo de trabalho esperado do agente

Antes de implementar:

1. Inspecionar estrutura, dependências e padrões já existentes no repositório.
2. Identificar o módulo, a regra de negócio e o tenant envolvidos.
3. Propor a menor mudança que resolva o problema sem introduzir abstrações desnecessárias.
4. Apontar explicitamente políticas comerciais não definidas; quando a implementação depender delas, pedir decisão antes de fixar comportamento irreversível.

Ao implementar:

1. Fazer migrations, modelos e regras de domínio de maneira consistente.
2. Incluir validação, autorização e isolamento por tenant em todos os caminhos relevantes.
3. Proteger alterações financeiras e de saldo com transação e mecanismos adequados contra duplicidade/concorrência.
4. Criar ou atualizar testes que demonstrem o comportamento e suas bordas.
5. Manter alterações focadas; evitar refatorações paralelas sem relação com a tarefa.

Ao concluir:

1. Resumir o que foi alterado e por quê.
2. Informar quais testes foram executados e o resultado; não afirmar que testes passaram se não foram executados.
3. Informar migrações, variáveis de ambiente ou passos de execução necessários.
4. Declarar limitações e decisões de negócio ainda pendentes.

### Comandos usuais (validar no repositório)

```bash
composer install
php artisan migrate
php artisan test
./vendor/bin/pest
./vendor/bin/pint --test
npm install
npm run build
```

**Segurança operacional:** nunca rodar `migrate:fresh`, `db:wipe`, `db:seed` com efeitos destrutivos, limpeza de volumes ou ações equivalentes em ambiente não descartável sem autorização explícita. Nunca alterar `.env` de produção ou executar operações externas de cobrança real sem consentimento.

## 11. Fora de escopo do MVP

Evitar implementar sem pedido expresso:

- Microsserviços, mensageria distribuída ou infraestrutura de múltiplos bancos por tenant.
- Gestão avançada de equipes, comissões e escala de múltiplos profissionais.
- Aplicativo móvel nativo.
- Gateway financeiro com repasse/split automático entre contas.
- Motor complexo de descontos, cupons e campanhas.
- Portal completo para clientes finais.
- Cobrança comercial automatizada das assinaturas do Sessia antes de validar o produto.
- Roteirização automática avançada e estoque completo.

## 12. Questões de negócio ainda em aberto

Não apresentar as seguintes decisões como concluídas:

- Política de faltas, cancelamento e reposição de sessões.
- Transferência ou vencimento de sessões não realizadas no plano mensal.
- Cobrança e saldo em caso de pausa ou cancelamento do plano no meio do mês.
- Prazos de validade e encerramento antecipado de pacotes.
- Regras de substituição de procedimentos dentro de um pacote.
- Momento exato de geração e vencimento da cobrança final do pacote.
- Regras de reajuste de mensalidades e de contratos em andamento.
- Provedor Pix e automação de confirmação de pagamentos.
- Modelo comercial e limites dos futuros planos de assinatura do SaaS.

Ao trabalhar nesses pontos, sugerir alternativas com efeitos claros sobre o domínio, o financeiro e a experiência da usuária, antes de cristalizar uma política no código.
