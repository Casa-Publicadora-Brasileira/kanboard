<div class="page-header">
    <h2><?= t('User Report') ?></h2>
</div>
<div class="page-content">
    <?php if (empty($user)): ?>
        <p class="alert alert-error"><?= t('User not found.') ?></p>
    <?php else: ?>
        <h3><?= t('Report for %s', $this->text->e($user['name'] ?: $user['username'])) ?></h3>
        <!-- Add your user-specific report charts or data here -->
    <?php endif ?>
</div>
