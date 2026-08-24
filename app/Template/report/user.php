<div class="bg-white p-6 rounded-lg shadow-md max-w-7xl mx-auto">

    <div class="border-b-2 border-primary-500 pb-1 mb-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <h2 class="text-2xl font-bold text-slate-800 tracking-tight">Desempenho Técnico Individual</h2>
            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-700 bg-slate-50 border rounded-full border-slate-200 px-3 py-1  shadow-sm">
                <span class="inline-block w-2 h-2 rounded-full bg-primary-500"></span>
                <span>Sprint <?= $sprint ?></span>
                <span class="text-primary-300">•</span>
                <span class="text-primary-500 font-normal"><?= $period ?></span>
            </span>


        </div>
        <h3 class="text-lg font-semibold text-slate-800 m-0!">
            <strong>#<?= $user_id ?></strong> <?= $user['name'] ?>
            <span class="text-xs font-normal text-slate-500"><?= $user['username'] ?> - @<?= $team ?></span>
        </h3>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 my-4">

        <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 flex flex-col">
            <span class="text-xs font-semibold text-primary-500 uppercase tracking-wider">Tarefas Entregues</span>
            <div class="flex items-baseline gap-1 mt-1">
                <span class="text-2xl font-semibold text-primary-800"><?= $tasks['concluded'] ?></span>
                <span class="text-lg font-normal text-primary-500">/<?= $tasks['total'] ?></span>
            </div>
        </div>


        <div class="bg-slate-50 border-slate-200 <?= $unexpected['tasks'] > 0 ? 'active' : '' ?> active:bg-orange-50/50 border active:border-orange-200 p-3.5 rounded-lg">
            <span class="block text-xs font-semibold text-primary-500 group-active:text-orange-800 uppercase tracking-wider mb-1">
                Taxa de Interrupção
            </span>
            <div class="flex items-center gap-2">
                <span class="text-2xl font-semibold text-primary-700 group-active:text-orange-700"><?= $unexpected['percent'] * 100 ?>%</span>
                <span class="inline-flex items-center text-xs font-normal text-primary-800 border-primary-200 bg-primary-50 group-active:text-orange-700 group-active:bg-orange-100 px-2 py-0.5 rounded-full border group-active:border-orange-200">
                    <?= $unexpected['tasks'] ?> tarefa(s) não prevista(s)
                </span>
            </div>
        </div>

        <div class="bg-slate-50 border-slate-200 <?= $concluded['total'] > 0 && $concluded['total'] === $concluded['tasks'] ? 'active' : '' ?> active:bg-emerald-50/50 border active:border-emerald-200 rounded-lg p-4 flex flex-col">
            <span class="text-xs font-semibold text-primary-500 group-active:text-emerald-700 uppercase">Concluídas</span>
            <div class="flex items-baseline gap-2 mt-1">
                <span class="text-2xl font-bold group-active:text-emerald-800 text-primary-800"><?= $concluded['percent'] * 100 ?>%</span>
                <span class="text-xs group-active:text-emerald-600 text-primary-800 font-normal"><?= $concluded['tasks'] ?> de <?= $concluded['total'] ?> concluída(s)</span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-10 border-b-2 border-primary-500 pb-4">

        <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 flex flex-col">
            <span class="text-xs font-semibold text-primary-500 uppercase tracking-wider">Média de Complexidade</span>
            <div class="flex items-baseline gap-1 mt-1">
                <span class="text-xl font-bold text-slate-800"><?= $tasks['avg'] ?></span>
                <span class="text-sm font-normal text-primary-500">Pontos/Task</span>
            </div>
        </div>

        <div class="bg-slate-50 border border-slate-200 p-3 rounded-lg">
            <span class="block text-xs font-semibold text-primary-500 uppercase tracking-wider mb-2">
                Foco de Atuação
            </span>
            <div class="flex items-center gap-2 flex-wrap">
                <?php if ($tasks['hotfixes'] > 0): ?>
                    <span class="inline-flex items-center gap-1 text-xs font-normal text-red-700 bg-red-50 border border-red-200 px-2.5 py-1 rounded-full">
                        <span><?= $tasks['hotfixes'] ?></span> Hotfix(s)
                    </span>
                <?php endif ?>
                <span class="inline-flex items-center gap-1 text-xs font-normal text-primary-700 bg-primary-50 border border-primary-200 px-2.5 py-1 rounded-full">
                    <span><?= $tasks['features'] ?></span> Feature(s)
                </span>
                <span class="inline-flex items-center gap-1 text-xs font-normal text-orange-700 bg-orange-50 border border-orange-200 px-2.5 py-1 rounded-full">
                    <span><?= $tasks['bugs'] ?></span> Bug(s)
                </span>
            </div>
        </div>


        <div class="bg-slate-50 border border-slate-200 p-3 rounded-lg">
            <span class="block text-xs font-semibold text-primary-500 uppercase tracking-wider mb-2">
                Planejamento
            </span>
            <?php if ($concluded['done'] === 1): ?>
                <span class="block text-sm font-bold text-emerald-600"><?= $concluded['done'] * 100 ?>% Entregue</span>
            <?php elseif ($concluded['done'] < 0.5): ?>
                <span class="block text-sm font-bold text-red-600"><?= $concluded['done'] * 100 ?>% Entregue</span>
            <?php else: ?>
                <span class="block text-sm font-bold"><?= $concluded['done'] * 100 ?>% Entregue</span>
            <?php endif ?>
        </div>
    </div>

    <?php foreach ($teams as $team): ?>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between bg-gray-50 border border-gray-200 p-3 rounded-t-lg mt-6">
            <div class="flex items-center gap-2">
                <h4 class="text-sm font-bold text-primary-800 uppercase tracking-wide m-0"><?= $team['title'] ?></h4>
            </div>
            <div class="flex items-center gap-2 mt-2 sm:mt-0 text-xs">
                <span class="bg-white border border-primary-200 px-2.5 py-0.5 rounded-full text-primary-600 font-medium">
                    <strong class="text-primary-800"><?= $team['tasks'] ?></strong> Tarefa(s)
                </span>
                <span class="bg-primary-50 border border-primary-200 text-primery-800 px-2.5 py-0.5 rounded-full font-semibold">
                    <?= $team['points'] ?> Ponto(s)
                </span>
            </div>

        </div>

        <div class="overflow-x-auto mb-6">
            <table class="w-full border-collapse text-xs">
                <thead>
                    <tr class="text-primary-700 text-left">
                        <th class="p-2 border bg-primary-50! border-primary-100!">Tarefa</th>
                        <th class="p-2 border text-center! bg-primary-50! border-primary-100!">Produto</th>
                        <th class="p-2 border text-center! bg-primary-50! border-primary-100!">Prevista</th>
                        <th class="p-2 border text-center! bg-primary-50! border-primary-100!">Complexidade</th>
                        <th class="p-2 border text-center! bg-primary-50! border-primary-100!">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <?php foreach ($team['items'] as $task): ?>
                        <tr>
                            <td class="p-2 border border-slate-200 truncate">
                                <a href="<?= $this->url->href('TaskViewController', 'show', array('task_id' => $task['number'])) ?>"
                                    class="text-primary-900 truncate hover:underline"
                                    title="<?= $this->text->e($task['title']) ?>">

                                    <strong>#<?= $task['number'] ?></strong> - <?= $this->text->e($task['title']) ?>

                                </a>
                            </td>
                            <td class="p-2 border text-center border-slate-200"><?= implode(', ', $task["products"]) ?></td>
                            <td class="p-2 border text-center border-slate-200">
                                <?php if ($task['planned']): ?>
                                    <span class="bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded font-bold">Sim</span>
                                <?php else: ?>
                                    <span class="bg-orange-100 text-orange-800 px-2 py-0.5 rounded font-bold">Não</span>
                                <?php endif ?>
                            </td>
                            <td class="p-2 border text-center border-slate-200"><?= $task['point'] ?> Ponto(s)</td>
                            <td class="p-2 border text-center border-slate-200">
                                <?php if ($task['status'] === 5): ?>
                                    <span class="bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded font-bold">Concluído</span>
                                <?php elseif ($task['status'] === 4): ?>
                                    <span class="bg-orange-100 text-orange-800 px-2 py-0.5 rounded font-bold">Homologação</span>
                                <?php elseif ($task['status'] === 3): ?>
                                    <span class="bg-blue-100 text-blue-800 px-2 py-0.5 rounded font-bold">Finalizado</span>
                                <?php elseif ($task['status'] === 2): ?>
                                    <span class="bg-primary-100 text-primary-800 px-2 py-0.5 rounded font-bold">Iniciado</span>
                                <?php elseif ($task['status'] === 1): ?>
                                    <span class="bg-gray-100 text-gray-800 px-2 py-0.5 rounded font-bold">Backlog</span>
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endforeach ?>
</div>