<?php

namespace Kanboard\Plugin\CompanyAgile\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;

class AgileTaskController extends BaseController
{
    public function estimate()
    {
        $this->checkCSRFForm();
        $task = $this->getTask();
        $this->assertCanModify($task);
        $values = $this->request->getValues();
        $error = '';
        $success = $this->estimateModel->setStoryPoints($task['id'], isset($values['story_points']) ? $values['story_points'] : '', $this->userSession->getId(), $error);
        $this->json($success, $success ? t('CompanyAgile: Story Points updated.') : t('CompanyAgile: Invalid Story Points.'));
    }

    public function time()
    {
        $this->checkCSRFForm();
        $task = $this->getTask();
        $this->assertCanModify($task);
        $values = $this->request->getValues();
        $estimated = isset($values['time_estimated']) ? trim($values['time_estimated']) : '';
        $spent = isset($values['time_spent']) ? trim($values['time_spent']) : '';
        if (! $this->validHours($estimated) || ! $this->validHours($spent)) {
            $this->json(false, t('CompanyAgile: Invalid time estimate.'));
            return;
        }
        $success = $this->taskModificationModel->update(array('id' => $task['id'], 'time_estimated' => (float) $estimated, 'time_spent' => (float) $spent));
        $this->json($success, $success ? t('CompanyAgile: Time estimate updated.') : t('CompanyAgile: Unable to update time estimate.'));
    }

    public function epic()
    {
        $this->checkCSRFForm();
        $task = $this->getTask();
        $this->assertCanModify($task);
        $values = $this->request->getValues();
        $epicId = isset($values['epic_id']) ? (int) $values['epic_id'] : 0;
        $current = $this->epicModel->getParentForStory($task['id']);
        if ($epicId === 0) {
            $success = empty($current) || $this->epicModel->remove($task['project_id'], $current['id'], $task['id']);
        } elseif (! empty($current) && (int) $current['id'] === $epicId) {
            $success = true;
        } elseif (! empty($current)) {
            $success = false;
        } else {
            $success = $this->epicModel->link($task['project_id'], $epicId, $task['id'], $this->userSession->getId());
        }
        $this->json($success, $success ? t('CompanyAgile: Epic updated.') : t('CompanyAgile: Unable to update Epic.'));
    }

    public function addStory()
    {
        $this->checkCSRFForm();
        $epic = $this->getTask();
        $this->assertCanModify($epic);
        $values = $this->request->getValues();
        $success = $this->epicModel->link($epic['project_id'], $epic['id'], isset($values['story_id']) ? (int) $values['story_id'] : 0, $this->userSession->getId());
        $this->json($success, $success ? t('CompanyAgile: Story added to Epic.') : t('CompanyAgile: Unable to add Story.'));
    }

    public function removeStory()
    {
        $this->checkCSRFForm();
        $epic = $this->getTask();
        $this->assertCanModify($epic);
        $values = $this->request->getValues();
        $success = $this->epicModel->remove($epic['project_id'], $epic['id'], isset($values['story_id']) ? (int) $values['story_id'] : 0);
        $this->json($success, $success ? t('CompanyAgile: Story removed from Epic.') : t('CompanyAgile: Unable to remove Story.'));
    }

    public function issueType()
    {
        $this->checkCSRFForm();
        $task = $this->getTask();
        $this->assertCanModify($task);
        $values = $this->request->getValues();
        $success = $this->issueTypeModel->assign($task['id'], isset($values['issue_type_id']) ? (int) $values['issue_type_id'] : 0);
        $this->json($success, $success ? t('CompanyAgile: Issue type updated.') : t('CompanyAgile: Remove active Epic links before changing the issue type.'));
    }

    private function assertCanModify(array $task)
    {
        if (! $this->projectPermissionModel->isUserAllowed($task['project_id'], $this->userSession->getId()) || ! $this->helper->projectRole->canUpdateTask($task)) {
            throw new AccessForbiddenException();
        }
    }

    private function validHours($value)
    {
        return $value !== '' && is_numeric($value) && is_finite((float) $value) && (float) $value >= 0 && (float) $value <= 1000000;
    }

    private function json($success, $message)
    {
        $this->response->json(array('success' => (bool) $success, 'message' => $message), $success ? 200 : 422);
    }
}
