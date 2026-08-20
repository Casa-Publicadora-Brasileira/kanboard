<?php

namespace Kanboard\Plugin\CompanyAgile\Model;

use Exception;
use Kanboard\Core\Base;

class EpicModel extends Base
{
    const RELATIONSHIP = 'epic_story';

    public function getParentForStory($taskId)
    {
        $sql = "SELECT parent.id, parent.title FROM company_agile_issue_links links INNER JOIN tasks parent ON parent.id = links.parent_task_id WHERE links.child_task_id = ? AND links.relationship_type = ? AND links.removed_at IS NULL ORDER BY links.id DESC LIMIT 1";
        $statement = $this->db->getConnection()->prepare($sql);
        $statement->execute(array((int) $taskId, self::RELATIONSHIP));
        return $statement->fetch(\PDO::FETCH_ASSOC) ?: array();
    }

    public function getEpicsForProject($projectId)
    {
        $sql = "SELECT tasks.id, tasks.title FROM tasks INNER JOIN company_agile_task_issue_types type_links ON type_links.task_id = tasks.id INNER JOIN company_agile_issue_types issue_types ON issue_types.id = type_links.issue_type_id AND issue_types.code = 'epic' WHERE tasks.project_id = ? AND tasks.is_active = 1 ORDER BY tasks.title";
        $statement = $this->db->getConnection()->prepare($sql);
        $statement->execute(array((int) $projectId));
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getAvailableStories($projectId, $epicId)
    {
        $sql = "SELECT tasks.id, tasks.title, estimates.story_points, sprints.name AS sprint_name FROM tasks INNER JOIN company_agile_task_issue_types type_links ON type_links.task_id = tasks.id INNER JOIN company_agile_issue_types issue_types ON issue_types.id = type_links.issue_type_id AND issue_types.code = 'story' LEFT JOIN company_agile_task_estimates estimates ON estimates.task_id = tasks.id LEFT JOIN company_agile_issue_links links ON links.child_task_id = tasks.id AND links.relationship_type = ? AND links.removed_at IS NULL LEFT JOIN company_agile_sprint_tasks sprint_links ON sprint_links.task_id = tasks.id AND sprint_links.removed_at IS NULL LEFT JOIN company_agile_sprints sprints ON sprints.id = sprint_links.sprint_id AND sprints.status IN ('planned','active') WHERE tasks.project_id = ? AND tasks.id <> ? AND links.id IS NULL ORDER BY tasks.id DESC LIMIT 100";
        $statement = $this->db->getConnection()->prepare($sql);
        $statement->execute(array(self::RELATIONSHIP, (int) $projectId, (int) $epicId));
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getStoriesForEpic($epicId)
    {
        $sql = "SELECT tasks.id, tasks.title, tasks.is_active, estimates.story_points, sprints.name AS sprint_name FROM company_agile_issue_links links INNER JOIN tasks ON tasks.id = links.child_task_id LEFT JOIN company_agile_task_estimates estimates ON estimates.task_id = tasks.id LEFT JOIN company_agile_sprint_tasks sprint_links ON sprint_links.task_id = tasks.id AND sprint_links.removed_at IS NULL LEFT JOIN company_agile_sprints sprints ON sprints.id = sprint_links.sprint_id AND sprints.status IN ('planned','active') WHERE links.parent_task_id = ? AND links.relationship_type = ? AND links.removed_at IS NULL ORDER BY tasks.id";
        $statement = $this->db->getConnection()->prepare($sql);
        $statement->execute(array((int) $epicId, self::RELATIONSHIP));
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getProgress($epicId)
    {
        $stories = $this->getStoriesForEpic($epicId);
        $result = array('stories' => $stories, 'total' => count($stories), 'completed' => 0, 'points_total' => 0.0, 'points_completed' => 0.0);
        foreach ($stories as $story) {
            $points = $story['story_points'] === null ? 0.0 : (float) $story['story_points'];
            $result['points_total'] += $points;
            if ((int) $story['is_active'] === 0) {
                ++$result['completed'];
                $result['points_completed'] += $points;
            }
        }
        return $result;
    }

    public function link($projectId, $epicId, $storyId, $userId)
    {
        $this->db->startTransaction();
        try {
            $this->linkWithinTransaction($projectId, $epicId, $storyId, $userId);
            $this->db->closeTransaction();
            return true;
        } catch (Exception $e) {
            $this->db->cancelTransaction();
            return false;
        }
    }

    public function linkWithinTransaction($projectId, $epicId, $storyId, $userId)
    {
        if ((int) $epicId === (int) $storyId) {
            throw new Exception('self_link');
        }
        $statement = $this->db->getConnection()->prepare('SELECT id, project_id FROM tasks WHERE id IN (?, ?) ORDER BY id FOR UPDATE');
        $statement->execute(array((int) $epicId, (int) $storyId));
        $tasks = $statement->fetchAll(\PDO::FETCH_ASSOC);
        if (count($tasks) !== 2) {
            throw new Exception('missing_task');
        }
        foreach ($tasks as $task) {
            if ((int) $task['project_id'] !== (int) $projectId) {
                throw new Exception('cross_project');
            }
        }
        if ($this->issueTypeModel->getByTaskId($epicId)['code'] !== 'epic' || $this->issueTypeModel->getByTaskId($storyId)['code'] !== 'story') {
            throw new Exception('invalid_types');
        }
        if ($this->db->table('company_agile_issue_links')->eq('child_task_id', (int) $storyId)->eq('relationship_type', self::RELATIONSHIP)->isNull('removed_at')->exists()) {
            throw new Exception('already_linked');
        }
        if (! $this->db->table('company_agile_issue_links')->insert(array('parent_task_id' => (int) $epicId, 'child_task_id' => (int) $storyId, 'relationship_type' => self::RELATIONSHIP, 'created_by' => (int) $userId, 'created_at' => time()))) {
            throw new Exception('insert_failed');
        }
    }

    public function remove($projectId, $epicId, $storyId)
    {
        $epic = $this->db->table('tasks')->eq('id', (int) $epicId)->findOne();
        $story = $this->db->table('tasks')->eq('id', (int) $storyId)->findOne();
        if (empty($epic) || empty($story) || (int) $epic['project_id'] !== (int) $projectId || (int) $story['project_id'] !== (int) $projectId) {
            return false;
        }
        return $this->db->table('company_agile_issue_links')->eq('parent_task_id', (int) $epicId)->eq('child_task_id', (int) $storyId)->eq('relationship_type', self::RELATIONSHIP)->isNull('removed_at')->update(array('removed_at' => time()));
    }
}
