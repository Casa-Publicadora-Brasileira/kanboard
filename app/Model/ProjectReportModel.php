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
            ->join(UserModel::TABLE, 'id', 'owner_id', TaskModel::TABLE)
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
