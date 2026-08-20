<form class="ca-advanced-create-form" method="post" action="<?= $this->url->href('TaskCreationController', 'save', array('project_id' => $project['id'])) ?>" autocomplete="off" enctype="multipart/form-data">
    <?= $this->form->csrf() ?>
    <header class="ca-advanced-create-header">
        <div><span><?= t('CompanyAgile: Project') ?></span><strong><?= $this->text->e($project['name']) ?></strong><h2><?= t('New task') ?></h2></div>
        <button type="button" class="ca-icon-button js-modal-close" aria-label="<?= t('CompanyAgile: Close quick creation') ?>"><i class="fa fa-times" aria-hidden="true"></i></button>
    </header>

    <div class="ca-advanced-create-scroll">
        <section class="ca-advanced-create-main" aria-label="<?= t('New task') ?>">
            <div class="ca-form-field ca-field-title"><?= $this->task->renderTitleField($values, $errors) ?></div>
            <div class="ca-form-field ca-field-description"><label><?= t('Description') ?></label><?= $this->task->renderDescriptionField($values, $errors) ?><?= $this->task->renderDescriptionTemplateDropdown($project['id']) ?></div>
            <div class="ca-form-field ca-field-tags"><?= $this->task->renderTagField($project) ?></div>
        </section>

        <section class="ca-advanced-create-grid" aria-label="<?= t('CompanyAgile: Details') ?>">
            <?= $this->hook->render('template:companyAgile:advanced-create:agile-fields', array('project' => $project, 'values' => $values)) ?>
            <div class="ca-form-field ca-assignee-field"><?= $this->task->renderAssigneeField($users_list, $values, $errors, array('data-ca-assignee-select="true"')) ?></div>
            <div class="ca-form-field"><?= $this->task->renderPriorityField($project, $values) ?></div>
            <div class="ca-form-field"><?= $this->task->renderCategoryField($categories_list, $values, $errors) ?></div>
            <div class="ca-form-field"><?= $this->task->renderColumnField($columns_list, $values, $errors) ?></div>
            <div class="ca-form-field"><?= $this->task->renderSwimlaneField($swimlanes_list, $values, $errors) ?></div>
        </section>

        <section class="ca-advanced-create-section">
            <h3><?= t('CompanyAgile: Dates') ?></h3>
            <div class="ca-advanced-create-grid"><div class="ca-form-field"><?= $this->task->renderStartDateField($values, $errors) ?></div><div class="ca-form-field"><?= $this->task->renderDueDateField($values, $errors) ?></div></div>
        </section>

        <details class="ca-advanced-create-more">
            <summary><?= t('CompanyAgile: Additional options') ?></summary>
            <div class="ca-advanced-create-grid">
                <div class="ca-form-field"><?= $this->task->renderColorField($values) ?></div>
                <div class="ca-form-field"><?= $this->task->renderReferenceField($values, $errors) ?></div>
                <div class="ca-form-field"><?= $this->task->renderTimeEstimatedField($values, $errors) ?></div>
                <div class="ca-form-field"><?= $this->task->renderTimeSpentField($values, $errors) ?></div>
                <div class="ca-form-field"><?= $this->task->renderScoreField($values, $errors) ?></div>
            </div>
            <div class="ca-advanced-create-attachments"><h3><?= t('Add attachments') ?></h3><?= $this->task->renderFileUpload($screenshot, $files) ?></div>
            <?= $this->hook->render('template:task:form:first-column', array('values' => $values, 'errors' => $errors)) ?>
            <?= $this->hook->render('template:task:form:second-column', array('values' => $values, 'errors' => $errors)) ?>
            <?= $this->hook->render('template:task:form:third-column', array('values' => $values, 'errors' => $errors)) ?>
            <?= $this->hook->render('template:task:form:bottom-before-buttons', array('values' => $values, 'errors' => $errors)) ?>
            <?php if (! isset($duplicate)): ?><div class="ca-advanced-create-checks"><?= $this->form->checkbox('another_task', t('Create another task'), 1, isset($values['another_task']) && $values['another_task'] == 1) ?><?= $this->form->checkbox('duplicate_multiple_projects', t('Duplicate to multiple projects'), 1, false) ?></div><?php endif ?>
        </details>
    </div>

    <footer class="ca-advanced-create-footer"><button type="button" class="btn ca-btn-secondary js-modal-close"><?= t('Cancel') ?></button><button type="submit" class="btn btn-blue"><?= t('CompanyAgile: Create task') ?></button></footer>
</form>
