<?= $this->render('companyAgile:task/modal_header', array('modal_title' => t('Edit a comment'), 'task' => $task)) ?>
<form method="post" action="<?= $this->url->href('CommentController', 'update', array('task_id' => $task['id'], 'comment_id' => $comment['id'])) ?>" autocomplete="off">
    <?= $this->form->csrf() ?>
    <?= $this->form->textEditor('comment', $values, $errors, array('autofocus' => true, 'required' => true, 'aria-label' => t('Comment'))) ?>
    <?= $this->modal->submitButtons() ?>
</form>
