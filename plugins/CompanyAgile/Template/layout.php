<?php $portalLayout = $this->user->getId() > 0; $fullTaskView = $portalLayout && strtolower($this->app->getRouterController()) === 'taskviewcontroller' ?>
<!DOCTYPE html>
<html lang="<?= $this->app->jsLang() ?>"<?= $this->app->isRtlLanguage() ? ' dir="rtl"' : '' ?><?= $portalLayout ? ' class="company-agile'.($fullTaskView ? ' ca-full-task-view' : '').'"' : '' ?>>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="robots" content="noindex,nofollow">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="referrer" content="no-referrer">
        <?php if (isset($board_public_refresh_interval)): ?><meta http-equiv="refresh" content="<?= $board_public_refresh_interval ?>"><?php endif ?>
        <?php if ($portalLayout): ?>
        <style id="portal-management-critical">html.company-agile{--ca-sidebar-width:248px;background:#f7f8fa;color:#172b4d}html.company-agile body{min-height:100vh;margin:0;padding-left:var(--ca-sidebar-width);background:#f7f8fa;box-sizing:border-box}html.company-agile .ca-sidebar{position:fixed;z-index:700;inset:0 auto 0 0;display:flex;width:var(--ca-sidebar-width);background:#172b4d;transition:none!important}html.company-agile body>header{background:#fff;border-bottom:1px solid #dfe1e6;transition:none!important}html.company-agile body>header .board-selector-container .js-select-dropdown-autocomplete,html.company-agile body>header .board-selector-container .js-select-dropdown-autocomplete-rendered{position:absolute!important;width:0!important;height:0!important;overflow:hidden!important;visibility:hidden!important;pointer-events:none!important}html.company-agile .page{background:#f7f8fa;transition:none!important}@media(max-width:760px){html.company-agile{--ca-sidebar-width:0px}html.company-agile body{padding-left:0}html.company-agile .ca-sidebar{display:none}}</style>
        <?php endif ?>
        <?= $this->asset->colorCss() ?>
        <?= $this->asset->css('assets/css/vendor.min.css') ?>
        <?= $this->asset->css(isset($not_editable) ? 'assets/css/light.min.css' : 'assets/css/'.$this->user->getTheme().'.min.css') ?>
        <?= $this->asset->css('assets/css/print.min.css', true, 'print') ?>
        <?= $this->asset->customCss() ?>
        <?php if (! isset($not_editable)): ?><?= $this->asset->js('assets/js/vendor.min.js') ?><?= $this->asset->js('assets/js/app.min.js') ?><?php endif ?>
        <?= $this->hook->asset('css', 'template:layout:css') ?>
        <?= $this->hook->asset('js', 'template:layout:js') ?>
        <link rel="icon" href="<?= $this->url->dir() ?>assets/img/adaptive-favicon.svg" type="image/svg+xml">
        <link rel="icon" type="image/png" href="<?= $this->url->dir() ?>assets/img/favicon.png">
        <link rel="apple-touch-icon" href="<?= $this->url->dir() ?>assets/img/touch-icon-iphone.png">
        <link rel="apple-touch-icon" sizes="72x72" href="<?= $this->url->dir() ?>assets/img/touch-icon-ipad.png">
        <link rel="apple-touch-icon" sizes="114x114" href="<?= $this->url->dir() ?>assets/img/touch-icon-iphone-retina.png">
        <link rel="apple-touch-icon" sizes="144x144" href="<?= $this->url->dir() ?>assets/img/touch-icon-ipad-retina.png">
        <title><?= isset($page_title) ? $this->text->e($page_title) : (isset($title) ? $this->text->e($title) : 'Portal Management') ?></title>
        <?= $this->hook->render('template:layout:head') ?>
    </head>
    <body data-status-url="<?= $this->url->href('UserAjaxController', 'status') ?>" data-login-url="<?= $this->url->href('AuthController', 'login') ?>" data-keyboard-shortcut-url="<?= $this->url->href('DocumentationController', 'shortcuts') ?>" data-timezone="<?= $this->app->getTimezone() ?>" data-js-date-format="<?= $this->app->getJsDateFormat() ?>" data-js-time-format="<?= $this->app->getJsTimeFormat() ?>">
    <?php if (isset($no_layout) && $no_layout): ?>
        <?= $this->app->flashMessage() ?><?= $content_for_layout ?>
    <?php else: ?>
        <?= $this->hook->render('template:layout:top') ?>
        <?= $this->render('header', array('title' => $title, 'description' => isset($description) ? $description : '', 'board_selector' => isset($board_selector) ? $board_selector : array(), 'project' => isset($project) ? $project : array())) ?>
        <section class="page"><?= $this->app->flashMessage() ?><?= $content_for_layout ?></section>
        <?= $this->hook->render('template:layout:bottom') ?>
    <?php endif ?>
    </body>
</html>
