<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login();

$pageTitle = 'Relatórios';
$activeMenu = 'relatorios';
$showDensityToggle = true;
$pdo = get_pdo();

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$type = $_GET['type'] ?? '';
$export = $_GET['export'] ?? '';
$sort = $_GET['sort'] ?? 'date';
$dir = strtolower((string) ($_GET['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

$sortMap = [
    'date' => 'eo.operation_date',
    'type' => 'eo.operation_type',
    'items' => 'quantidade',
    'client' => 'c.name',
    'user' => 'u.name',
    'notes' => 'eo.notes',
];
$orderBy = $sortMap[$sort] ?? 'eo.operation_date';
$orderDir = strtoupper($dir);

$params = [
    'start' => $startDate . ' 00:00:00',
    'end' => $endDate . ' 23:59:59',
];

$typeFilter = '';
if (in_array($type, ['ENTRADA', 'SAIDA', 'RETORNO'], true)) {
    $typeFilter = ' AND eo.operation_type = :type';
    $params['type'] = $type;
}

$baseOpsSql = <<<SQL
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
    ORDER BY {$orderBy} {$orderDir}
SQL;

if ($export === 'csv') {
    $exportStmt = $pdo->prepare($baseOpsSql);
    $exportStmt->execute($params);
    $exportRows = $exportStmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorios.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Data', 'Tipo', 'Itens', 'Cliente', 'Responsável', 'Observações'], ';');
    foreach ($exportRows as $row) {
        fputcsv($output, [
            format_datetime($row['operation_date']),
            $row['operation_type'],
            (int) $row['quantidade'],
            $row['cliente'] ?? '-',
            $row['usuario'] ?? '-',
            $row['notes'] ?? '-',
        ], ';');
    }
    fclose($output);
    exit;
}

$operationsStmt = $pdo->prepare($baseOpsSql . ' LIMIT 200');
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

$exportLink = '?' . http_build_query(array_merge($_GET, ['export' => 'csv']));

$buildSortLink = static function (string $key) use ($sort, $dir): string {
    $params = $_GET;
    $params['sort'] = $key;
    $params['dir'] = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
    return '?' . http_build_query($params);
};

$sortIndicator = static function (string $key) use ($sort, $dir): string {
    if ($sort !== $key) {
        return '';
    }
    return $dir === 'asc' ? '^' : 'v';
};

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>
<main class="flex-1 flex flex-col bg-slate-950 text-slate-100">
    <?php include __DIR__ . '/../templates/topbar.php'; ?>
    <section class="flex-1 overflow-y-auto px-6 pb-12 space-y-6">
        <div class="surface-card">
            <form method="get" class="flex flex-wrap items-end gap-4 text-sm">
                <div>
                    <label class="text-xs font-medium text-slate-300" for="start_date">De</label>
                    <input type="date" id="start_date" name="start_date" value="<?= sanitize($startDate); ?>" class="surface-field-compact w-40">
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-300" for="end_date">Até</label>
                    <input type="date" id="end_date" name="end_date" value="<?= sanitize($endDate); ?>" class="surface-field-compact w-40">
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-300" for="type">Tipo</label>
                    <select id="type" name="type" class="surface-select-compact w-44">
                        <option value="">Todos</option>
                        <option value="ENTRADA" <?= $type === 'ENTRADA' ? 'selected' : ''; ?>>Entradas</option>
                        <option value="SAIDA" <?= $type === 'SAIDA' ? 'selected' : ''; ?>>Saídas</option>
                        <option value="RETORNO" <?= $type === 'RETORNO' ? 'selected' : ''; ?>>Retornos</option>
                    </select>
                </div>
                <button type="submit" class="rounded-xl bg-slate-800 px-4 py-2 font-semibold text-white hover:bg-slate-700">Aplicar</button>
                <a id="exportCsv" href="<?= sanitize($exportLink); ?>" class="rounded-xl border border-slate-700 px-4 py-2 font-semibold text-slate-200 hover:bg-slate-800/40">Exportar CSV</a>
            </form>
            <div class="mt-4 flex flex-wrap gap-2">
                <span class="surface-chip">Período: <?= sanitize($startDate); ?> a <?= sanitize($endDate); ?></span>
                <?php if ($type !== ''): ?>
                    <span class="surface-chip">Tipo: <?= sanitize($type); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-3">
            <div class="surface-card">
                <p class="text-xs uppercase tracking-wide surface-muted">Entradas</p>
                <p class="mt-2 text-3xl font-semibold"><?= (int) ($summaryMap['ENTRADA']['total_itens'] ?? 0); ?></p>
                <p class="text-xs surface-muted">Operações: <?= (int) ($summaryMap['ENTRADA']['total_operacoes'] ?? 0); ?></p>
            </div>
            <div class="surface-card">
                <p class="text-xs uppercase tracking-wide surface-muted">Saídas</p>
                <p class="mt-2 text-3xl font-semibold"><?= (int) ($summaryMap['SAIDA']['total_itens'] ?? 0); ?></p>
                <p class="text-xs surface-muted">Operações: <?= (int) ($summaryMap['SAIDA']['total_operacoes'] ?? 0); ?></p>
            </div>
            <div class="surface-card">
                <p class="text-xs uppercase tracking-wide surface-muted">Retornos</p>
                <p class="mt-2 text-3xl font-semibold"><?= (int) ($summaryMap['RETORNO']['total_itens'] ?? 0); ?></p>
                <p class="text-xs surface-muted">Operações: <?= (int) ($summaryMap['RETORNO']['total_operacoes'] ?? 0); ?></p>
            </div>
        </div>

        <div class="surface-card">
            <h2 class="surface-heading">Movimentações</h2>
            <div class="mt-4 overflow-x-auto surface-table-wrapper">
                <table class="min-w-full text-sm">
                    <thead class="surface-table-head">
                        <tr>
                            <th class="surface-table-cell text-left">
                                <a href="<?= sanitize($buildSortLink('date')); ?>" class="inline-flex items-center gap-2">
                                    Data <span class="text-slate-400"><?= sanitize($sortIndicator('date')); ?></span>
                                </a>
                            </th>
                            <th class="surface-table-cell text-left">
                                <a href="<?= sanitize($buildSortLink('type')); ?>" class="inline-flex items-center gap-2">
                                    Tipo <span class="text-slate-400"><?= sanitize($sortIndicator('type')); ?></span>
                                </a>
                            </th>
                            <th class="surface-table-cell text-left">
                                <a href="<?= sanitize($buildSortLink('items')); ?>" class="inline-flex items-center gap-2">
                                    Itens <span class="text-slate-400"><?= sanitize($sortIndicator('items')); ?></span>
                                </a>
                            </th>
                            <th class="surface-table-cell text-left">
                                <a href="<?= sanitize($buildSortLink('client')); ?>" class="inline-flex items-center gap-2">
                                    Cliente <span class="text-slate-400"><?= sanitize($sortIndicator('client')); ?></span>
                                </a>
                            </th>
                            <th class="surface-table-cell text-left">
                                <a href="<?= sanitize($buildSortLink('user')); ?>" class="inline-flex items-center gap-2">
                                    Responsável <span class="text-slate-400"><?= sanitize($sortIndicator('user')); ?></span>
                                </a>
                            </th>
                            <th class="surface-table-cell text-left">
                                <a href="<?= sanitize($buildSortLink('notes')); ?>" class="inline-flex items-center gap-2">
                                    Observações <span class="text-slate-400"><?= sanitize($sortIndicator('notes')); ?></span>
                                </a>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="surface-table-body">
                        <?php if (!$operations): ?>
                            <tr>
                                <td colspan="6" class="surface-table-cell text-center surface-muted">Nenhuma movimentação no período.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($operations as $operation): ?>
                            <tr class="<?= match ($operation['operation_type']) {
                                'ENTRADA' => 'bg-emerald-500/5',
                                'SAIDA' => 'bg-blue-500/5',
                                'RETORNO' => 'bg-amber-500/5',
                                default => ''
                            }; ?>">
                                <td class="surface-table-cell surface-muted"><?= format_datetime($operation['operation_date']); ?></td>
                                <td class="surface-table-cell font-semibold <?= match ($operation['operation_type']) {
                                    'ENTRADA' => 'text-green-600',
                                    'SAIDA' => 'text-blue-600',
                                    'RETORNO' => 'text-orange-600',
                                    default => 'text-slate-600'
                                }; ?>"><?= sanitize($operation['operation_type']); ?></td>
                                <td class="surface-table-cell surface-muted"><?= (int) $operation['quantidade']; ?></td>
                                <td class="surface-table-cell surface-muted"><?= sanitize($operation['cliente'] ?? '-'); ?></td>
                                <td class="surface-table-cell surface-muted"><?= sanitize($operation['usuario'] ?? '-'); ?></td>
                                <td class="surface-table-cell surface-muted"><?= sanitize($operation['notes'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
<?php
$footerScripts = <<<HTML
<script>
    const exportLink = document.getElementById('exportCsv');
    if (exportLink) {
        exportLink.addEventListener('click', () => {
            exportLink.textContent = 'Gerando...';
            exportLink.classList.add('opacity-70', 'pointer-events-none');
        });
    }
</script>
HTML;
include __DIR__ . '/../templates/footer.php';


