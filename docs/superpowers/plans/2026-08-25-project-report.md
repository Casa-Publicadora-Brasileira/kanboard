# Relatório por Projeto - Plano de Execução

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar a funcionalidade completa do Relatório de Desempenho por Projeto (baseado nas tags de projeto `ProjectTagEnum`), exibindo indicadores sintéticos da Sprint atual e listagem analítica de tarefas agrupadas por Produto (`ProductTagEnum`), com acesso restrito a administradores.

**Architecture:** Padrão MVC nativo do Kanboard. A camada de modelo `ProjectReportModel` consulta o banco via PicoDb filtrando tarefas da Sprint ativa (`project_id = 1`) pela tag do projeto, calcula métricas de planejamento e agrupa tarefas por produto. O `ReportController` gerencia autenticação/autorização administrativa e injeta os dados na view `report/project` estilizada com Tailwind CSS v4.

**Tech Stack:** PHP 8.x, Kanboard MVC (PicoDb), Carbon (datas/sprint), Tailwind CSS v4.

**Spec:** `docs/superpowers/specs/2026-08-25-project-report-design.md`

## Global Constraints

- Namespace de Models: `Kanboard\Model`
- Namespace de Controllers: `Kanboard\Controller`
- Estilização: Tailwind CSS v4 compilado para `assets/css/tailwind.min.css`
- Internacionalização: Funções `t(...)` em todas as strings da UI
- Segurança: Sanitização com `$this->text->e(...)` em saídas dinâmicas

---

### Task 1: Criar o Model `ProjectReportModel`

**Files:**

- Create: `app/Model/ProjectReportModel.php`

**Interfaces:**

- Produces:
  - `ProjectReportModel::getAvailableProjects(): array`
  - `ProjectReportModel::getReportData(int $projectTagId): array`

- [ ] **Passo 1: Criar o arquivo `app/Model/ProjectReportModel.php`**

```php
<?php

namespace Kanboard\Model;

use Carbon\Carbon;
use Kanboard\Core\Base;
use Kanboard\Enum\ProjectTagEnum;
use Kanboard\Enum\ProductTagEnum;

/**
 * Project Report Model
 *
 * @package  Kanboard\Model
 */
class ProjectReportModel extends Base
{
    /**
     * Get list of all available project tags
     *
     * @access public
     * @return array
     */
    public function getAvailableProjects(): array
    {
        $projects = [];
        foreach (ProjectTagEnum::cases() as $case) {
            $projects[] = [
                'id'    => $case->value,
                'name'  => $case->label(),
            ];
        }
        return $projects;
    }

    /**
     * Get report data for a specific project tag
     *
     * @access public
     * @param  int $projectTagId
     * @return array
     */
    public function getReportData(int $projectTagId): array
    {
        $projectEnum = ProjectTagEnum::tryFrom($projectTagId);
        $projectName = $projectEnum ? $projectEnum->label() : t('Desconhecido');

        $sprintData = $this->getCurrentSprintPeriod();
        $tasks = $this->fetchProjectTasks($projectTagId);

        $tasks = $this->assignTaskPlanningStatus($tasks, $sprintData['monday_end']);
        $unexpectedCount = count(array_filter($tasks, fn($task) => !$task['is_planned']));

        $totalTasks = count($tasks);
        [$totalPoints, $averagePoints] = $this->calculateTaskPoints($tasks);
        [$finishedTasks, $concludedTasks, $completionRate] = $this->calculateCompletionStats($tasks);
        [$features, $bugs, $hotfixes] = $this->calculateTaskCategories($tasks);

        return [
            'project_id'   => $projectTagId,
            'project_name' => $projectName,
            'sprint'       => $sprintData['sprint'],
            'period'       => $sprintData['label'],
            'tasks'        => [
                'concluded' => $finishedTasks,
                'total'     => $totalTasks,
                'points'    => $totalPoints,
                'avg'       => $averagePoints,
                'features'  => $features,
                'bugs'      => $bugs,
                'hotfixes'  => $hotfixes
            ],
            'unexpected'   => [
                'percent' => $totalTasks > 0 ? round($unexpectedCount / $totalTasks, 2) : 0,
                'tasks'   => $unexpectedCount
            ],
            'concluded'    => [
                'percent' => $completionRate,
                'tasks'   => $concludedTasks,
                'total'   => $totalTasks
            ],
            'products'     => $this->groupTasksByProduct($tasks)
        ];
    }

    /**
     * Calculate the current Sprint period based on the fortnight of the year.
     *
     * @access private
     * @return array
     */
    private function getCurrentSprintPeriod(): array
    {
        Carbon::setLocale('pt_BR');
        $date = Carbon::now();

        $sprintNumber = (int) ceil($date->isoWeek / 2);

        $startDate = $date->copy()->isoWeek(($sprintNumber * 2) - 1)->startOfWeek()->addDay();
        $endDate = $date->copy()->isoWeek($sprintNumber * 2)->endOfWeek()->addDay();

        return [
            'sprint'     => $sprintNumber,
            'start'      => $startDate,
            'end'        => $endDate,
            'label'      => $startDate->translatedFormat("d \de M \a ") . $endDate->translatedFormat("d \de M, Y"),
            'monday_end' => $startDate->copy()->endOfDay()->timestamp
        ];
    }

    /**
     * Fetch active tasks for the given project tag in the Sprint project (ID 1).
     *
     * @access private
     * @param  int $projectTagId
     * @return array
     */
    private function fetchProjectTasks(int $projectTagId): array
    {
        return $this->db->table(TaskModel::TABLE)
            ->columns(
                TaskModel::TABLE.'.*',
                UserModel::TABLE.'.name AS owner_name',
                UserModel::TABLE.'.username AS owner_username'
            )
            ->join('task_has_tags', 'task_id', 'id')
            ->left(UserModel::TABLE, 'id', 'owner_id')
            ->eq(TaskModel::TABLE.'.project_id', 1)
            ->eq('task_has_tags.tag_id', $projectTagId)
            ->eq(TaskModel::TABLE.'.is_active', 1)
            ->desc(TaskModel::TABLE.'.score')
            ->desc(TaskModel::TABLE.'.column_id')
            ->findAll();
    }

    /**
     * Determine if each task is planned or an unexpected interruption.
     *
     * @access private
     * @param  array $tasks
     * @param  int $sprintPlanningEndTimestamp
     * @return array
     */
    private function assignTaskPlanningStatus(array $tasks, int $sprintPlanningEndTimestamp): array
    {
        $taskIds = array_column($tasks, 'id');
        $entryDates = $this->fetchTaskEntryDates($taskIds);

        return array_map(function ($task) use ($entryDates, $sprintPlanningEndTimestamp) {
            $entryDate = $entryDates[$task['id']] ?? (int)$task['date_creation'];
            $task['is_planned'] = $entryDate <= $sprintPlanningEndTimestamp;
            return $task;
        }, $tasks);
    }

    /**
     * Get the timestamp of when a task was first created or most recently moved into the sprint project.
     *
     * @access private
     * @param  array $taskIds
     * @return array
     */
    private function fetchTaskEntryDates(array $taskIds): array
    {
        if (empty($taskIds)) {
            return [];
        }

        $activities = $this->db->table('project_activities')
            ->in('task_id', $taskIds)
            ->in('event_name', ['task.move.project', 'task.create'])
            ->eq('project_id', 1)
            ->desc('date_creation')
            ->findAll();

        $taskActivities = [];
        foreach ($activities as $activity) {
            $taskActivities[$activity['task_id']][] = $activity;
        }

        $entryDates = [];
        foreach ($taskActivities as $taskId => $events) {
            $moveEvents = array_filter($events, fn($e) => $e['event_name'] === 'task.move.project');

            if (!empty($moveEvents)) {
                $entryDates[$taskId] = (int) max(array_column($moveEvents, 'date_creation'));
            } else {
                $entryDates[$taskId] = (int) min(array_column($events, 'date_creation'));
            }
        }

        return $entryDates;
    }

    /**
     * Calculate total and average points.
     *
     * @access private
     * @param  array $tasks
     * @return array [totalPoints, averagePoints]
     */
    private function calculateTaskPoints(array $tasks): array
    {
        $totalTasks = count($tasks);
        $totalPoints = 0;

        foreach ($tasks as $task) {
            $totalPoints += (float) $task['score'];
        }

        $averagePoints = $totalTasks > 0 ? round($totalPoints / $totalTasks, 1) : 0;

        return [$totalPoints, $averagePoints];
    }

    /**
     * Calculate completion statistics.
     *
     * @access private
     * @param  array $tasks
     * @return array [finishedTasks, concludedTasks, completionRate]
     */
    private function calculateCompletionStats(array $tasks): array
    {
        $totalTasks = count($tasks);

        $finishedTasks = count(array_filter($tasks, fn($task) => in_array((int)$task['column_id'], [3, 4, 5])));
        $concludedTasks = count(array_filter($tasks, fn($task) => (int)$task['column_id'] === 5));

        $completionRate = $totalTasks > 0 ? round($concludedTasks / $totalTasks, 2) : 0;

        return [$finishedTasks, $concludedTasks, $completionRate];
    }

    /**
     * Count tasks by category.
     *
     * @access private
     * @param  array $tasks
     * @return array [features, bugs, hotfixes]
     */
    private function calculateTaskCategories(array $tasks): array
    {
        $features = count(array_filter($tasks, fn($task) => (int)$task['category_id'] === 3));
        $bugs = count(array_filter($tasks, fn($task) => (int)$task['category_id'] === 1));
        $hotfixes = count(array_filter($tasks, fn($task) => (int)$task['category_id'] === 33));

        return [$features, $bugs, $hotfixes];
    }

    /**
     * Group tasks by product tag.
     *
     * @access private
     * @param  array $tasks
     * @return array
     */
    private function groupTasksByProduct(array $tasks): array
    {
        if (empty($tasks)) {
            return [];
        }

        $taskIds = array_column($tasks, 'id');
        $tags = $this->db->table('task_has_tags')->in('task_id', $taskIds)->findAll();

        $taskProducts = [];
        foreach ($tags as $tag) {
            $taskId = $tag['task_id'];
            $tagId = (int)$tag['tag_id'];

            $productEnum = ProductTagEnum::tryFrom($tagId);
            if ($productEnum) {
                $taskProducts[$taskId][] = $productEnum->label();
            }
        }

        $groupedTasks = [];
        foreach ($tasks as $task) {
            $taskId = $task['id'];
            $products = $taskProducts[$taskId] ?? [];
            $productKey = !empty($products) ? implode(' / ', $products) : 'Outros / Geral';
            $groupedTasks[$productKey][] = $task;
        }

        $productGroups = [];
        foreach ($groupedTasks as $productName => $items) {
            $productTotalTasks = count($items);
            [$productTotalPoints] = $this->calculateTaskPoints($items);
            [, $productConcludedTasks] = $this->calculateCompletionStats($items);

            $productGroups[] = [
                'title'  => $productName,
                'tasks'  => "{$productConcludedTasks}/{$productTotalTasks}",
                'points' => $productTotalPoints,
                'items'  => array_map(function ($task) {
                    return [
                        'number'         => $task['id'],
                        'title'          => $task['title'],
                        'owner_name'     => !empty($task['owner_name']) ? $task['owner_name'] : ($task['owner_username'] ?? t('Não atribuído')),
                        'owner_username' => $task['owner_username'] ?? '',
                        'planned'        => $task['is_planned'] ?? true,
                        'point'          => $task['score'] ?: 0,
                        'status'         => (int) $task['column_id'],
                    ];
                }, $items)
            ];
        }

        return $productGroups;
    }
}
```

- [ ] **Passo 2: Validar sintaxe PHP**

Executar: `php -l app/Model/ProjectReportModel.php`
Esperado: No syntax errors detected in app/Model/ProjectReportModel.php

---

### Task 2: Registrar `ProjectReportModel` em `ClassProvider`

**Files:**

- Modify: `app/ServiceProvider/ClassProvider.php`

- [ ] **Passo 1: Adicionar `'ProjectReportModel'` na lista de models em `app/ServiceProvider/ClassProvider.php`**

```php
            'ProjectReportModel',
```

- [ ] **Passo 2: Validar sintaxe PHP**

Executar: `php -l app/ServiceProvider/ClassProvider.php`
Esperado: No syntax errors detected in app/ServiceProvider/ClassProvider.php

---

### Task 3: Configurar Rotas e Atualizar `ReportController`

**Files:**

- Modify: `app/ServiceProvider/RouteProvider.php`
- Modify: `app/Controller/ReportController.php`

- [ ] **Passo 1: Garantir rotas em `app/ServiceProvider/RouteProvider.php`**

```php
$container['route']->addRoute('report/project', 'ReportController', 'project');
$container['route']->addRoute('report/project/:project_tag_id', 'ReportController', 'project');
```

- [ ] **Passo 2: Atualizar método `project()` em `app/Controller/ReportController.php`**

```php
    /**
     * Report by Project (Tag)
     * Admin only
     *
     * @access public
     */
    public function project()
    {
        if (! $this->userSession->isAdmin()) {
            return $this->response->redirect($this->helper->url->to('DashboardController', 'show'));
        }

        $availableProjects = $this->projectReportModel->getAvailableProjects();
        $defaultTagId = !empty($availableProjects) ? $availableProjects[0]['id'] : 233;

        $projectTagId = $this->request->getIntegerParam('project_tag_id', $defaultTagId);

        $report_data = $this->projectReportModel->getReportData($projectTagId);

        $this->response->html($this->helper->layout->pageLayout('report/project', array_merge(array(
            'no_layout'          => true,
            'title'              => t('Project Report'),
            'available_projects' => $availableProjects,
            'selected_project'   => $projectTagId,
        ), $report_data)));
    }
```

- [ ] **Passo 3: Validar sintaxe PHP**

Executar: `php -l app/Controller/ReportController.php` e `php -l app/ServiceProvider/RouteProvider.php`
Esperado: No syntax errors detected

---

### Task 4: Implementar o Template `app/Template/report/project.php`

**Files:**

- Modify: `app/Template/report/project.php`

- [ ] **Passo 1: Escrever o template completo com Tailwind CSS v4**

```php
<div class="bg-white p-6 rounded-lg shadow-md max-w-7xl mx-auto">

    <!-- Cabeçalho -->
    <div class="border-b-2 border-primary-500 pb-3 mb-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <h2 class="text-2xl font-bold text-slate-800 tracking-tight">
                <?= t('Desempenho do Projeto') ?>: <span class="text-primary-600"><?= $this->text->e($project_name) ?></span>
            </h2>
            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-700 bg-slate-50 border rounded-full border-slate-200 px-3 py-1 shadow-sm">
                <span class="inline-block w-2 h-2 rounded-full bg-primary-500"></span>
                <span>Sprint <?= $sprint ?></span>
                <span class="text-primary-300">•</span>
                <span class="text-primary-500 font-normal"><?= $period ?></span>
            </span>
        </div>

        <!-- Seletor de Projetos -->
        <div class="flex items-center gap-2 mt-4 flex-wrap">
            <span class="text-xs font-semibold text-slate-500 uppercase"><?= t('Projetos') ?>:</span>
            <?php foreach ($available_projects as $proj): ?>
                <a href="<?= $this->url->href('ReportController', 'project', array('project_tag_id' => $proj['id'])) ?>"
                   class="text-xs px-3 py-1 rounded-full border transition <?= $selected_project == $proj['id'] ? 'bg-primary-600 text-white border-primary-600 font-bold shadow-xs' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100 hover:border-slate-300' ?>">
                    <?= $this->text->e($proj['name']) ?>
                </a>
            <?php endforeach ?>
        </div>
    </div>

    <!-- Indicadores Sintéticos -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 my-4">

        <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 flex flex-col">
            <span class="text-xs font-semibold text-primary-500 uppercase tracking-wider"><?= t('Tarefas Entregues') ?></span>
            <div class="flex items-baseline gap-1 mt-1">
                <span class="text-2xl font-semibold text-primary-800"><?= $tasks['concluded'] ?></span>
                <span class="text-lg font-normal text-primary-500">/<?= $tasks['total'] ?></span>
            </div>
        </div>

        <div class="bg-slate-50 border-slate-200 <?= $unexpected['tasks'] > 0 ? 'active' : '' ?> active:bg-orange-50/50 border active:border-orange-200 p-3.5 rounded-lg">
            <span class="block text-xs font-semibold text-primary-500 group-active:text-orange-800 uppercase tracking-wider mb-1">
                <?= t('Taxa de Interrupção') ?>
            </span>
            <div class="flex items-center gap-2">
                <span class="text-2xl font-semibold text-primary-700 group-active:text-orange-700"><?= $unexpected['percent'] * 100 ?>%</span>
                <span class="inline-flex items-center text-xs font-normal text-primary-800 border-primary-200 bg-primary-50 group-active:text-orange-700 group-active:bg-orange-100 px-2 py-0.5 rounded-full border group-active:border-orange-200">
                    <?= $unexpected['tasks'] ?> <?= t('não planejada(s)') ?>
                </span>
            </div>
        </div>

        <div class="bg-slate-50 border-slate-200 <?= $concluded['total'] > 0 && $concluded['total'] === $concluded['tasks'] ? 'active' : '' ?> active:bg-emerald-50/50 border active:border-emerald-200 rounded-lg p-4 flex flex-col">
            <span class="text-xs font-semibold text-primary-500 group-active:text-emerald-700 uppercase"><?= t('Concluídas') ?></span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-2xl font-bold group-active:text-emerald-800 text-primary-800"><?= $concluded['percent'] * 100 ?>%</span>
                <span class="text-xs group-active:text-emerald-600 text-primary-800 font-normal"><?= $concluded['tasks'] ?> <?= t('de') ?> <?= $concluded['total'] ?></span>
            </div>
        </div>

        <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 flex flex-col">
            <span class="text-xs font-semibold text-primary-500 uppercase tracking-wider"><?= t('Total de Pontos (SP)') ?></span>
            <div class="flex items-baseline gap-1 mt-1">
                <span class="text-2xl font-bold text-slate-800"><?= $tasks['points'] ?></span>
                <span class="text-xs font-normal text-primary-500">(média <?= $tasks['avg'] ?>/task)</span>
            </div>
        </div>
    </div>

    <div class="bg-slate-50 border border-slate-200 p-3 rounded-lg mb-8">
        <span class="block text-xs font-semibold text-primary-500 uppercase tracking-wider mb-2">
            <?= t('Foco de Atuação') ?>
        </span>
        <div class="flex items-center gap-2 flex-wrap">
            <?php if ($tasks['hotfixes'] > 0): ?>
                <span class="inline-flex items-center gap-1 text-xs font-normal text-red-700 bg-red-50 border border-red-200 px-2.5 py-1 rounded-full">
                    <span><?= $tasks['hotfixes'] ?></span> Hotfix(s)
                </span>
            <?php endif ?>
            <span class="inline-flex items-center gap-1 text-xs font-normal text-primary-700 bg-primary-50 border border-primary-200 px-2.5 py-1 rounded-full">
                <span><?= $tasks['features'] ?></span> Funcionalidade(s)
            </span>
            <span class="inline-flex items-center gap-1 text-xs font-normal text-orange-700 bg-orange-50 border border-orange-200 px-2.5 py-1 rounded-full">
                <span><?= $tasks['bugs'] ?></span> Correção(ões)
            </span>
        </div>
    </div>

    <!-- Visão Analítica Agrupada por Produto -->
    <h3 class="text-base font-bold text-slate-800 mb-2"><?= t('Detalhamento por Produto') ?></h3>

    <?php if (empty($products)): ?>
        <div class="p-8 text-center bg-slate-50 border border-slate-200 rounded-lg text-slate-500 text-sm">
            <?= t('Nenhuma tarefa encontrada para este projeto na Sprint atual.') ?>
        </div>
    <?php else: ?>
        <?php foreach ($products as $prodGroup): ?>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between bg-gray-50 border border-gray-200 p-3 rounded-t-lg mt-6">
                <div class="flex items-center gap-2">
                    <h4 class="text-sm font-bold text-primary-800 uppercase tracking-wide m-0"><?= $this->text->e($prodGroup['title']) ?></h4>
                </div>
                <div class="flex items-center gap-2 mt-2 sm:mt-0 text-xs">
                    <span class="bg-white border border-primary-200 px-2.5 py-0.5 rounded-full text-primary-600 font-medium">
                        <strong class="text-primary-800"><?= $prodGroup['tasks'] ?></strong> <?= t('Tarefa(s)') ?>
                    </span>
                    <span class="bg-primary-50 border border-primary-200 text-primary-800 px-2.5 py-0.5 rounded-full font-semibold">
                        <?= $prodGroup['points'] ?> <?= t('Ponto(s)') ?>
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto mb-6">
                <table class="w-full table-fixed border-collapse text-xs">
                    <thead>
                        <tr class="text-primary-700 text-left">
                            <th class="w-5/12 p-2 border bg-primary-50! border-primary-100!"><?= t('Tarefa') ?></th>
                            <th class="w-2/12 p-2 border bg-primary-50! border-primary-100!"><?= t('Responsável') ?></th>
                            <th class="w-2/12 p-2 border text-center! bg-primary-50! border-primary-100!"><?= t('Planejado') ?></th>
                            <th class="w-1/12 p-2 border text-center! bg-primary-50! border-primary-100!"><?= t('Complexidade') ?></th>
                            <th class="w-2/12 p-2 border text-center! bg-primary-50! border-primary-100!"><?= t('Status') ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <?php foreach ($prodGroup['items'] as $task): ?>
                            <tr>
                                <td class="p-2 border border-slate-200 truncate">
                                    <a href="<?= $this->url->href('TaskViewController', 'show', array('task_id' => $task['number'])) ?>"
                                       class="text-primary-900 truncate hover:underline"
                                       title="<?= $this->text->e($task['title']) ?>">
                                        <strong>#<?= $task['number'] ?></strong> - <?= $this->text->e($task['title']) ?>
                                    </a>
                                </td>
                                <td class="p-2 border border-slate-200 truncate text-slate-700">
                                    <?= $this->text->e($task['owner_name']) ?>
                                </td>
                                <td class="p-2 border text-center border-slate-200">
                                    <?php if ($task['planned']): ?>
                                        <span class="bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded font-bold"><?= t('Sim') ?></span>
                                    <?php else: ?>
                                        <span class="bg-orange-100 text-orange-800 px-2 py-0.5 rounded font-bold"><?= t('Não') ?></span>
                                    <?php endif ?>
                                </td>
                                <td class="p-2 border text-center border-slate-200"><?= $task['point'] ?> <?= t('Ponto(s)') ?></td>
                                <td class="p-2 border text-center border-slate-200">
                                    <?php if ($task['status'] === 5): ?>
                                        <span class="bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded font-bold"><?= t('Concluído') ?></span>
                                    <?php elseif ($task['status'] === 4): ?>
                                        <span class="bg-orange-100 text-orange-800 px-2 py-0.5 rounded font-bold"><?= t('Homologação') ?></span>
                                    <?php elseif ($task['status'] === 3): ?>
                                        <span class="bg-blue-100 text-blue-800 px-2 py-0.5 rounded font-bold"><?= t('Finalizado') ?></span>
                                    <?php elseif ($task['status'] === 2): ?>
                                        <span class="bg-primary-100 text-primary-800 px-2 py-0.5 rounded font-bold"><?= t('Iniciado') ?></span>
                                    <?php elseif ($task['status'] === 1): ?>
                                        <span class="bg-gray-100 text-gray-800 px-2 py-0.5 rounded font-bold"><?= t('Backlog') ?></span>
                                    <?php endif ?>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach ?>
    <?php endif ?>
</div>
```

- [ ] **Passo 2: Validar sintaxe PHP**

Executar: `php -l app/Template/report/project.php`
Esperado: No syntax errors detected

---

### Task 5: Compilar CSS do Tailwind e Verificação Final

**Files:**

- Output: `assets/css/tailwind.min.css`

- [ ] **Passo 1: Compilar assets com Tailwind CSS v4**

Executar: `npm run tw:build`
Esperado: Build concluído com sucesso sem erros.

- [ ] **Passo 2: Verificação de Lint e Inicialização do Container**

Executar: `php -l app/Model/ProjectReportModel.php && php -l app/Controller/ReportController.php && php -l app/Template/report/project.php`
Esperado: Todos os arquivos sem erros de sintaxe.
