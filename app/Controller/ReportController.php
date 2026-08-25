<?php

namespace Kanboard\Controller;

use Kanboard\Enum\ProjectTagEnum;

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

        $overviewData = $this->overviewReportModel->getOverviewData();

        $this->response->html($this->helper->layout->pageLayout('report/overview', array_merge(array(
            'no_layout' => true,
            'title'     => t('Visão Geral de Operações (Portfólio)'),
        ), $overviewData)));
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
     *
     * @access public
     */
    public function project()
    {

        $availableProjects = ProjectTagEnum::toArray();
        $project_id = $this->request->getIntegerParam('project_id', $availableProjects[0]['id']);

        if (empty(ProjectTagEnum::tryFrom($project_id))) {
            return $this->response->redirect($this->helper->url->to('DashboardController', 'show'));
        }

        $report_data = $this->projectReportModel->getReportData($project_id);

        $this->response->html($this->helper->layout->pageLayout('report/project', array_merge(array(
            'no_layout'          => true,
            'title'              => t('Project Report'),
            'available_projects' => $availableProjects,
            'selected_project'   => $project_id,
        ), $report_data)));
    }
}
