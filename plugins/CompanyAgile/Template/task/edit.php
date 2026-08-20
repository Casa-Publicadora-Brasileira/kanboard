<?= $this->render('companyAgile:task/modal_header', array('modal_title' => t('CompanyAgile: Edit task'), 'task' => $task, 'extra_class' => 'ca-task-edit-header')) ?>
<form class="ca-native-modal-form ca-task-edit-form" method="post" action="<?= $this->url->href('TaskModificationController', 'update', array('task_id' => $task['id'])) ?>" autocomplete="off">
    <?= $this->form->csrf() ?>
    <div class="ca-modal-form-body">
        <section class="ca-modal-form-section ca-task-edit-details">
            <header><h3><?= t('CompanyAgile: Task details') ?></h3></header>
            <?= $this->task->renderTitleField($values, $errors) ?>
            <?= $this->task->renderDescriptionField($values, $errors) ?>
            <?= $this->task->renderDescriptionTemplateDropdown($project['id']) ?>
            <?= $this->hook->render('template:task:form:first-column', array('values' => $values, 'errors' => $errors)) ?>
        </section>

        <section class="ca-modal-form-section">
            <header><h3><?= t('CompanyAgile: Organization') ?></h3></header>
            <div class="ca-modal-field-grid">
                <div><?= $this->task->renderAssigneeField($users_list, $values, $errors) ?></div>
                <div><?= $this->task->renderCategoryField($categories_list, $values, $errors) ?></div>
                <div><?= $this->task->renderPriorityField($project, $values) ?></div>
                <div><?= $this->task->renderColorField($values) ?></div>
            </div>
            <?= $this->hook->render('template:task:form:second-column', array('values' => $values, 'errors' => $errors)) ?>
        </section>

        <section class="ca-modal-form-section">
            <header><h3><?= t('CompanyAgile: Planning') ?></h3></header>
            <div class="ca-modal-field-grid">
                <div><?= $this->task->renderStartDateField($values, $errors) ?></div>
                <div><?= $this->task->renderDueDateField($values, $errors) ?></div>
                <div><?= $this->task->renderScoreField($values, $errors) ?></div>
                <div><?= $this->task->renderReferenceField($values, $errors) ?></div>
            </div>
            <?= $this->hook->render('template:task:form:third-column', array('values' => $values, 'errors' => $errors)) ?>
        </section>

        <section class="ca-modal-form-section ca-task-edit-tags">
            <header><h3><?= t('CompanyAgile: Tags') ?></h3></header>
            <?php
            // Keep Kanboard's native tags[] payload, but do not expose its Select2 UI.
            // The section heading is the only visible label and the Portal picker uses
            // this select as its submission source from the first rendered frame.
            $tagField = $this->task->renderTagField($project, $tags);
            $tagField = preg_replace('/^<label\b[^>]*>.*?<\/label>/s', '', $tagField, 1);
            $tagField = str_replace(
                'class="tag-autocomplete" multiple tabindex="3"',
                'class="ca-native-tag-select ca-task-tag-source" multiple tabindex="-1" aria-hidden="true"',
                $tagField
            );
            ?>
            <?= $tagField ?>
        </section>

        <?= $this->hook->render('template:task:form:bottom-before-buttons', array('values' => $values, 'errors' => $errors)) ?>
    </div>
    <footer class="ca-modal-form-footer"><?= $this->modal->submitButtons(array('submitLabel' => t('CompanyAgile: Save changes'))) ?></footer>
</form>
