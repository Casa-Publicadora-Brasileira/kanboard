<div class="bg-white p-6 rounded-lg shadow-md max-w-7xl mx-auto">

    <!-- Cabeçalho -->
    <div class="border-b-2 border-primary-500 pb-3 mb-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <h2 class="text-2xl font-bold text-slate-800 tracking-tight">
                <?= t('Desempenho do Projeto') ?>: <span class="text-primary-600"><?= $this->text->e($project_name) ?></span>
            </h2>
            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-700 bg-slate-50 border rounded-full border-slate-200 px-3 py-1 shadow-sm">
                <span class="inline-block w-2 h-2 rounded-full bg-primary-500"></span>
                <span>Sprint <?= $sprint ?></span>
                <span class="text-primary-300">•</span>
                <span class="text-primary-500 font-normal"><?= $period ?></span>
            </span>
        </div>

        <!-- Seletor de Projetos -->
        <div class="flex items-center gap-2 mt-4 flex-wrap">
            <span class="text-xs font-semibold text-slate-500 uppercase"><?= t('Projetos') ?>:</span>
            <?php foreach ($available_projects as $proj): ?>
                <a href="<?= $this->url->href('ReportController', 'project', array('project_tag_id' => $proj['id'])) ?>"
                   class="text-xs px-3 py-1 rounded-full border transition <?= $selected_project == $proj['id'] ? 'bg-primary-600 text-white border-primary-600 font-bold shadow-xs' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100 hover:border-slate-300' ?>">
                    <?= $this->text->e($proj['name']) ?>
                </a>
            <?php endforeach ?>
        </div>
    </div>

    <!-- Indicadores Sintéticos -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 my-4">

        <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 flex flex-col">
            <span class="text-xs font-semibold text-primary-500 uppercase tracking-wider"><?= t('Tarefas Entregues') ?></span>
            <div class="flex items-baseline gap-1 mt-1">
                <span class="text-2xl font-semibold text-primary-800"><?= $tasks['concluded'] ?></span>
                <span class="text-lg font-normal text-primary-500">/<?= $tasks['total'] ?></span>
            </div>
        </div>

        <div class="bg-slate-50 border border-slate-200 <?= $unexpected['tasks'] > 0 ? 'active' : '' ?> active:bg-orange-50/50 border active:border-orange-200 p-3.5 rounded-lg">
            <span class="block text-xs font-semibold text-primary-500 group-active:text-orange-800 uppercase tracking-wider mb-1">
                <?= t('Taxa de Interrupção') ?>
            </span>
            <div class="flex items-center gap-2">
                <span class="text-2xl font-semibold text-primary-700 group-active:text-orange-700"><?= $unexpected['percent'] * 100 ?>%</span>
                <span class="inline-flex items-center text-xs font-normal text-primary-800 border-primary-200 bg-primary-50 group-active:text-orange-700 group-active:bg-orange-100 px-2 py-0.5 rounded-full border group-active:border-orange-200">
                    <?= $unexpected['tasks'] ?> <?= t('não planejada(s)') ?>
                </span>
            </div>
        </div>

        <div class="bg-slate-50 border border-slate-200 <?= $concluded['total'] > 0 && $concluded['total'] === $concluded['tasks'] ? 'active' : '' ?> active:bg-emerald-50/50 border active:border-emerald-200 rounded-lg p-4 flex flex-col">
            <span class="text-xs font-semibold text-primary-500 group-active:text-emerald-700 uppercase"><?= t('Concluídas') ?></span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-2xl font-bold group-active:text-emerald-800 text-primary-800"><?= $concluded['percent'] * 100 ?>%</span>
                <span class="text-xs group-active:text-emerald-600 text-primary-800 font-normal"><?= $concluded['tasks'] ?> <?= t('de') ?> <?= $concluded['total'] ?></span>
            </div>
        </div>

        <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 flex flex-col">
            <span class="text-xs font-semibold text-primary-500 uppercase tracking-wider"><?= t('Total de Pontos (SP)') ?></span>
            <div class="flex items-baseline gap-1 mt-1">
                <span class="text-2xl font-bold text-slate-800"><?= $tasks['points'] ?></span>
                <span class="text-xs font-normal text-primary-500">(média <?= $tasks['avg'] ?>/task)</span>
            </div>
        </div>
    </div>

    <div class="bg-slate-50 border border-slate-200 p-3 rounded-lg mb-8">
        <span class="block text-xs font-semibold text-primary-500 uppercase tracking-wider mb-2">
            <?= t('Foco de Atuação') ?>
        </span>
        <div class="flex items-center gap-2 flex-wrap">
            <?php if ($tasks['hotfixes'] > 0): ?>
                <span class="inline-flex items-center gap-1 text-xs font-normal text-red-700 bg-red-50 border border-red-200 px-2.5 py-1 rounded-full">
                    <span><?= $tasks['hotfixes'] ?></span> Hotfix(s)
                </span>
            <?php endif ?>
            <span class="inline-flex items-center gap-1 text-xs font-normal text-primary-700 bg-primary-50 border border-primary-200 px-2.5 py-1 rounded-full">
                <span><?= $tasks['features'] ?></span> Funcionalidade(s)
            </span>
            <span class="inline-flex items-center gap-1 text-xs font-normal text-orange-700 bg-orange-50 border border-orange-200 px-2.5 py-1 rounded-full">
                <span><?= $tasks['bugs'] ?></span> Correção(ões)
            </span>
        </div>
    </div>

    <!-- Visão Analítica Agrupada por Produto -->
    <h3 class="text-base font-bold text-slate-800 mb-2"><?= t('Detalhamento por Produto') ?></h3>

    <?php if (empty($products)): ?>
        <div class="p-8 text-center bg-slate-50 border border-slate-200 rounded-lg text-slate-500 text-sm">
            <?= t('Nenhuma tarefa encontrada para este projeto na Sprint atual.') ?>
        </div>
    <?php else: ?>
        <?php foreach ($products as $prodGroup): ?>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between bg-gray-50 border border-gray-200 p-3 rounded-t-lg mt-6">
                <div class="flex items-center gap-2">
                    <h4 class="text-sm font-bold text-primary-800 uppercase tracking-wide m-0"><?= $this->text->e($prodGroup['title']) ?></h4>
                </div>
                <div class="flex items-center gap-2 mt-2 sm:mt-0 text-xs">
                    <span class="bg-white border border-primary-200 px-2.5 py-0.5 rounded-full text-primary-600 font-medium">
                        <strong class="text-primary-800"><?= $prodGroup['tasks'] ?></strong> <?= t('Tarefa(s)') ?>
                    </span>
                    <span class="bg-primary-50 border border-primary-200 text-primary-800 px-2.5 py-0.5 rounded-full font-semibold">
                        <?= $prodGroup['points'] ?> <?= t('Ponto(s)') ?>
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto mb-6">
                <table class="w-full table-fixed border-collapse text-xs">
                    <thead>
                        <tr class="text-primary-700 text-left">
                            <th class="w-5/12 p-2 border bg-primary-50! border-primary-100!"><?= t('Tarefa') ?></th>
                            <th class="w-2/12 p-2 border bg-primary-50! border-primary-100!"><?= t('Responsável') ?></th>
                            <th class="w-2/12 p-2 border text-center! bg-primary-50! border-primary-100!"><?= t('Planejado') ?></th>
                            <th class="w-1/12 p-2 border text-center! bg-primary-50! border-primary-100!"><?= t('Complexidade') ?></th>
                            <th class="w-2/12 p-2 border text-center! bg-primary-50! border-primary-100!"><?= t('Status') ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <?php foreach ($prodGroup['items'] as $task): ?>
                            <tr>
                                <td class="p-2 border border-slate-200 truncate">
                                    <a href="<?= $this->url->href('TaskViewController', 'show', array('task_id' => $task['number'])) ?>"
                                       class="text-primary-900 truncate hover:underline"
                                       title="<?= $this->text->e($task['title']) ?>">
                                        <strong>#<?= $task['number'] ?></strong> - <?= $this->text->e($task['title']) ?>
                                    </a>
                                </td>
                                <td class="p-2 border border-slate-200 truncate text-slate-700">
                                    <?= $this->text->e($task['owner_name']) ?>
                                </td>
                                <td class="p-2 border text-center border-slate-200">
                                    <?php if ($task['planned']): ?>
                                        <span class="bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded font-bold"><?= t('Sim') ?></span>
                                    <?php else: ?>
                                        <span class="bg-orange-100 text-orange-800 px-2 py-0.5 rounded font-bold"><?= t('Não') ?></span>
                                    <?php endif ?>
                                </td>
                                <td class="p-2 border text-center border-slate-200"><?= $task['point'] ?> <?= t('Ponto(s)') ?></td>
                                <td class="p-2 border text-center border-slate-200">
                                    <?php if ($task['status'] === 5): ?>
                                        <span class="bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded font-bold"><?= t('Concluído') ?></span>
                                    <?php elseif ($task['status'] === 4): ?>
                                        <span class="bg-orange-100 text-orange-800 px-2 py-0.5 rounded font-bold"><?= t('Homologação') ?></span>
                                    <?php elseif ($task['status'] === 3): ?>
                                        <span class="bg-blue-100 text-blue-800 px-2 py-0.5 rounded font-bold"><?= t('Finalizado') ?></span>
                                    <?php elseif ($task['status'] === 2): ?>
                                        <span class="bg-primary-100 text-primary-800 px-2 py-0.5 rounded font-bold"><?= t('Iniciado') ?></span>
                                    <?php elseif ($task['status'] === 1): ?>
                                        <span class="bg-gray-100 text-gray-800 px-2 py-0.5 rounded font-bold"><?= t('Backlog') ?></span>
                                    <?php endif ?>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach ?>
    <?php endif ?>
</div>
