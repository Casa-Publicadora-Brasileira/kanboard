<?php

use Kanboard\Core\Security\Role;

$userRole = $this->user->getRole();

if ($userRole === '' && $comment['visibility'] !== Role::APP_USER) {
    return;
}

if ($userRole === Role::APP_MANAGER && $comment['visibility'] === Role::APP_ADMIN) {
    return;
}

if ($userRole === Role::APP_USER && $comment['visibility'] !== Role::APP_USER) {
    return;
}

// Legacy comments can keep a user_id after the related user identity fields
// have become NULL. PHP 8.4 deprecates passing that NULL to getInitials().
$commentUsername = isset($comment['username']) ? (string) $comment['username'] : '';
$commentName = isset($comment['name']) ? (string) $comment['name'] : '';
$commentEmail = isset($comment['email']) ? (string) $comment['email'] : '';
$commentAvatarPath = isset($comment['avatar_path']) ? (string) $comment['avatar_path'] : '';
$commentAuthor = $commentName !== '' ? $commentName : $commentUsername;
$hasCommentAuthor = $commentAuthor !== '';

?>

<div class="comment <?= isset($preview) ? 'comment-preview' : '' ?><?= $hasCommentAuthor ? '' : ' ca-comment-without-author' ?>" id="comment-<?= (int) $comment['id'] ?>">
    <?php if ($hasCommentAuthor): ?>
        <?= $this->avatar->render($comment['user_id'], $commentUsername, $commentName, $commentEmail, $commentAvatarPath) ?>
    <?php endif ?>

    <div class="comment-title">
        <?php if ($hasCommentAuthor): ?><strong class="comment-username"><?= $this->text->e($commentAuthor) ?></strong><?php endif ?>
        <small class="comment-date"><?= t('Created at:') ?> <?= $this->dt->datetime($comment['date_creation']) ?></small>
        <small class="comment-date"><?= t('Updated at:') ?> <?= $this->dt->datetime($comment['date_modification']) ?></small>
        <small class="comment-visibility"><?= t('Visibility:') ?>
            <?php if ($comment['visibility'] === Role::APP_USER): ?>
                <?= t('Standard users') ?>
            <?php elseif ($comment['visibility'] === Role::APP_MANAGER): ?>
                <?= t('Application managers or more') ?>
            <?php else: ?>
                <?= t('Administrators') ?>
            <?php endif ?>
        </small>
    </div>

    <?php if (! isset($hide_actions)): ?>
    <div class="comment-actions">
        <div class="dropdown">
            <a href="#" class="dropdown-menu dropdown-menu-link-icon" aria-label="<?= t('Actions') ?>"><span aria-hidden="true">•••</span></a>
            <ul>
                <li><?= $this->url->icon('link', t('Link'), 'TaskViewController', 'show', array('task_id' => $task['id']), false, '', '', $this->app->isAjax(), 'comment-'.$comment['id']) ?></li>
                <li data-comment-id="<?= (int) $comment['id'] ?>"><?= $this->url->icon('reply', t('Reply'), 'TaskViewController', 'show', array('task_id' => $task['id']), false, 'js-reply-to-comment', '', $this->app->isAjax(), 'form-task_id') ?></li>
                <?php if ($editable && ($this->user->isAdmin() || $this->user->isCurrentUser($comment['user_id']))): ?>
                    <li><?= $this->modal->medium('edit', t('Edit'), 'CommentController', 'edit', array('task_id' => $task['id'], 'comment_id' => $comment['id'])) ?></li>
                    <li><?= $this->modal->confirm('trash-o', t('Remove'), 'CommentController', 'confirm', array('task_id' => $task['id'], 'comment_id' => $comment['id'])) ?></li>
                <?php endif ?>
            </ul>
        </div>
    </div>
    <?php endif ?>

    <div class="comment-content">
        <div class="markdown"><?= $this->text->markdown((string) $comment['comment'], isset($is_public) && $is_public) ?></div>
        <template id="comment-reply-content-<?= (int) $comment['id'] ?>">
            <textarea><?= $this->text->e($this->text->reply($commentAuthor, (string) $comment['comment'])) ?></textarea>
        </template>
    </div>
</div>
