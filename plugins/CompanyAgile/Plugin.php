<?php

namespace Kanboard\Plugin\CompanyAgile;

use Kanboard\Core\Translator;
use Kanboard\Core\Plugin\Base;

class Plugin extends Base
{
    public function initialize()
    {
        Translator::load($this->languageModel->getCurrentLanguage(), __DIR__.'/Locale');

        $this->route->addRoute('/company-agile/task/:task_id/panel', 'TaskPanelController', 'show', 'CompanyAgile');
        $this->route->addRoute('/company-agile/project/:project_id/quick-create', 'QuickTaskController', 'show', 'CompanyAgile');
        $this->route->addRoute('/company-agile/project/:project_id/quick-create/save', 'QuickTaskController', 'save', 'CompanyAgile');
        $this->route->addRoute('/company-agile/project/:project_id/backlog', 'BacklogController', 'show', 'CompanyAgile');
        $this->route->addRoute('/company-agile/project/:project_id/sprints', 'SprintController', 'index', 'CompanyAgile');
        $this->route->addRoute('/company-agile/project/:project_id/sprint/create', 'SprintController', 'create', 'CompanyAgile');
        $this->route->addRoute('/company-agile/project/:project_id/sprint/save', 'SprintController', 'save', 'CompanyAgile');
        $this->route->addRoute('/company-agile/project/:project_id/sprint/:sprint_id/edit', 'SprintController', 'edit', 'CompanyAgile');
        $this->route->addRoute('/company-agile/project/:project_id/sprint/:sprint_id/update', 'SprintController', 'update', 'CompanyAgile');
        $this->route->addRoute('/company-agile/project/:project_id/sprint/:sprint_id/start', 'SprintController', 'start', 'CompanyAgile');
        $this->route->addRoute('/company-agile/project/:project_id/sprint/:sprint_id/complete', 'SprintController', 'completeForm', 'CompanyAgile');
        $this->route->addRoute('/company-agile/project/:project_id/sprint/:sprint_id/complete/save', 'SprintController', 'complete', 'CompanyAgile');
        $this->route->addRoute('/company-agile/project/:project_id/sprint-task/move', 'SprintTaskController', 'move', 'CompanyAgile');
        $this->route->addRoute('/company-agile/task/:task_id/story-points', 'AgileTaskController', 'estimate', 'CompanyAgile');
        $this->route->addRoute('/company-agile/task/:task_id/time', 'AgileTaskController', 'time', 'CompanyAgile');
        $this->route->addRoute('/company-agile/task/:task_id/epic', 'AgileTaskController', 'epic', 'CompanyAgile');
        $this->route->addRoute('/company-agile/task/:task_id/epic/add-story', 'AgileTaskController', 'addStory', 'CompanyAgile');
        $this->route->addRoute('/company-agile/task/:task_id/epic/remove-story', 'AgileTaskController', 'removeStory', 'CompanyAgile');
        $this->route->addRoute('/company-agile/task/:task_id/issue-type', 'AgileTaskController', 'issueType', 'CompanyAgile');
        $this->route->addRoute('/company-agile/project/:project_id/filter/tag', 'FilterController', 'createTag', 'CompanyAgile');
        $this->route->addRoute('/company-agile/management/managers', 'ManagementDashboardController', 'managers', 'CompanyAgile');
        $this->route->addRoute('/company-agile/management/members', 'ManagementDashboardController', 'members', 'CompanyAgile');
        $this->route->addRoute('/company-agile/management/open-tasks', 'ManagementDashboardController', 'opens', 'CompanyAgile');
        $this->route->addRoute('/company-agile/management/completed-tasks', 'ManagementDashboardController', 'closed', 'CompanyAgile');
        $this->route->addRoute('/company-agile/management/users', 'ManagementDashboardController', 'users', 'CompanyAgile');

        // Canonical Portal Management URLs. The legacy aliases above remain valid
        // for bookmarks and integrations while the technical plugin key is kept.
        $this->route->addRoute('/portal-management/project/:project_id/backlog', 'BacklogController', 'show', 'CompanyAgile');
        $this->route->addRoute('/portal-management/project/:project_id/sprints', 'SprintController', 'index', 'CompanyAgile');
        $this->route->addRoute('/portal-management/management/managers', 'ManagementDashboardController', 'managers', 'CompanyAgile');
        $this->route->addRoute('/portal-management/management/members', 'ManagementDashboardController', 'members', 'CompanyAgile');
        $this->route->addRoute('/portal-management/management/open-tasks', 'ManagementDashboardController', 'opens', 'CompanyAgile');
        $this->route->addRoute('/portal-management/management/completed-tasks', 'ManagementDashboardController', 'closed', 'CompanyAgile');
        $this->route->addRoute('/portal-management/management/users', 'ManagementDashboardController', 'users', 'CompanyAgile');
        $this->route->addRoute('/portal-management/my-tasks', 'MyTasksController', 'show', 'CompanyAgile');

        $this->hook->on('template:layout:css', array(
            'template' => 'plugins/CompanyAgile/Asset/css/company-agile.css',
        ));
        $this->hook->on('template:layout:js', array(
            'template' => 'plugins/CompanyAgile/Asset/js/company-agile.js',
        ));
        $this->template->hook->attach('template:layout:head', 'companyAgile:navigation/early_state');
        $this->template->setTemplateOverride('config/layout', 'companyAgile:config/layout');
        $this->template->setTemplateOverride('project/layout', 'companyAgile:project/layout');
        $this->template->hook->attachCallable(
            'template:layout:top',
            'companyAgile:navigation/sidebar',
            function () {
                $projectId = $this->request->getIntegerParam('project_id');
                $taskId = $this->request->getIntegerParam('task_id');

                if ($projectId === 0 && $taskId > 0) {
                    $projectId = (int) $this->taskFinderModel->getProjectId($taskId);
                }

                if ($projectId > 0 && $this->projectPermissionModel->isUserAllowed($projectId, $this->userSession->getId())) {
                    session_set('portal_management_current_project_id', $projectId);
                } elseif ($projectId === 0) {
                    $rememberedProjectId = (int) session_get('portal_management_current_project_id');
                    if ($rememberedProjectId > 0 && $this->projectPermissionModel->isUserAllowed($rememberedProjectId, $this->userSession->getId())) {
                        $projectId = $rememberedProjectId;
                    } elseif ($rememberedProjectId > 0) {
                        session_set('portal_management_current_project_id', 0);
                    }
                }

                return array(
                    'project' => $projectId > 0 ? $this->projectModel->getById($projectId) : array(),
                );
            }
        );
        $this->template->hook->attachCallable(
            'template:dashboard:show:before-filter-box',
            'companyAgile:dashboard/hero',
            function (array $user) {
                return array(
                    'display_name' => ! empty($user['name']) ? $user['name'] : $user['username'],
                );
            }
        );
        $this->template->hook->attach(
            'template:project-list:menu:after',
            'companyAgile:management/project_list_labels'
        );
        $this->template->hook->attachCallable(
            'template:project:header:after',
            'companyAgile:filters/visual',
            function (array $project) {
                return array(
                    'issue_types' => $this->issueTypeModel->getActiveTypes(),
                    'sprints' => $this->sprintModel->getSprints($project['id'], false),
                    'epics' => $this->epicModel->getEpicsForProject($project['id']),
                    'users' => $this->projectUserRoleModel->getAssignableUsersList($project['id'], false),
                    'tags' => $this->tagModel->getAllByProjectIds(array($project['id'])),
                    'can_create_tasks' => $this->helper->user->hasProjectAccess('TaskCreationController', 'save', $project['id']),
                    'can_manage_project' => $this->userSession->isAdmin() || $this->helper->projectRole->getProjectUserRole($project['id']) === 'project-manager',
                    'project' => $project,
                );
            }
        );
        $this->template->hook->attachCallable(
            'template:board:private:task:before-title',
            'companyAgile:board/card_kicker',
            function (array $task) {
                return array(
                    'issue_type' => $this->issueTypeModel->getForBoardTask($task),
                );
            }
        );
        $this->template->hook->attach(
            'template:board:private:task:after-title',
            'companyAgile:board/card_excerpt'
        );
        $this->template->hook->attach(
            'template:board:task:icons',
            'companyAgile:board/card_priority'
        );
        $this->template->hook->attachCallable(
            'template:board:column:header',
            'companyAgile:board/quick_create',
            function (array $swimlane, array $column) {
                return array(
                    'column' => $column,
                    'swimlane' => $swimlane,
                );
            }
        );
    }

    public function getPluginName()
    {
        return 'Portal Management';
    }

    public function getPluginDescription()
    {
        return t('CompanyAgile: Plugin description');
    }

    public function getPluginAuthor()
    {
        return 'Company';
    }

    public function getPluginVersion()
    {
        return '0.5.3';
    }

    public function getCompatibleVersion()
    {
        return APP_VERSION;
    }

    public function getClasses()
    {
        return array(
            'Plugin\CompanyAgile\Model' => array(
                'IssueTypeModel',
                'SprintModel',
                'EstimateModel',
                'EpicModel',
                'InternalLinkModel',
            ),
        );
    }
}
