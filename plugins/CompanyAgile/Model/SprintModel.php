<?php

namespace Kanboard\Plugin\CompanyAgile\Model;

use Exception;
use Kanboard\Core\Base;

class SprintModel extends Base
{
    const STATUS_PLANNED = 'planned';
    const STATUS_ACTIVE = 'active';
    const STATUS_COMPLETED = 'completed';

    public function getPlanningData($projectId)
    {
        $sprints = $this->getSprints($projectId, false);
        $tasks = $this->getPlanningTasks($projectId);
        $grouped = array('backlog' => array(), 'sprints' => array());

        foreach ($sprints as $sprint) {
            $grouped['sprints'][$sprint['id']] = array('sprint' => $sprint, 'tasks' => array());
        }

        foreach ($tasks as $task) {
            if (! empty($task['sprint_id']) && isset($grouped['sprints'][$task['sprint_id']])) {
                $grouped['sprints'][$task['sprint_id']]['tasks'][] = $task;
            } else {
                $grouped['backlog'][] = $task;
            }
        }

        return $grouped;
    }

    public function getSprints($projectId, $includeCompleted = true)
    {
        $sql = "SELECT sprints.*,
                    COUNT(relations.id) AS task_count,
                    SUM(CASE WHEN tasks.is_active = 0 THEN 1 ELSE 0 END) AS completed_count,
                    SUM(COALESCE(estimates.story_points, 0)) AS story_points_total,
                    SUM(CASE WHEN tasks.is_active = 0 THEN COALESCE(estimates.story_points, 0) ELSE 0 END) AS story_points_completed
                FROM company_agile_sprints sprints
                LEFT JOIN company_agile_sprint_tasks relations ON relations.sprint_id = sprints.id AND relations.removed_at IS NULL
                LEFT JOIN tasks ON tasks.id = relations.task_id
                LEFT JOIN company_agile_task_estimates estimates ON estimates.task_id = tasks.id
                WHERE sprints.project_id = ?";
        $params = array((int) $projectId);
        if (! $includeCompleted) {
            $sql .= " AND sprints.status IN ('planned', 'active')";
        }
        $sql .= " GROUP BY sprints.id ORDER BY FIELD(sprints.status, 'active', 'planned', 'completed'), sprints.planned_start_at ASC, sprints.id ASC";
        $statement = $this->db->getConnection()->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getById($sprintId)
    {
        return $this->db->table('company_agile_sprints')->eq('id', (int) $sprintId)->findOne();
    }

    public function getCurrentSprintForTask($taskId)
    {
        $sql = "SELECT sprints.* FROM company_agile_sprint_tasks relations
                INNER JOIN company_agile_sprints sprints ON sprints.id = relations.sprint_id
                WHERE relations.task_id = ? AND relations.removed_at IS NULL AND sprints.status IN ('planned', 'active')
                ORDER BY relations.id DESC LIMIT 1";
        $statement = $this->db->getConnection()->prepare($sql);
        $statement->execute(array((int) $taskId));
        return $statement->fetch(\PDO::FETCH_ASSOC) ?: array();
    }

    public function create(array $values, $userId)
    {
        $now = time();
        if (! $this->db->table('company_agile_sprints')->insert(array(
            'project_id' => (int) $values['project_id'],
            'name' => trim($values['name']),
            'goal' => trim(isset($values['goal']) ? $values['goal'] : ''),
            'status' => self::STATUS_PLANNED,
            'planned_start_at' => $this->parseDate(isset($values['planned_start_at']) ? $values['planned_start_at'] : ''),
            'planned_end_at' => $this->parseDate(isset($values['planned_end_at']) ? $values['planned_end_at'] : ''),
            'created_by' => (int) $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ))) {
            return 0;
        }
        return (int) $this->db->getConnection()->lastInsertId();
    }

    public function updatePlanned(array $sprint, array $values)
    {
        if ($sprint['status'] !== self::STATUS_PLANNED || trim($values['name']) === '') {
            return false;
        }
        return $this->db->table('company_agile_sprints')->eq('id', $sprint['id'])->update(array(
            'name' => trim($values['name']),
            'goal' => trim(isset($values['goal']) ? $values['goal'] : ''),
            'planned_start_at' => $this->parseDate(isset($values['planned_start_at']) ? $values['planned_start_at'] : ''),
            'planned_end_at' => $this->parseDate(isset($values['planned_end_at']) ? $values['planned_end_at'] : ''),
            'updated_at' => time(),
        ));
    }

    public function start(array $sprint)
    {
        if ($sprint['status'] !== self::STATUS_PLANNED) {
            return false;
        }
        $this->db->startTransaction();
        try {
            $this->lockProject($sprint['project_id']);
            $active = $this->db->table('company_agile_sprints')->eq('project_id', $sprint['project_id'])->eq('status', self::STATUS_ACTIVE)->exists();
            if ($active) {
                throw new Exception('active_sprint_exists');
            }
            $result = $this->db->table('company_agile_sprints')->eq('id', $sprint['id'])->eq('status', self::STATUS_PLANNED)->update(array(
                'status' => self::STATUS_ACTIVE,
                'started_at' => time(),
                'updated_at' => time(),
            ));
            if (! $result) {
                throw new Exception('start_failed');
            }
            $this->db->closeTransaction();
            return true;
        } catch (Exception $e) {
            $this->db->cancelTransaction();
            return false;
        }
    }

    public function moveTask($projectId, $taskId, $sprintId, $position = 0)
    {
        $this->db->startTransaction();
        try {
            $this->moveTaskWithinTransaction($projectId, $taskId, $sprintId, $position);
            $this->db->closeTransaction();
            return true;
        } catch (Exception $e) {
            $this->db->cancelTransaction();
            return false;
        }
    }

    public function moveTaskWithinTransaction($projectId, $taskId, $sprintId, $position = 0)
    {
        $task = $this->db->table('tasks')->eq('id', (int) $taskId)->findOne();
        if (empty($task) || (int) $task['project_id'] !== (int) $projectId) {
            throw new Exception('invalid_task_project');
        }

        $current = $this->getCurrentSprintForTask($taskId);
        if (! empty($current) && (int) $current['id'] === (int) $sprintId) {
            $sql = 'UPDATE company_agile_sprint_tasks SET position = ? WHERE task_id = ? AND sprint_id = ? AND removed_at IS NULL';
            $statement = $this->db->getConnection()->prepare($sql);
            return $statement->execute(array((int) $position, (int) $taskId, (int) $sprintId));
        }

        $statement = $this->db->getConnection()->prepare('UPDATE company_agile_sprint_tasks SET removed_at = ? WHERE task_id = ? AND removed_at IS NULL');
        $statement->execute(array(time(), (int) $taskId));

        if ((int) $sprintId === 0) {
            return true;
        }

        $sprint = $this->getById($sprintId);
        if (empty($sprint) || (int) $sprint['project_id'] !== (int) $projectId || ! in_array($sprint['status'], array(self::STATUS_PLANNED, self::STATUS_ACTIVE), true)) {
            throw new Exception('invalid_sprint');
        }

        if ($position <= 0) {
            $position = (int) $this->db->table('company_agile_sprint_tasks')->eq('sprint_id', $sprintId)->isNull('removed_at')->count() + 1;
        }

        if (! $this->db->table('company_agile_sprint_tasks')->insert(array(
            'sprint_id' => (int) $sprintId,
            'task_id' => (int) $taskId,
            'added_at' => time(),
            'position' => (int) $position,
            'original_position' => (int) $position,
        ))) {
            throw new Exception('association_failed');
        }
        return true;
    }

    public function getCompletionSummary(array $sprint)
    {
        $sql = 'SELECT SUM(CASE WHEN tasks.is_active = 0 THEN 1 ELSE 0 END) AS completed, SUM(CASE WHEN tasks.is_active = 1 THEN 1 ELSE 0 END) AS incomplete FROM company_agile_sprint_tasks relations INNER JOIN tasks ON tasks.id = relations.task_id WHERE relations.sprint_id = ? AND relations.removed_at IS NULL';
        $statement = $this->db->getConnection()->prepare($sql);
        $statement->execute(array((int) $sprint['id']));
        $summary = $statement->fetch(\PDO::FETCH_ASSOC);
        return array('completed' => (int) $summary['completed'], 'incomplete' => (int) $summary['incomplete']);
    }

    public function complete(array $sprint, $destinationSprintId)
    {
        if ($sprint['status'] !== self::STATUS_ACTIVE) {
            return false;
        }
        $this->db->startTransaction();
        try {
            $this->lockProject($sprint['project_id']);
            $destination = array();
            if ((int) $destinationSprintId > 0) {
                $destination = $this->getById($destinationSprintId);
                if (empty($destination) || (int) $destination['project_id'] !== (int) $sprint['project_id'] || $destination['status'] !== self::STATUS_PLANNED) {
                    throw new Exception('invalid_destination');
                }
            }

            $sql = 'SELECT relations.id, relations.task_id, relations.position, tasks.is_active, estimates.story_points FROM company_agile_sprint_tasks relations INNER JOIN tasks ON tasks.id = relations.task_id LEFT JOIN company_agile_task_estimates estimates ON estimates.task_id = tasks.id WHERE relations.sprint_id = ? AND relations.removed_at IS NULL FOR UPDATE';
            $statement = $this->db->getConnection()->prepare($sql);
            $statement->execute(array((int) $sprint['id']));
            $relations = $statement->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($relations as $relation) {
                $completed = (int) $relation['is_active'] === 0 ? 1 : 0;
                $this->db->table('company_agile_sprint_tasks')->eq('id', $relation['id'])->update(array('completed_in_sprint' => $completed, 'story_points_snapshot' => $relation['story_points']));
                if (! $completed) {
                    $this->db->table('company_agile_sprint_tasks')->eq('id', $relation['id'])->update(array('removed_at' => time()));
                    if (! empty($destination)) {
                        $this->moveTaskWithinTransaction($sprint['project_id'], $relation['task_id'], $destination['id'], $relation['position']);
                    }
                }
            }
            if (! $this->db->table('company_agile_sprints')->eq('id', $sprint['id'])->eq('status', self::STATUS_ACTIVE)->update(array('status' => self::STATUS_COMPLETED, 'completed_at' => time(), 'updated_at' => time()))) {
                throw new Exception('complete_failed');
            }
            $this->db->closeTransaction();
            return true;
        } catch (Exception $e) {
            $this->db->cancelTransaction();
            return false;
        }
    }

    public function getDefaultTaskContext($projectId)
    {
        $column = $this->db->table('columns')->eq('project_id', (int) $projectId)->asc('position')->findOne();
        $swimlane = $this->db->table('swimlanes')->eq('project_id', (int) $projectId)->eq('is_active', 1)->asc('position')->findOne();
        return array('column_id' => isset($column['id']) ? $column['id'] : 0, 'swimlane_id' => isset($swimlane['id']) ? $swimlane['id'] : 0);
    }

    private function getPlanningTasks($projectId)
    {
        $sql = "SELECT tasks.id, tasks.title, tasks.priority, tasks.project_id, tasks.is_active,
                    users.username AS assignee_username, users.name AS assignee_name,
                    COALESCE(issue_types.code, default_type.code) AS issue_type_code,
                    COALESCE(issue_types.icon, default_type.icon) AS issue_type_icon,
                    COALESCE(issue_types.color, default_type.color) AS issue_type_color,
                    estimates.story_points,
                    epic.id AS epic_id, epic.title AS epic_title,
                    current_sprint.sprint_id, current_sprint.position AS sprint_position
                FROM tasks
                INNER JOIN company_agile_issue_types default_type ON default_type.code = 'task'
                LEFT JOIN users ON users.id = tasks.owner_id
                LEFT JOIN company_agile_task_issue_types type_links ON type_links.task_id = tasks.id
                LEFT JOIN company_agile_issue_types issue_types ON issue_types.id = type_links.issue_type_id
                LEFT JOIN company_agile_task_estimates estimates ON estimates.task_id = tasks.id
                LEFT JOIN company_agile_issue_links epic_links ON epic_links.child_task_id = tasks.id AND epic_links.relationship_type = 'epic_story' AND epic_links.removed_at IS NULL
                LEFT JOIN tasks epic ON epic.id = epic_links.parent_task_id
                LEFT JOIN (
                    SELECT relations.task_id, relations.sprint_id, relations.position
                    FROM company_agile_sprint_tasks relations
                    INNER JOIN company_agile_sprints sprints ON sprints.id = relations.sprint_id
                    WHERE relations.removed_at IS NULL AND sprints.status IN ('planned', 'active')
                ) current_sprint ON current_sprint.task_id = tasks.id
                WHERE tasks.project_id = ? AND tasks.is_active = 1
                ORDER BY CASE WHEN current_sprint.sprint_id IS NULL THEN 1 ELSE 0 END, current_sprint.position ASC, tasks.id DESC";
        $statement = $this->db->getConnection()->prepare($sql);
        $statement->execute(array((int) $projectId));
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function lockProject($projectId)
    {
        $statement = $this->db->getConnection()->prepare('SELECT id FROM projects WHERE id = ? FOR UPDATE');
        $statement->execute(array((int) $projectId));
    }

    private function parseDate($value)
    {
        if ($value === '' || $value === null) {
            return null;
        }
        $timestamp = strtotime($value.' 00:00:00');
        return $timestamp === false ? null : $timestamp;
    }
}
