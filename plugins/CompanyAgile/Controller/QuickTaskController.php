<?php

namespace Kanboard\Plugin\CompanyAgile\Controller;

use Exception;
use InvalidArgumentException;
use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Model\TaskModel;

class QuickTaskController extends BaseController
{
    public function show()
    {
        $project = $this->getProject();
        $columnId = $this->request->getIntegerParam('column_id');
        $swimlaneId = $this->request->getIntegerParam('swimlane_id');
        $this->assertCanCreate($project, $columnId);

        $usersList = $this->projectUserRoleModel->getAssignableUsersList($project['id'], true, false, $project['is_private'] == 1);
        $swimlanesList = $this->swimlaneModel->getList($project['id'], false, true);
        $projectsList = $this->projectUserRoleModel->getActiveProjectsByUser($this->userSession->getId());
        unset($projectsList[$project['id']]);
        natcasesort($projectsList);
        $values = array(
            'project_id' => $project['id'],
            'column_id' => $columnId,
            'swimlane_id' => $swimlaneId ?: key($swimlanesList),
            'owner_id' => $project['is_private'] == 1 ? $this->userSession->getId() : 0,
            'color_id' => $this->colorModel->getDefaultColor(),
        );
        $this->response->html($this->template->render('companyAgile:task/quick_create', array(
            'project' => $project,
            'column_id' => $columnId,
            'swimlane_id' => $swimlaneId,
            'issue_types' => $this->issueTypeModel->getActiveTypes(),
            'users_list' => $usersList,
            'current_user_id' => $this->userSession->getId(),
            'can_assign_self' => array_key_exists($this->userSession->getId(), $usersList),
            'sprint_id' => $this->request->getIntegerParam('sprint_id'),
            'selected_issue_type' => $this->request->getStringParam('issue_type'),
            'epics' => $this->epicModel->getEpicsForProject($project['id']),
            'values' => $values,
            'errors' => array(),
            'columns_list' => $this->columnModel->getList($project['id']),
            'categories_list' => $this->categoryModel->getList($project['id']),
            'swimlanes_list' => $swimlanesList,
            'screenshot' => '',
            'files' => array(),
            'projects_list' => $projectsList,
            'relation_types' => $this->linkModel->getList(0, false),
            'task_search_url' => $this->helper->url->to('TaskAjaxController', 'autocomplete'),
        )));
    }

    public function save()
    {
        $this->checkCSRFForm();
        $project = $this->getProject();
        $values = $this->request->getValues();
        $values['project_id'] = $project['id'];
        $values['column_id'] = isset($values['column_id']) ? (int) $values['column_id'] : 0;
        $values['swimlane_id'] = isset($values['swimlane_id']) ? (int) $values['swimlane_id'] : 0;
        $values['owner_id'] = isset($values['owner_id']) ? (int) $values['owner_id'] : 0;
        $values['priority'] = isset($values['priority']) ? (int) $values['priority'] : 0;
        $values['color_id'] = isset($values['color_id']) ? $values['color_id'] : $this->colorModel->getDefaultColor();
        $files = $this->request->getFileInfo('files');
        $screenshot = isset($values['screenshot']) ? $values['screenshot'] : null;
        $issueTypeId = isset($values['issue_type_id']) ? (int) $values['issue_type_id'] : 0;
        $sprintId = isset($values['sprint_id']) ? (int) $values['sprint_id'] : 0;
        $epicId = isset($values['epic_id']) ? (int) $values['epic_id'] : 0;
        $storyPoints = isset($values['story_points']) ? $values['story_points'] : '';
        $duplicateProjectIds = isset($values['duplicate_project_ids']) && is_array($values['duplicate_project_ids']) ? array_map('intval', $values['duplicate_project_ids']) : array();
        $issueType = $this->issueTypeModel->getById($issueTypeId);
        if (empty($issueType)) {
            $this->validationErrorResponse(array('issue_type_id' => array(t('CompanyAgile: Issue type is required.'))));
            return;
        }
        try {
            $relations = $this->getRequestedRelations($values);
        } catch (InvalidArgumentException $e) {
            $this->validationErrorResponse(array('relations' => array($e->getMessage())));
            return;
        }
        unset($values['issue_type_id'], $values['sprint_id'], $values['epic_id'], $values['story_points'], $values['duplicate_project_ids'], $values['relation_link_ids'], $values['relation_task_ids'], $values['csrf_token'], $values['screenshot']);

        $this->assertCanCreate($project, $values['column_id']);
        try {
            $this->validateRelations($project, $relations);
        } catch (InvalidArgumentException $e) {
            $this->validationErrorResponse(array('relations' => array($e->getMessage())));
            return;
        }
        list($valid, $errors) = $this->taskValidator->validateCreation($values);
        if (! $valid) {
            $this->validationErrorResponse($errors);
            return;
        }

        $this->db->startTransaction();
        $createdTaskLinkIds = array();
        try {
            $taskId = $this->taskCreationModel->create($values);
            if ($taskId === 0 || ! $this->issueTypeModel->assign($taskId, $issueTypeId)) {
                throw new Exception('Unable to create task with issue type');
            }
            if (! empty($issueType) && $issueType['code'] !== 'epic' && $storyPoints !== '') {
                $estimateError = '';
                if (! $this->estimateModel->setStoryPoints($taskId, $storyPoints, $this->userSession->getId(), $estimateError)) {
                    throw new Exception('Invalid story points');
                }
            }
            if (! empty($issueType) && $issueType['code'] === 'story' && $epicId > 0) {
                $this->epicModel->linkWithinTransaction($project['id'], $epicId, $taskId, $this->userSession->getId());
            }
            if ($sprintId > 0) {
                $this->sprintModel->moveTaskWithinTransaction($project['id'], $taskId, $sprintId);
            }
            foreach ($relations as $relation) {
                list($linkValid) = $this->taskLinkValidator->validateCreation(array(
                    'task_id' => $taskId,
                    'opposite_task_id' => $relation['task_id'],
                    'link_id' => $relation['link_id'],
                ));
                if (! $linkValid) {
                    throw new InvalidArgumentException(t('CompanyAgile: One or more internal links are invalid.'));
                }
                $linkIds = $this->internalLinkModel->createWithinTransaction($taskId, $relation['task_id'], $relation['link_id']);
                if ($linkIds === false) {
                    throw new InvalidArgumentException(t('CompanyAgile: The same internal link already exists.'));
                }
                $createdTaskLinkIds = array_merge($createdTaskLinkIds, $linkIds);
            }
            $this->db->closeTransaction();
        } catch (InvalidArgumentException $e) {
            $this->db->cancelTransaction();
            $this->validationErrorResponse(array('relations' => array($e->getMessage())));
            return;
        } catch (Exception $e) {
            $this->db->cancelTransaction();
            $this->response->json(array('success' => false, 'message' => t('CompanyAgile: Unable to create the task.')), 500);
            return;
        }

        if (! empty($createdTaskLinkIds)) {
            $this->internalLinkModel->publishCreatedEvents($createdTaskLinkIds);
        }

        if ($screenshot) {
            $this->taskFileModel->uploadScreenshot($taskId, $screenshot);
        }
        if (isset($files['name'][0]) && $files['name'][0] !== '' && ! $this->taskFileModel->uploadFiles($taskId, $files)) {
            $this->response->json(array('success' => false, 'message' => t('Unable to upload files, check the permissions of your data folder.')), 500);
            return;
        }
        if (isset($values['duplicate_multiple_projects']) && $values['duplicate_multiple_projects'] == 1) {
            foreach ($duplicateProjectIds as $duplicateProjectId) {
                if ($duplicateProjectId !== (int) $project['id'] && $this->projectPermissionModel->isUserAllowed($duplicateProjectId, $this->userSession->getId())) {
                    $this->taskProjectDuplicationModel->duplicateToProject($taskId, $duplicateProjectId);
                }
            }
        }

        $this->response->json(array(
            'success' => true,
            'task_id' => $taskId,
            'message' => t('CompanyAgile: Task created successfully.'),
            'board_url' => $this->helper->url->to('BoardViewController', 'show', array('project_id' => $project['id'])),
            'another_task' => isset($values['another_task']) && $values['another_task'] == 1,
        ));
    }

    private function assertCanCreate(array $project, $columnId)
    {
        if (! $this->projectPermissionModel->isUserAllowed($project['id'], $this->userSession->getId())
            || ! $this->helper->user->hasProjectAccess('TaskCreationController', 'save', $project['id'])
            || ! $this->helper->projectRole->canCreateTaskInColumn($project['id'], $columnId)) {
            throw new AccessForbiddenException();
        }
    }

    private function validationErrorResponse(array $errors)
    {
        $normalized = array();
        foreach ($errors as $field => $messages) {
            $messages = is_array($messages) ? $messages : array($messages);
            $normalized[$field] = implode(' ', array_filter(array_map('strval', $messages)));
        }
        $this->response->json(array(
            'success' => false,
            'message' => t('CompanyAgile: Check the highlighted fields.'),
            'errors' => $normalized,
        ), 422);
    }

    private function getRequestedRelations(array $values)
    {
        $linkIds = isset($values['relation_link_ids']) && is_array($values['relation_link_ids']) ? $values['relation_link_ids'] : array();
        $taskIds = isset($values['relation_task_ids']) && is_array($values['relation_task_ids']) ? $values['relation_task_ids'] : array();

        if (count($linkIds) !== count($taskIds)) {
            throw new InvalidArgumentException(t('CompanyAgile: One or more internal links are invalid.'));
        }

        $relations = array();
        foreach ($linkIds as $index => $linkId) {
            $relations[] = array('link_id' => (int) $linkId, 'task_id' => (int) $taskIds[$index]);
        }

        return $relations;
    }

    private function validateRelations(array $project, array $relations)
    {
        if (! empty($relations) && ! $this->helper->user->hasProjectAccess('TaskInternalLinkController', 'save', $project['id'])) {
            throw new AccessForbiddenException();
        }

        $relationTypes = array();
        foreach ($this->linkModel->getAll() as $relationType) {
            $relationTypes[(int) $relationType['id']] = true;
        }

        $taskIds = array_values(array_unique(array_map(function (array $relation) {
            return $relation['task_id'];
        }, $relations)));
        $tasks = empty($taskIds) ? array() : $this->db->table(TaskModel::TABLE)->in('id', $taskIds)->findAll();
        $tasksById = array();
        foreach ($tasks as $task) {
            $tasksById[(int) $task['id']] = $task;
        }
        $allowedProjectIds = array_map('intval', $this->projectPermissionModel->getActiveProjectIds($this->userSession->getId()));

        $seen = array();
        foreach ($relations as $relation) {
            if ($relation['link_id'] <= 0 || $relation['task_id'] <= 0 || ! isset($relationTypes[$relation['link_id']])) {
                throw new InvalidArgumentException(t('CompanyAgile: One or more internal links are invalid.'));
            }
            if (! isset($tasksById[$relation['task_id']]) || ! in_array((int) $tasksById[$relation['task_id']]['project_id'], $allowedProjectIds, true)) {
                throw new InvalidArgumentException(t('CompanyAgile: The related task does not exist or is not accessible.'));
            }
            $key = $relation['link_id'].'-'.$relation['task_id'];
            if (isset($seen[$key])) {
                throw new InvalidArgumentException(t('CompanyAgile: Duplicate internal links are not allowed.'));
            }
            $seen[$key] = true;
        }
    }
}
