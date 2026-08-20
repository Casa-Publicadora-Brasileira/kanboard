<header class="ca-modal-page-header ca-task-context-header <?= isset($extra_class) ? $this->text->e($extra_class) : '' ?>">
    <h2><?= $this->text->e($modal_title) ?></h2>
    <div class="ca-task-modal-context">
        <span><?= t('CompanyAgile: Task context', (int) $task['id']) ?></span>
        <p><?= $this->text->e($task['title']) ?></p>
    </div>
</header>
