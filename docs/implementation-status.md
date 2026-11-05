# Sessia — implementação do MVP v0.1

Atualizado em 04/11/2026. As cinco etapas possuem implementação funcional integrada:
fundação, cadastros, agenda, contratações e financeiro. Não há indicadores simulados
ou integrações bancárias fictícias.

## Funcionalidades operacionais

### Fundação e cadastros

- Cadastro, login, sessão renovada, limitação de tentativas e logout por POST/CSRF.
- Criação transacional de usuário, negócio e vínculo de proprietária.
- Contexto de negócio validado contra a associação autenticada em cada acesso.
- Clientes com telefone, e-mail opcional, observações, busca, filtros, paginação,
  detalhes, edição, desativação e reativação.
- Vários endereços por cliente, principal, edição, remoção lógica e Google Maps.
- Procedimentos com descrição, duração, preço padrão e situação ativa/inativa.
- Preço habitual por cliente/procedimento, sem alterar o catálogo. A sugestão dá
  prioridade ao personalizado, inclusive zero, e depois ao padrão.
- Detalhe da cliente com contratos, saldos, últimas competências, agenda e financeiro.

### Agenda e execução

- Visualizações diária, semanal e mensal, por data, com compromissos e bloqueios.
- Uma visita pode conter vários procedimentos, ligados ou não a contratos.
- Horários em UTC, apresentados no fuso IANA do negócio.
- Preparação antes e deslocamento após o serviço compõem o intervalo ocupado.
- Conflitos são verificados sob bloqueio transacional, incluindo bloqueios de agenda.
- Endereço, nomes e durações preservados como snapshots.
- Confirmação, reagendamento, cancelamento, falta, conclusão e estorno de execução.
- Histórico registra ator, data, motivo informado e dados anteriores às alterações.
- Cancelamento e falta liberam reservas sem consumir sessões.
- Conclusão cria execuções separadas, consome uma sessão por item e é idempotente.
- Estorno de execução exige motivo, restaura saldos e cancela a visita original;
  outro atendimento deve usar um novo agendamento. Cobranças existentes são preservadas.

Um compromisso sem vínculo de contrato não cria cobrança automaticamente. Quando
houver valor a receber, registre a contratação avulsa e vincule suas sessões na agenda.

### Contratações

- Avulso, pacote e plano mensal, com cliente, quantidades e preços negociados.
- Itens guardam nome, duração e preço negociado, independentes de alterações futuras.
- O total acordado pode diferir da soma dos preços por sessão.
- Pacotes com procedimentos diferentes e saldos independentes por item.
- Reservadas, realizadas e disponíveis são mostradas separadamente. Disponível para
  novo agendamento é contratado menos realizado e reservado.
- Um pacote concluído operacionalmente continua separado de sua situação financeira.
- Planos com sessões fixas por competência, dia semanal, horário, mensalidade,
  vencimento, início, fim opcional, pausa e encerramento.
- Competências mensais com snapshots de quantidade, valor e vencimento.
- Um plano de quatro sessões não gera uma quinta sessão em mês com cinco ocorrências.
- Competência, agenda e mensalidade possuem ações independentes.
- Geração repetível sem duplicação; o comando trabalha com horizonte de 1 a 3 meses,
  sendo dois meses o padrão. A tela oferece o mês atual e o seguinte.
- Conflitos são apresentados para revisão, sem deslocar horários automaticamente.
- Ocorrências canceladas não são recriadas. Horários já reagendados são preservados.
- Início/fim no meio do mês exige confirmação explícita do valor e quantidades da
  competência parcial; a rotina automática não presume proporcionalidade.

### Financeiro e Pix

- Obrigações a receber, parcelas, vencimentos, pagamentos e alocações separados.
- Cobrança de avulso liberada por ação explícita, com os vencimentos acordados.
- Cobrança final de pacote liberada após conclusão operacional, mediante ação da
  profissional, à vista ou parcelada e com datas escolhidas na tela.
- Mensalidade vinculada ao plano e à competência; recebimento e execução independentes.
- Parcelamento preserva o total em centavos; sobras ficam nas primeiras parcelas.
- Baixa manual, parcial ou integral; um pagamento pode alcançar várias parcelas.
- Validação de saldo, cliente e tenant; proteção contra duplicidade e sobrepagamento.
- Situações pendente, parcial, paga e atrasada, com identificação de parcial vencida.
- Estorno integral de pagamento com motivo e ator; valores e parcelas originais
  não são reescritos, e o histórico das alocações permanece.
- Cada negócio configura sua chave Pix, recebedor e cidade. A cobrança apresenta
  a chave e permite copiá-la; somente a confirmação manual registra recebimento.
- Dashboard real: compromissos do dia/semana, próximos atendimentos, recebido no mês,
  saldo a receber e parcelas vencidas.

Pix Copia e Cola no padrão EMV e QR Code estático não foram implementados. O pedido
condiciona esses recursos a uma implementação tecnicamente válida e testada; esta
entrega utiliza a apresentação/cópia da chave e baixa manual. Não há integração
bancária, webhook, conciliação ou assinatura comercial do SaaS.

## Decisões técnicas e segurança

- Laravel 13.40.0, Livewire 4.4.8, Tailwind 4, Pest 4.7.8 e plugin Laravel 4.1.0.
  O ambiente validado usa PHP 8.5.12. Nenhuma dependência nova nas etapas finais.
- A instalação do Pest na fundação exigiu PHPUnit 12.5.33, dentro da faixa original
  `^12.5.12`; Laravel e demais dependências não foram atualizados por esta entrega.
- Monólito; formulários Livewire/Blade e regras críticas em serviços por módulo.
- `TenantRecord`: escopo obrigatório, tenant atribuído pelo backend e proteção de
  saves/deletes de instâncias estrangeiras. Não se aceita tenant do formulário.
- Policies e verificações de proprietário complementam os escopos. Identificadores
  dos registros editados são propriedades Livewire bloqueadas.
- Chaves estrangeiras compostas reforçam associações dentro do tenant. Services
  também verificam cliente, procedimento, contrato e competência correspondentes.
- `TenantTransaction` bloqueia primeiro a linha do negócio. Como há uma profissional
  por tenant, isso serializa conflitos, reservas, consumo e operações financeiras,
  incluindo concorrência em intervalos antes vazios. Todas as leituras de saldo
  das escritas críticas ocorrem dentro da transação após o bloqueio.
- Valores individuais são DECIMAL(12,2), com entrada decimal em strings. Operações
  usam centavos inteiros e formatação sem float; agregados podem superar o limite
  de um lançamento individual. Não há juros, multas ou reajustes implícitos.
- Unicidade no banco para competência, ocorrência recorrente, cobrança de origem,
  execução por item de visita e identificador idempotente de pagamento.
- `CurrentTenant::forJob` fornece contexto explícito exclusivamente no console,
  sem autenticação de usuário. O comando enumera tenants do banco, executa cada
  contexto e o restaura em `finally`, inclusive quando há falha. Não depende da sessão.
- Endereços removidos usam soft delete; clientes/procedimentos são desativados.
  Contratos e execuções financeiras não têm caminhos de exclusão silenciosa.
- O endereço principal é trocado sob transação e bloqueio da cliente. Se removido
  ou desmarcado, outro principal não é escolhido automaticamente.
- Queries diretas/massivas exigem restrição explícita; elas não executam eventos
  de instância. Os caminhos implementados foram revisados e exercitados nos testes.

## Migrations

1. Migrations originais: usuários/sessões, cache e jobs, preservadas.
2. `2026_11_04_000001_create_tenants_table.php`: tenants e tenant_user.
3. `2026_11_04_000002_create_customer_and_catalog_tables.php`: clientes, endereços,
   procedimentos e preços personalizados, com índices e FKs compostas.
4. `2026_11_04_000003_create_operations_tables.php`: dados Pix em tenants;
   service_contracts, contract_items, contract_events, plan_periods,
   plan_period_items, appointments, appointment_items, appointment_events,
   schedule_blocks, service_executions, receivables, installments, payments e
   payment_allocations, com índices e integridade por tenant.

Nenhuma migration foi aplicada ao banco existente da aplicação. A validação ocorreu
somente em SQLite em memória e em MySQL descartável. O `.env` existente foi preservado.

## Instalação e atualização

1. Execute `composer install` e `composer check-platform-reqs`.
2. Se não houver `.env`, copie `.env.example` sem sobrescrever outro ambiente.
   Execute `php artisan key:generate` somente se APP_KEY ainda estiver vazia.
3. No ambiente local, configure APP_NAME=Sessia, APP_LOCALE=pt_BR,
   APP_FALLBACK_LOCALE=pt_BR, APP_FAKER_LOCALE=pt_BR e DB_CONNECTION=mysql,
   com host, porta, banco, usuário e senha corretos.
4. O Compose opcional usa MySQL 8.4. Defina DB_DATABASE, DB_USERNAME,
   DB_PASSWORD e MYSQL_ROOT_PASSWORD locais; execute `docker compose up -d mysql`.
   A porta é vinculada ao loopback; os dados persistem no volume mysql_data.
5. No banco correto, execute `php artisan migrate`.
6. Execute `npm ci --ignore-scripts`, `npm run build` e `php artisan serve`.
7. Abra `/cadastro` e crie sua conta. Não há usuário padrão ou seeder de contas.
8. Configure o negócio, clientes, endereços e procedimentos antes dos demais fluxos.

Para atualizar uma instalação anterior: `php artisan migrate` e `npm run build`.
Não são necessárias novas variáveis de ambiente para os módulos finais.

Em desenvolvimento, `npm run dev` pode substituir o build durante as alterações.
Em produção, sirva public com HTTPS, APP_DEBUG=false, cookies seguros e credenciais
próprias. O Compose incluído atende desenvolvimento, não provisionamento de produção.

### Geração programada dos planos

- Execução manual: `php artisan sessia:generate-plans --months=2`.
- Desenvolvimento: `php artisan schedule:work`.
- Produção: execute `php artisan schedule:run` a cada minuto no agendador do servidor.
- A tarefa está registrada diariamente, com `withoutOverlapping`, em UTC.
  Competências e recorrências são calculadas no fuso de cada negócio.
- O comando respeita planos ativos e seus limites. Mensalidade não depende de a
  agenda estar livre. Revise os avisos de conflitos e competências parciais.
- Preparação/deslocamento das ocorrências geradas começam em zero; ajuste os
  horários conforme os trajetos reais. Não há estimativa automática de deslocamento.

A rotina cria as competências e mensalidades previstas nos planos cadastrados;
revise os acordos e os avisos de geração como parte da operação.

## Validação

A suíte cobre cadastro e isolamento; telas e formulários; snapshots; conflitos,
bloqueios e fuso; pacotes mistos; reserva e consumo único; rollback integral;
planos fixos em mês com cinco ocorrências; mensalidade paga sem execução e execução
sem pagamento; geração idempotente; contexto programado entre tenants; competências
parciais; total de parcelas; pagamentos parciais; rejeição de excesso/duplicidade;
estornos; Pix sem confirmação automática; indicadores reais; acesso e vínculos cruzados.

- SQLite em memória: 108 testes passaram, 456 assertions; três casos de concorrência
  real são explicitamente pulados por exigirem MySQL.
- MySQL 8.4 descartável: 111 testes passaram, 468 assertions, incluindo os três
  cenários com processos e conexões concorrentes.
- Os testes concorrentes usam processos PHP e conexões independentes. Verificam
  consumo repetido da mesma visita, disputa pelo último saldo e sobrepagamento.
- Pint, build do Vite, validação do Composer, rotas e agenda de comandos verificados.
- O plugin de fontes emite aviso sobre otimização opcional de fallback, sem impedir
  o build; nenhuma dependência extra foi instalada apenas para esse aviso.

A suíte usa RefreshDatabase. Para testar em MySQL, forneça variáveis de processo
DB_CONNECTION=mysql, DB_HOST, DB_PORT, DB_DATABASE=sessia_test, DB_USERNAME,
DB_PASSWORD e DB_URL vazio, apontando exclusivamente para um banco descartável,
e execute `php artisan test`. Os testes de concorrência fazem commit de fixtures
fictícias e recriam o schema descartável entre casos. Nunca use um banco operacional.

### Limites da verificação

Testes HTTP e Livewire exercitam persistência, renderização e ações completas.
Não foi concluída revisão visual ou teste ponta a ponta em navegador: o host de
acesso ao navegador está indisponível. A revisão responsiva em dispositivos reais
permanece pendente. Não se afirma homologação visual ou validação de integração bancária.

A listagem financeira e os indicadores carregam parcelas/alocações do tenant para
calcular saldos exatos. Essa escolha é adequada ao MVP; para grandes históricos,
será necessário agregar e paginar no banco mantendo as mesmas regras e isolamento.

## Políticas ainda abertas e comportamento conservador

- Faltas/cancelamentos não consomem sessões nem geram taxas automaticamente.
- Não há transferência, vencimento automático ou reposição automática de sessões.
  Uma competência não pode ser usada para reservar em outro mês.
- Pausa/encerramento interrompe novas gerações. Registros, horários e obrigações
  existentes são preservados para revisão; não há cancelamento ou proporcionalidade
  financeira automática. Planos encerrados não são reabertos; registre outro acordo.
- Mês parcial depende de quantidade e valor confirmados pela profissional.
- Pacotes não recebem validade ou encerramento antecipado arbitrário. A cobrança
  final exige conclusão e liberação explícita com os vencimentos acordados.
- Não há substituição automática de procedimentos, reajuste contratual, multas,
  descontos ou ajustes automáticos de obrigações existentes.
- Estorno de execução não estorna pagamento e não apaga cobrança. Estorno de
  recebimento preserva parcelas e alocações, reabrindo o saldo correspondente.
- Políticas comerciais gerais continuam abertas conforme AGENTS.md. As escolhas
  pontuais confirmadas na tela não definem uma política geral de negócio.

Recuperação de senha, verificação de e-mail, QR/Pix EMV, integrações bancárias,
assinaturas comerciais do SaaS, portal de clientes e equipe avançada não foram
adicionados. Os últimos itens permanecem fora do escopo solicitado para este MVP,
exceto QR/EMV, que foi condicionado à validação técnica e não está disponível.
