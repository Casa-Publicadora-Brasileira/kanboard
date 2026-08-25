# Especificação Técnica: Relatório por Projeto

## 1. Visão Geral
O objetivo desta funcionalidade é fornecer um relatório de desempenho da Sprint atual focado em uma **Tag de Projeto** específica (`ProjectTagEnum`), permitindo que administradores acompanhem métricas consolidadas e o detalhamento de tarefas agrupadas por **Produto** (`ProductTagEnum`).

## 2. Requisitos e Regras de Negócio

### 2.1 Permissões
- Acesso exclusivo a **Administradores** (`$this->userSession->isAdmin()`).
- Usuários não-administradores que tentarem acessar a rota serão redirecionados para a Dashboard.

### 2.2 Escopo Temporal e Dados da Sprint
- O cálculo da Sprint atual é baseado na quinzena do ano (14 dias, iniciando na segunda-feira), alinhado com a lógica existente em `UserReportModel`.
- Apenas tarefas ativas (`is_active = 1`) do projeto de Sprint (`project_id = 1`) vinculadas à Tag de Projeto selecionada (`ProjectTagEnum`) são contabilizadas.
- O fim do planejamento da Sprint é a segunda-feira às 23:59:59 do primeiro dia da quinzena. Tarefas criadas ou movidas para o projeto da Sprint após esse horário são marcadas como **Não Planejadas** (interrupções).

### 2.3 Métricas Sintéticas
- **Tarefas Entregues:** Quantidade de tarefas concluídas (coluna Concluído `column_id = 5`) / Total de tarefas no projeto.
- **Taxa de Interrupção:** Percentual e quantidade de tarefas não planejadas.
- **Taxa de Conclusão:** Percentual de tarefas concluídas.
- **Pontos Totais e Média de Complexidade:** Soma dos pontos (`score`) e média por tarefa.
- **Foco de Atuação:** Contagem de tarefas por categoria:
  - Funcionalidades (`category_id = 3`)
  - Correções/Bugs (`category_id = 1`)
  - Hotfixes (`category_id = 33`)

### 2.4 Visão Analítica (Detalhamento de Tarefas)
- As tarefas do projeto são agrupadas por **Produto** (`ProductTagEnum`).
- Caso a tarefa não possua tag de produto, ela é agrupada sob *"Outros / Geral"*.
- Cada grupo exibe o total de tarefas e soma de pontos.
- Tabela com as colunas:
  1. **Tarefa:** `#ID - Título` com link direto para a página de visualização da tarefa.
  2. **Responsável:** Nome e username do proprietário da tarefa (`owner_id`).
  3. **Planejado:** Badge indicando `Sim` (verde) ou `Não` (laranja).
  4. **Complexidade:** Pontuação em Story Points (`score`).
  5. **Status:** Badge com a coluna atual da tarefa (`Backlog`, `Iniciado`, `Finalizado`, `Homologação`, `Concluído`).

### 2.5 Navegação e Seletor
- O cabeçalho deve exibir o nome do projeto atual e um seletor dinâmico (dropdown ou botões de alternância) listando todos os projetos de `ProjectTagEnum` (`CPB Provas`, `DevOps`, `ACES`, `Gestor de Conteúdo`, `Gestor de Operações`, `Projetos`, `Soluções Educacionais`).
- Rota padrão: `/report/project` (carrega o primeiro projeto disponível) ou `/report/project/:project_tag_id`.

## 3. Arquitetura de Componentes

### 3.1 Model (`app/Model/ProjectReportModel.php`)
- Métodos públicos:
  - `getReportData(int $projectTagId): array`: Retorna todos os dados calculados da sprint, métricas sintéticas e agrupamento analítico por produto.
  - `getAvailableProjects(): array`: Retorna a lista de tags de projetos válidas a partir de `ProjectTagEnum`.
- Métodos privados de suporte:
  - `getCurrentSprintPeriod(): array`
  - `fetchProjectTasks(int $projectTagId): array`
  - `assignTaskPlanningStatus(array $tasks, int $sprintPlanningEndTimestamp): array`
  - `fetchTaskEntryDates(array $taskIds): array`
  - `calculateTaskPoints(array $tasks): array`
  - `calculateCompletionStats(array $tasks): array`
  - `calculateTaskCategories(array $tasks): array`
  - `groupTasksByProduct(array $tasks): array`

### 3.2 Injeção de Dependências (`app/ServiceProvider/ClassProvider.php`)
- Registro de `ProjectReportModel` para ser injetado no container de DI como `$this->projectReportModel`.

### 3.3 Controller (`app/Controller/ReportController.php`)
- Método `project()`:
  - Verifica permissão de administrador.
  - Recupera `project_tag_id` da requisição (ou usa a primeira tag do enum como fallback).
  - Executa `$this->projectReportModel->getReportData($projectTagId)`.
  - Passa os dados e a lista de projetos para a view `report/project`.

### 3.4 Rotas (`app/ServiceProvider/RouteProvider.php`)
- `report/project` -> `ReportController@project`
- `report/project/:project_tag_id` -> `ReportController@project`

### 3.5 Template (`app/Template/report/project.php`)
- Layout construído com Tailwind CSS v4.
- Uso de `t()` para internacionalização e `$this->text->e()` para sanitização contra XSS.
