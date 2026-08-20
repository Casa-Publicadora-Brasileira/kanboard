<?php

namespace Kanboard\Plugin\CompanyAgile\Model;

use Kanboard\Core\Base;

class IssueTypeModel extends Base
{
    const DEFAULT_CODE = 'task';

    private $projectCache = array();

    public function getActiveTypes()
    {
        return $this->db->table('company_agile_issue_types')
            ->eq('is_active', 1)
            ->asc('position')
            ->findAll();
    }

    public function getActiveTypesList()
    {
        $types = array();
        foreach ($this->getActiveTypes() as $type) {
            $types[$type['id']] = $type;
        }
        return $types;
    }

    public function getDefaultType()
    {
        $type = $this->db->table('company_agile_issue_types')->eq('code', self::DEFAULT_CODE)->findOne();
        return $type ?: array('id' => 0, 'code' => self::DEFAULT_CODE, 'name' => 'Task', 'icon' => 'check', 'color' => '#4c6ef5');
    }

    public function getById($issueTypeId)
    {
        return $this->db->table('company_agile_issue_types')->eq('id', (int) $issueTypeId)->eq('is_active', 1)->findOne();
    }

    public function getByTaskId($taskId)
    {
        $sql = "SELECT COALESCE(issue_types.id, default_type.id) AS id,
                    COALESCE(issue_types.code, default_type.code) AS code,
                    COALESCE(issue_types.name, default_type.name) AS name,
                    COALESCE(issue_types.icon, default_type.icon) AS icon,
                    COALESCE(issue_types.color, default_type.color) AS color
                FROM company_agile_issue_types default_type
                LEFT JOIN company_agile_task_issue_types links ON links.task_id = ?
                LEFT JOIN company_agile_issue_types issue_types ON issue_types.id = links.issue_type_id
                WHERE default_type.code = 'task'";
        $statement = $this->db->getConnection()->prepare($sql);
        $statement->execute(array((int) $taskId));
        $type = $statement->fetch(\PDO::FETCH_ASSOC);
        return $type ?: array('id' => 0, 'code' => self::DEFAULT_CODE, 'name' => 'Task', 'icon' => 'check', 'color' => '#4c6ef5');
    }

    public function getForBoardTask(array $task)
    {
        $projectId = (int) $task['project_id'];
        if (! isset($this->projectCache[$projectId])) {
            $this->projectCache[$projectId] = $this->loadProjectTypes($projectId);
        }

        return isset($this->projectCache[$projectId][$task['id']])
            ? $this->projectCache[$projectId][$task['id']]
            : $this->projectCache[$projectId][0];
    }

    public function assign($taskId, $issueTypeId)
    {
        $validType = $this->db->table('company_agile_issue_types')
            ->eq('id', (int) $issueTypeId)
            ->eq('is_active', 1)
            ->findOne();

        if (empty($validType) || ! $this->canChangeType($taskId, $validType['code'])) {
            return false;
        }

        $sql = 'INSERT INTO company_agile_task_issue_types (task_id, issue_type_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE issue_type_id = VALUES(issue_type_id)';
        $statement = $this->db->getConnection()->prepare($sql);
        return $statement->execute(array((int) $taskId, (int) $issueTypeId));
    }

    public function canChangeType($taskId, $newCode)
    {
        $current = $this->getByTaskId($taskId);
        if ($current['code'] === $newCode) {
            return true;
        }
        if ($current['code'] === 'story' && $newCode !== 'story'
            && $this->db->table('company_agile_issue_links')->eq('child_task_id', (int) $taskId)->eq('relationship_type', 'epic_story')->isNull('removed_at')->exists()) {
            return false;
        }
        if ($current['code'] === 'epic' && $newCode !== 'epic'
            && $this->db->table('company_agile_issue_links')->eq('parent_task_id', (int) $taskId)->eq('relationship_type', 'epic_story')->isNull('removed_at')->exists()) {
            return false;
        }
        return true;
    }

    private function loadProjectTypes($projectId)
    {
        $types = array();
        $sql = "SELECT tasks.id AS task_id,
                    COALESCE(issue_types.id, default_type.id) AS id,
                    COALESCE(issue_types.code, default_type.code) AS code,
                    COALESCE(issue_types.name, default_type.name) AS name,
                    COALESCE(issue_types.icon, default_type.icon) AS icon,
                    COALESCE(issue_types.color, default_type.color) AS color,
                    current_sprint.sprint_id,
                    current_sprint.sprint_name,
                    current_sprint.sprint_status,
                    estimates.story_points,
                    epic.id AS epic_id,
                    epic.title AS epic_title,
                    epic_progress.points_total AS epic_points_total,
                    epic_progress.points_completed AS epic_points_completed
                FROM tasks
                INNER JOIN company_agile_issue_types default_type ON default_type.code = 'task'
                LEFT JOIN company_agile_task_issue_types links ON links.task_id = tasks.id
                LEFT JOIN company_agile_issue_types issue_types ON issue_types.id = links.issue_type_id
                LEFT JOIN company_agile_task_estimates estimates ON estimates.task_id = tasks.id
                LEFT JOIN company_agile_issue_links epic_links ON epic_links.child_task_id = tasks.id AND epic_links.relationship_type = 'epic_story' AND epic_links.removed_at IS NULL
                LEFT JOIN tasks epic ON epic.id = epic_links.parent_task_id
                LEFT JOIN (
                    SELECT relations.parent_task_id,
                        SUM(COALESCE(child_estimates.story_points, 0)) AS points_total,
                        SUM(CASE WHEN children.is_active = 0 THEN COALESCE(child_estimates.story_points, 0) ELSE 0 END) AS points_completed
                    FROM company_agile_issue_links relations
                    INNER JOIN tasks children ON children.id = relations.child_task_id
                    LEFT JOIN company_agile_task_estimates child_estimates ON child_estimates.task_id = children.id
                    WHERE relations.relationship_type = 'epic_story' AND relations.removed_at IS NULL
                    GROUP BY relations.parent_task_id
                ) epic_progress ON epic_progress.parent_task_id = tasks.id
                LEFT JOIN (
                    SELECT relations.task_id, sprints.id AS sprint_id, sprints.name AS sprint_name, sprints.status AS sprint_status
                    FROM company_agile_sprint_tasks relations
                    INNER JOIN company_agile_sprints sprints ON sprints.id = relations.sprint_id
                    WHERE relations.removed_at IS NULL AND sprints.status IN ('planned', 'active')
                ) current_sprint ON current_sprint.task_id = tasks.id
                WHERE tasks.project_id = ?";
        $statement = $this->db->getConnection()->prepare($sql);
        $statement->execute(array((int) $projectId));

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $type) {
            $types[(int) $type['task_id']] = $type;
        }

        if (empty($types)) {
            $types[0] = array('id' => 0, 'code' => self::DEFAULT_CODE, 'name' => 'Task', 'icon' => 'check', 'color' => '#4c6ef5');
        } else {
            $types[0] = reset($types);
        }

        return $types;
    }
}
