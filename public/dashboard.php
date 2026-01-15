<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login();

$pageTitle = 'Dashboard';
$pageSubtitle = 'Resumo atualizado do estoque e das movimentações.';
$activeMenu = 'dashboard';
$pdo = get_pdo();

$inventoryTotals = $pdo->query(<<<SQL
    SELECT
        COUNT(*) AS total,
        SUM(status = 'em_estoque') AS em_estoque,
        SUM(status = 'alocado') AS alocado,
        SUM(status = 'manutencao') AS manutencao,
        SUM(status = 'descartar') AS descartar
    FROM equipment
SQL)->fetch() ?: [];

$inventoryTotals = array_merge([
    'total' => 0,
    'em_estoque' => 0,
    'alocado' => 0,
    'manutencao' => 0,
    'descartar' => 0,
], array_map('intval', array_map(static fn($value) => $value ?? 0, $inventoryTotals)));

$clientsTotal = (int) ($pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn() ?: 0);


$operationsWindowStmt = $pdo->query(<<<SQL
    SELECT operation_type,
           COUNT(*) AS operacoes,
           SUM(item_count) AS itens
    FROM (
        SELECT eo.id,
               eo.operation_type,
               COUNT(eoi.id) AS item_count
        FROM equipment_operations eo
        LEFT JOIN equipment_operation_items eoi ON eoi.operation_id = eo.id
        WHERE eo.operation_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY eo.id, eo.operation_type
    ) aggregated
    GROUP BY operation_type
SQL);
$operationsWindowRows = $operationsWindowStmt->fetchAll();

$operationsWindow = [
    'ENTRADA' => ['operacoes' => 0, 'itens' => 0],
    'SAIDA' => ['operacoes' => 0, 'itens' => 0],
    'RETORNO' => ['operacoes' => 0, 'itens' => 0],
];
foreach ($operationsWindowRows as $row) {
    $type = strtoupper((string) $row['operation_type']);
    if (isset($operationsWindow[$type])) {
        $operationsWindow[$type]['operacoes'] = (int) $row['operacoes'];
        $operationsWindow[$type]['itens'] = (int) $row['itens'];
    }
}

$operationsWindowTotals = [
    'operacoes' => array_sum(array_column($operationsWindow, 'operacoes')),
    'itens' => array_sum(array_column($operationsWindow, 'itens')),
];

$shipmentsTrendStmt = $pdo->prepare(<<<SQL
    SELECT DATE_FORMAT(eo.operation_date, '%Y-%m-01') AS bucket,
           DATE_FORMAT(eo.operation_date, '%b %Y') AS label,
           COUNT(DISTINCT eo.id) AS operacoes,
           COUNT(eoi.id) AS itens
    FROM equipment_operations eo
    LEFT JOIN equipment_operation_items eoi ON eoi.operation_id = eo.id
    WHERE eo.operation_type = 'SAIDA'
      AND eo.operation_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
    GROUP BY bucket, label
    ORDER BY bucket
SQL);
$shipmentsTrendStmt->execute();
$shipmentsTrendRows = $shipmentsTrendStmt->fetchAll();

$trendLabels = [];
$trendOps = [];
$trendItems = [];
foreach ($shipmentsTrendRows as $row) {
    $trendLabels[] = $row['label'];
    $trendOps[] = (int) $row['operacoes'];
    $trendItems[] = (int) $row['itens'];
}

if (!$trendLabels) {
    $trendLabels = ['Sem dados'];
    $trendOps = [0];
    $trendItems = [0];
}

$statesStmt = $pdo->query(<<<SQL
    SELECT COALESCE(NULLIF(TRIM(c.state), ''), 'Nao informado') AS state,
           COUNT(eoi.id) AS total_itens
    FROM equipment_operations eo
    INNER JOIN equipment_operation_items eoi ON eoi.operation_id = eo.id
    LEFT JOIN clients c ON c.id = eo.client_id
    WHERE eo.operation_type = 'SAIDA'
    GROUP BY state
    HAVING total_itens > 0
    ORDER BY total_itens DESC
    LIMIT 7
SQL);
$statesRows = $statesStmt->fetchAll();

$statesLabels = [];
$statesValues = [];
foreach ($statesRows as $row) {
    $statesLabels[] = $row['state'];
    $statesValues[] = (int) $row['total_itens'];
}

$conditionStmt = $pdo->query(<<<SQL
    SELECT COALESCE(NULLIF(TRIM(condition_status), ''), 'indefinido') AS condition_status,
           COUNT(*) AS total
    FROM equipment
    GROUP BY condition_status
    ORDER BY total DESC
SQL);
$conditionRows = $conditionStmt->fetchAll();

$conditionLabelMap = [
    'novo' => 'Novo',
    'usado' => 'Usado',
    'manutencao' => 'Manutencao',
    'descartar' => 'Descartar',
    'indefinido' => 'Indefinido',
];

$conditionLabels = [];
$conditionValues = [];
foreach ($conditionRows as $row) {
    $key = strtolower((string) $row['condition_status']);
    $conditionLabels[] = $conditionLabelMap[$key] ?? $key;
    $conditionValues[] = (int) $row['total'];
}

$topModelsStmt = $pdo->query(<<<SQL
    SELECT TRIM(CONCAT_WS(' ', em.brand, em.model_name)) AS modelo,
           COUNT(eoi.id) AS movimentacoes
    FROM equipment eq
    INNER JOIN equipment_models em ON em.id = eq.model_id
    LEFT JOIN equipment_operation_items eoi ON eoi.equipment_id = eq.id
    GROUP BY modelo
    ORDER BY movimentacoes DESC
    LIMIT 5
SQL);
$topModels = $topModelsStmt->fetchAll();

$recentOpsStmt = $pdo->query(<<<SQL
    SELECT eo.id,
           eo.operation_type,
           eo.operation_date,
           u.name AS usuario,
           c.name AS cliente,
           c.state,
           COUNT(eoi.id) AS itens
    FROM equipment_operations eo
    LEFT JOIN equipment_operation_items eoi ON eoi.operation_id = eo.id
    LEFT JOIN users u ON u.id = eo.performed_by
    LEFT JOIN clients c ON c.id = eo.client_id
    GROUP BY eo.id, eo.operation_type, eo.operation_date, u.name, c.name, c.state
    ORDER BY eo.operation_date DESC
    LIMIT 8
SQL);
$recentOps = $recentOpsStmt->fetchAll();


$operationTypeLabels = [
    'ENTRADA' => 'Entrada',
    'SAIDA' => 'Saida',
    'RETORNO' => 'Retorno',
];

$operationBadgeClasses = [
    'ENTRADA' => 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30',
    'SAIDA' => 'bg-blue-500/15 text-blue-200 border border-blue-500/30',
    'RETORNO' => 'bg-amber-500/15 text-amber-200 border border-amber-500/30',
];

$trendLabelsJson = json_encode($trendLabels, JSON_UNESCAPED_UNICODE);
$trendOpsJson = json_encode($trendOps, JSON_NUMERIC_CHECK);
$trendItemsJson = json_encode($trendItems, JSON_NUMERIC_CHECK);
$statesLabelsJson = json_encode($statesLabels, JSON_UNESCAPED_UNICODE);
$statesValuesJson = json_encode($statesValues, JSON_NUMERIC_CHECK);
$conditionLabelsJson = json_encode($conditionLabels, JSON_UNESCAPED_UNICODE);
$conditionValuesJson = json_encode($conditionValues, JSON_NUMERIC_CHECK);

$footerScripts = <<<HTML
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js"></script>
<script>
    const enviosLabels = {$trendLabelsJson};
    const enviosOperacoes = {$trendOpsJson};
    const enviosItens = {$trendItemsJson};
    const estadosLabels = {$statesLabelsJson};
    const estadosValues = {$statesValuesJson};
    const condicaoLabels = {$conditionLabelsJson};
    const condicaoValues = {$conditionValuesJson};

    const enviosCtx = document.getElementById('enviosMes');
    if (enviosCtx) {
        new Chart(enviosCtx, {
            type: 'line',
            data: {
                labels: enviosLabels,
                datasets: [
                    {
                        label: 'Operacoes',
                        data: enviosOperacoes,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.18)',
                        tension: 0.35,
                        fill: true,
                        borderWidth: 2,
                        pointRadius: 3,
                    },
                    {
                        label: 'Itens enviados',
                        data: enviosItens,
                        borderColor: '#22c55e',
                        backgroundColor: 'rgba(34, 197, 94, 0.18)',
                        tension: 0.3,
                        fill: true,
                        borderWidth: 2,
                        pointRadius: 3,
                    }
                ]
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                scales: {
                    x: {
                        ticks: { color: '#cbd5f5' },
                        grid: { display: false }
                    },
                    y: {
                        ticks: { color: '#cbd5f5' },
                        grid: { color: 'rgba(148, 163, 184, 0.15)' }
                    }
                },
                plugins: {
                    legend: {
                        labels: { color: '#e2e8f0' }
                    }
                }
            }
        });
    }

    const estadosCtx = document.getElementById('estadosDistribuicao');
    if (estadosCtx && estadosLabels.length) {
        new Chart(estadosCtx, {
            type: 'bar',
            data: {
                labels: estadosLabels,
                datasets: [{
                    label: 'Itens enviados',
                    data: estadosValues,
                    backgroundColor: '#38bdf8',
                    borderRadius: 6,
                }]
            },
            options: {
                indexAxis: 'y',
                maintainAspectRatio: false,
                responsive: true,
                scales: {
                    x: {
                        ticks: { color: '#cbd5f5' },
                        grid: { color: 'rgba(148, 163, 184, 0.12)' }
                    },
                    y: {
                        ticks: { color: '#f8fafc' },
                        grid: { display: false }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    const condicaoCtx = document.getElementById('condicaoEstoque');
    if (condicaoCtx) {
        new Chart(condicaoCtx, {
            type: 'doughnut',
            data: {
                labels: condicaoLabels,
                datasets: [{
                    data: condicaoValues,
                    backgroundColor: ['#34d399', '#60a5fa', '#fbbf24', '#f87171', '#94a3b8'],
                    borderWidth: 0,
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#e2e8f0' }
                    }
                }
            }
        });
    }
</script>
HTML;
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>
<main class="flex-1 flex flex-col bg-slate-950 text-slate-100">
    <?php include __DIR__ . '/../templates/topbar.php'; ?>
    <div class="flex-1 overflow-y-auto">
        <div class="mx-auto max-w-7xl px-6 pb-12 space-y-8">
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-5 shadow-lg shadow-slate-900/20">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Equipamentos cadastrados</p>
                    <p class="mt-3 text-3xl font-semibold text-white"><?= number_format($inventoryTotals['total'], 0, ',', '.'); ?></p>
                    <p class="mt-2 text-xs text-slate-400"><?= number_format($inventoryTotals['em_estoque'], 0, ',', '.'); ?> disponíveis em estoque</p>
                </div>
                <div class="rounded-2xl border border-slate-800 bg-blue-900/40 p-5 shadow-lg shadow-blue-900/20">
                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-200">Alocados</p>
                    <p class="mt-3 text-3xl font-semibold text-blue-50"><?= number_format($inventoryTotals['alocado'], 0, ',', '.'); ?></p>
                    <p class="mt-2 text-xs text-blue-200/80">Equipamentos atualmente em campo</p>
                </div>
                <div class="rounded-2xl border border-slate-800 bg-emerald-900/40 p-5 shadow-lg shadow-emerald-900/20">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-200">Operações (30 dias)</p>
                    <p class="mt-3 text-3xl font-semibold text-emerald-50"><?= number_format($operationsWindowTotals['operacoes'], 0, ',', '.'); ?></p>
                    <p class="mt-2 text-xs text-emerald-200/80"><?= number_format($operationsWindowTotals['itens'], 0, ',', '.'); ?> itens movimentados</p>
                </div>
                <div class="rounded-2xl border border-slate-800 bg-violet-900/40 p-5 shadow-lg shadow-violet-900/25">
                    <p class="text-xs font-semibold uppercase tracking-wide text-violet-200">Clientes cadastrados</p>
                    <p class="mt-3 text-3xl font-semibold text-violet-50"><?= number_format($clientsTotal, 0, ',', '.'); ?></p>
                    <p class="mt-2 text-xs text-violet-200/80">Com movimentações registradas</p>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-[2fr,1fr]">
                <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-slate-900/30">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Envios mensais</p>
                            <h2 class="text-lg font-semibold text-white">Saídas de equipamentos (últimos 12 meses)</h2>
                        </div>
                        <span class="text-xs text-slate-400">Atualizado em <?= sanitize(format_datetime(date('Y-m-d H:i:s'))); ?></span>
                    </div>
                    <div class="mt-6 h-72">
                        <canvas id="enviosMes"></canvas>
                    </div>
                </div>
                <div class="flex flex-col gap-6">
                    <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-slate-900/30">
                        <h2 class="text-lg font-semibold text-white">Resumo das operações (30 dias)</h2>
                        <ul class="mt-5 space-y-4 text-sm">
                            <li class="flex items-center justify-between rounded-xl border border-slate-800/60 bg-slate-950/60 px-4 py-3">
                                <div>
                                    <p class="text-slate-200 font-medium">Entradas</p>
                                    <p class="text-xs text-slate-400 mt-1">Itens recebidos</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-base font-semibold text-white"><?= $operationsWindow['ENTRADA']['operacoes']; ?></p>
                                    <p class="text-xs text-slate-400"><?= $operationsWindow['ENTRADA']['itens']; ?> itens</p>
                                </div>
                            </li>
                            <li class="flex items-center justify-between rounded-xl border border-slate-800/60 bg-slate-950/60 px-4 py-3">
                                <div>
                                    <p class="text-slate-200 font-medium">Saídas</p>
                                    <p class="text-xs text-slate-400 mt-1">Envios para clientes</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-base font-semibold text-white"><?= $operationsWindow['SAIDA']['operacoes']; ?></p>
                                    <p class="text-xs text-slate-400"><?= $operationsWindow['SAIDA']['itens']; ?> itens</p>
                                </div>
                            </li>
                            <li class="flex items-center justify-between rounded-xl border border-slate-800/60 bg-slate-950/60 px-4 py-3">
                                <div>
                                    <p class="text-slate-200 font-medium">Retornos</p>
                                    <p class="text-xs text-slate-400 mt-1">Itens de volta ao estoque</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-base font-semibold text-white"><?= $operationsWindow['RETORNO']['operacoes']; ?></p>
                                    <p class="text-xs text-slate-400"><?= $operationsWindow['RETORNO']['itens']; ?> itens</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-[1.6fr,1fr]">
                <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-slate-900/30">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-lg font-semibold text-white">Movimentações recentes</h2>
                        <a href="relatorios.php" class="text-xs text-blue-300 hover:text-blue-200">Ver relatório completo</a>
                    </div>
                    <div class="mt-5 overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-800 text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-slate-400">
                                    <th class="px-4 py-3">Operação</th>
                                    <th class="px-4 py-3">Itens</th>
                                    <th class="px-4 py-3">Cliente</th>
                                    <th class="px-4 py-3">Responsável</th>
                                    <th class="px-4 py-3 text-right">Data</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60">
                                <?php if (!$recentOps): ?>
                                    <tr>
                                        <td colspan="5" class="px-4 py-6 text-center text-slate-400">Nenhuma movimentação registrada até o momento.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentOps as $op): ?>
                                        <?php
                                            $type = strtoupper((string) $op['operation_type']);
                                            $badgeClass = $operationBadgeClasses[$type] ?? 'bg-slate-800 text-slate-200';
                                            $typeLabel = $operationTypeLabels[$type] ?? $type;
                                        ?>
                                        <tr>
                                            <td class="px-4 py-4">
                                                <span class="inline-flex items-center gap-2">
                                                    <span class="inline-flex items-center justify-center rounded-full px-3 py-1 text-xs font-semibold <?= sanitize($badgeClass); ?>"><?= sanitize($typeLabel); ?></span>
                                                </span>
                                            </td>
                                            <td class="px-4 py-4 text-slate-200"><?= (int) $op['itens']; ?></td>
                                            <td class="px-4 py-4 text-slate-300">
                                                <?php if ($op['cliente']): ?>
                                                    <?= sanitize($op['cliente']); ?>
                                                    <?php if ($op['state']): ?>
                                                        <span class="text-xs text-slate-500">(<?= sanitize($op['state']); ?>)</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-slate-500">Sem cliente</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-4 text-slate-300"><?= sanitize($op['usuario'] ?? 'Não informado'); ?></td>
                                            <td class="px-4 py-4 text-right text-slate-400"><?= format_datetime($op['operation_date']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="flex flex-col gap-6">
                    <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-slate-900/30">
                        <h2 class="text-lg font-semibold text-white">Distribuição por estado</h2>
                        <?php if ($statesLabels): ?>
                            <div class="mt-5 h-60">
                                <canvas id="estadosDistribuicao"></canvas>
                            </div>
                        <?php else: ?>
                            <p class="mt-4 text-sm text-slate-400">Ainda não há saídas com clientes cadastrados.</p>
                        <?php endif; ?>
                    </div>
                    <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-slate-900/30">
                        <h2 class="text-lg font-semibold text-white">Condição do estoque</h2>
                        <div class="mt-5 h-56">
                            <canvas id="condicaoEstoque"></canvas>
                        </div>
                        <?php if ($topModels): ?>
                            <div class="mt-6">
                                <p class="text-xs uppercase tracking-wide text-slate-400">Modelos com mais movimentações</p>
                                <ul class="mt-3 space-y-2 text-sm">
                                    <?php foreach ($topModels as $model): ?>
                                        <li class="flex items-center justify-between">
                                            <span class="text-slate-200"><?= sanitize($model['modelo']); ?></span>
                                            <span class="text-slate-400"><?= (int) $model['movimentacoes']; ?> movimentações</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../templates/footer.php'; ?>








