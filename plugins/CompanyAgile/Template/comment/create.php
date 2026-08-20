<?php use Kanboard\Core\Security\Role; ?>
<?= $this->render('companyAgile:task/modal_header', array('modal_title' => t('Add a comment'), 'task' => $task)) ?>
<div class="ca-task-modal-aux-actions">
    <?= $this->modal->medium('paper-plane', t('Send by email'), 'CommentMailController', 'create', array('task_id' => $task['id'])) ?>
</div>
<form method="post" action="<?= $this->url->href('CommentController', 'save', array('task_id' => $task['id'])) ?>" autocomplete="off">
    <?= $this->form->csrf() ?>
    <?= $this->form->textEditor('comment', $values, $errors, array('autofocus' => true, 'required' => true, 'aria-label' => t('New comment'))) ?>
    <?php
    $formName = 'visibility';
    $visibilityOptions['app-user'] = t('Standard users');
    $attribute[] = 'hidden';
    if ($this->user->getRole() !== Role::APP_USER) {
        echo $this->form->label(t('Visibility:'), $formName);
        $attribute = array();
        $visibilityOptions['app-manager'] = t('Application managers or more');
    }
    if ($this->user->getRole() === Role::APP_ADMIN) {
        $visibilityOptions['app-admin'] = t('Administrators');
    }
    ?>
    <?= $this->form->select($formName, $visibilityOptions, array(), array(), $attribute) ?>
    <?= $this->modal->submitButtons() ?>
</form>
