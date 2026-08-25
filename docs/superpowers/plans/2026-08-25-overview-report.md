# Overview Report (Relatório Geral de Operações) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar o painel executivo consolidado da Sprint (`/report/overview`), fornecendo à liderança visão dos KPIs globais, ranking e status de ocupação dos desenvolvedores (identificação de sobrecargas ou ociosidades), acompanhamento dos Product Leaders e status de todos os produtos agrupados por projetos.

**Architecture:** O `OverviewReportModel` orquestra agregações em lote no banco (`tasks`, `task_has_tags`, `group_has_users`, `groups`, `project_activities`), cruza as tags dos Enums `ProjectTagEnum` e `ProductTagEnum`, calcula o status de carga dos desenvolvedores (excluindo Product Leaders de sua listagem) e envia os dados estruturados para o `ReportController::overview`, que renderiza o template responsivo `report/overview.php` estilizado com Tailwind CSS v4.

**Tech Stack:** PHP 8.2+, Kanboard MVC, MariaDB, Carbon, Tailwind CSS v4.

**Spec:** [`docs/superpowers/specs/2026-08-25-overview-report-design.md`](file:///Users/jonathas.assuncao/Documents/CPB/ambiente-portal/EC2/kanboard/docs/superpowers/specs/2026-08-25-overview-report-design.md)

## Global Constraints
- Acesso estritamente restrito a Administradores (`$this->userSession->isAdmin()`). Usuários não-administradores são redirecionados para a Dashboard.
- Membros do grupo `Product Leaders` (ID 4) são estritamente excluídos da listagem e ranking de `Desenvolvedores` (ID 2).
- Todos os projetos catalogados em `ProjectTagEnum` e produtos em `ProductTagEnum` devem ser mapeados no portfólio, mesmo com 0 tarefas.
- Strings na interface devem utilizar `$this->text->e(...)` para sanitização XSS e `t(...)` para internacionalização.
- Tailwind CSS v4 gerado via `npm run tw:build`.

---

### Task 1: OverviewReportModel Creation & DI Registration

**Files:**
- Create: `app/Model/OverviewReportModel.php`
- Modify: `app/ServiceProvider/ClassProvider.php:40-60`
- Test: `tests/units/Model/OverviewReportModelTest.php`

**Interfaces:**
- Produces:
  - `OverviewReportModel::getOverviewData(): array`
    - Retorna: `['sprint', 'period', 'kpis', 'developers', 'product_leaders', 'projects']`

- [ ] **Step 1: Write the unit test for OverviewReportModel**

```php
<?php

require_once __DIR__.'/../BaseTestCase.php';

use Kanboard\Model\OverviewReportModel;
use Kanboard\Model\TaskModel;
use Kanboard\Model\GroupModel;
use Kanboard\Model\GroupMemberModel;

class OverviewReportModelTest extends BaseTestCase
{
    public function testGetOverviewDataStructure()
    {
        $overviewReportModel = new OverviewReportModel($this->container);
        $data = $overviewReportModel->getOverviewData();

        $this->assertArrayHasKey('sprint', $data);
        $this->assertArrayHasKey('period', $data);
        $this->assertArrayHasKey('kpis', $data);
        $this->assertArrayHasKey('developers', $data);
        $this->assertArrayHasKey('product_leaders', $data);
        $this->assertArrayHasKey('projects', $data);

        $this->assertArrayHasKey('total_tasks', $data['kpis']);
        $this->assertArrayHasKey('concluded_tasks', $data['kpis']);
        $this->assertArrayHasKey('total_points', $data['kpis']);
        $this->assertArrayHasKey('interruption_rate', $data['kpis']);
        $this->assertArrayHasKey('team_occupancy', $data['kpis']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/units/Model/OverviewReportModelTest.php`
Expected: FAIL (Class `Kanboard\Model\OverviewReportModel` not found)

- [ ] **Step 3: Implement OverviewReportModel and register in ClassProvider**

Crie `app/Model/OverviewReportModel.php`:
```php
<?php

namespace Kanboard\Model;

use Carbon\Carbon;
use Kanboard\Core\Base;
use Kanboard\Enum\ProjectTagEnum;
use Kanboard\Enum\ProductTagEnum;

/**
 * Overview Report Model
 *
 * @package Kanboard\Model
 */
class OverviewReportModel extends Base
{
    const GROUP_DEVELOPERS = 2;
    const GROUP_PRODUCT_LEADERS = 4;

    /**
     * Get consolidated sprint overview data
     *
     * @access public
     * @return array
     */
    public function getOverviewData(): array
    {
        $sprintData = $this->getCurrentSprintPeriod();
        $tasks = $this->fetchAllSprintTasks();
        $tasks = $this->assignTaskPlanningStatus($tasks, $sprintData['monday_end']);

        [$developers, $productLeaders] = $this->fetchTeamMembers();

        $developerStats = $this->calculateDeveloperStats($developers, $tasks);
        $leaderStats = $this->calculateProductLeaderStats($productLeaders, $tasks);
        $projectStats = $this->calculatePortfolioStats($tasks);
        $kpis = $this->calculateGlobalKpis($tasks, $developerStats, $sprintData['monday_end']);

        return [
            'sprint'          => $sprintData['sprint'],
            'period'          => $sprintData['label'],
            'kpis'            => $kpis,
            'developers'      => $developerStats,
            'product_leaders' => $leaderStats,
            'projects'        => $projectStats,
        ];
    }

    /**
     * Calculate current sprint fortnight period
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
            'monday_end' => $startDate->copy()->endOfDay()->timestamp,
        ];
    }

    /**
     * Fetch active sprint tasks
     *
     * @access private
     * @return array
     */
    private function fetchAllSprintTasks(): array
    {
        return $this->db->table(TaskModel::TABLE)
            ->columns(
                TaskModel::TABLE . '.*',
                'ua.name AS owner_name',
                'ua.username AS owner_username'
            )
            ->left(UserModel::TABLE, 'ua', 'id', TaskModel::TABLE, 'owner_id')
            ->eq(TaskModel::TABLE . '.project_id', 1)
            ->eq(TaskModel::TABLE . '.is_active', 1)
            ->desc(TaskModel::TABLE . '.column_id')
            ->desc(TaskModel::TABLE . '.score')
            ->findAll();
    }

    /**
     * Determine planning status for tasks
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
            $entryDate = $entryDates[$task['id']] ?? (int) $task['date_creation'];
            $task['is_planned'] = $entryDate <= $sprintPlanningEndTimestamp;
            return $task;
        }, $tasks);
    }

    /**
     * Fetch task entry dates from activities
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
     * Fetch team members with mutual exclusion rule
     *
     * @access private
     * @return array [$developers, $productLeaders]
     */
    private function fetchTeamMembers(): array
    {
        $members = $this->db->table('users')
            ->join('group_has_users', 'user_id', 'id')
            ->in('group_has_users.group_id', [self::GROUP_DEVELOPERS, self::GROUP_PRODUCT_LEADERS])
            ->columns('users.id', 'users.username', 'users.name', 'group_has_users.group_id')
            ->asc('users.name')
            ->findAll();

        $productLeaderUserIds = [];
        $productLeaders = [];
        foreach ($members as $m) {
            if ((int) $m['group_id'] === self::GROUP_PRODUCT_LEADERS) {
                $productLeaderUserIds[] = (int) $m['id'];
                $productLeaders[$m['id']] = [
                    'id'       => (int) $m['id'],
                    'name'     => !empty($m['name']) ? $m['name'] : $m['username'],
                    'username' => $m['username'],
                ];
            }
        }

        $developers = [];
        foreach ($members as $m) {
            $userId = (int) $m['id'];
            if ((int) $m['group_id'] === self::GROUP_DEVELOPERS && !in_array($userId, $productLeaderUserIds)) {
                $developers[$userId] = [
                    'id'       => $userId,
                    'name'     => !empty($m['name']) ? $m['name'] : $m['username'],
                    'username' => $m['username'],
                ];
            }
        }

        return [array_values($developers), array_values($productLeaders)];
    }

    /**
     * Calculate developer ranking, points, complexity and workload status
     *
     * @access private
     * @param  array $developers
     * @param  array $tasks
     * @return array
     */
    private function calculateDeveloperStats(array $developers, array $tasks): array
    {
        $devTasks = [];
        foreach ($tasks as $task) {
            if (!empty($task['owner_id'])) {
                $devTasks[$task['owner_id']][] = $task;
            }
        }

        $results = [];
        foreach ($developers as $dev) {
            $uTasks = $devTasks[$dev['id']] ?? [];
            $totalTasks = count($uTasks);
            $concludedTasks = count(array_filter($uTasks, fn($t) => (int) $t['column_id'] === 5));
            $totalPoints = (float) array_sum(array_column($uTasks, 'score'));
            $avgComplexity = $totalTasks > 0 ? round($totalPoints / $totalTasks, 1) : 0.0;

            if ($totalTasks === 0 || $totalPoints == 0) {
                $workloadStatus = 'idle';
            } elseif ($totalPoints > 15) {
                $workloadStatus = 'heavy';
            } else {
                $workloadStatus = 'balanced';
            }

            $results[] = [
                'id'              => $dev['id'],
                'name'            => $dev['name'],
                'username'        => $dev['username'],
                'total_tasks'     => $totalTasks,
                'concluded_tasks' => $concludedTasks,
                'total_points'    => $totalPoints,
                'avg_complexity'  => $avgComplexity,
                'workload_status' => $workloadStatus,
            ];
        }

        usort($results, function ($a, $b) {
            if ($b['total_points'] !== $a['total_points']) {
                return $b['total_points'] <=> $a['total_points'];
            }
            return $b['total_tasks'] <=> $a['total_tasks'];
        });

        foreach ($results as $index => &$item) {
            $item['rank'] = $index + 1;
        }

        return $results;
    }

    /**
     * Calculate product leaders stats
     *
     * @access private
     * @param  array $productLeaders
     * @param  array $tasks
     * @return array
     */
    private function calculateProductLeaderStats(array $productLeaders, array $tasks): array
    {
        $leaderTasks = [];
        foreach ($tasks as $task) {
            if (!empty($task['owner_id'])) {
                $leaderTasks[$task['owner_id']][] = $task;
            }
        }

        $results = [];
        foreach ($productLeaders as $leader) {
            $uTasks = $leaderTasks[$leader['id']] ?? [];
            $totalTasks = count($uTasks);
            $concludedTasks = count(array_filter($uTasks, fn($t) => (int) $t['column_id'] === 5));
            $totalPoints = (float) array_sum(array_column($uTasks, 'score'));

            $results[] = [
                'id'              => $leader['id'],
                'name'            => $leader['name'],
                'username'        => $leader['username'],
                'total_tasks'     => $totalTasks,
                'concluded_tasks' => $concludedTasks,
                'total_points'    => $totalPoints,
            ];
        }

        usort($results, fn($a, $b) => $b['total_points'] <=> $a['total_points']);

        return $results;
    }

    /**
     * Calculate portfolio statistics for all projects and products
     *
     * @access private
     * @param  array $tasks
     * @return array
     */
    private function calculatePortfolioStats(array $tasks): array
    {
        $taskIds = array_column($tasks, 'id');
        $tags = !empty($taskIds) ? $this->db->table('task_has_tags')->in('task_id', $taskIds)->findAll() : [];

        $taskProjectTags = [];
        $taskProductTags = [];

        foreach ($tags as $t) {
            $tId = $t['task_id'];
            $tagId = (int) $t['tag_id'];

            $projEnum = ProjectTagEnum::tryFrom($tagId);
            if ($projEnum) {
                $taskProjectTags[$tId] = $projEnum->value;
            }

            $prodEnum = ProductTagEnum::tryFrom($tagId);
            if ($prodEnum) {
                $taskProductTags[$tId][] = $prodEnum->value;
            }
        }

        // Group tasks by project and product
        $projectTaskMap = [];
        foreach ($tasks as $task) {
            $tId = $task['id'];
            $projId = $taskProjectTags[$tId] ?? 0;
            $prodIds = $taskProductTags[$tId] ?? [0];

            foreach ($prodIds as $prodId) {
                $projectTaskMap[$projId][$prodId][] = $task;
            }
        }

        $allProjects = ProjectTagEnum::cases();
        $allProducts = ProductTagEnum::cases();

        $projectResults = [];
        foreach ($allProjects as $projCase) {
            $projId = $projCase->value;
            $projName = $projCase->label();

            $productsData = [];
            $projTotalTasks = 0;
            $projConcludedTasks = 0;
            $projTotalPoints = 0.0;

            foreach ($allProducts as $prodCase) {
                $prodId = $prodCase->value;
                $prodName = $prodCase->label();

                $pTasks = $projectTaskMap[$projId][$prodId] ?? [];
                $totalPTasks = count($pTasks);
                $plannedPTasks = count(array_filter($pTasks, fn($t) => $t['is_planned']));
                $concludedPTasks = count(array_filter($pTasks, fn($t) => (int) $t['column_id'] === 5));
                $pointsPTasks = (float) array_sum(array_column($pTasks, 'score'));
                $completionRate = $totalPTasks > 0 ? round(($concludedPTasks / $totalPTasks) * 100) : 0;

                $projTotalTasks += $totalPTasks;
                $projConcludedTasks += $concludedPTasks;
                $projTotalPoints += $pointsPTasks;

                $productsData[] = [
                    'id'              => $prodId,
                    'name'            => $prodName,
                    'planned_tasks'   => $plannedPTasks,
                    'total_tasks'     => $totalPTasks,
                    'concluded_tasks' => $concludedPTasks,
                    'completion_rate' => $completionRate,
                    'points'          => $pointsPTasks,
                    'has_activity'    => $totalPTasks > 0,
                ];
            }

            // Also check for non-specified products (prodId = 0)
            if (!empty($projectTaskMap[$projId][0])) {
                $pTasks = $projectTaskMap[$projId][0];
                $totalPTasks = count($pTasks);
                $plannedPTasks = count(array_filter($pTasks, fn($t) => $t['is_planned']));
                $concludedPTasks = count(array_filter($pTasks, fn($t) => (int) $t['column_id'] === 5));
                $pointsPTasks = (float) array_sum(array_column($pTasks, 'score'));
                $completionRate = $totalPTasks > 0 ? round(($concludedPTasks / $totalPTasks) * 100) : 0;

                $projTotalTasks += $totalPTasks;
                $projConcludedTasks += $concludedPTasks;
                $projTotalPoints += $pointsPTasks;

                $productsData[] = [
                    'id'              => 0,
                    'name'            => 'Outros / Geral',
                    'planned_tasks'   => $plannedPTasks,
                    'total_tasks'     => $totalPTasks,
                    'concluded_tasks' => $concludedPTasks,
                    'completion_rate' => $completionRate,
                    'points'          => $pointsPTasks,
                    'has_activity'    => true,
                ];
            }

            $projectResults[] = [
                'id'              => $projId,
                'name'            => $projName,
                'has_activity'    => $projTotalTasks > 0,
                'total_tasks'     => $projTotalTasks,
                'concluded_tasks' => $projConcludedTasks,
                'total_points'    => $projTotalPoints,
                'products'        => $productsData,
            ];
        }

        return $projectResults;
    }

    /**
     * Calculate global synthetic KPIs
     *
     * @access private
     * @param  array $tasks
     * @param  array $developerStats
     * @param  int $planningEndTimestamp
     * @return array
     */
    private function calculateGlobalKpis(array $tasks, array $developerStats, int $planningEndTimestamp): array
    {
        $totalTasks = count($tasks);
        $concludedTasks = count(array_filter($tasks, fn($t) => (int) $t['column_id'] === 5));
        $totalPoints = (float) array_sum(array_column($tasks, 'score'));
        $unexpectedCount = count(array_filter($tasks, fn($t) => !$t['is_planned']));
        $interruptionRate = $totalTasks > 0 ? round(($unexpectedCount / $totalTasks) * 100) : 0;
        $completionRate = $totalTasks > 0 ? round(($concludedTasks / $totalTasks) * 100) : 0;

        $totalDevs = count($developerStats);
        $activeDevs = count(array_filter($developerStats, fn($d) => $d['workload_status'] !== 'idle'));
        $occupancyRate = $totalDevs > 0 ? round(($activeDevs / $totalDevs) * 100) : 0;

        return [
            'total_tasks'       => $totalTasks,
            'concluded_tasks'   => $concludedTasks,
            'completion_rate'   => $completionRate,
            'total_points'      => $totalPoints,
            'unexpected_tasks'  => $unexpectedCount,
            'interruption_rate' => $interruptionRate,
            'total_devs'        => $totalDevs,
            'active_devs'       => $activeDevs,
            'occupancy_rate'    => $occupancyRate,
        ];
    }
}
```

Registre em `app/ServiceProvider/ClassProvider.php`:
```php
'OverviewReportModel',
```

- [ ] **Step 4: Run unit test to verify it passes**

Run: `php vendor/bin/phpunit tests/units/Model/OverviewReportModelTest.php`
Expected: PASS

- [ ] **Step 5: Commit changes**

```bash
git add app/Model/OverviewReportModel.php app/ServiceProvider/ClassProvider.php tests/units/Model/OverviewReportModelTest.php
git commit -m "feat(report): add OverviewReportModel with sprint aggregations and DI registration"
```

---

### Task 2: ReportController Overview Action Integration

**Files:**
- Modify: `app/Controller/ReportController.php:19-30`

**Interfaces:**
- Consumes: `OverviewReportModel::getOverviewData(): array`
- Produces: HTML render of `report/overview` layout

- [ ] **Step 1: Update ReportController::overview method**

Modifique `app/Controller/ReportController.php`:
```php
    public function overview()
    {
        if (! $this->userSession->isAdmin()) {
            return $this->response->redirect($this->helper->url->to('DashboardController', 'show'));
        }

        $overviewData = $this->overviewReportModel->getOverviewData();

        $this->response->html($this->helper->layout->pageLayout('report/overview', array_merge(array(
            'no_layout' => true,
            'title'     => t('Visão Geral de Operações (Portfólio)'),
        ), $overviewData)));
    }
```

- [ ] **Step 2: Test syntax**

Run: `php -l app/Controller/ReportController.php`
Expected: No syntax errors detected

- [ ] **Step 3: Commit changes**

```bash
git add app/Controller/ReportController.php
git commit -m "feat(report): update ReportController overview action with model integration"
```

---

### Task 3: Executive Overview View Template (`report/overview.php`)

**Files:**
- Modify: `app/Template/report/overview.php`

**Interfaces:**
- Consumes: `$sprint`, `$period`, `$kpis`, `$developers`, `$product_leaders`, `$projects`

- [ ] **Step 1: Implement full executive overview view template**

Substitua `app/Template/report/overview.php` pelo layout responsivo completo com Tailwind CSS v4:
- Header com Sprint e período.
- 4 Cards de KPIs executivos (Tarefas Concluídas, Total de Pontos, Taxa de Interrupção, Ocupação da Equipe).
- Bloco de Capacidade & Gestão da Equipe (Tabela com ranking de devs + badges de carga e Tabela de Product Leaders com links).
- Bloco de Portfólio (Cards de projetos com badge Ativo/Inativo e tabela de produtos com Previstas, Concluídas, % e SP).

- [ ] **Step 2: Test template syntax**

Run: `php -l app/Template/report/overview.php`
Expected: No syntax errors detected

- [ ] **Step 3: Commit changes**

```bash
git add app/Template/report/overview.php
git commit -m "feat(report): implement executive overview view template with Tailwind CSS"
```

---

### Task 4: Asset Build, Graphify Update & Full Validation

**Files:**
- Output: `assets/css/tailwind.min.css`
- Output: `graphify-out/`

- [ ] **Step 1: Build Tailwind CSS**

Run: `npm run tw:build`
Expected: CSS compiled successfully.

- [ ] **Step 2: Update Graphify Knowledge Graph**

Run: `graphify update .`
Expected: Graph updated.

- [ ] **Step 3: Final Commit**

```bash
git add assets/css/tailwind.min.css
git commit -m "build(css): compile Tailwind CSS for overview report"
```
