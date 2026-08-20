<div class="task-board ca-board-card <?= $task['is_draggable'] ? 'draggable-item ' : '' ?><?= $task['is_active'] == 1 ? 'task-board-status-open '.($task['date_modification'] > (time() - $board_highlight_period) ? 'task-board-recent' : '') : 'task-board-status-closed' ?> color-<?= $task['color_id'] ?>"
     data-task-id="<?= $task['id'] ?>" data-column-id="<?= $task['column_id'] ?>" data-swimlane-id="<?= $task['swimlane_id'] ?>" data-position="<?= $task['position'] ?>" data-owner-id="<?= $task['owner_id'] ?>" data-category-id="<?= $task['category_id'] ?>" data-due-date="<?= $task['date_due'] ?>" data-task-url="<?= $this->url->href('TaskViewController', 'show', array('task_id' => $task['id'])) ?>">
    <div class="task-board-sort-handle" style="display:none"><i class="fa fa-arrows-alt"></i></div>
    <?php if ($this->board->isCollapsed($task['project_id'])): ?>
        <div class="task-board-collapsed">
            <div class="task-board-saving-icon" style="display:none"><i class="fa fa-spinner fa-pulse"></i></div>
            <?php if ($this->user->hasProjectAccess('TaskModificationController', 'edit', $task['project_id'])): ?>
                <?= $this->render('task/dropdown', array('task' => $task, 'redirect' => 'board')) ?>
                <?php if ($this->projectRole->canUpdateTask($task)): ?><?= $this->modal->large('edit', '', 'TaskModificationController', 'edit', array('task_id' => $task['id'])) ?><?php endif ?>
            <?php else: ?><strong>#<?= (int) $task['id'] ?></strong><?php endif ?>
            <?php if (! empty($task['assignee_username'])): ?><span title="<?= $this->text->e($task['assignee_name'] ?: $task['assignee_username']) ?>"><?= $this->text->e($this->user->getInitials($task['assignee_name'] ?: $task['assignee_username'])) ?></span> -<?php endif ?>
            <?= $this->url->link($this->text->e($task['title']), 'TaskViewController', 'show', array('task_id' => $task['id']), false, 'ca-task-link', $this->text->e($task['title'])) ?>
        </div>
    <?php else: ?>
        <div class="task-board-expanded">
            <div class="task-board-saving-icon" style="display:none"><i class="fa fa-spinner fa-pulse fa-2x"></i></div>
            <div class="task-board-header ca-board-card-header">
                <?php if ($this->user->hasProjectAccess('TaskModificationController', 'edit', $task['project_id'])): ?>
                    <?= $this->render('task/dropdown', array('task' => $task, 'redirect' => 'board')) ?>
                    <?php if ($this->projectRole->canUpdateTask($task)): ?><?= $this->modal->large('edit', '', 'TaskModificationController', 'edit', array('task_id' => $task['id'])) ?><?php endif ?>
                <?php else: ?><strong>#<?= (int) $task['id'] ?></strong><?php endif ?>
            </div>
            <?= $this->hook->render('template:board:private:task:before-title', array('task' => $task)) ?>
            <div class="task-board-title"><?= $this->url->link($this->text->e($task['title']), 'TaskViewController', 'show', array('task_id' => $task['id']), false, 'ca-task-link') ?></div>
            <?= $this->hook->render('template:board:private:task:after-title', array('task' => $task)) ?>
            <div class="ca-board-card-footer"><?= $this->render('board/task_footer', array('task' => $task, 'not_editable' => $not_editable, 'project' => $project)) ?><?php if (! empty($task['owner_id']) && (! empty($task['assignee_username']) || ! empty($task['assignee_name']))): ?><span class="ca-card-assignee ca-card-assignee-server"><?= $this->render('board/task_avatar', array('task' => $task)) ?></span><?php endif ?></div>
        </div>
    <?php endif ?>
</div>
