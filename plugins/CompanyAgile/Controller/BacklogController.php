<?php

namespace Kanboard\Plugin\CompanyAgile\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;

class BacklogController extends BaseController
{
    public function show()
    {
        $project = $this->getProject();
        $this->assertProjectAccess($project['id']);
        $context = $this->sprintModel->getDefaultTaskContext($project['id']);

        $this->response->html($this->helper->layout->app('companyAgile:backlog/show', array(
            'title' => t('CompanyAgile: Backlog'),
            'project' => $project,
            'planning' => $this->sprintModel->getPlanningData($project['id']),
            'task_context' => $context,
            'can_manage_sprints' => $this->userSession->isAdmin() || $this->helper->projectRole->getProjectUserRole($project['id']) === 'project-manager',
            'can_modify_tasks' => $this->helper->user->hasProjectAccess('TaskModificationController', 'update', $project['id']),
            'epics' => $this->epicModel->getEpicsForProject($project['id']),
        )));
    }

    private function assertProjectAccess($projectId)
    {
        if (! $this->projectPermissionModel->isUserAllowed($projectId, $this->userSession->getId())) {
            throw new AccessForbiddenException();
        }
    }
}
