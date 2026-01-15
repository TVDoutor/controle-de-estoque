<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login();

$pageTitle = 'Relatórios';
$activeMenu = 'relatorios';
$pdo = get_pdo();

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$type = $_GET['type'] ?? '';

$params = [
    'start' => $startDate . ' 00:00:00',
    'end' => $endDate . ' 23:59:59',
];

$typeFilter = '';
if (in_array($type, ['ENTRADA', 'SAIDA', 'RETORNO'], true)) {
    $typeFilter = ' AND eo.operation_type = :type';
    $params['type'] = $type;
}

$operationsStmt = $pdo->prepare(<<<SQL
    SELECT eo.id,
           eo.operation_type,
           eo.operation_date,
           eo.notes,
           u.name AS usuario,
           c.name AS cliente,
           COUNT(eoi.id) AS quantidade
    FROM equipment_operations eo
    LEFT JOIN equipment_operation_items eoi ON eoi.operation_id = eo.id
    LEFT JOIN users u ON u.id = eo.performed_by
    LEFT JOIN clients c ON c.id = eo.client_id
    WHERE eo.operation_date BETWEEN :start AND :end
    $typeFilter
    GROUP BY eo.id
    ORDER BY eo.operation_date DESC
    LIMIT 200
SQL);
$operationsStmt->execute($params);
$operations = $operationsStmt->fetchAll();

$summaryStmt = $pdo->prepare(<<<SQL
    SELECT eo.operation_type,
           COUNT(DISTINCT eo.id) AS total_operacoes,
           COUNT(eoi.id) AS total_itens
    FROM equipment_operations eo
    LEFT JOIN equipment_operation_items eoi ON eoi.operation_id = eo.id
    WHERE eo.operation_date BETWEEN :start AND :end
    GROUP BY eo.operation_type
SQL);
$summaryStmt->execute([
    'start' => $startDate . ' 00:00:00',
    'end' => $endDate . ' 23:59:59',
]);
$summary = $summaryStmt->fetchAll();
$summaryMap = [];
foreach ($summary as $row) {
    $summaryMap[$row['operation_type']] = $row;
}

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>
<main class="flex-1 flex flex-col bg-slate-950 text-slate-100">
    <?php include __DIR__ . '/../templates/topbar.php'; ?>
    <section class="flex-1 overflow-y-auto px-6 pb-12 space-y-6">
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <form method="get" class="flex flex-wrap items-end gap-4 text-sm">
                <div>
                    <label class="text-xs font-medium text-slate-600" for="start_date">De</label>
                    <input type="date" id="start_date" name="start_date" value="<?= sanitize($startDate); ?>" class="mt-1 w-40 rounded-lg border border-slate-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-600" for="end_date">At</label>
                    <input type="date" id="end_date" name="end_date" value="<?= sanitize($endDate); ?>" class="mt-1 w-40 rounded-lg border border-slate-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-600" for="type">Tipo</label>
                    <select id="type" name="type" class="mt-1 w-44 rounded-lg border border-slate-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                        <option value="">Todos</option>
                        <option value="ENTRADA" <?= $type === 'ENTRADA' ? 'selected' : ''; ?>>Entradas</option>
                        <option value="SAIDA" <?= $type === 'SAIDA' ? 'selected' : ''; ?>>Saídas</option>
                        <option value="RETORNO" <?= $type === 'RETORNO' ? 'selected' : ''; ?>>Retornos</option>
                    </select>
                </div>
                <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 font-semibold text-white hover:bg-slate-900">Aplicar</button>
            </form>
        </div>

        <div class="grid gap-6 md:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Entradas</p>
                <p class="mt-2 text-3xl font-semibold text-slate-800"><?= (int) ($summaryMap['ENTRADA']['total_itens'] ?? 0); ?></p>
                <p class="text-xs text-slate-500">Operaes: <?= (int) ($summaryMap['ENTRADA']['total_operacoes'] ?? 0); ?></p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Saídas</p>
                <p class="mt-2 text-3xl font-semibold text-slate-800"><?= (int) ($summaryMap['SAIDA']['total_itens'] ?? 0); ?></p>
                <p class="text-xs text-slate-500">Operaes: <?= (int) ($summaryMap['SAIDA']['total_operacoes'] ?? 0); ?></p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Retornos</p>
                <p class="mt-2 text-3xl font-semibold text-slate-800"><?= (int) ($summaryMap['RETORNO']['total_itens'] ?? 0); ?></p>
                <p class="text-xs text-slate-500">Operaes: <?= (int) ($summaryMap['RETORNO']['total_operacoes'] ?? 0); ?></p>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-800">Movimentaes</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-3 text-left">Data</th>
                            <th class="px-3 py-3 text-left">Tipo</th>
                            <th class="px-3 py-3 text-left">Itens</th>
                            <th class="px-3 py-3 text-left">Cliente</th>
                            <th class="px-3 py-3 text-left">Responsável</th>
                            <th class="px-3 py-3 text-left">Observações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (!$operations): ?>
                            <tr>
                                <td colspan="6" class="px-3 py-4 text-center text-slate-500">Nenhuma movimentação no período.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($operations as $operation): ?>
                            <tr>
                                <td class="px-3 py-2 text-slate-600"><?= format_datetime($operation['operation_date']); ?></td>
                                <td class="px-3 py-2 font-semibold <?= match ($operation['operation_type']) {
                                    'ENTRADA' => 'text-green-600',
                                    'SAIDA' => 'text-blue-600',
                                    'RETORNO' => 'text-orange-600',
                                    default => 'text-slate-600'
                                }; ?>"><?= sanitize($operation['operation_type']); ?></td>
                                <td class="px-3 py-2 text-slate-600"><?= (int) $operation['quantidade']; ?></td>
                                <td class="px-3 py-2 text-slate-600"><?= sanitize($operation['cliente'] ?? '-'); ?></td>
                                <td class="px-3 py-2 text-slate-600"><?= sanitize($operation['usuario'] ?? '-'); ?></td>
                                <td class="px-3 py-2 text-slate-500"><?= sanitize($operation['notes'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
<?php include __DIR__ . '/../templates/footer.php';


