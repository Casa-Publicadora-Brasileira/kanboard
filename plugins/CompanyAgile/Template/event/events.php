<?php if (empty($events)): ?>
    <p class="alert"><?= t('There is no activity yet.') ?></p>
<?php else: ?>
    <?php foreach ($events as $event): ?>
        <?php
        $authorUsername = isset($event['author_username']) ? (string) $event['author_username'] : '';
        $authorName = isset($event['author_name']) ? (string) $event['author_name'] : '';
        $authorEmail = isset($event['email']) ? (string) $event['email'] : '';
        $authorAvatarPath = isset($event['avatar_path']) ? (string) $event['avatar_path'] : '';
        $authorLabel = $authorName !== '' ? $authorName : $authorUsername;
        $hasEventAuthor = $authorLabel !== '';
        ?>
        <div class="activity-event<?= $hasEventAuthor ? '' : ' ca-activity-without-author' ?>">
            <?php if ($hasEventAuthor): ?><?= $this->avatar->render($event['creator_id'], $authorUsername, $authorName, $authorEmail, $authorAvatarPath) ?><?php endif ?>
            <div class="activity-content"><?= $event['event_content'] ?></div>
        </div>
    <?php endforeach ?>
<?php endif ?>
