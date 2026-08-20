<?php

namespace Kanboard\Plugin\CompanyAgile\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Core\Controller\PageNotFoundException;

class SprintController extends BaseController
{
    public function index()
    {
        $project = $this->getProject();
        $this->assertProjectAccess($project['id']);
        $this->response->html($this->helper->layout->app('companyAgile:sprint/index', array(
            'title' => t('CompanyAgile: Sprints'),
            'project' => $project,
            'sprints' => $this->sprintModel->getSprints($project['id']),
            'can_manage_sprints' => $this->canManage($project['id']),
        )));
    }

    public function create()
    {
        $project = $this->getProject();
        $this->assertManager($project['id']);
        $this->response->html($this->template->render('companyAgile:sprint/form', array(
            'project' => $project, 'sprint' => array(), 'action' => 'save',
        )));
    }

    public function save()
    {
        $this->checkCSRFForm();
        $project = $this->getProject();
        $this->assertManager($project['id']);
        $values = $this->request->getValues();
        if (trim(isset($values['name']) ? $values['name'] : '') === '') {
            $this->flash->failure(t('CompanyAgile: Sprint name is required.'));
        } else {
            $values['project_id'] = $project['id'];
            $id = $this->sprintModel->create($values, $this->userSession->getId());
            $id ? $this->flash->success(t('CompanyAgile: Sprint created.')) : $this->flash->failure(t('CompanyAgile: Unable to create Sprint.'));
        }
        $this->redirectBacklog($project['id']);
    }

    public function edit()
    {
        $project = $this->getProject();
        $this->assertManager($project['id']);
        $sprint = $this->getSprint($project['id']);
        $this->response->html($this->template->render('companyAgile:sprint/form', array(
            'project' => $project, 'sprint' => $sprint, 'action' => 'update',
        )));
    }

    public function update()
    {
        $this->checkCSRFForm();
        $project = $this->getProject();
        $this->assertManager($project['id']);
        $sprint = $this->getSprint($project['id']);
        $values = $this->request->getValues();
        $this->sprintModel->updatePlanned($sprint, $values)
            ? $this->flash->success(t('CompanyAgile: Sprint updated.'))
            : $this->flash->failure(t('CompanyAgile: Unable to update Sprint.'));
        $this->redirectBacklog($project['id']);
    }

    public function start()
    {
        $this->checkCSRFForm();
        $project = $this->getProject();
        $this->assertManager($project['id']);
        $sprint = $this->getSprint($project['id']);
        $this->sprintModel->start($sprint)
            ? $this->flash->success(t('CompanyAgile: Sprint started.'))
            : $this->flash->failure(t('CompanyAgile: Another Sprint is active or this Sprint cannot be started.'));
        $this->redirectBacklog($project['id']);
    }

    public function completeForm()
    {
        $project = $this->getProject();
        $this->assertManager($project['id']);
        $sprint = $this->getSprint($project['id']);
        if ($sprint['status'] !== 'active') {
            throw new AccessForbiddenException();
        }
        $destinations = array_filter($this->sprintModel->getSprints($project['id'], false), function ($candidate) use ($sprint) {
            return $candidate['status'] === 'planned' && $candidate['id'] != $sprint['id'];
        });
        $this->response->html($this->template->render('companyAgile:sprint/complete', array(
            'project' => $project, 'sprint' => $sprint,
            'summary' => $this->sprintModel->getCompletionSummary($sprint),
            'destinations' => $destinations,
        )));
    }

    public function complete()
    {
        $this->checkCSRFForm();
        $project = $this->getProject();
        $this->assertManager($project['id']);
        $sprint = $this->getSprint($project['id']);
        $values = $this->request->getValues();
        $destination = isset($values['destination_sprint_id']) ? (int) $values['destination_sprint_id'] : 0;
        $this->sprintModel->complete($sprint, $destination)
            ? $this->flash->success(t('CompanyAgile: Sprint completed.'))
            : $this->flash->failure(t('CompanyAgile: Unable to complete Sprint.'));
        $this->redirectBacklog($project['id']);
    }

    private function getSprint($projectId)
    {
        $sprint = $this->sprintModel->getById($this->request->getIntegerParam('sprint_id'));
        if (empty($sprint) || (int) $sprint['project_id'] !== (int) $projectId) {
            throw new PageNotFoundException();
        }
        return $sprint;
    }

    private function canManage($projectId)
    {
        return $this->userSession->isAdmin() || $this->helper->projectRole->getProjectUserRole($projectId) === 'project-manager';
    }

    private function assertManager($projectId)
    {
        $this->assertProjectAccess($projectId);
        if (! $this->canManage($projectId)) {
            throw new AccessForbiddenException();
        }
    }

    private function assertProjectAccess($projectId)
    {
        if (! $this->projectPermissionModel->isUserAllowed($projectId, $this->userSession->getId())) {
            throw new AccessForbiddenException();
        }
    }

    private function redirectBacklog($projectId)
    {
        $this->response->redirect($this->helper->url->to('BacklogController', 'show', array('plugin' => 'CompanyAgile', 'project_id' => $projectId)), true);
    }
}
