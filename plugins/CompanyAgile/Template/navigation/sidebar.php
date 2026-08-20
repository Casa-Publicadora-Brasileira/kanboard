<?php if ($this->user->getId() > 0): ?>
<aside class="ca-sidebar" aria-label="<?= t('CompanyAgile: Main navigation') ?>" data-ca-current-project="<?= empty($project) ? '' : $this->text->e($project['name']) ?>" data-ca-current-project-id="<?= empty($project) ? '0' : (int) $project['id'] ?>" data-ca-panel-url="<?= $this->url->href('TaskPanelController', 'show', array('plugin' => 'CompanyAgile', 'task_id' => '__TASK_ID__')) ?>">
    <div class="ca-brand">
        <span class="ca-brand-mark">PM</span>
        <span class="ca-brand-copy">
            <strong><?= t('CompanyAgile: Product name') ?></strong>
            <small><?= t('CompanyAgile: Workspace') ?></small>
        </span>
    </div>

    <nav class="ca-nav">
        <p class="ca-nav-label"><?= t('CompanyAgile: Current project') ?></p>
        <div class="ca-current-project" title="<?= t('CompanyAgile: Current project') ?>">
            <i class="fa fa-folder-open fa-fw" aria-hidden="true"></i>
            <span><?= empty($project) ? t('CompanyAgile: No project selected') : $this->text->e($project['name']) ?></span>
        </div>
        <?php if (! empty($project)): ?>
            <?= $this->url->icon('columns', t('CompanyAgile: Board'), 'BoardViewController', 'show', array('project_id' => $project['id']), false, 'ca-nav-item') ?>
            <?= $this->url->icon('list', t('CompanyAgile: Tasks'), 'TaskListController', 'show', array('project_id' => $project['id']), false, 'ca-nav-item') ?>
            <?= $this->url->icon('history', t('CompanyAgile: Activity'), 'ActivityController', 'project', array('project_id' => $project['id']), false, 'ca-nav-item') ?>
            <?php if ($this->user->hasProjectAccess('ProjectViewController', 'show', $project['id'])): ?>
                <?php $projectSettingsControllers = array('projectviewcontroller', 'customfiltercontroller', 'projecteditcontroller', 'projectpredefinedcontentcontroller', 'columncontroller', 'swimlanecontroller', 'categorycontroller', 'projecttagcontroller', 'projectpermissioncontroller', 'projectrolecontroller', 'projectrolerestrictioncontroller', 'actioncontroller', 'projectactionduplicationcontroller', 'projectstatuscontroller') ?>
                <?php $projectSettingsOpen = in_array(strtolower($this->app->getRouterController()), $projectSettingsControllers, true) ?>
                <details class="ca-project-settings"<?= $projectSettingsOpen ? ' open' : '' ?> data-ca-project-settings>
                    <summary class="ca-nav-item" aria-expanded="<?= $projectSettingsOpen ? 'true' : 'false' ?>">
                        <i class="fa fa-cog fa-fw" aria-hidden="true"></i><span><?= t('CompanyAgile: Project settings') ?></span><i class="fa fa-angle-down ca-project-settings-chevron" aria-hidden="true"></i>
                    </summary>
                    <div class="ca-project-settings-menu">
                        <?= $this->render('project/sidebar', array('project' => $project)) ?>
                    </div>
                </details>
            <?php endif ?>
        <?php else: ?>
            <a class="ca-nav-item ca-nav-needs-project" href="<?= $this->url->href('ProjectListController', 'show') ?>"><i class="fa fa-columns fa-fw" aria-hidden="true"></i><?= t('CompanyAgile: Select a project') ?></a>
        <?php endif ?>

        <p class="ca-nav-label ca-nav-label-spaced"><?= t('CompanyAgile: Work') ?></p>
        <?= $this->url->icon('tachometer', t('CompanyAgile: Overview'), 'DashboardController', 'show', array(), false, 'ca-nav-item') ?>
        <?= $this->url->icon('folder-open', t('CompanyAgile: Projects'), 'ProjectListController', 'show', array(), false, 'ca-nav-item') ?>
        <?php if ($this->user->hasAccess('ProjectUserOverviewController', 'managers')): ?>
            <?php $managementActive = strtolower($this->app->getRouterController()) === 'managementdashboardcontroller' ?>
            <a href="<?= $this->url->href('ManagementDashboardController', 'managers', array('plugin' => 'CompanyAgile')) ?>" class="ca-nav-item<?= $managementActive ? ' ca-nav-active' : '' ?>"<?= $managementActive ? ' aria-current="page"' : '' ?>><i class="fa fa-bar-chart fa-fw" aria-hidden="true"></i><?= t('CompanyAgile: Management overview') ?></a>
        <?php endif ?>
        <?= $this->url->icon('tasks', t('CompanyAgile: My tasks'), 'MyTasksController', 'show', array('plugin' => 'CompanyAgile'), false, 'ca-nav-item') ?>
        <?= $this->url->icon('history', t('CompanyAgile: My activity'), 'ActivityController', 'user', array(), false, 'ca-nav-item js-modal-medium') ?>

        <p class="ca-nav-label ca-nav-label-spaced"><?= t('CompanyAgile: Planning') ?></p>
        <?php if (! empty($project)): ?>
            <?= $this->url->icon('inbox', t('CompanyAgile: Backlog'), 'BacklogController', 'show', array('plugin' => 'CompanyAgile', 'project_id' => $project['id']), false, 'ca-nav-item') ?>
            <?= $this->url->icon('bolt', t('CompanyAgile: Sprints'), 'SprintController', 'index', array('plugin' => 'CompanyAgile', 'project_id' => $project['id']), false, 'ca-nav-item') ?>
        <?php else: ?>
            <a class="ca-nav-item ca-nav-needs-project" href="<?= $this->url->href('ProjectListController', 'show') ?>" title="<?= t('CompanyAgile: Select a project to use planning') ?>"><i class="fa fa-inbox fa-fw"></i><?= t('CompanyAgile: Backlog') ?><small><?= t('CompanyAgile: Select project') ?></small></a>
            <a class="ca-nav-item ca-nav-needs-project" href="<?= $this->url->href('ProjectListController', 'show') ?>" title="<?= t('CompanyAgile: Select a project to use planning') ?>"><i class="fa fa-bolt fa-fw"></i><?= t('CompanyAgile: Sprints') ?><small><?= t('CompanyAgile: Select project') ?></small></a>
        <?php endif ?>
        <span class="ca-nav-item ca-nav-disabled" aria-disabled="true"><i class="fa fa-line-chart fa-fw"></i><?= t('CompanyAgile: Reports') ?> <small><?= t('CompanyAgile: Coming soon') ?></small></span>
    </nav>

    <div class="ca-sidebar-footer">
        <?php if ($this->user->hasAccess('ConfigController', 'index')): ?>
            <?= $this->url->icon('cog', t('CompanyAgile: General settings'), 'ConfigController', 'index', array(), false, 'ca-nav-item') ?>
            <?php if (empty($project)): ?><?= $this->url->icon('tags', t('Tags management'), 'TagController', 'index', array(), false, 'ca-nav-item') ?><?php endif ?>
        <?php endif ?>
        <span class="ca-version">Portal Management 0.5.3</span>
    </div>
</aside>
<div class="ca-panel-backdrop" data-ca-panel-backdrop hidden></div>
<aside class="ca-task-panel" data-ca-task-panel role="dialog" aria-modal="true" aria-labelledby="ca-task-panel-title" hidden>
    <div class="ca-panel-loading" data-ca-panel-loading><i class="fa fa-circle-o-notch fa-spin"></i><span><?= t('CompanyAgile: Loading task') ?></span></div>
    <div data-ca-panel-body></div>
</aside>
<div class="ca-quick-backdrop" data-ca-quick-backdrop hidden></div>
<section class="ca-quick-dialog" data-ca-quick-dialog role="dialog" aria-modal="true" aria-labelledby="ca-quick-create-title" hidden><div data-ca-quick-body></div></section>
<?php endif ?>
