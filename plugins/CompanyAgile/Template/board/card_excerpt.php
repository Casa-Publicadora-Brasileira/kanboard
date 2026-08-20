<?php if (! empty($task['description'])): ?>
    <?php $excerpt = trim(preg_replace('/\s+/', ' ', strip_tags($task['description']))); ?>
    <?php if ($excerpt !== ''): ?>
        <p class="ca-card-excerpt"><?= $this->text->e(mb_strimwidth($excerpt, 0, 115, '…', 'UTF-8')) ?></p>
    <?php endif ?>
<?php endif ?>
