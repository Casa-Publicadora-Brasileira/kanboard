<section id="main" class="ca-management" data-ca-management-action="<?= $this->text->e($action) ?>">
    <header class="ca-management-header">
        <div><span><?= t('CompanyAgile: Management overview') ?></span><h1><?= $this->text->e($title) ?></h1></div>
    </header>

    <nav class="ca-management-tabs" aria-label="<?= t('CompanyAgile: Management views') ?>">
        <?php foreach (array('managers' => t('Project managers'), 'members' => t('Project members'), 'opens' => t('Open tasks'), 'closed' => t('Closed tasks')) as $tab_action => $tab_label): ?>
            <?= $this->url->link($this->text->e($tab_label), 'ManagementDashboardController', $tab_action, array('plugin' => 'CompanyAgile', 'project_id' => $project_id, 'project_ids' => implode(',', $selected_project_ids), 'column_ids' => implode(',', $selected_column_ids), 'user_id' => $user_id, 'search' => $search), false, $action === $tab_action ? 'is-active' : '') ?>
        <?php endforeach ?>
    </nav>

    <div class="ca-management-toolbar">
        <?php if ($action !== 'opens' && $action !== 'closed'): ?><?= $this->render('companyAgile:management/picker', array(
            'name' => 'project_id',
            'label' => t('CompanyAgile: Project'),
            'items' => $projects,
            'value' => $project_id,
            'search_label' => t('CompanyAgile: Search or select project...'),
            'placeholder' => t('CompanyAgile: Search or select project...'),
            'clear_label' => t('CompanyAgile: Clear project filter'),
            'remote_url' => '',
        )) ?><?php endif ?>
        <?php if ($action === 'opens' || $action === 'closed'): ?>
            <?= $this->render('companyAgile:management/picker', array(
                'name' => 'user_id',
                'label' => t('CompanyAgile: Assignee'),
                'items' => $users,
                'value' => $user_id,
                'search_label' => t('CompanyAgile: Search or select assignee...'),
                'placeholder' => t('CompanyAgile: Search or select assignee...'),
                'clear_label' => t('CompanyAgile: Clear assignee filter'),
                'remote_url' => $user_search_url,
            )) ?>
            <label class="ca-management-search"><span><?= t('CompanyAgile: Search tasks') ?></span><input type="search" value="<?= $this->text->e($search) ?>" placeholder="<?= t('CompanyAgile: Search by ID or title...') ?>" data-ca-management-search></label>
        <?php endif ?>
        <form class="ca-management-filter-form" method="get" action="<?= $this->url->href('ManagementDashboardController', $action, array('plugin' => 'CompanyAgile')) ?>">
            <input type="hidden" name="project_id" value="<?= (int) $project_id ?>" data-ca-management-filter="project_id">
            <input type="hidden" name="project_ids" value="<?= $this->text->e(implode(',', $selected_project_ids)) ?>" data-ca-management-filter="project_ids">
            <input type="hidden" name="column_ids" value="<?= $this->text->e(implode(',', $selected_column_ids)) ?>" data-ca-management-filter="column_ids">
            <input type="hidden" name="user_id" value="<?= (int) $user_id ?>" data-ca-management-filter="user_id">
            <input type="hidden" name="search" value="<?= $this->text->e($search) ?>" data-ca-management-filter="search">
        </form>
    </div>

    <?php if ($action === 'opens' || $action === 'closed'): ?>
        <?= $this->render($content_template, array('paginator' => $paginator, 'summary' => $summary, 'is_active' => $is_active, 'selected_project_ids' => $selected_project_ids, 'selected_column_ids' => $selected_column_ids)) ?>
    <?php else: ?>
        <?= $this->render($content_template, array('paginator' => $paginator, 'role_label' => $role_label)) ?>
    <?php endif ?>
</section>
