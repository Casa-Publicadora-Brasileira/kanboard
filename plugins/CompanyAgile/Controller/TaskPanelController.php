<?php

namespace Kanboard\Plugin\CompanyAgile\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Model\UserMetadataModel;

class TaskPanelController extends BaseController
{
    public function show()
    {
        $task = $this->getTask();
        $this->assertProjectAccess($task['project_id']);
        $commentSorting = $this->userMetadataCacheDecorator->get(UserMetadataModel::KEY_COMMENT_SORTING_DIRECTION, 'ASC');
        $issueType = $this->issueTypeModel->getByTaskId($task['id']);
        $epicProgress = $issueType['code'] === 'epic' ? $this->epicModel->getProgress($task['id']) : array();

        $this->response->html($this->template->render('companyAgile:task/panel', array(
            'task' => $task,
            'project' => $this->projectModel->getById($task['project_id']),
            'issue_type' => $issueType,
            'issue_types' => $this->issueTypeModel->getActiveTypes(),
            'story_points' => $this->estimateModel->getByTaskId($task['id']),
            'parent_epic' => $issueType['code'] === 'story' ? $this->epicModel->getParentForStory($task['id']) : array(),
            'project_epics' => $issueType['code'] === 'story' ? $this->epicModel->getEpicsForProject($task['project_id']) : array(),
            'epic_progress' => $epicProgress,
            'available_stories' => $issueType['code'] === 'epic' ? $this->epicModel->getAvailableStories($task['project_id'], $task['id']) : array(),
            'can_modify' => $this->helper->projectRole->canUpdateTask($task),
            'sprint' => $this->sprintModel->getCurrentSprintForTask($task['id']),
            'tags' => $this->taskTagModel->getTagsByTask($task['id']),
            'subtasks' => $this->subtaskModel->getAll($task['id']),
            'comments' => $this->commentModel->getAll($task['id'], $commentSorting),
            'files' => $this->taskFileModel->getAllDocuments($task['id']),
            'images' => $this->taskFileModel->getAllImages($task['id']),
            'events' => $this->helper->projectActivity->getTaskEvents($task['id']),
        )));
    }

    private function assertProjectAccess($projectId)
    {
        if (! $this->projectPermissionModel->isUserAllowed($projectId, $this->userSession->getId())) {
            throw new AccessForbiddenException();
        }
    }
}
