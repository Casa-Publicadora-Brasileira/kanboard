<?php

namespace Kanboard\Plugin\CompanyAgile\Model;

use Kanboard\Core\Base;

class EstimateModel extends Base
{
    const MIN_STORY_POINTS = 1;
    const MAX_STORY_POINTS = 5;

    public function normalize($value, &$error = '')
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (! preg_match('/^[1-5]$/', $value)) {
            $error = 'invalid';
            return false;
        }
        return number_format((int) $value, 2, '.', '');
    }

    public function setStoryPoints($taskId, $value, $userId, &$error = '')
    {
        $task = $this->db->table('tasks')->eq('id', (int) $taskId)->findOne();
        if (empty($task)) {
            $error = 'task';
            return false;
        }
        $type = $this->issueTypeModel->getByTaskId($taskId);
        if ($type['code'] === 'epic') {
            $error = 'epic';
            return false;
        }
        $normalized = $this->normalize($value, $error);
        if ($normalized === false) {
            return false;
        }
        $now = time();
        $sql = 'INSERT INTO company_agile_task_estimates (task_id, story_points, created_at, updated_at, updated_by) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE story_points = VALUES(story_points), updated_at = VALUES(updated_at), updated_by = VALUES(updated_by)';
        $statement = $this->db->getConnection()->prepare($sql);
        return $statement->execute(array((int) $taskId, $normalized, $now, $now, (int) $userId));
    }

    public function getByTaskId($taskId)
    {
        $row = $this->db->table('company_agile_task_estimates')->eq('task_id', (int) $taskId)->findOne();
        return empty($row) ? null : $row['story_points'];
    }
}
