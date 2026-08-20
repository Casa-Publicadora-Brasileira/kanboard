<article class="ca-task-panel-content" aria-labelledby="ca-task-panel-title">
    <header class="ca-task-panel-header">
        <div>
            <span class="ca-task-panel-eyebrow"><i class="fa fa-<?= $this->text->e($issue_type['icon']) ?>" aria-hidden="true"></i> <?= t('CompanyAgile issue type: '.$issue_type['code']) ?> · #<?= (int) $task['id'] ?></span>
            <h2 id="ca-task-panel-title"><?= $this->text->e($task['title']) ?></h2>
        </div>
        <button type="button" class="ca-icon-button" data-ca-panel-close aria-label="<?= t('CompanyAgile: Close task panel') ?>">
            <i class="fa fa-times" aria-hidden="true"></i>
        </button>
    </header>

    <div class="ca-task-panel-actions">
        <?= $this->url->icon('pencil', t('CompanyAgile: Edit task'), 'TaskModificationController', 'edit', array('task_id' => $task['id']), false, 'js-modal-large') ?>
        <?= $this->modal->medium('comment', t('CompanyAgile: Add comment'), 'CommentController', 'create', array('task_id' => $task['id'])) ?>
        <?= $this->modal->medium('paperclip', t('CompanyAgile: Add attachment'), 'TaskFileController', 'create', array('task_id' => $task['id'])) ?>
        <?= $this->modal->medium('plus', t('CompanyAgile: Add subtask'), 'SubtaskController', 'create', array('task_id' => $task['id'])) ?>
        <details class="ca-task-more-actions ca-panel-more"><summary><?= t('CompanyAgile: More actions') ?></summary><?= $this->render('companyAgile:task/more_actions', array('task' => $task)) ?></details>
        <a class="ca-open-full-task" href="<?= $this->url->href('TaskViewController', 'show', array('task_id' => $task['id'])) ?>" target="_blank" rel="noopener noreferrer" title="<?= t('CompanyAgile: Open full task page in new tab') ?>"><i class="fa fa-external-link" aria-hidden="true"></i><?= t('CompanyAgile: Open full task page') ?></a>
    </div>

    <section class="ca-panel-section ca-panel-details">
        <h3><?= t('CompanyAgile: Details') ?></h3>
        <dl class="ca-detail-grid">
            <div><dt><?= t('CompanyAgile: Issue type') ?></dt><dd><?php if ($can_modify): ?><form class="ca-agile-inline-form" method="post" action="<?= $this->url->href('AgileTaskController', 'issueType', array('plugin' => 'CompanyAgile', 'task_id' => $task['id'])) ?>"><?= $this->form->csrf() ?><select name="issue_type_id" aria-label="<?= t('CompanyAgile: Issue type') ?>"><?php foreach ($issue_types as $type): ?><option value="<?= (int) $type['id'] ?>" <?= $type['code'] === $issue_type['code'] ? 'selected' : '' ?>><?= t('CompanyAgile issue type: '.$type['code']) ?></option><?php endforeach ?></select><button type="submit"><?= t('CompanyAgile: Save') ?></button></form><?php else: ?><span class="ca-issue-type" style="--ca-issue-color: <?= $this->text->e($issue_type['color']) ?>"><i class="fa fa-<?= $this->text->e($issue_type['icon']) ?>"></i><?= t('CompanyAgile issue type: '.$issue_type['code']) ?></span><?php endif ?></dd></div>
            <div><dt><?= t('CompanyAgile: Status') ?></dt><dd><?= $task['is_active'] ? t('CompanyAgile: Open') : t('CompanyAgile: Closed') ?></dd></div>
            <div><dt><?= t('CompanyAgile: Assignee') ?></dt><dd><?= empty($task['assignee_name']) && empty($task['assignee_username']) ? t('CompanyAgile: Unassigned') : $this->text->e($task['assignee_name'] ?: $task['assignee_username']) ?></dd></div>
            <div><dt><?= t('CompanyAgile: Priority') ?></dt><dd><?= $this->text->e($task['priority']) ?></dd></div>
            <div><dt><?= t('CompanyAgile: Category') ?></dt><dd><?= empty($task['category_name']) ? '—' : $this->text->e($task['category_name']) ?></dd></div>
            <div><dt><?= t('CompanyAgile: Column') ?></dt><dd><?= $this->text->e($task['column_title']) ?></dd></div>
            <div><dt><?= t('CompanyAgile: Sprint') ?></dt><dd><?= empty($sprint) ? t('CompanyAgile: Backlog') : $this->text->e($sprint['name']) ?></dd></div>
            <?php if ($issue_type['code'] === 'story'): ?><div><dt><?= t('CompanyAgile: Epic') ?></dt><dd><?php if ($can_modify): ?><form class="ca-agile-inline-form" method="post" action="<?= $this->url->href('AgileTaskController', 'epic', array('plugin' => 'CompanyAgile', 'task_id' => $task['id'])) ?>"><?= $this->form->csrf() ?><select name="epic_id"><option value="0"><?= t('CompanyAgile: None') ?></option><?php foreach ($project_epics as $epic): ?><option value="<?= (int) $epic['id'] ?>" <?= ! empty($parent_epic) && $parent_epic['id'] == $epic['id'] ? 'selected' : '' ?>>#<?= (int) $epic['id'] ?> <?= $this->text->e($epic['title']) ?></option><?php endforeach ?></select><button type="submit"><?= t('CompanyAgile: Save') ?></button></form><?php else: ?><?= empty($parent_epic) ? t('CompanyAgile: None') : $this->text->e($parent_epic['title']) ?><?php endif ?></dd></div><?php endif ?>
            <?php if ($issue_type['code'] !== 'epic'): ?><div><dt><?= t('CompanyAgile: Story Points') ?></dt><dd><?php if ($can_modify): ?><form class="ca-agile-inline-form" method="post" action="<?= $this->url->href('AgileTaskController', 'estimate', array('plugin' => 'CompanyAgile', 'task_id' => $task['id'])) ?>"><?= $this->form->csrf() ?><select name="story_points" aria-label="<?= t('CompanyAgile: Story Points') ?>"><option value=""><?= t('CompanyAgile: Not estimated') ?></option><?php foreach (range(1, 5) as $point): ?><option value="<?= $point ?>"<?= (float) $story_points === (float) $point ? ' selected' : '' ?>><?= $point ?></option><?php endforeach ?><?php if ($story_points !== null && ((float) $story_points < 1 || (float) $story_points > 5 || floor((float) $story_points) != (float) $story_points)): ?><option selected disabled><?= t('CompanyAgile: Legacy value: %s', $story_points) ?></option><?php endif ?></select><button type="submit"><?= t('CompanyAgile: Save') ?></button></form><?php else: ?><?= $story_points === null ? t('CompanyAgile: Not estimated') : $this->text->e($story_points).' SP' ?><?php endif ?></dd></div><?php endif ?>
            <div class="ca-detail-wide"><dt><?= t('CompanyAgile: Work estimates') ?></dt><dd><?php if ($can_modify): ?><form class="ca-agile-inline-form ca-time-form" method="post" action="<?= $this->url->href('AgileTaskController', 'time', array('plugin' => 'CompanyAgile', 'task_id' => $task['id'])) ?>"><?= $this->form->csrf() ?><label><?= t('CompanyAgile: Original estimate') ?> <input name="time_estimated" type="number" min="0" step="0.01" value="<?= $this->text->e($task['time_estimated']) ?>"></label><label><?= t('CompanyAgile: Time spent') ?> <input name="time_spent" type="number" min="0" step="0.01" value="<?= $this->text->e($task['time_spent']) ?>"></label><button type="submit"><?= t('CompanyAgile: Save') ?></button></form><?php else: ?><span><?= t('CompanyAgile: Original estimate') ?>: <?= $this->text->e($task['time_estimated']) ?>h · <?= t('CompanyAgile: Time spent') ?>: <?= $this->text->e($task['time_spent']) ?>h · </span><?php endif ?><span><?= t('CompanyAgile: Remaining time') ?>: <?= $this->text->e(max((float) $task['time_estimated'] - (float) $task['time_spent'], 0)) ?>h</span></dd></div>
            <div class="ca-detail-wide"><dt><?= t('CompanyAgile: Tags') ?></dt><dd><?php if (empty($tags)): ?>—<?php else: ?><?php foreach ($tags as $tag): ?><span class="ca-tag <?= ! empty($tag['project_id']) ? 'ca-tag-project' : 'ca-tag-global' ?>"><?= $this->text->e($tag['name']) ?></span><?php endforeach ?><?php endif ?></dd></div>
        </dl>
    </section>

    <?php if ($issue_type['code'] === 'epic'): ?><section class="ca-panel-section ca-epic-progress"><h3><?= t('CompanyAgile: Stories') ?> <span class="ca-count"><?= (int) $epic_progress['completed'] ?> / <?= (int) $epic_progress['total'] ?></span></h3><p><strong><?= $this->text->e($epic_progress['points_completed']) ?> / <?= $this->text->e($epic_progress['points_total']) ?> SP</strong> <?= t('CompanyAgile: completed') ?></p><div class="ca-epic-stories"><?php foreach ($epic_progress['stories'] as $story): ?><div><span>STORY #<?= (int) $story['id'] ?></span><a href="<?= $this->url->href('TaskViewController', 'show', array('task_id' => $story['id'])) ?>"><?= $this->text->e($story['title']) ?></a><span><?= $story['story_points'] === null ? '—' : $this->text->e($story['story_points']).' SP' ?></span><?php if ($can_modify): ?><form class="ca-agile-inline-form" method="post" action="<?= $this->url->href('AgileTaskController', 'removeStory', array('plugin' => 'CompanyAgile', 'task_id' => $task['id'])) ?>"><?= $this->form->csrf() ?><input type="hidden" name="story_id" value="<?= (int) $story['id'] ?>"><button type="submit"><?= t('CompanyAgile: Remove from Epic') ?></button></form><?php endif ?></div><?php endforeach ?></div><?php if ($can_modify && ! empty($available_stories)): ?><form class="ca-agile-inline-form ca-add-story-form" method="post" action="<?= $this->url->href('AgileTaskController', 'addStory', array('plugin' => 'CompanyAgile', 'task_id' => $task['id'])) ?>"><?= $this->form->csrf() ?><select name="story_id"><?php foreach ($available_stories as $story): ?><option value="<?= (int) $story['id'] ?>">#<?= (int) $story['id'] ?> <?= $this->text->e($story['title']) ?> · <?= empty($story['sprint_name']) ? 'Backlog' : $this->text->e($story['sprint_name']) ?> · <?= $story['story_points'] === null ? '—' : $this->text->e($story['story_points']).' SP' ?></option><?php endforeach ?></select><button type="submit">+ <?= t('CompanyAgile: Add Story') ?></button></form><?php endif ?></section><?php endif ?>

    <section class="ca-panel-section ca-panel-description">
        <h3><?= t('CompanyAgile: Description') ?></h3>
        <div class="markdown ca-description"><?= empty($task['description']) ? '<p class="ca-empty">'.t('CompanyAgile: No description.').'</p>' : $this->text->markdown($task['description']) ?></div>
    </section>

    <section class="ca-panel-section ca-panel-subtasks">
        <h3><?= t('CompanyAgile: Subtasks') ?> <span class="ca-count"><?= count($subtasks) ?></span></h3>
        <?= $this->render('subtask/table', array('subtasks' => $subtasks, 'task' => $task, 'editable' => $this->user->hasProjectAccess('SubtaskController', 'edit', $project['id']))) ?>
        <p class="ca-section-action"><?= $this->modal->medium('plus', t('CompanyAgile: Add subtask'), 'SubtaskController', 'create', array('task_id' => $task['id'])) ?></p>
    </section>

    <section class="ca-panel-section ca-panel-comments">
        <h3><?= t('CompanyAgile: Comments') ?> <span class="ca-count"><?= count($comments) ?></span></h3>
        <div class="comments"><?php foreach ($comments as $comment): ?><?= $this->render('comment/show', array('comment' => $comment, 'task' => $task, 'project' => $project, 'editable' => $this->user->hasProjectAccess('CommentController', 'edit', $project['id']))) ?><?php endforeach ?></div>
        <p class="ca-section-action"><?= $this->modal->medium('comment', t('CompanyAgile: Add comment'), 'CommentController', 'create', array('task_id' => $task['id'])) ?></p>
    </section>

    <section class="ca-panel-section ca-panel-attachments">
        <h3><?= t('CompanyAgile: Attachments') ?> <span class="ca-count"><?= count($files) + count($images) ?></span></h3>
        <?= $this->render('task_file/images', array('task' => $task, 'images' => $images)) ?>
        <?= $this->render('task_file/files', array('task' => $task, 'files' => $files)) ?>
        <p class="ca-section-action"><?= $this->modal->medium('paperclip', t('CompanyAgile: Add attachment'), 'TaskFileController', 'create', array('task_id' => $task['id'])) ?></p>
    </section>

    <section class="ca-panel-section ca-panel-activity">
        <h3><?= t('CompanyAgile: Activity') ?></h3>
        <?= $this->render('event/events', array('events' => $events)) ?>
    </section>
</article>
