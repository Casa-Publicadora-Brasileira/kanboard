# Especificação Técnica: Relatório Executivo Geral (Overview de Operações)

## 1. Visão Geral
Esta funcionalidade implementa o painel consolidado da Sprint (**Overview de Operações**), voltado para a liderança e gestão executiva. O objetivo é fornecer uma visão rápida da saúde da Sprint atual, identificando o volume global de entregas, o ranking de produtividade e nível de ocupação dos desenvolvedores (identificando sobrecargas ou ociosidades), o acompanhamento dos Product Leaders e o status de todos os produtos agrupados por seus respectivos projetos.

---

## 2. Requisitos e Regras de Negócio

### 2.1 Controle de Acesso e Permissões
- Acesso exclusivo a **Administradores** (`$this->userSession->isAdmin()`).
- Usuários não-administradores que tentarem acessar a rota `/report/overview` serão redirecionados para a Dashboard padrão.

### 2.2 Escopo Temporal e Definição da Sprint
- Cálculo baseado na quinzena do ano (14 dias, iniciando na segunda-feira às 00:00:00 e finalizando no domingo da semana subsequente às 23:59:59).
- Marco de encerramento do planejamento da Sprint: 1ª segunda-feira às 23:59:59.
  - Tarefas criadas ou movidas para o projeto da Sprint (`project_id = 1`) até esse horário são marcadas como **Planejadas/Previstas**.
  - Tarefas que entraram após esse horário são contabilizadas como **Não Planejadas / Interrupções**.

### 2.3 Gestão de Equipe & Capacidade (Desenvolvedores e Product Leaders)
- **Segregação de Papéis:**
  - Grupo `Product Leaders` (ID 4 no banco).
  - Grupo `Desenvolvedores` (ID 2 no banco).
  - **Regra de Exclusividade:** Usuários pertencentes ao grupo *Product Leaders* NÃO devem figurar na listagem de *Desenvolvedores*.
- **Ranking de Desenvolvedores:**
  - Ordenação decrescente por **Total de Pontos (Story Points)** e desempate por **Total de Tarefas**.
  - Métricas por desenvolvedor:
    - Tarefas Entregues (`column_id = 5`) / Total de Tarefas na Sprint.
    - Média de Complexidade por Tarefa (SP / Total de Tarefas).
    - Total de Story Points acumulados na Sprint.
    - **Classificação de Carga de Trabalho:**
      - 💤 **Ocioso / Sem Tarefas:** 0 SP ou 0 tarefas atribuídas na Sprint.
      - ⚖️ **Carga Equilibrada:** 1 a 15 SP atribuídos.
      - 🔥 **Alta Demanda / Sobrecarga:** > 15 SP atribuídos.
    - Hiperlink no nome do desenvolvedor apontando para `/report/user/:user_id`.
- **Painel de Product Leaders:**
  - Lista os membros do grupo *Product Leaders*.
  - Exibe tarefas atribuídas, tarefas concluídas e pontuação total.
  - Hiperlink no nome do líder apontando para `/report/user/:user_id`.

### 2.4 Portfólio de Projetos e Produtos da Sprint
- Mapeamento completo de todos os projetos catalogados em `ProjectTagEnum` e produtos em `ProductTagEnum`.
- Exibição de todos os projetos:
  - Projetos com tarefas ativas na Sprint são destacados com badge `Ativo na Sprint`.
  - Projetos sem tarefas na Sprint são exibidos com estilo atenuado e badge `Sem Movimentação` (exibindo 0 tarefas e 0 SP).
  - Hiperlink no título de cada projeto apontando para `/report/project/:project_id`.
- Para cada projeto, detalhamento de seus produtos associados:
  - Nome do Produto (`ProductTagEnum`).
  - Tarefas Previstas (Planejadas).
  - Tarefas Concluídas / Total de Tarefas.
  - Taxa de Conclusão (% entregue).
  - Total de Story Points do produto.

### 2.5 KPIs Sintéticos Globais (Cabeçalho Executivo)
- **Tarefas Entregues:** Concluídas (`column_id = 5`) / Total da Sprint (% de entrega).
- **Total de Pontos (SP):** Soma total de Story Points em desenvolvimento ou concluídos na Sprint.
- **Taxa de Interrupção:** Quantidade e percentual de tarefas não planejadas inseridas após o início da Sprint.
- **Taxa de Ocupação da Equipe:** Contagem e percentual de desenvolvedores ativos (com tarefas) vs ociosos (ex: 8 de 9 ativos).

---

## 3. Arquitetura de Componentes

### 3.1 Model (`app/Model/OverviewReportModel.php`)
- **Métodos Públicos:**
  - `getOverviewData(): array`: Orquestra e retorna a estrutura consolidada para a view.
- **Métodos Privados de Apoio:**
  - `getCurrentSprintPeriod(): array`: Retorna número da sprint, datas de início/fim e timestamp do fim do planejamento.
  - `fetchAllSprintTasks(): array`: Busca todas as tarefas ativas do projeto `project_id = 1` com responsáveis e tags associadas.
  - `fetchTaskEntryDates(array $taskIds): array`: Consulta `project_activities` para determinar a data de entrada de cada tarefa na Sprint.
  - `fetchTeamMembers(): array`: Recupera membros dos grupos `Desenvolvedores` (ID 2) e `Product Leaders` (ID 4), aplicando a regra de exclusão mútua.
  - `calculateDeveloperStats(array $developers, array $tasks): array`: Calcula ranking, complexidade, pontos e status de carga de cada dev.
  - `calculateProductLeaderStats(array $leaders, array $tasks): array`: Calcula totais para cada líder de produto.
  - `calculatePortfolioStats(array $tasks, int $planningEndTimestamp): array`: Mapeia todos os `ProjectTagEnum` e seus respectivos `ProductTagEnum` cruzando tarefas e métricas.
  - `calculateGlobalKpis(array $tasks, array $developerStats, int $planningEndTimestamp): array`: Consolida os 4 KPIs sintéticos do topo.

### 3.2 Injeção de Dependências (`app/ServiceProvider/ClassProvider.php`)
- Registrar `OverviewReportModel` no container de DI como `$this->overviewReportModel`.

### 3.3 Controller (`app/Controller/ReportController.php`)
- Atualizar método `overview()`:
  - Validar `$this->userSession->isAdmin()`.
  - Obter dados consolidados via `$this->overviewReportModel->getOverviewData()`.
  - Renderizar a view `report/overview` passando os dados organizados.

### 3.4 Rotas (`app/ServiceProvider/RouteProvider.php`)
- `report/overview` -> `ReportController@overview` (já configurada).

### 3.5 View Template (`app/Template/report/overview.php`)
- Interface estilizada com Tailwind CSS v4.
- Uso de `t()` para internacionalização e `$this->text->e()` para sanitização contra XSS.
- Badges visuais de status, links clicáveis e tipografia executiva consistente com as páginas `report/project` e `report/user`.
