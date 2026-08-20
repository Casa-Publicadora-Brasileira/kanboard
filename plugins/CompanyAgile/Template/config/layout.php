<section id="main" class="pm-settings-shell">
    <section class="pm-settings-layout" id="config-section">
        <nav class="sidebar pm-settings-navigation" aria-label="<?= t('CompanyAgile: Settings') ?>">
            <?= $this->render($sidebar_template) ?>
        </nav>

        <main class="sidebar-content pm-settings-content">
            <?= $content_for_sublayout ?>
        </main>
    </section>
</section>
