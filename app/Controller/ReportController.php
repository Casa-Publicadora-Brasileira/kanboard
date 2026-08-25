<?php

namespace Kanboard\Controller;

/**
 * Report Controller
 *
 * @package  Kanboard\Controller
 */
class ReportController extends BaseController
{
    /**
     * Report Overview (all projects/users) — Admin only
     *
     * @access public
     */
    public function overview()
    {
        if (! $this->userSession->isAdmin()) {
            return $this->response->redirect($this->helper->url->to('DashboardController', 'show'));
        }

        $this->response->html($this->helper->layout->app('report/overview', array(
            'no_layout' => true,
            'title' => t('Report Overview')
        )));
    }

    /**
     * Report by User
     * Admin: can view any user_id from the URL
     * Regular user: always sees their own data
     *
     * @access public
     */
    public function user()
    {
        if ($this->userSession->isAdmin()) {
            $user_id = $this->request->getIntegerParam('user_id', $this->userSession->getId());
        } else {
            $user_id = $this->userSession->getId();
        }

        $user = $this->userModel->getById($user_id);

        if (empty($user)) {
            return $this->response->redirect($this->helper->url->to('DashboardController', 'show'));
        }

        $report_data = $this->userReportModel->getReportData($user_id);

        $this->response->html($this->helper->layout->pageLayout('report/user', array_merge(array(
            'no_layout' => true,
            'title'   => t('User Report'),
            'user_id' => $user_id,
            'user'    => $user,
        ), $report_data)));
    }

    /**
     * Report by Project (Tag)
     * Admin only
     *
     * @access public
     */
    public function project()
    {
        if (! $this->userSession->isAdmin()) {
            return $this->response->redirect($this->helper->url->to('DashboardController', 'show'));
        }

        $availableProjects = $this->projectReportModel->getAvailableProjects();
        $defaultTagId = !empty($availableProjects) ? $availableProjects[0]['id'] : 233;

        $projectTagId = $this->request->getIntegerParam('project_tag_id', $defaultTagId);

        $report_data = $this->projectReportModel->getReportData($projectTagId);

        $this->response->html($this->helper->layout->pageLayout('report/project', array_merge(array(
            'no_layout'          => true,
            'title'              => t('Project Report'),
            'available_projects' => $availableProjects,
            'selected_project'   => $projectTagId,
        ), $report_data)));
    }
}
