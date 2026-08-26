<div class="bg-white p-6 rounded-lg shadow-md max-w-7xl mx-auto">

    <div class="border-b-2 border-primary-500 pb-1 mb-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <h2 class="text-2xl font-bold text-slate-800 tracking-tight"><?= t('Desempenho Técnico Individual') ?></h2>
            <a href="<?= $this->url->href('ReportController', 'overview') ?>">
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-700 bg-slate-50 border rounded-full border-slate-200 px-3 py-1 shadow-sm">
                    <span class="inline-block w-2 h-2 rounded-full bg-primary-500"></span>
                    <span>Sprint <?= $sprint ?></span>
                    <span class="text-primary-300">•</span>
                    <span class="text-primary-500 font-normal"><?= $period ?></span>
                </span>
            </a>
        </div>
        <h3 class="text-lg font-semibold text-slate-800 m-0!">
            <strong>#<?= $user_id ?></strong> <?= $this->text->e($user['name'] ?: $user['username']) ?>
            <span class="text-xs font-normal text-slate-500">
                <?= $this->text->e($user['username']) ?><?= !empty($team) ? " - @" . $this->text->e($team) : '' ?>
            </span>
        </h3>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 my-4">

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
                    <?= $unexpected['tasks'] ?> <?= t('tarefa(s) não planejadas(s)') ?>
                </span>
            </div>
        </div>

        <div class="bg-slate-50 border border-slate-200 <?= $concluded['total'] > 0 && $concluded['total'] === $concluded['tasks'] ? 'active' : '' ?> active:bg-emerald-50/50 border active:border-emerald-200 rounded-lg p-4 flex flex-col">
            <span class="text-xs font-semibold text-primary-500 group-active:text-emerald-700 uppercase"><?= t('Concluídas') ?></span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-2xl font-bold group-active:text-emerald-800 text-primary-800"><?= $concluded['percent'] * 100 ?>%</span>
                <span class="text-xs group-active:text-emerald-600 text-primary-800 font-normal"><?= $concluded['tasks'] ?> <?= t('de') ?> <?= $concluded['total'] ?> <?= t('concluída(s)') ?></span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-10 border-b-2 border-primary-500 pb-4">

        <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 flex flex-col">
            <span class="text-xs font-semibold text-primary-500 uppercase tracking-wider"><?= t('Média de Complexidade') ?></span>
            <div class="flex items-baseline gap-1 mt-1">
                <span class="text-xl font-bold text-slate-800"><?= $tasks['avg'] ?></span>
                <span class="text-sm font-normal text-primary-500"><?= t('Ponto(s)') ?></span>
            </div>
        </div>

        <div class="bg-slate-50 border border-slate-200 p-3 rounded-lg">
            <span class="block text-xs font-semibold text-primary-500 uppercase tracking-wider mb-2">
                <?= t('Foco de Atuação') ?>
            </span>
            <div class="flex items-center gap-2 flex-wrap">
                <?php if ($tasks['hotfixes'] > 0): ?>
                    <span class="inline-flex items-center gap-1 text-xs font-normal text-red-700 bg-red-50 border border-red-200 px-2.5 py-1 rounded-full">
                        <span><?= $tasks['hotfixes'] ?></span> <?= t('Hotfix(s)') ?>
                    </span>
                <?php endif ?>
                <span class="inline-flex items-center gap-1 text-xs font-normal text-primary-700 bg-primary-50 border border-primary-200 px-2.5 py-1 rounded-full">
                    <span><?= $tasks['features'] ?></span> <?= t('Funcionalidade(s)') ?>
                </span>
                <span class="inline-flex items-center gap-1 text-xs font-normal text-orange-700 bg-orange-50 border border-orange-200 px-2.5 py-1 rounded-full">
                    <span><?= $tasks['bugs'] ?></span> <?= t('Correção(ões)') ?>
                </span>
            </div>
        </div>

    </div>

    <?php if (empty($teams)): ?>
        <div class="p-8 text-center bg-slate-50 border border-slate-200 rounded-lg text-slate-500 text-sm">
            <?= t('Nenhuma tarefa encontrada para este usuário na Sprint atual.') ?>
        </div>
    <?php else: ?>
        <?php foreach ($teams as $team): ?>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between bg-gray-50 border border-gray-200 p-3 rounded-t-lg mt-6">
                <div class="flex items-center gap-2">
                    <?php if (!empty($team['project_id'])): ?>
                        <a href="<?= $this->url->href('ReportController', 'project', array('project_id' => $team['project_id'])) ?>"
                            class="text-sm font-bold text-primary-800 hover:text-primary-600 hover:underline uppercase tracking-wide m-0 inline-flex items-center gap-1.5"
                            title="<?= t('Ver relatório do projeto') ?> <?= $this->text->e($team['title']) ?>">
                            <span><?= $this->text->e($team['title']) ?></span>
                            <svg class="w-3.5 h-3.5 text-primary-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                            </svg>
                        </a>
                    <?php else: ?>
                        <h4 class="text-sm font-bold text-primary-800 uppercase tracking-wide m-0"><?= $this->text->e($team['title']) ?></h4>
                    <?php endif ?>
                </div>
                <div class="flex items-center gap-2 mt-2 sm:mt-0 text-xs">
                    <span class="bg-white border border-primary-200 px-2.5 py-0.5 rounded-full text-primary-600 font-medium">
                        <strong class="text-primary-800"><?= $team['tasks'] ?></strong> <?= t('Tarefa(s)') ?>
                    </span>
                    <span class="bg-primary-50 border border-primary-200 text-primary-800 px-2.5 py-0.5 rounded-full font-semibold">
                        <?= $team['points'] ?> <?= t('Ponto(s)') ?>
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto mb-6">
                <table class="w-full table-fixed border-collapse text-xs">
                    <thead>
                        <tr class="text-primary-700 text-left">
                            <th class="w-4/12 p-2 border bg-primary-50! border-primary-100!"><?= t('Tarefa') ?></th>
                            <th class="w-2/12 p-2 border text-center! bg-primary-50! border-primary-100!"><?= t('Produto') ?></th>
                            <th class="w-1/12 p-2 border text-center! bg-primary-50! border-primary-100!"><?= t('Planejado') ?></th>
                            <th class="w-1/12 p-2 border text-center! bg-primary-50! border-primary-100!"><?= t('Complexidade') ?></th>
                            <th class="w-2/12 p-2 border text-center! bg-primary-50! border-primary-100!"><?= t('Na coluna desde') ?></th>
                            <th class="w-2/12 p-2 border text-center! bg-primary-50! border-primary-100!"><?= t('Status') ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <?php foreach ($team['items'] as $task): ?>
                            <?php
                            $carbonMoved = !empty($task['date_moved']) ? \Carbon\Carbon::createFromTimestamp($task['date_moved'])->locale('pt_BR') : null;
                            ?>
                            <tr>
                                <td class="p-2 border border-slate-200 truncate">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <?php if (!empty($task['is_hotfix'])): ?>
                                            <span class="inline-flex items-center bg-red-100 text-red-700 border border-red-200 text-[10px] font-bold px-1.5 py-0.5 rounded shrink-0 uppercase tracking-wider"><?= t('Hotfix') ?></span>
                                        <?php endif ?>
                                        <a href="<?= $this->url->href('TaskViewController', 'show', array('task_id' => $task['number'])) ?>"
                                            class="text-primary-900 truncate hover:underline"
                                            title="<?= $this->text->e($task['title']) ?>">
                                            <strong>#<?= $task['number'] ?></strong> - <?= $this->text->e($task['title']) ?>
                                        </a>
                                    </div>
                                </td>
                                <td class="p-2 border text-center border-slate-200 truncate"><?= $this->text->e(implode(', ', $task["products"])) ?></td>
                                <td class="p-2 border text-center border-slate-200">
                                    <?php if ($task['planned']): ?>
                                        <span class="bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded font-bold"><?= t('Sim') ?></span>
                                    <?php else: ?>
                                        <span class="bg-orange-100 text-orange-800 px-2 py-0.5 rounded font-bold"><?= t('Não') ?></span>
                                    <?php endif ?>
                                </td>
                                <td class="p-2 border text-center border-slate-200"><?= $task['point'] ?> <?= t('Ponto(s)') ?></td>
                                <td class="p-2 border text-center border-slate-200 text-slate-700 truncate">
                                    <?php if ($carbonMoved): ?>
                                        <span class="font-medium"><?= $carbonMoved->format('d/m/Y') ?></span>
                                        <span class="text-[11px] text-slate-400 font-normal block sm:inline sm:ml-1">(<?= $carbonMoved->diffForHumans() ?>)</span>
                                    <?php else: ?>
                                        <span class="text-slate-400">-</span>
                                    <?php endif ?>
                                </td>
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