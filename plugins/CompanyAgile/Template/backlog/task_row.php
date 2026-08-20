<article class="ca-backlog-task" draggable="true" data-task-id="<?= (int) $task['id'] ?>" data-ca-epic-id="<?= empty($task['epic_id']) ? 'none' : (int) $task['epic_id'] ?>">
    <button type="button" class="ca-drag-handle" aria-label="<?= t('CompanyAgile: Reorder task') ?>"><i class="fa fa-bars"></i></button>
    <span class="ca-backlog-key"><span class="ca-card-type" style="--ca-issue-color: <?= $this->text->e($task['issue_type_color']) ?>"><i class="fa fa-<?= $this->text->e($task['issue_type_icon']) ?>"></i><?= t('CompanyAgile issue type: '.$task['issue_type_code']) ?></span><span class="ca-backlog-id">#<?= (int) $task['id'] ?></span></span>
    <span class="ca-backlog-summary"><?= $this->url->link($this->text->e($task['title']), 'TaskViewController', 'show', array('task_id' => $task['id']), false, 'ca-backlog-title', $this->text->e($task['title'])) ?><small class="ca-backlog-assignee-mobile"><?= empty($task['assignee_username']) ? t('CompanyAgile: Unassigned') : $this->text->e($task['assignee_name'] ?: $task['assignee_username']) ?></small></span>
    <?php if (! empty($task['epic_title'])): ?><span class="ca-backlog-epic"><?= $this->text->e($task['epic_title']) ?></span><?php endif ?>
    <span class="ca-backlog-assignee"><?= empty($task['assignee_username']) ? t('CompanyAgile: Unassigned') : $this->text->e($task['assignee_name'] ?: $task['assignee_username']) ?></span>
    <span class="ca-backlog-priority" title="<?= t('CompanyAgile: Priority') ?>"><i class="fa fa-<?= (int) $task['priority'] > 0 ? 'arrow-up' : ((int) $task['priority'] < 0 ? 'arrow-down' : 'minus') ?>" aria-hidden="true"></i><?= (int) $task['priority'] > 0 ? t('CompanyAgile: High') : ((int) $task['priority'] < 0 ? t('CompanyAgile: Low') : t('CompanyAgile: Normal')) ?></span>
    <?php if ($task['issue_type_code'] !== 'epic'): ?>
        <?php if ($can_modify_tasks): ?>
            <form class="ca-agile-inline-form ca-backlog-points" method="post" action="<?= $this->url->href('AgileTaskController', 'estimate', array('plugin' => 'CompanyAgile', 'task_id' => $task['id'])) ?>">
                <?= $this->form->csrf() ?>
                <select name="story_points" aria-label="<?= t('CompanyAgile: Story Points') ?>">
                    <option value="">—</option>
                    <?php foreach (range(1, 5) as $point): ?>
                        <option value="<?= $point ?>"<?= (float) $task['story_points'] == (float) $point ? ' selected' : '' ?>><?= $point ?> SP</option>
                    <?php endforeach ?>
                    <?php if ($task['story_points'] !== null && ((float) $task['story_points'] < 1 || (float) $task['story_points'] > 5 || floor((float) $task['story_points']) != (float) $task['story_points'])): ?>
                        <option selected disabled><?= $this->text->e($task['story_points']) ?> SP</option>
                    <?php endif ?>
                </select>
                <button type="submit"><i class="fa fa-check"></i></button>
            </form>
        <?php else: ?>
            <span><?= $task['story_points'] === null ? '—' : $this->text->e($task['story_points']).' SP' ?></span>
        <?php endif ?>
    <?php endif ?>
</article>
