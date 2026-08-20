<?= $this->render('companyAgile:task/modal_header', array('modal_title' => t('Add a sub-task'), 'task' => $task)) ?>
<?php if (isset($values['subtasks_added']) && $values['subtasks_added'] > 0): ?>
    <p class="alert alert-success"><?= $values['subtasks_added'] == 1 ? t('Subtask added successfully.') : t('%d subtasks added successfully.', $values['subtasks_added']) ?></p>
<?php endif ?>
<form method="post" action="<?= $this->url->href('SubtaskController', 'save', array('task_id' => $task['id'])) ?>" autocomplete="off">
    <?= $this->form->csrf() ?>
    <?= $this->subtask->renderBulkTitleField($values, $errors, array('autofocus')) ?>
    <?= $this->subtask->renderAssigneeField($users_list, $values, $errors) ?>
    <?= $this->subtask->renderTimeEstimatedField($values, $errors) ?>
    <?= $this->hook->render('template:subtask:form:create', array('values' => $values, 'errors' => $errors)) ?>
    <?= $this->form->checkbox('another_subtask', t('Create another sub-task'), 1, isset($values['another_subtask']) && $values['another_subtask'] == 1) ?>
    <?= $this->modal->submitButtons() ?>
</form>
