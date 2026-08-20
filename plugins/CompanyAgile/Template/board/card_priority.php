<?php $priority = (int) $task['priority']; ?>
<span class="ca-card-priority ca-priority-<?= $priority > 0 ? 'high' : ($priority < 0 ? 'low' : 'normal') ?>" title="<?= t('CompanyAgile: Priority') ?>: <?= $priority > 0 ? t('CompanyAgile: High') : ($priority < 0 ? t('CompanyAgile: Low') : t('CompanyAgile: Normal')) ?>">
    <i class="fa fa-<?= $priority > 0 ? 'arrow-up' : ($priority < 0 ? 'arrow-down' : 'minus') ?>" aria-hidden="true"></i>
    <?= $priority > 0 ? t('CompanyAgile: High') : ($priority < 0 ? t('CompanyAgile: Low') : t('CompanyAgile: Normal')) ?>
</span>
