<?php

namespace Kanboard\Plugin\CompanyAgile\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Core\Security\Role;
use Kanboard\Model\ProjectModel;
use Kanboard\Model\TaskModel;
use Kanboard\Model\UserModel;

class ManagementDashboardController extends BaseController
{
    public function managers()
    {
        $this->renderPeople(Role::PROJECT_MANAGER, 'managers', t('Project managers'));
    }

    public function members()
    {
        $this->renderPeople(Role::PROJECT_MEMBER, 'members', t('Project members'));
    }

    public function opens()
    {
        $this->renderTasks(TaskModel::STATUS_OPEN, 'opens', t('Open tasks'));
    }

    public function closed()
    {
        $this->renderTasks(TaskModel::STATUS_CLOSED, 'closed', t('Closed tasks'));
    }

    public function users()
    {
        if (! $this->helper->user->hasAccess('ProjectUserOverviewController', 'opens')) {
            throw new AccessForbiddenException();
        }

        $projectId = $this->request->getIntegerParam('project_id');
        $projectIds = $this->getIntegerListParam('project_ids');
        if ($projectId > 0 && empty($projectIds)) $projectIds = array($projectId);
        $term = trim($this->request->getStringParam('term'));
        if (! empty($projectIds)) {
            $allowedProjectIds = $this->getAllowedProjects();
            foreach ($projectIds as $selectedProjectId) {
                if (! isset($allowedProjectIds[$selectedProjectId])) throw new AccessForbiddenException();
            }
            $rows = $this->getAssignableUsersForProjects($projectIds);
        } else {
            $query = $this->db->table(UserModel::TABLE)->eq('is_active', 1)->columns('id', 'username', 'name')->asc('name')->asc('username')->limit(50);
            if ($term !== '') $query->beginOr()->ilike('name', '%'.$term.'%')->ilike('username', '%'.$term.'%')->closeOr();
            $rows = $query->findAll();
        }

        $items = array();
        foreach (array_slice($rows, 0, ! empty($projectIds) ? count($rows) : 50) as $row) {
            $items[] = array('id' => (int) $row['id'], 'label' => isset($row['label']) ? $row['label'] : $this->helper->user->getFullname($row));
        }
        $this->response->json($items);
    }

    private function renderPeople($role, $action, $title)
    {
        $context = $this->getContext($action);
        $query = $this->projectPermissionModel->getQueryByRole($context['project_ids'], $role);
        if ($context['project_id'] > 0) {
            $query->eq(ProjectModel::TABLE.'.id', $context['project_id']);
        }
        $paginator = $this->paginator
            ->setUrl('ManagementDashboardController', $action, array('plugin' => 'CompanyAgile', 'project_id' => $context['project_id']))
            ->setMax(50)
            ->setOrder(ProjectModel::TABLE.'.name')
            ->setQuery($query)
            ->calculate();

        $this->response->html($this->helper->layout->app('companyAgile:management/layout', array_merge($context, array(
            'title' => $title,
            'content_template' => 'companyAgile:management/people',
            'paginator' => $paginator,
            'role_label' => $role === Role::PROJECT_MANAGER ? t('Project manager') : t('Project member'),
        ))));
    }

    private function renderTasks($isActive, $action, $title)
    {
        $context = $this->getContext($action);
        $query = $this->taskFinderModel->getProjectUserOverviewQuery($context['project_ids'], $isActive);
        if (! empty($context['selected_project_ids'])) $query->in(TaskModel::TABLE.'.project_id', $context['selected_project_ids']);
        if (! empty($context['selected_column_ids'])) $query->in(TaskModel::TABLE.'.column_id', $context['selected_column_ids']);
        if ($context['user_id'] > 0) {
            $query->eq(TaskModel::TABLE.'.owner_id', $context['user_id']);
        }
        if ($context['search'] !== '') {
            if (ctype_digit($context['search']) || (strlen($context['search']) > 1 && $context['search'][0] === '#' && ctype_digit(substr($context['search'], 1)))) {
                $query->beginOr()->eq(TaskModel::TABLE.'.id', ltrim($context['search'], '#'))->ilike(TaskModel::TABLE.'.title', '%'.$context['search'].'%')->closeOr();
            } else {
                $query->ilike(TaskModel::TABLE.'.title', '%'.$context['search'].'%');
            }
        }

        $paginator = $this->paginator
            ->setUrl('ManagementDashboardController', $action, array('plugin' => 'CompanyAgile', 'project_ids' => implode(',', $context['selected_project_ids']), 'column_ids' => implode(',', $context['selected_column_ids']), 'user_id' => $context['user_id'], 'search' => $context['search']))
            ->setMax(50)
            ->setOrder(TaskModel::TABLE.'.id')
            ->setQuery($query)
            ->calculate();

        $this->response->html($this->helper->layout->app('companyAgile:management/layout', array_merge($context, array(
            'title' => $title,
            'content_template' => 'companyAgile:management/tasks',
            'is_active' => $isActive,
            'summary' => $this->getTaskSummary($context, $isActive),
            'paginator' => $paginator,
        ))));
    }

    private function getContext($action)
    {
        if (! $this->helper->user->hasAccess('ProjectUserOverviewController', $action)) {
            throw new AccessForbiddenException();
        }

        $projects = $this->getAllowedProjects();

        $projectId = $this->request->getIntegerParam('project_id');
        if ($projectId > 0 && ! isset($projects[$projectId])) {
            throw new AccessForbiddenException();
        }
        $isTaskView = $action === 'opens' || $action === 'closed';
        $selectedProjectIds = $isTaskView ? $this->getIntegerListParam('project_ids') : array();
        $selectedColumnIds = $isTaskView ? $this->getIntegerListParam('column_ids') : array();
        foreach ($selectedProjectIds as $selectedProjectId) {
            if (! isset($projects[$selectedProjectId])) throw new AccessForbiddenException();
        }
        $users = array();
        $userId = $isTaskView ? $this->request->getIntegerParam('user_id') : 0;
        if ($userId > 0) {
            $selectedUser = $this->userModel->getById($userId);
            if (empty($selectedUser) || (int) $selectedUser['is_active'] !== 1 || (! empty($selectedProjectIds) && ! $this->isProjectMemberOfAny($selectedProjectIds, $userId))) {
                $userId = 0;
            } else {
                $users[$userId] = $this->helper->user->getFullname($selectedUser);
            }
        }

        return array(
            'action' => $action,
            'project_id' => $projectId,
            'user_id' => $userId,
            'project_ids' => array_keys($projects),
            'projects' => $projects,
            'users' => $users,
            'selected_project_ids' => $selectedProjectIds,
            'selected_column_ids' => $selectedColumnIds,
            'user_search_url' => $this->helper->url->to('ManagementDashboardController', 'users', array('plugin' => 'CompanyAgile')),
            'search' => trim($this->request->getStringParam('search')),
        );
    }

    private function getAllowedProjects()
    {
        if ($this->userSession->isAdmin()) {
            $rows = $this->db->table(ProjectModel::TABLE)->eq('is_active', ProjectModel::ACTIVE)->columns('id', 'name')->asc('name')->findAll();
            $projects = array();
            foreach ($rows as $row) $projects[(int) $row['id']] = $row['name'];
            return $projects;
        }

        $projects = $this->projectUserRoleModel->getActiveProjectsByUser($this->userSession->getId());
        natcasesort($projects);
        return $projects;
    }

    private function isProjectMemberOfAny(array $projectIds, $userId)
    {
        $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
        $sql = 'SELECT 1 FROM project_has_users WHERE project_id IN ('.$placeholders.') AND user_id = ? AND role <> ?
                UNION
                SELECT 1 FROM project_has_groups
                INNER JOIN group_has_users ON group_has_users.group_id = project_has_groups.group_id
                WHERE project_has_groups.project_id IN ('.$placeholders.') AND group_has_users.user_id = ? AND project_has_groups.role <> ?
                LIMIT 1';
        $params = array_merge($projectIds, array($userId, Role::PROJECT_VIEWER), $projectIds, array($userId, Role::PROJECT_VIEWER));
        return (bool) $this->db->execute($sql, $params)->fetchColumn();
    }

    private function getAssignableUsersForProjects(array $projectIds)
    {
        $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
        $sql = 'SELECT DISTINCT users.id, users.username, users.name FROM users
                INNER JOIN (
                    SELECT user_id FROM project_has_users WHERE project_id IN ('.$placeholders.') AND role <> ?
                    UNION
                    SELECT group_has_users.user_id FROM project_has_groups
                    INNER JOIN group_has_users ON group_has_users.group_id = project_has_groups.group_id
                    WHERE project_has_groups.project_id IN ('.$placeholders.') AND project_has_groups.role <> ?
                ) members ON members.user_id = users.id
                WHERE users.is_active = 1 ORDER BY users.name, users.username';
        return $this->db->execute($sql, array_merge($projectIds, array(Role::PROJECT_VIEWER), $projectIds, array(Role::PROJECT_VIEWER)))->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getIntegerListParam($name)
    {
        $values = array_filter(array_map('intval', explode(',', $this->request->getStringParam($name))));
        return array_values(array_unique($values));
    }

    private function getTaskSummary(array $context, $isActive)
    {
        if (empty($context['project_ids'])) {
            return array('task_count' => 0, 'project_count' => 0, 'user_count' => 0, 'projects' => array(), 'columns' => array());
        }

        list($where, $params) = $this->buildTaskSummaryWhere($context, $isActive);
        list($projectWhere, $projectParams) = $this->buildTaskSummaryWhere($context, $isActive, 'projects');
        list($columnWhere, $columnParams) = $this->buildTaskSummaryWhere($context, $isActive, 'columns');

        $metrics = $this->db->execute('SELECT COUNT(*) AS task_count, COUNT(DISTINCT tasks.project_id) AS project_count, COUNT(DISTINCT NULLIF(tasks.owner_id, 0)) AS user_count FROM tasks WHERE '.$where, $params)->fetch(\PDO::FETCH_ASSOC);
        $projects = $this->db->execute('SELECT projects.id, projects.name, COUNT(*) AS task_count FROM tasks INNER JOIN projects ON projects.id = tasks.project_id WHERE '.$projectWhere.' GROUP BY projects.id, projects.name ORDER BY task_count DESC, projects.name ASC', $projectParams)->fetchAll(\PDO::FETCH_ASSOC);
        $columnRows = $this->db->execute('SELECT columns.id, columns.title, COUNT(*) AS task_count FROM tasks INNER JOIN columns ON columns.id = tasks.column_id WHERE '.$columnWhere.' GROUP BY columns.id, columns.title ORDER BY task_count DESC, columns.title ASC', $columnParams)->fetchAll(\PDO::FETCH_ASSOC);
        $allColumnParams = array_values(array_map('intval', $context['project_ids']));
        $allColumnRows = $this->db->execute('SELECT id, title FROM columns WHERE project_id IN ('.implode(',', array_fill(0, count($allColumnParams), '?')).')', $allColumnParams)->fetchAll(\PDO::FETCH_ASSOC);
        $allColumnIdsByTitle = array();
        foreach ($allColumnRows as $column) $allColumnIdsByTitle[mb_strtolower($column['title'], 'UTF-8')][] = (int) $column['id'];
        $columns = array();
        foreach ($columnRows as $column) {
            $key = mb_strtolower($column['title'], 'UTF-8');
            if (! isset($columns[$key])) $columns[$key] = array('ids' => array(), 'title' => $column['title'], 'task_count' => 0);
            $columns[$key]['ids'] = $allColumnIdsByTitle[$key];
            $columns[$key]['task_count'] += (int) $column['task_count'];
        }
        $columns = array_values($columns);

        return array(
            'task_count' => (int) $metrics['task_count'],
            'project_count' => (int) $metrics['project_count'],
            'user_count' => (int) $metrics['user_count'],
            'projects' => $projects,
            'columns' => $columns,
        );
    }

    private function buildTaskSummaryWhere(array $context, $isActive, $exclude = '')
    {
        $params = array_values(array_map('intval', $context['project_ids']));
        $where = 'tasks.project_id IN ('.implode(',', array_fill(0, count($params), '?')).') AND tasks.is_active = ?';
        $params[] = (int) $isActive;
        if ($exclude !== 'projects' && ! empty($context['selected_project_ids'])) {
            $where .= ' AND tasks.project_id IN ('.implode(',', array_fill(0, count($context['selected_project_ids']), '?')).')';
            $params = array_merge($params, $context['selected_project_ids']);
        }
        if ($exclude !== 'columns' && ! empty($context['selected_column_ids'])) {
            $where .= ' AND tasks.column_id IN ('.implode(',', array_fill(0, count($context['selected_column_ids']), '?')).')';
            $params = array_merge($params, $context['selected_column_ids']);
        }
        if ($context['user_id'] > 0) { $where .= ' AND tasks.owner_id = ?'; $params[] = $context['user_id']; }
        if ($context['search'] !== '') {
            if (ctype_digit($context['search']) || (strlen($context['search']) > 1 && $context['search'][0] === '#' && ctype_digit(substr($context['search'], 1)))) {
                $where .= ' AND (tasks.id = ? OR tasks.title LIKE ?)';
                $params[] = (int) ltrim($context['search'], '#');
                $params[] = '%'.$context['search'].'%';
            } else { $where .= ' AND tasks.title LIKE ?'; $params[] = '%'.$context['search'].'%'; }
        }
        return array($where, $params);
    }
}
