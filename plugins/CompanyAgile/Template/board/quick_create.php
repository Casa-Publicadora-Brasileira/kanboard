<?php
$visibleTasks = (int) $column['nb_tasks'];
$totalTasks = isset($column['nb_unfiltered_tasks_across_swimlane']) ? (int) $column['nb_unfiltered_tasks_across_swimlane'] : $visibleTasks;
$metricLabel = $visibleTasks === $totalTasks ? (string) $visibleTasks : $visibleTasks.' / '.$totalTasks;
$metricTitle = t('CompanyAgile: %d visible tasks of %d total tasks', $visibleTasks, $totalTasks);
if (! empty($column['score'])) {
    $metricTitle .= ' · '.t('CompanyAgile: Complexity score: %s', $column['score']);
}
if (! empty($column['task_limit'])) {
    $metricTitle .= ' · '.t('CompanyAgile: WIP limit: %d', $column['task_limit']);
}
?>
<span class="ca-column-metrics" title="<?= $this->text->e($metricTitle) ?>" aria-label="<?= $this->text->e($metricTitle) ?>"><?= $this->text->e($metricLabel) ?></span>
<?php if ($this->projectRole->canCreateTaskInColumn($column['project_id'], $column['id'])): ?>
<button type="button" class="ca-column-create" data-ca-quick-create-url="<?= $this->url->href('QuickTaskController', 'show', array('plugin' => 'CompanyAgile', 'project_id' => $column['project_id'], 'column_id' => $column['id'], 'swimlane_id' => $swimlane['id'])) ?>">
    <i class="fa fa-plus" aria-hidden="true"></i> <?= t('CompanyAgile: Create task') ?>
</button>
<?php endif ?>
