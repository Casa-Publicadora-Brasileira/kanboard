<?php $isEdit = ! empty($sprint); ?>
<form method="post" action="<?= $this->url->href('SprintController', $action, array('plugin' => 'CompanyAgile', 'project_id' => $project['id'], 'sprint_id' => $isEdit ? $sprint['id'] : 0)) ?>" class="ca-sprint-form">
    <?= $this->form->csrf() ?>
    <div class="page-header"><h2><?= $isEdit ? t('CompanyAgile: Edit Sprint') : t('CompanyAgile: Create Sprint') ?></h2></div>
    <?= $this->form->label(t('CompanyAgile: Name'), 'name') ?><?= $this->form->text('name', array('name' => $isEdit ? $sprint['name'] : ''), array(), array('required', 'autofocus')) ?>
    <?= $this->form->label(t('CompanyAgile: Goal'), 'goal') ?><textarea name="goal" id="form-goal" rows="4"><?= $isEdit ? $this->text->e($sprint['goal']) : '' ?></textarea>
    <div class="ca-form-grid"><div><?= $this->form->label(t('CompanyAgile: Start date'), 'planned_start_at') ?><input type="date" id="form-planned_start_at" name="planned_start_at" value="<?= $isEdit && $sprint['planned_start_at'] ? date('Y-m-d', $sprint['planned_start_at']) : '' ?>"></div><div><?= $this->form->label(t('CompanyAgile: End date'), 'planned_end_at') ?><input type="date" id="form-planned_end_at" name="planned_end_at" value="<?= $isEdit && $sprint['planned_end_at'] ? date('Y-m-d', $sprint['planned_end_at']) : '' ?>"></div></div>
    <?= $this->modal->submitButtons() ?>
</form>
