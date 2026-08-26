<?php

namespace Kanboard\Model;

use Carbon\Carbon;
use Kanboard\Core\Base;
use Kanboard\Enum\ProjectTagEnum;
use Kanboard\Enum\ProductTagEnum;

/**
 * User Report Model
 *
 * @package  Kanboard\Model
 */
class UserReportModel extends Base
{
    /**
     * Get the user report data
     *
     * @access public
     * @param  int $userId
     * @return array
     */
    public function getReportData(int $userId): array
    {
        $sprintData = $this->getCurrentSprintPeriod();
        $tasks = $this->fetchUserTasks($userId);
    
        $tasks = $this->assignTaskPlanningStatus($tasks, $sprintData['monday_end']);
        $unexpectedCount = count(array_filter($tasks, fn($task) => !$task['is_planned']));
        
        $totalTasks = count($tasks);
        [$totalPoints, $averagePoints] = $this->calculateTaskPoints($tasks);
        [$finishedTasks, $concludedTasks, $completionRate] = $this->calculateCompletionStats($tasks);
        [$features, $bugs, $hotfixes] = $this->calculateTaskCategories($tasks);

        return [
            'team'       => $this->fetchUserTeamName($userId),
            'sprint'     => $sprintData['sprint'],
            'period'     => $sprintData['label'],
            'tasks'      => [
                'concluded' => $finishedTasks,
                'total'     => $totalTasks,
                'avg'       => $averagePoints,
                'features'  => $features,
                'bugs'      => $bugs,
                'hotfixes'  => $hotfixes
            ],
            'unexpected' => [
                'percent' => $totalTasks > 0 ? round($unexpectedCount / $totalTasks, 2) : 0,
                'tasks'   => $unexpectedCount
            ],
            'concluded'  => [
                'percent' => $completionRate,
                'tasks'   => $concludedTasks,
                'total'   => $totalTasks
            ],
            'teams'      => $this->groupTasksByProject($tasks)
        ];
    }

    /**
     * Calculate the current Sprint period based on the fortnight of the year.
     * Fortnight: starts on Monday, lasts 14 days, ends on Sunday.
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
     * Fetch active tasks for the given user in the Sprint project (ID 1).
     *
     * @access private
     * @param  int $userId
     * @return array
     */
    private function fetchUserTasks(int $userId): array
    {
        return $this->db->table(TaskModel::TABLE)
            ->eq('project_id', 1)
            ->eq('owner_id', $userId)
            ->eq('is_active', 1)
            ->desc('column_id')
            ->desc('score')
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
        // echo '<pre>'; print_r($entryDates); die();
        
        return $entryDates;
    }

    /**
     * Fetch the user's primary team name from the groups table.
     *
     * @access private
     * @param  int $userId
     * @return string
     */
    private function fetchUserTeamName(int $userId): string
    {
        $group = $this->db->table('groups')
            ->join('group_has_users', 'group_id', 'id')
            ->eq('group_has_users.user_id', $userId)
            ->findOne();
            
        return $group ? $group['name'] : '';
    }

    /**
     * Calculate the total and average complexity points of tasks.
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
     * Calculate completion statistics based on the column ID.
     *
     * @access private
     * @param  array $tasks
     * @return array [finishedTasks, concludedTasks, completionRate]
     */
    private function calculateCompletionStats(array $tasks): array
    {
        $totalTasks = count($tasks);
        
        // Column IDs: 3 (Finalizado), 4 (Homologação), 5 (Concluído)
        $finishedTasks = count(array_filter($tasks, fn($task) => in_array((int)$task['column_id'], [3, 4, 5])));
        $concludedTasks = count(array_filter($tasks, fn($task) => (int)$task['column_id'] === 5));

        $completionRate = $totalTasks > 0 ? round($concludedTasks / $totalTasks, 2) : 0;

        return [$finishedTasks, $concludedTasks, $completionRate];
    }

    /**
     * Count tasks by their category classification (Features, Bugs, Hotfixes).
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
     * Group tasks by project tag and generate the frontend 'teams' structure.
     *
     * @access private
     * @param  array $tasks
     * @return array
     */
    private function groupTasksByProject(array $tasks): array
    {
        if (empty($tasks)) {
            return [];
        }

        $taskIds = array_column($tasks, 'id');
        $tags = $this->db->table('task_has_tags')->in('task_id', $taskIds)->findAll();

        $taskProjects = [];
        $taskProducts = [];
        
        foreach ($tags as $tag) {
            $taskId = $tag['task_id'];
            $tagId = (int)$tag['tag_id'];
            
            if (!isset($taskProjects[$taskId])) {
                $projectEnum = ProjectTagEnum::tryFrom($tagId);
                if ($projectEnum) {
                    $taskProjects[$taskId] = [
                        'id'   => $projectEnum->value,
                        'name' => $projectEnum->label(),
                    ];
                }
            }

            $productEnum = ProductTagEnum::tryFrom($tagId);
            if ($productEnum) {
                $taskProducts[$taskId][] = $productEnum->label();
            }
        }

        $groupedTasks = [];
        foreach ($tasks as $task) {
            $taskId = $task['id'];
            $projectInfo = $taskProjects[$taskId] ?? ['id' => 0, 'name' => 'Outros / Não Especificado'];
            $projectName = $projectInfo['name'];
            $groupedTasks[$projectName]['project_id'] = $projectInfo['id'];
            $groupedTasks[$projectName]['tasks'][] = $task;
        }

        $projectGroups = [];
        foreach ($groupedTasks as $projectName => $data) {
            $projectTasks = $data['tasks'];
            $projectId = $data['project_id'];
            $projectTotalTasks = count($projectTasks);
            [$projectTotalPoints] = $this->calculateTaskPoints($projectTasks);
            [, $projectConcludedTasks] = $this->calculateCompletionStats($projectTasks);

            $projectGroups[] = [
                'project_id' => $projectId,
                'title'      => $projectName,
                'tasks'      => "{$projectConcludedTasks}/{$projectTotalTasks}",
                'points'     => $projectTotalPoints,
                'items'      => array_map(function ($task) use ($taskProducts) {
                    $dateMoved = !empty($task['date_moved']) ? (int) $task['date_moved'] : (int) $task['date_creation'];
                    return [
                        'number'     => $task['id'],
                        'title'      => $task['title'],
                        'products'   => $taskProducts[$task['id']] ?? [],
                        'planned'    => $task['is_planned'] ?? true,
                        'point'      => $task['score'] ?: 0,
                        'status'     => (int) $task['column_id'],
                        'is_hotfix'  => (int) ($task['category_id'] ?? 0) === 33,
                        'date_moved' => $dateMoved,
                    ];
                }, $projectTasks)
            ];
        }

        return $projectGroups;
    }
}
