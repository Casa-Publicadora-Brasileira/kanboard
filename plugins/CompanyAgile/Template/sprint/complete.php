<form method="post" action="<?= $this->url->href('SprintController', 'complete', array('plugin' => 'CompanyAgile', 'project_id' => $project['id'], 'sprint_id' => $sprint['id'])) ?>" class="ca-sprint-form">
    <?= $this->form->csrf() ?>
    <div class="page-header"><h2><?= t('CompanyAgile: Complete %s', $sprint['name']) ?></h2></div>
    <div class="ca-completion-summary"><strong><?= t('CompanyAgile: %d completed tasks', $summary['completed']) ?></strong><strong><?= t('CompanyAgile: %d incomplete tasks', $summary['incomplete']) ?></strong></div>
    <?php if ($summary['incomplete'] > 0): ?><fieldset><legend><?= t('CompanyAgile: What should happen to incomplete tasks?') ?></legend><label><input type="radio" name="destination_sprint_id" value="0" checked> <?= t('CompanyAgile: Move to Backlog') ?></label><?php foreach ($destinations as $destination): ?><label><input type="radio" name="destination_sprint_id" value="<?= (int) $destination['id'] ?>"> <?= t('CompanyAgile: Move to %s', $destination['name']) ?></label><?php endforeach ?></fieldset><?php else: ?><input type="hidden" name="destination_sprint_id" value="0"><?php endif ?>
    <?= $this->modal->submitButtons(array('submitLabel' => t('CompanyAgile: Complete Sprint'))) ?>
</form>
