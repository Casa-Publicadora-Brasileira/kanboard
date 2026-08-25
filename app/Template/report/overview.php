<div class="bg-white p-6 rounded-lg shadow-md max-w-7xl mx-auto space-y-6">

    <!-- Cabeçalho -->
    <div class="border-b-2 border-primary-500 pb-3">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h2 class="text-2xl font-bold text-slate-800 tracking-tight">
                    <?= t('Visão Geral de Operações (Portfólio)') ?>
                </h2>
                <p class="text-xs text-slate-500 font-medium mt-0.5">
                    <?= t('Consolidado da Sprint Atual • Acompanhamento Executivo') ?>
                </p>
            </div>
            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-700 bg-slate-50 border rounded-full border-slate-200 px-3 py-1 shadow-sm self-start sm:self-auto">
                <span class="inline-block w-2 h-2 rounded-full bg-primary-500"></span>
                <span>Sprint <?= $sprint ?></span>
                <span class="text-primary-300">•</span>
                <span class="text-primary-500 font-normal"><?= $this->text->e($period) ?></span>
            </span>
        </div>
    </div>

    <!-- Indicadores Sintéticos Globais -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

        <!-- Tarefas Entregues -->
        <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold text-primary-500 uppercase tracking-wider"><?= t('Tarefas Entregues') ?></span>
            <div class="flex items-baseline justify-between gap-2 mt-2">
                <div class="flex items-baseline gap-1">
                    <span class="text-2xl font-bold text-slate-800"><?= $kpis['concluded_tasks'] ?></span>
                    <span class="text-base font-normal text-slate-500">/<?= $kpis['total_tasks'] ?></span>
                </div>
                <span class="inline-flex items-center text-xs font-bold text-emerald-800 bg-emerald-100 px-2 py-0.5 rounded">
                    <?= $kpis['completion_rate'] ?>%
                </span>
            </div>
        </div>

        <!-- Total de Pontos -->
        <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold text-primary-500 uppercase tracking-wider"><?= t('Total de Pontos') ?></span>
            <div class="flex items-baseline gap-1 mt-2">
                <span class="text-2xl font-bold text-slate-800"><?= $kpis['total_points'] ?></span>
                <span class="text-xs font-normal text-primary-500"><?= t('SP') ?></span>
            </div>
        </div>

        <!-- Taxa de Interrupção -->
        <div class="bg-slate-50 border <?= $kpis['unexpected_tasks'] > 0 ? 'border-orange-200 bg-orange-50/30' : 'border-slate-200' ?> rounded-lg p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold <?= $kpis['unexpected_tasks'] > 0 ? 'text-orange-700' : 'text-primary-500' ?> uppercase tracking-wider"><?= t('Taxa de Interrupção') ?></span>
            <div class="flex items-baseline justify-between gap-2 mt-2">
                <span class="text-2xl font-bold <?= $kpis['unexpected_tasks'] > 0 ? 'text-orange-700' : 'text-slate-800' ?>"><?= $kpis['interruption_rate'] ?>%</span>
                <span class="inline-flex items-center text-xs font-normal <?= $kpis['unexpected_tasks'] > 0 ? 'text-orange-800 bg-orange-100 border border-orange-200' : 'text-slate-600 bg-slate-100' ?> px-2 py-0.5 rounded-full">
                    <?= $kpis['unexpected_tasks'] ?> <?= t('não planejada(s)') ?>
                </span>
            </div>
        </div>

        <!-- Ocupação da Equipe -->
        <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 flex flex-col justify-between">
            <span class="text-xs font-semibold text-primary-500 uppercase tracking-wider"><?= t('Ocupação da Equipe') ?></span>
            <div class="flex items-baseline justify-between gap-2 mt-2">
                <span class="text-2xl font-bold text-slate-800"><?= $kpis['occupancy_rate'] ?>%</span>
                <span class="inline-flex items-center text-xs font-normal text-primary-800 bg-primary-50 border border-primary-200 px-2 py-0.5 rounded-full">
                    <?= $kpis['active_devs'] ?> <?= t('de') ?> <?= $kpis['total_devs'] ?> <?= t('ativos') ?>
                </span>
            </div>
        </div>

    </div>

    <!-- Bloco de Capacidade & Gestão da Equipe -->
    <div class="space-y-4">
        <h3 class="text-base font-bold text-slate-800 tracking-tight">
            <?= t('Capacidade & Gestão da Equipe') ?>
        </h3>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

            <!-- Ranking de Desenvolvedores -->
            <div class="xl:col-span-2 bg-slate-50/50 border border-slate-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-bold text-primary-800 uppercase tracking-wide">
                        <?= t('Ranking de Desenvolvedores') ?>
                    </h4>
                    <span class="text-xs text-slate-500 font-medium">
                        <?= count($developers) ?> <?= t('desenvolvedor(es)') ?>
                    </span>
                </div>

                <?php if (empty($developers)): ?>
                    <p class="text-xs text-slate-400 text-center py-4"><?= t('Nenhum desenvolvedor encontrado.') ?></p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-xs">
                            <thead>
                                <tr class="text-primary-700 text-left">
                                    <th class="p-2 border bg-primary-50! border-primary-100! w-10 text-center!"><?= t('#') ?></th>
                                    <th class="p-2 border bg-primary-50! border-primary-100!"><?= t('Desenvolvedor') ?></th>
                                    <th class="p-2 border bg-primary-50! border-primary-100! text-center!"><?= t('Tarefas') ?></th>
                                    <th class="p-2 border bg-primary-50! border-primary-100! text-center!"><?= t('Média SP') ?></th>
                                    <th class="p-2 border bg-primary-50! border-primary-100! text-center!"><?= t('Total SP') ?></th>
                                    <th class="p-2 border bg-primary-50! border-primary-100! text-center!"><?= t('Carga') ?></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                <?php foreach ($developers as $dev): ?>
                                    <tr class="hover:bg-slate-50/80 transition">
                                        <td class="p-2 border border-slate-200 text-center font-bold text-slate-600">
                                            <?= $dev['rank'] ?>
                                        </td>
                                        <td class="p-2 border border-slate-200">
                                            <a href="<?= $this->url->href('ReportController', 'user', array('user_id' => $dev['id'])) ?>"
                                               class="text-primary-700 hover:text-primary-900 hover:underline inline-flex items-center gap-1 font-semibold"
                                               title="<?= t('Ver relatório individual') ?>: <?= $this->text->e($dev['name']) ?>">
                                                <span><?= $this->text->e($dev['name']) ?></span>
                                                <span class="text-[11px] font-normal text-slate-400">(<?= $this->text->e($dev['username']) ?>)</span>
                                                <svg class="w-3 h-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                            </a>
                                        </td>
                                        <td class="p-2 border border-slate-200 text-center">
                                            <span class="font-semibold text-slate-800"><?= $dev['concluded_tasks'] ?></span>
                                            <span class="text-slate-400">/<?= $dev['total_tasks'] ?></span>
                                        </td>
                                        <td class="p-2 border border-slate-200 text-center text-slate-700">
                                            <?= number_format($dev['avg_complexity'], 1) ?>
                                        </td>
                                        <td class="p-2 border border-slate-200 text-center font-bold text-slate-800">
                                            <?= $dev['total_points'] ?>
                                        </td>
                                        <td class="p-2 border border-slate-200 text-center">
                                            <?php if ($dev['workload_status'] === 'heavy'): ?>
                                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-red-800 bg-red-50 border border-red-200 px-2 py-0.5 rounded-full" title="<?= t('Mais de 15 SP atribuídos') ?>">
                                                    🔥 <?= t('Sobrecarga') ?>
                                                </span>
                                            <?php elseif ($dev['workload_status'] === 'balanced'): ?>
                                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-800 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full" title="<?= t('Entre 1 e 15 SP atribuídos') ?>">
                                                    ⚖️ <?= t('Equilibrada') ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-600 bg-slate-100 border border-slate-200 px-2 py-0.5 rounded-full" title="<?= t('0 SP ou 0 tarefas atribuídas') ?>">
                                                    💤 <?= t('Ocioso') ?>
                                                </span>
                                            <?php endif ?>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif ?>
            </div>

            <!-- Product Leaders -->
            <div class="xl:col-span-1 bg-slate-50/50 border border-slate-200 rounded-lg p-4 flex flex-col">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-bold text-primary-800 uppercase tracking-wide">
                        <?= t('Product Leaders') ?>
                    </h4>
                    <span class="text-xs text-slate-500 font-medium">
                        <?= count($product_leaders) ?> <?= t('líder(es)') ?>
                    </span>
                </div>

                <?php if (empty($product_leaders)): ?>
                    <p class="text-xs text-slate-400 text-center py-4"><?= t('Nenhum product leader encontrado.') ?></p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-xs">
                            <thead>
                                <tr class="text-primary-700 text-left">
                                    <th class="p-2 border bg-primary-50! border-primary-100!"><?= t('Líder') ?></th>
                                    <th class="p-2 border bg-primary-50! border-primary-100! text-center!"><?= t('Tarefas') ?></th>
                                    <th class="p-2 border bg-primary-50! border-primary-100! text-center!"><?= t('Total SP') ?></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                <?php foreach ($product_leaders as $leader): ?>
                                    <tr class="hover:bg-slate-50/80 transition">
                                        <td class="p-2 border border-slate-200">
                                            <a href="<?= $this->url->href('ReportController', 'user', array('user_id' => $leader['id'])) ?>"
                                               class="text-primary-700 hover:text-primary-900 hover:underline inline-flex items-center gap-1 font-semibold"
                                               title="<?= t('Ver relatório individual') ?>: <?= $this->text->e($leader['name']) ?>">
                                                <span><?= $this->text->e($leader['name']) ?></span>
                                                <svg class="w-3 h-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                            </a>
                                        </td>
                                        <td class="p-2 border border-slate-200 text-center">
                                            <span class="font-semibold text-slate-800"><?= $leader['concluded_tasks'] ?></span>
                                            <span class="text-slate-400">/<?= $leader['total_tasks'] ?></span>
                                        </td>
                                        <td class="p-2 border border-slate-200 text-center font-bold text-slate-800">
                                            <?= $leader['total_points'] ?>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif ?>
            </div>

        </div>
    </div>

    <!-- Bloco de Portfólio de Projetos e Produtos -->
    <div class="space-y-4 pt-4 border-t-2 border-slate-100">
        <div class="flex items-center justify-between">
            <h3 class="text-base font-bold text-slate-800 tracking-tight">
                <?= t('Portfólio de Projetos e Produtos') ?>
            </h3>
            <span class="text-xs text-slate-500 font-medium">
                <?= count($projects) ?> <?= t('projeto(s) monitorado(s)') ?>
            </span>
        </div>

        <?php if (empty($projects)): ?>
            <div class="p-8 text-center bg-slate-50 border border-slate-200 rounded-lg text-slate-500 text-sm">
                <?= t('Nenhum projeto cadastrado no portfólio.') ?>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($projects as $proj): ?>
                    <div class="border rounded-lg overflow-hidden <?= $proj['has_activity'] ? 'border-slate-200 shadow-xs' : 'border-slate-200/60 opacity-75' ?>">

                        <!-- Cabeçalho do Card de Projeto -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between <?= $proj['has_activity'] ? 'bg-gray-50 border-b border-gray-200' : 'bg-slate-50/50 border-b border-slate-100' ?> p-3">
                            <div class="flex items-center gap-2">
                                <a href="<?= $this->url->href('ReportController', 'project', array('project_id' => $proj['id'])) ?>"
                                   class="text-sm font-bold text-primary-800 hover:text-primary-600 hover:underline uppercase tracking-wide inline-flex items-center gap-1.5"
                                   title="<?= t('Ver relatório do projeto') ?> <?= $this->text->e($proj['name']) ?>">
                                    <span><?= $this->text->e($proj['name']) ?></span>
                                    <svg class="w-3.5 h-3.5 text-primary-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                </a>

                                <?php if ($proj['has_activity']): ?>
                                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-800 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        <?= t('Ativo na Sprint') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 text-[11px] font-medium text-slate-500 bg-slate-100 border border-slate-200 px-2 py-0.5 rounded-full">
                                        <?= t('Sem Movimentação') ?>
                                    </span>
                                <?php endif ?>
                            </div>

                            <div class="flex items-center gap-2 mt-2 sm:mt-0 text-xs">
                                <span class="bg-white border border-primary-200 px-2.5 py-0.5 rounded-full text-primary-600 font-medium">
                                    <strong class="text-primary-800"><?= $proj['concluded_tasks'] ?>/<?= $proj['total_tasks'] ?></strong> <?= t('Concluída(s)') ?>
                                </span>
                                <span class="bg-primary-50 border border-primary-200 text-primary-800 px-2.5 py-0.5 rounded-full font-semibold">
                                    <?= $proj['total_points'] ?> <?= t('SP') ?>
                                </span>
                            </div>
                        </div>

                        <!-- Tabela de Produtos -->
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse text-xs">
                                <thead>
                                    <tr class="text-primary-700 text-left">
                                        <th class="p-2 border bg-primary-50! border-primary-100! w-5/12"><?= t('Produto') ?></th>
                                        <th class="p-2 border bg-primary-50! border-primary-100! text-center! w-2/12"><?= t('Previstas') ?></th>
                                        <th class="p-2 border bg-primary-50! border-primary-100! text-center! w-2/12"><?= t('Concluídas / Total') ?></th>
                                        <th class="p-2 border bg-primary-50! border-primary-100! text-center! w-2/12"><?= t('Taxa de Conclusão') ?></th>
                                        <th class="p-2 border bg-primary-50! border-primary-100! text-center! w-1/12"><?= t('Story Points') ?></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 bg-white">
                                    <?php if (empty($proj['products'])): ?>
                                        <tr>
                                            <td colspan="5" class="p-3 text-center text-slate-400 italic">
                                                <?= t('Nenhum produto associado.') ?>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($proj['products'] as $prod): ?>
                                            <tr class="<?= $prod['has_activity'] ? 'hover:bg-slate-50/80' : 'text-slate-400 bg-slate-50/20' ?> transition">
                                                <td class="p-2 border border-slate-200 font-medium <?= $prod['has_activity'] ? 'text-slate-800' : 'text-slate-400' ?>">
                                                    <?= $this->text->e($prod['name']) ?>
                                                </td>
                                                <td class="p-2 border border-slate-200 text-center">
                                                    <?= $prod['planned_tasks'] ?>
                                                </td>
                                                <td class="p-2 border border-slate-200 text-center">
                                                    <span class="<?= $prod['has_activity'] ? 'font-semibold text-slate-800' : 'text-slate-400' ?>"><?= $prod['concluded_tasks'] ?></span>
                                                    <span class="text-slate-400">/<?= $prod['total_tasks'] ?></span>
                                                </td>
                                                <td class="p-2 border border-slate-200 text-center">
                                                    <?php if ($prod['total_tasks'] > 0): ?>
                                                        <div class="flex items-center justify-center gap-2">
                                                            <div class="w-16 bg-slate-200 rounded-full h-1.5 overflow-hidden">
                                                                <div class="bg-emerald-500 h-1.5 rounded-full" style="width: <?= min(100, $prod['completion_rate']) ?>%"></div>
                                                            </div>
                                                            <span class="font-semibold <?= $prod['completion_rate'] === 100 ? 'text-emerald-700' : 'text-slate-700' ?>">
                                                                <?= $prod['completion_rate'] ?>%
                                                            </span>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-slate-400">-</span>
                                                    <?php endif ?>
                                                </td>
                                                <td class="p-2 border border-slate-200 text-center <?= $prod['has_activity'] ? 'font-bold text-slate-800' : 'text-slate-400' ?>">
                                                    <?= $prod['points'] ?> <?= t('SP') ?>
                                                </td>
                                            </tr>
                                        <?php endforeach ?>
                                    <?php endif ?>
                                </tbody>
                            </table>
                        </div>

                    </div>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </div>

</div>