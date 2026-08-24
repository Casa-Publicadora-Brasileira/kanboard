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
     * Report Overview (all projects/users)
     *
     * @access public
     */
    public function overview()
    {
        $this->response->html($this->helper->layout->app('report/overview', array(
            'no_layout' => true,
            'title' => t('Report Overview')
        )));
    }

    /**
     * Report by User
     *
     * @access public
     */
    public function user()
    {
        $user_id = $this->request->getIntegerParam('user_id');
        $user = $this->userModel->getById($user_id);

        if (empty($user)) {
            // Flash error or redirect, but since it's just rendering we can just pass an empty user or handle it in template
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
        $project_id = $this->request->getIntegerParam('project_id');
        $project = $this->projectModel->getById($project_id);

        if (empty($project)) {
            // Flash error or redirect
        }

        $this->response->html($this->helper->layout->pageLayout('report/project', array(
            'no_layout' => true,
            'title'      => t('Project Report'),
            'project_id' => $project_id,
            'project'    => $project
        )));
    }
}
