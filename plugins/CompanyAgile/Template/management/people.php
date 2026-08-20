<?php if ($paginator->isEmpty()): ?>
    <div class="ca-management-empty"><i class="fa fa-users" aria-hidden="true"></i><p><?= t('CompanyAgile: No people found.') ?></p></div>
<?php else: ?>
    <div class="ca-management-table-wrap"><table class="ca-management-table ca-management-people-table">
        <thead><tr><th><?= $paginator->order(t('Project'), 'projects.name') ?></th><th><?= $paginator->order(t('User'), 'users.username') ?></th><th><?= t('CompanyAgile: Role') ?></th></tr></thead>
        <tbody><?php foreach ($paginator->getCollection() as $person): ?>
            <?php $display_name = (string) ($person['name'] ?: $person['username'] ?: ''); $words = $display_name === '' ? array() : preg_split('/\s+/', trim($display_name)); $initials = empty($words) ? '' : mb_strtoupper(mb_substr($words[0], 0, 1).(count($words) > 1 ? mb_substr(end($words), 0, 1) : '')); ?>
            <tr><td><strong><?= $this->text->e($person['project_name']) ?></strong></td><td><?php if ($display_name !== ''): ?><span class="ca-management-person"><span class="ca-person-avatar"><?= $this->text->e($initials) ?></span><?= $this->text->e($display_name) ?></span><?php endif ?></td><td><span class="ca-management-role"><?= $this->text->e($role_label) ?></span></td></tr>
        <?php endforeach ?></tbody>
    </table></div>
    <?= $paginator ?>
<?php endif ?>
