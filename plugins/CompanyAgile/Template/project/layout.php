<section id="main">
    <?= $this->projectHeader->render($project, 'TaskListController', 'show') ?>
    <?php if ($sidebar_template === 'project/sidebar'): ?>
        <section id="project-settings-section" class="sidebar-container pm-project-settings-surface">
            <main class="sidebar-content">
                <?= $content_for_sublayout ?>
            </main>
        </section>
    <?php else: ?>
        <section class="sidebar-container">
            <?= $this->render($sidebar_template, array('project' => $project)) ?>
            <div class="sidebar-content">
                <?= $content_for_sublayout ?>
            </div>
        </section>
    <?php endif ?>
</section>
