<section class="ca-management-summary" aria-label="<?= t('CompanyAgile: Summary') ?>">
    <article><strong><?= (int) $summary['task_count'] ?></strong><span><?= $is_active ? t('Open tasks') : t('Closed tasks') ?></span></article>
    <article><strong><?= (int) $summary['user_count'] ?></strong><span><?= t('CompanyAgile: Active assignees') ?></span></article>
    <article><strong><?= (int) $summary['project_count'] ?></strong><span><?= t('CompanyAgile: Projects') ?></span></article>
</section>
<section class="ca-management-quick-filters" data-ca-quick-filters>
    <?php if (! empty($summary['projects'])): ?><div class="ca-management-filter-group"><header><span><?= t('CompanyAgile: Projects') ?></span></header><div><?php foreach ($summary['projects'] as $project): ?><?php $active = in_array((int) $project['id'], $selected_project_ids, true) ?><button type="button" class="ca-management-filter-chip<?= $active ? ' is-active' : '' ?>" data-filter-group="project_ids" data-filter-value="<?= (int) $project['id'] ?>" aria-pressed="<?= $active ? 'true' : 'false' ?>"><i class="fa fa-check" aria-hidden="true"></i><strong><?= (int) $project['task_count'] ?></strong><?= $this->text->e($project['name']) ?></button><?php endforeach ?></div></div><?php endif ?>
    <?php if (! empty($summary['columns'])): ?><div class="ca-management-filter-group"><header><span><?= t('CompanyAgile: Workflow columns') ?></span></header><div><?php foreach ($summary['columns'] as $column): ?><?php $active = (bool) array_intersect($column['ids'], $selected_column_ids) ?><button type="button" class="ca-management-filter-chip<?= $active ? ' is-active' : '' ?>" data-filter-group="column_ids" data-filter-values="<?= $this->text->e(implode(',', $column['ids'])) ?>" aria-pressed="<?= $active ? 'true' : 'false' ?>"><i class="fa fa-check" aria-hidden="true"></i><strong><?= (int) $column['task_count'] ?></strong><?= $this->text->e($column['title']) ?></button><?php endforeach ?></div></div><?php endif ?>
    <?php if (! empty($selected_project_ids) || ! empty($selected_column_ids)): ?><button type="button" class="ca-management-clear-filters" data-ca-clear-quick-filters><?= t('CompanyAgile: Clear selection') ?></button><?php endif ?>
</section>

<?php if ($paginator->isEmpty()): ?>
    <div class="ca-management-empty"><i class="fa fa-search" aria-hidden="true"></i><p><?= t('CompanyAgile: No tasks found for the selected filters.') ?></p></div>
<?php else: ?>
    <div class="ca-management-table-wrap"><table class="ca-management-table">
        <thead><tr><th><?= $paginator->order(t('Id'), 'tasks.id') ?></th><th><?= $paginator->order(t('Project'), 'projects.name') ?></th><th><?= $paginator->order(t('Title'), 'tasks.title') ?></th><th><?= $paginator->order(t('Assignee'), 'users.username') ?></th><th><?= $paginator->order(t('Column'), 'tasks.column_id') ?></th><th><?= $paginator->order(t('Priority'), 'tasks.priority') ?></th><th><?= $paginator->order(t('Due date'), 'tasks.date_due') ?></th></tr></thead>
        <tbody><?php foreach ($paginator->getCollection() as $task): ?><tr>
            <td><?= $this->url->link('#'.(int) $task['id'], 'TaskViewController', 'show', array('task_id' => $task['id']), false, 'ca-management-task-link') ?></td><td><?= $this->text->e($task['project_name']) ?></td><td><?= $this->url->link($this->text->e($task['title']), 'TaskViewController', 'show', array('task_id' => $task['id']), false, 'ca-management-task-link') ?></td><td><?= $this->text->e($task['assignee_name'] ?: ($task['assignee_username'] ?: t('Unassigned'))) ?></td><td><?= $this->text->e($task['column_name']) ?></td>
            <td><span class="ca-management-priority ca-priority-<?= (int) $task['priority'] ?>"><?= (int) $task['priority'] > 0 ? t('CompanyAgile: High') : ((int) $task['priority'] < 0 ? t('CompanyAgile: Low') : t('CompanyAgile: Normal')) ?></span></td><td><?= (int) $task['date_due'] > 0 ? $this->dt->datetime($task['date_due']) : '—' ?></td>
        </tr><?php endforeach ?></tbody>
    </table></div>
    <?= $paginator ?>
<?php endif ?>
