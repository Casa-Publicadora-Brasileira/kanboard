<form class="ca-quick-create-form" method="post" enctype="multipart/form-data" action="<?= $this->url->href('QuickTaskController', 'save', array('plugin' => 'CompanyAgile', 'project_id' => $project['id'])) ?>" autocomplete="off" novalidate>
    <?= $this->form->csrf() ?>
    <input type="hidden" name="sprint_id" value="<?= (int) $sprint_id ?>">
    <header class="ca-quick-create-header">
        <div><span><?= $this->text->e($project['name']) ?></span><h2 id="ca-quick-create-title"><?= t('CompanyAgile: Create task') ?></h2></div>
        <button type="button" class="ca-icon-button" data-ca-quick-close aria-label="<?= t('CompanyAgile: Close quick creation') ?>"><i class="fa fa-times"></i></button>
    </header>
    <div class="ca-quick-create-body">
    <section class="ca-create-section"><h3><?= t('CompanyAgile: Task details') ?></h3>
    <div class="ca-form-field ca-create-title-field">
        <label for="ca-task-title"><?= t('CompanyAgile: Title') ?> <span aria-hidden="true">*</span></label>
        <input id="ca-task-title" name="title" type="text" required maxlength="255" autofocus>
    </div>
    <div class="ca-form-grid ca-create-grid-three ca-quick-primary-fields">
        <div class="ca-form-field"><label for="ca-issue-type"><?= t('CompanyAgile: Issue type') ?> <span aria-hidden="true">*</span></label><select id="ca-issue-type" name="issue_type_id" required data-ca-issue-type-select><?php foreach ($issue_types as $type): ?><option value="<?= (int) $type['id'] ?>" data-code="<?= $this->text->e($type['code']) ?>"<?= $selected_issue_type === $type['code'] ? ' selected' : '' ?>><?= t('CompanyAgile issue type: '.$type['code']) ?></option><?php endforeach ?></select></div>
        <div class="ca-form-field" data-ca-points-field<?= $selected_issue_type === 'epic' ? ' hidden' : '' ?>><label for="ca-generic-story-points"><?= t('CompanyAgile: Story Points') ?></label><select id="ca-generic-story-points" name="story_points"><option value=""><?= t('CompanyAgile: Not estimated') ?></option><?php foreach (range(1, 5) as $point): ?><option value="<?= $point ?>"><?= $point ?></option><?php endforeach ?></select></div>
        <div class="ca-form-field"><label for="ca-priority"><?= t('CompanyAgile: Priority') ?></label><select id="ca-priority" name="priority"><option value="0"><?= t('CompanyAgile: Normal') ?></option><option value="1"><?= t('CompanyAgile: High') ?></option><option value="-1"><?= t('CompanyAgile: Low') ?></option></select></div>
    </div>
    <div class="ca-form-field" data-ca-story-fields<?= $selected_issue_type === 'story' ? '' : ' hidden' ?>><label for="ca-epic"><?= t('CompanyAgile: Epic') ?></label><select id="ca-epic" name="epic_id"><option value="0"><?= t('CompanyAgile: None') ?></option><?php foreach ($epics as $epic): ?><option value="<?= (int) $epic['id'] ?>">#<?= (int) $epic['id'] ?> <?= $this->text->e($epic['title']) ?></option><?php endforeach ?></select></div>
    <?= $this->task->renderDescriptionField($values, $errors) ?>
    </section>
    <section class="ca-create-section"><h3><?= t('CompanyAgile: Assignment') ?></h3><div class="ca-form-grid">
        <div class="ca-form-field ca-assignee-field"><label for="ca-owner"><?= t('CompanyAgile: Assignee') ?></label><div class="ca-assignee-control"><?= $this->form->select('owner_id', $users_list, array('owner_id' => 0), array(), array('id="ca-owner"', 'data-ca-assignee-select="true"')) ?><?php if ($can_assign_self): ?><button type="button" class="ca-assign-me" data-ca-assign-me data-ca-current-user-id="<?= (int) $current_user_id ?>"><?= t('CompanyAgile: Assign to me') ?></button><?php endif ?></div></div>
    </div></section>
    <section class="ca-create-section"><h3><?= t('CompanyAgile: Organization') ?></h3><div class="ca-form-grid ca-create-grid-three">
        <div><?= $this->task->renderCategoryField($categories_list, $values, $errors) ?></div>
        <div><?= $this->task->renderColumnField($columns_list, $values, $errors) ?></div>
        <div><?php if (count($swimlanes_list) > 1): ?><?= $this->task->renderSwimlaneField($swimlanes_list, $values, $errors) ?><?php else: ?><input type="hidden" name="swimlane_id" value="<?= (int) $values['swimlane_id'] ?>"><?php endif ?></div>
    </div><div class="ca-create-tags"><?= $this->task->renderTagField($project) ?></div></section>
    <details class="ca-create-section ca-create-relations" data-ca-create-relations
        data-search-url="<?= $this->text->e($task_search_url) ?>"
        data-search-placeholder="<?= t('CompanyAgile: Type task ID or title...') ?>"
        data-no-results="<?= t('CompanyAgile: No accessible tasks found.') ?>"
        data-search-error="<?= t('CompanyAgile: Unable to search tasks.') ?>"
        data-remove-label="<?= t('CompanyAgile: Remove internal link') ?>">
        <summary><span><?= t('CompanyAgile: Internal links') ?></span> <span class="ca-relation-count" data-ca-relation-count hidden>0</span></summary>
        <div class="ca-create-relations-content">
            <div class="ca-create-relation-list" data-ca-relation-list></div>
            <div class="ca-create-relation-empty" data-ca-relation-empty><?= t('CompanyAgile: No internal links configured.') ?></div>
            <div class="ca-create-relation-composer">
                <div class="ca-form-field">
                    <label for="ca-relation-type"><?= t('CompanyAgile: Relation') ?></label>
                    <select id="ca-relation-type" data-ca-relation-type><?php foreach ($relation_types as $relation_id => $relation_label): ?><option value="<?= (int) $relation_id ?>"><?= $this->text->e($relation_label) ?></option><?php endforeach ?></select>
                </div>
                <div class="ca-form-field ca-relation-task-search">
                    <label for="ca-relation-task-search"><?= t('CompanyAgile: Related task') ?></label>
                    <input id="ca-relation-task-search" type="search" data-ca-relation-search placeholder="<?= t('CompanyAgile: Type task ID or title...') ?>" aria-autocomplete="list" aria-expanded="false" aria-controls="ca-relation-results">
                    <div id="ca-relation-results" class="ca-relation-results" data-ca-relation-results role="listbox" hidden></div>
                </div>
            </div>
            <p class="ca-create-relation-help"><?= t('CompanyAgile: Select a result to add the internal link.') ?></p>
        </div>
    </details>
    <details class="ca-create-section"><summary><?= t('CompanyAgile: Dates and time') ?></summary><div class="ca-form-grid ca-create-grid-three">
        <div><?= $this->task->renderStartDateField($values, $errors) ?></div>
        <div><?= $this->task->renderDueDateField($values, $errors) ?></div>
        <div><?= $this->task->renderTimeEstimatedField($values, $errors) ?><?= $this->task->renderTimeSpentField($values, $errors) ?></div>
    </div></details>
    <details class="ca-create-section"><summary><?= t('CompanyAgile: Additional options') ?></summary><div class="ca-form-grid">
        <div><?= $this->task->renderColorField($values) ?></div>
        <div><?= $this->task->renderReferenceField($values, $errors) ?></div>
    </div></details>
    <details class="ca-create-section"><summary><?= t('Add attachments') ?></summary><?= $this->task->renderFileUpload($screenshot, $files) ?></details>
    <section class="ca-create-section ca-create-flags">
        <?= $this->form->checkbox('another_task', t('Create another task'), 1, false) ?>
        <?= $this->form->checkbox('duplicate_multiple_projects', t('Duplicate to multiple projects'), 1, false) ?>
        <?php if (! empty($projects_list)): ?><div class="ca-duplicate-projects" data-ca-duplicate-projects hidden><label for="ca-duplicate-project-ids"><?= t('Projects') ?></label><select id="ca-duplicate-project-ids" name="duplicate_project_ids[]" multiple><?php foreach ($projects_list as $project_id => $project_name): ?><option value="<?= (int) $project_id ?>"><?= $this->text->e($project_name) ?></option><?php endforeach ?></select></div><?php endif ?>
    </section>
    <div class="ca-quick-create-feedback" role="status" aria-live="polite"></div>
    </div>
    <footer class="ca-quick-create-footer">
        <span></span>
        <div><button type="button" class="btn ca-btn-secondary" data-ca-quick-close><?= t('Cancel') ?></button><button type="submit" class="btn btn-blue" data-saving-label="<?= t('CompanyAgile: Saving...') ?>"><?= t('CompanyAgile: Create task') ?></button></div>
    </footer>
</form>
