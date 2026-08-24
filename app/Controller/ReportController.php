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

        $this->response->html($this->helper->layout->pageLayout('report/user', array(
            'no_layout' => true,
            'title'   => t('User Report'),
            'user_id' => $user_id,
            'user'    => $user
        )));
    }

    /**
     * Report by Project
     *
     * @access public
     */
    public function project()
    {
        // $project_id = $this->request->getIntegerParam('project_id');
        // $project = $this->projectModel->getById($project_id);

        // if (empty($project)) {
        //     return $this->response->redirect($this->helper->url->to('DashboardController', 'show'));
        // }

        $this->response->html($this->helper->layout->pageLayout('report/project', array(
            'no_layout' => true,
            'title'      => t('Project Report'),
            // 'project_id' => $project_id,
            // 'project'    => $project
        )));
    }
}
