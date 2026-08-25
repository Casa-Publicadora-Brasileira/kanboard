<?php

namespace Kanboard\Model;

use Carbon\Carbon;
use Kanboard\Core\Base;
use Kanboard\Enum\ProjectTagEnum;
use Kanboard\Enum\ProductTagEnum;
use Kanboard\Model\TaskModel;
use Kanboard\Model\UserModel;

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
        unset($item);

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
            'team_occupancy'    => $occupancyRate,
        ];
    }
}
