<?php

namespace Kanboard\Plugin\CompanyAgile\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;

class FilterController extends BaseController
{
    public function createTag()
    {
        $this->checkCSRFForm();
        $project = $this->getProject();
        $role = $this->helper->projectRole->getProjectUserRole($project['id']);

        if (! $this->userSession->isAdmin() && $role !== 'project-manager') {
            throw new AccessForbiddenException();
        }

        $values = $this->request->getValues();
        $name = isset($values['name']) ? trim($values['name']) : '';
        if ($name === '') {
            $this->response->json(array('success' => false, 'message' => t('CompanyAgile: Tag name is required.')), 422);
            return;
        }

        $tagId = $this->tagModel->findOrCreateTag($project['id'], $name);
        $this->response->json(array(
            'success' => $tagId > 0,
            'id' => (int) $tagId,
            'name' => $name,
            'message' => $tagId > 0 ? t('CompanyAgile: Tag created.') : t('CompanyAgile: Unable to create tag.'),
        ), $tagId > 0 ? 200 : 500);
    }
}
