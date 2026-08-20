<?php

namespace Kanboard\Plugin\CompanyAgile\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Model\ProjectModel;
use Kanboard\Model\TaskModel;

class MyTasksController extends BaseController
{
    public function show()
    {
        $viewerId = (int) $this->userSession->getId();
        $canInspectUsers = $this->userSession->isAdmin() || $this->helper->user->hasAccess('ProjectUserOverviewController', 'opens');
        $requestedUserId = $this->request->getIntegerParam('user_id');
        if ($requestedUserId > 0 && $requestedUserId !== $viewerId && ! $canInspectUsers) {
            throw new AccessForbiddenException();
        }

        $userId = $requestedUserId > 0 ? $requestedUserId : $viewerId;
        $user = $this->userModel->getById($userId);
        if (empty($user) || (int) $user['is_active'] !== 1) throw new AccessForbiddenException();

        $projects = $this->getAllowedProjects();
        $selectedProjectIds = $this->getIntegerListParam('project_ids');
        foreach ($selectedProjectIds as $projectId) {
            if (! isset($projects[$projectId])) throw new AccessForbiddenException();
        }

        $selectedColumnIds = $this->getIntegerListParam('column_ids');
        if (! empty($selectedColumnIds) && ! $this->columnsBelongToProjects($selectedColumnIds, array_keys($projects))) {
            throw new AccessForbiddenException();
        }

        $priority = $this->request->getStringParam('priority');
        if (! in_array($priority, array('', 'high', 'normal', 'low'), true)) $priority = '';
        $search = trim($this->request->getStringParam('search'));
        $context = array(
            'allowed_project_ids' => array_keys($projects),
            'selected_project_ids' => $selectedProjectIds,
            'selected_column_ids' => $selectedColumnIds,
            'priority' => $priority,
            'search' => $search,
            'user_id' => $userId,
        );

        $query = $this->taskFinderModel->getProjectUserOverviewQuery($context['allowed_project_ids'], TaskModel::STATUS_OPEN)
            ->eq(TaskModel::TABLE.'.owner_id', $userId);
        $this->applyQueryFilters($query, $context);
        $paginator = $this->paginator
            ->setUrl('MyTasksController', 'show', array('plugin' => 'CompanyAgile', 'user_id' => $userId, 'project_ids' => implode(',', $selectedProjectIds), 'column_ids' => implode(',', $selectedColumnIds), 'priority' => $priority, 'search' => $search))
            ->setMax(48)
            ->setOrder(ProjectModel::TABLE.'.name')
            ->setDirection('ASC')
            ->setQuery($query)
            ->calculate();

        $tasks = $paginator->getCollection();
        $estimates = $this->getEstimates(array_column($tasks, 'id'));
        foreach ($tasks as &$task) $task['story_points'] = isset($estimates[(int) $task['id']]) ? $estimates[(int) $task['id']] : null;
        unset($task);

        $selectedUsers = array($userId => $this->helper->user->getFullname($user));
        $this->response->html($this->helper->layout->app('companyAgile:my_tasks/show', array(
            'title' => t('CompanyAgile: My tasks'),
            'represented_user' => $user,
            'represented_user_name' => $this->helper->user->getFullname($user),
            'can_inspect_users' => $canInspectUsers,
            'users' => $selectedUsers,
            'user_search_url' => $this->helper->url->to('ManagementDashboardController', 'users', array('plugin' => 'CompanyAgile')),
            'projects' => $projects,
            'selected_project_ids' => $selectedProjectIds,
            'selected_column_ids' => $selectedColumnIds,
            'priority' => $priority,
            'search' => $search,
            'summary' => $this->getSummary($context),
            'attention' => $this->getAttention($context),
            'tasks' => $tasks,
            'task_groups' => $this->groupTasks($tasks),
            'paginator' => $paginator,
        )));
    }

    private function getAllowedProjects()
    {
        if ($this->userSession->isAdmin()) {
            $rows = $this->db->table(ProjectModel::TABLE)->eq('is_active', ProjectModel::ACTIVE)->columns('id', 'name')->asc('name')->findAll();
            $projects = array();
            foreach ($rows as $row) $projects[(int) $row['id']] = $row['name'];
            return $projects;
        }
        return $this->projectUserRoleModel->getActiveProjectsByUser($this->userSession->getId());
    }

    private function getIntegerListParam($name)
    {
        return array_values(array_unique(array_filter(array_map('intval', explode(',', $this->request->getStringParam($name))))));
    }

    private function columnsBelongToProjects(array $columnIds, array $projectIds)
    {
        if (empty($projectIds)) return false;
        $rows = $this->db->table('columns')->in('id', $columnIds)->in('project_id', $projectIds)->columns('id')->findAll();
        return count($rows) === count($columnIds);
    }

    private function applyQueryFilters($query, array $context)
    {
        if (! empty($context['selected_project_ids'])) $query->in(TaskModel::TABLE.'.project_id', $context['selected_project_ids']);
        if (! empty($context['selected_column_ids'])) $query->in(TaskModel::TABLE.'.column_id', $context['selected_column_ids']);
        if ($context['priority'] === 'high') $query->gt(TaskModel::TABLE.'.priority', 0);
        if ($context['priority'] === 'normal') $query->eq(TaskModel::TABLE.'.priority', 0);
        if ($context['priority'] === 'low') $query->lt(TaskModel::TABLE.'.priority', 0);
        if ($context['search'] !== '') {
            $needle = ltrim($context['search'], '#');
            if (ctype_digit($needle)) $query->beginOr()->eq(TaskModel::TABLE.'.id', (int) $needle)->ilike(TaskModel::TABLE.'.title', '%'.$context['search'].'%')->closeOr();
            else $query->ilike(TaskModel::TABLE.'.title', '%'.$context['search'].'%');
        }
    }

    private function buildWhere(array $context)
    {
        $projectIds = empty($context['allowed_project_ids']) ? array(-1) : $context['allowed_project_ids'];
        $where = 'tasks.is_active = 1 AND tasks.owner_id = ? AND tasks.project_id IN ('.implode(',', array_fill(0, count($projectIds), '?')).')';
        $params = array_merge(array($context['user_id']), $projectIds);
        if (! empty($context['selected_project_ids'])) { $where .= ' AND tasks.project_id IN ('.implode(',', array_fill(0, count($context['selected_project_ids']), '?')).')'; $params = array_merge($params, $context['selected_project_ids']); }
        if (! empty($context['selected_column_ids'])) { $where .= ' AND tasks.column_id IN ('.implode(',', array_fill(0, count($context['selected_column_ids']), '?')).')'; $params = array_merge($params, $context['selected_column_ids']); }
        if ($context['priority'] === 'high') $where .= ' AND tasks.priority > 0';
        if ($context['priority'] === 'normal') $where .= ' AND tasks.priority = 0';
        if ($context['priority'] === 'low') $where .= ' AND tasks.priority < 0';
        if ($context['search'] !== '') {
            $needle = ltrim($context['search'], '#');
            if (ctype_digit($needle)) { $where .= ' AND (tasks.id = ? OR tasks.title LIKE ?)'; $params[] = (int) $needle; $params[] = '%'.$context['search'].'%'; }
            else { $where .= ' AND tasks.title LIKE ?'; $params[] = '%'.$context['search'].'%'; }
        }
        return array($where, $params);
    }

    private function getSummary(array $context)
    {
        list($where, $params) = $this->buildWhere($context);
        $now = time();
        $metricsParams = array_merge(array($now), $params);
        $metrics = $this->db->execute('SELECT COUNT(*) task_count, COUNT(DISTINCT tasks.project_id) project_count, SUM(CASE WHEN tasks.priority > 0 THEN 1 ELSE 0 END) high_count, SUM(CASE WHEN tasks.date_due > 0 AND tasks.date_due < ? THEN 1 ELSE 0 END) overdue_count FROM tasks WHERE '.$where, $metricsParams)->fetch(\PDO::FETCH_ASSOC);
        $projects = $this->db->execute('SELECT projects.id, projects.name, COUNT(*) task_count FROM tasks INNER JOIN projects ON projects.id=tasks.project_id WHERE '.$where.' GROUP BY projects.id,projects.name ORDER BY task_count DESC,projects.name', $params)->fetchAll(\PDO::FETCH_ASSOC);
        $columnRows = $this->db->execute('SELECT columns.id,columns.title,COUNT(*) task_count FROM tasks INNER JOIN columns ON columns.id=tasks.column_id WHERE '.$where.' GROUP BY columns.id,columns.title ORDER BY task_count DESC,columns.title', $params)->fetchAll(\PDO::FETCH_ASSOC);
        $columns = array();
        foreach ($columnRows as $row) {
            $key = mb_strtolower($row['title'], 'UTF-8');
            if (! isset($columns[$key])) $columns[$key] = array('ids' => array(), 'title' => $row['title'], 'task_count' => 0);
            $columns[$key]['ids'][] = (int) $row['id'];
            $columns[$key]['task_count'] += (int) $row['task_count'];
        }
        return array('task_count' => (int) $metrics['task_count'], 'project_count' => (int) $metrics['project_count'], 'high_count' => (int) $metrics['high_count'], 'overdue_count' => (int) $metrics['overdue_count'], 'projects' => $projects, 'columns' => array_values($columns));
    }

    private function getAttention(array $context)
    {
        list($where, $params) = $this->buildWhere($context);
        $now = time();
        $soon = $now + 3 * 86400;
        $sql = 'SELECT tasks.id,tasks.title,tasks.priority,tasks.date_due,projects.name project_name,columns.title column_name FROM tasks INNER JOIN projects ON projects.id=tasks.project_id INNER JOIN columns ON columns.id=tasks.column_id WHERE '.$where.' AND (tasks.priority > 0 OR (tasks.date_due > 0 AND tasks.date_due <= ?)) ORDER BY CASE WHEN tasks.date_due > 0 AND tasks.date_due < ? THEN 0 WHEN tasks.date_due > 0 THEN 1 ELSE 2 END,tasks.priority DESC,tasks.date_due ASC LIMIT 8';
        return $this->db->execute($sql, array_merge($params, array($soon, $now)))->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getEstimates(array $taskIds)
    {
        if (empty($taskIds)) return array();
        $rows = $this->db->table('company_agile_task_estimates')->in('task_id', array_map('intval', $taskIds))->columns('task_id', 'story_points')->findAll();
        $values = array();
        foreach ($rows as $row) $values[(int) $row['task_id']] = $row['story_points'];
        return $values;
    }

    private function groupTasks(array $tasks)
    {
        $groups = array();
        foreach ($tasks as $task) {
            $projectKey = (int) $task['project_id'];
            $columnKey = (string) $task['column_name'];
            if (! isset($groups[$projectKey])) $groups[$projectKey] = array('name' => $task['project_name'], 'count' => 0, 'columns' => array());
            if (! isset($groups[$projectKey]['columns'][$columnKey])) $groups[$projectKey]['columns'][$columnKey] = array();
            $groups[$projectKey]['columns'][$columnKey][] = $task;
            $groups[$projectKey]['count']++;
        }
        foreach ($groups as &$group) ksort($group['columns'], SORT_NATURAL | SORT_FLAG_CASE);
        unset($group);
        return $groups;
    }
}
