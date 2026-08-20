<?php

namespace Kanboard\Plugin\CompanyAgile\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;

class SprintTaskController extends BaseController
{
    public function move()
    {
        $this->checkCSRFForm();
        $project = $this->getProject();
        if (! $this->projectPermissionModel->isUserAllowed($project['id'], $this->userSession->getId())
            || ! $this->helper->user->hasProjectAccess('TaskModificationController', 'update', $project['id'])) {
            throw new AccessForbiddenException();
        }
        $values = $this->request->getValues();
        $success = $this->sprintModel->moveTask(
            $project['id'],
            isset($values['task_id']) ? (int) $values['task_id'] : 0,
            isset($values['sprint_id']) ? (int) $values['sprint_id'] : 0,
            isset($values['position']) ? (int) $values['position'] : 0
        );
        $this->response->json(array(
            'success' => $success,
            'message' => $success ? t('CompanyAgile: Task planning updated.') : t('CompanyAgile: Unable to update task planning.'),
        ), $success ? 200 : 422);
    }
}
