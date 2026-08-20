<div class="ca-agile-page" data-ca-planning-page data-ca-move-url="<?= $this->url->href('SprintTaskController', 'move', array('plugin' => 'CompanyAgile', 'project_id' => $project['id'])) ?>" data-ca-csrf="<?= $this->app->getToken()->getCSRFToken() ?>">
    <header class="ca-agile-page-header">
        <div><span><?= $this->text->e($project['name']) ?></span><h1><?= t('CompanyAgile: Backlog') ?></h1></div>
        <div class="ca-agile-header-actions">
            <?= $this->url->link(t('CompanyAgile: Sprints'), 'SprintController', 'index', array('plugin' => 'CompanyAgile', 'project_id' => $project['id']), false, 'btn') ?>
            <?php if ($can_manage_sprints): ?><?= $this->modal->medium('plus', t('CompanyAgile: Create Sprint'), 'SprintController', 'create', array('plugin' => 'CompanyAgile', 'project_id' => $project['id'])) ?><?php endif ?>
        </div>
    </header>

    <div class="ca-planning-feedback" data-ca-planning-feedback role="status" aria-live="polite"></div>
    <div class="ca-backlog-filters"><label><?= t('CompanyAgile: Epic') ?> <select data-ca-backlog-epic-filter><option value="all"><?= t('CompanyAgile: All') ?></option><option value="none"><?= t('CompanyAgile: No Epic') ?></option><?php foreach ($epics as $epic): ?><option value="<?= (int) $epic['id'] ?>"><?= $this->text->e($epic['title']) ?></option><?php endforeach ?></select></label></div>

    <?php if (empty($planning['sprints'])): ?>
        <section class="ca-empty-state"><i class="fa fa-calendar-o"></i><h2><?= t('CompanyAgile: No planned Sprints') ?></h2><p><?= t('CompanyAgile: Organize the next work cycle by creating a Sprint.') ?></p><?php if ($can_manage_sprints): ?><?= $this->modal->medium('plus', t('CompanyAgile: Create Sprint'), 'SprintController', 'create', array('plugin' => 'CompanyAgile', 'project_id' => $project['id'])) ?><?php endif ?></section>
    <?php endif ?>

    <?php foreach ($planning['sprints'] as $group): ?>
        <?php $sprint = $group['sprint']; ?>
        <section class="ca-sprint-box ca-sprint-<?= $this->text->e($sprint['status']) ?>" data-ca-task-list data-sprint-id="<?= (int) $sprint['id'] ?>">
            <header class="ca-sprint-header">
                <div><div class="ca-sprint-title"><h2><?= $this->text->e($sprint['name']) ?></h2><span class="ca-status-badge ca-status-<?= $this->text->e($sprint['status']) ?>"><?= t('CompanyAgile sprint status: '.$sprint['status']) ?></span></div><?php if (! empty($sprint['goal'])): ?><p><strong><?= t('CompanyAgile: Goal') ?>:</strong> <?= $this->text->e($sprint['goal']) ?></p><?php endif ?><small><?= $this->dt->date($sprint['planned_start_at']) ?> → <?= $this->dt->date($sprint['planned_end_at']) ?> · <?= (int) $sprint['task_count'] ?> <?= t('CompanyAgile: items') ?> · <?= $this->text->e((float) $sprint['story_points_total']) ?> SP · <?= $this->text->e((float) $sprint['story_points_completed']) ?> <?= t('CompanyAgile: completed') ?></small></div>
                <?php if ($can_manage_sprints): ?><div class="ca-sprint-actions">
                    <?php if ($sprint['status'] === 'planned'): ?>
                        <?= $this->modal->medium('pencil', t('CompanyAgile: Edit Sprint'), 'SprintController', 'edit', array('plugin' => 'CompanyAgile', 'project_id' => $project['id'], 'sprint_id' => $sprint['id'])) ?>
                        <form method="post" action="<?= $this->url->href('SprintController', 'start', array('plugin' => 'CompanyAgile', 'project_id' => $project['id'], 'sprint_id' => $sprint['id'])) ?>"><?= $this->form->csrf() ?><button class="btn btn-blue" type="submit"><?= t('CompanyAgile: Start Sprint') ?></button></form>
                    <?php elseif ($sprint['status'] === 'active'): ?>
                        <?= $this->modal->medium('check', t('CompanyAgile: Complete Sprint'), 'SprintController', 'completeForm', array('plugin' => 'CompanyAgile', 'project_id' => $project['id'], 'sprint_id' => $sprint['id'])) ?>
                    <?php endif ?>
                </div><?php endif ?>
            </header>
            <div class="ca-task-rows" data-ca-drop-zone>
                <?php foreach ($group['tasks'] as $task): ?><?= $this->render('companyAgile:backlog/task_row', array('task' => $task, 'can_modify_tasks' => $can_modify_tasks)) ?><?php endforeach ?>
                <?php if (empty($group['tasks'])): ?><p class="ca-drop-placeholder"><?= t('CompanyAgile: Drag tasks here') ?></p><?php endif ?>
            </div>
            <footer><button type="button" class="ca-inline-create" data-ca-quick-create-url="<?= $this->url->href('QuickTaskController', 'show', array('plugin' => 'CompanyAgile', 'project_id' => $project['id'], 'column_id' => $task_context['column_id'], 'swimlane_id' => $task_context['swimlane_id'], 'sprint_id' => $sprint['id'])) ?>"><i class="fa fa-plus"></i> <?= t('CompanyAgile: Create task') ?></button></footer>
        </section>
    <?php endforeach ?>

    <section class="ca-sprint-box ca-backlog-box" data-ca-task-list data-sprint-id="0">
        <header class="ca-sprint-header"><div><div class="ca-sprint-title"><h2><?= t('CompanyAgile: Backlog') ?></h2><span class="ca-count"><?= count($planning['backlog']) ?> <?= t('CompanyAgile: items') ?></span></div></div></header>
        <div class="ca-task-rows" data-ca-drop-zone>
            <?php foreach ($planning['backlog'] as $task): ?><?= $this->render('companyAgile:backlog/task_row', array('task' => $task, 'can_modify_tasks' => $can_modify_tasks)) ?><?php endforeach ?>
            <?php if (empty($planning['backlog'])): ?><div class="ca-empty-inline"><strong><?= t('CompanyAgile: Empty Backlog') ?></strong><span><?= t('CompanyAgile: All tasks are already planned.') ?></span></div><?php endif ?>
        </div>
        <footer><button type="button" class="ca-inline-create" data-ca-quick-create-url="<?= $this->url->href('QuickTaskController', 'show', array('plugin' => 'CompanyAgile', 'project_id' => $project['id'], 'column_id' => $task_context['column_id'], 'swimlane_id' => $task_context['swimlane_id'])) ?>"><i class="fa fa-plus"></i> <?= t('CompanyAgile: Create task') ?></button></footer>
    </section>
</div>
