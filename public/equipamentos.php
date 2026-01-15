<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login();

$pageTitle = 'Equipamentos';
$activeMenu = 'equipamentos';
$showDensityToggle = true;
$pdo = get_pdo();

$status = $_GET['status'] ?? '';
$condition = $_GET['condition'] ?? '';
$search = trim($_GET['search'] ?? '');
$modelo = trim($_GET['modelo'] ?? '');
$sort = $_GET['sort'] ?? 'entry_date';
$dir = strtolower((string) ($_GET['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

$sortMap = [
    'asset_tag' => 'e.asset_tag',
    'model' => 'em.model_name',
    'serial' => 'e.serial_number',
    'mac' => 'e.mac_address',
    'status' => 'e.status',
    'condition' => 'e.condition_status',
    'client' => 'c.name',
    'entry_date' => 'e.entry_date',
];
$orderBy = $sortMap[$sort] ?? 'e.entry_date';

$query = <<<SQL
    SELECT e.id,
           e.asset_tag,
           e.serial_number,
           e.mac_address,
           e.status,
           e.condition_status,
           e.entry_date,
           e.batch,
           e.notes,
           em.brand,
           em.model_name,
           em.category,
           em.monitor_size,
           c.name AS cliente
    FROM equipment e
    INNER JOIN equipment_models em ON em.id = e.model_id
    LEFT JOIN clients c ON c.id = e.current_client_id
SQL;

$where = [];
$params = [];

if (in_array($status, ['em_estoque', 'alocado', 'manutencao', 'baixado'], true)) {
    $where[] = 'e.status = :status';
    $params['status'] = $status;
}

if (in_array($condition, ['novo', 'usado'], true)) {
    $where[] = 'e.condition_status = :condition_status';
    $params['condition_status'] = $condition;
}

if ($search !== '') {
    $where[] = '(e.asset_tag LIKE :term OR e.serial_number LIKE :term OR e.mac_address LIKE :term)';
    $params['term'] = '%' . $search . '%';
}

if ($modelo !== '') {
    $where[] = '(em.model_name LIKE :modelo OR em.brand LIKE :modelo)';
    $params['modelo'] = '%' . $modelo . '%';
}

if ($where) {
    $query .= ' WHERE ' . implode(' AND ', $where);
}

$query .= sprintf(' ORDER BY %s %s, e.id DESC LIMIT 200', $orderBy, strtoupper($dir));

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$equipment = $stmt->fetchAll();

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
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <form method="get" id="equipmentFiltersForm" class="flex flex-1 flex-col gap-3 text-sm">
                    <div class="flex flex-wrap items-center gap-3">
                        <input type="text" name="search" placeholder="Pesquisar etiqueta, série ou MAC" value="<?= sanitize($search); ?>" class="surface-field-compact md:w-64">
                        <button type="submit" class="rounded-xl bg-slate-800 px-4 py-2 font-semibold text-white hover:bg-slate-900">Filtrar</button>
                        <button type="button" id="saveEquipmentFilters" class="rounded-xl border border-slate-700 px-4 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-800/40">Salvar filtros</button>
                        <button type="button" id="applyEquipmentFilters" class="rounded-xl border border-slate-700 px-4 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-800/40">Aplicar salvos</button>
                        <?php if ($search !== '' || $status !== '' || $condition !== '' || $modelo !== ''): ?>
                            <a href="equipamentos.php" class="text-xs text-blue-300 hover:text-blue-200">Limpar filtros</a>
                        <?php endif; ?>
                    </div>
                    <details class="rounded-2xl border border-slate-800 bg-slate-950/60 px-4 py-3">
                        <summary class="cursor-pointer text-xs uppercase tracking-wide text-slate-400">Filtros avançados</summary>
                        <div class="mt-3 grid gap-3 md:grid-cols-3">
                            <select name="status" class="surface-select-compact">
                                <option value="">Status (todos)</option>
                                <option value="em_estoque" <?= $status === 'em_estoque' ? 'selected' : ''; ?>>Em estoque</option>
                                <option value="alocado" <?= $status === 'alocado' ? 'selected' : ''; ?>>Alocado</option>
                                <option value="manutencao" <?= $status === 'manutencao' ? 'selected' : ''; ?>>Manutenção</option>
                                <option value="baixado" <?= $status === 'baixado' ? 'selected' : ''; ?>>Baixado</option>
                            </select>
                            <select name="condition" class="surface-select-compact">
                                <option value="">Condição</option>
                                <option value="novo" <?= $condition === 'novo' ? 'selected' : ''; ?>>Novo</option>
                                <option value="usado" <?= $condition === 'usado' ? 'selected' : ''; ?>>Usado</option>
                            </select>
                            <input type="text" name="modelo" placeholder="Marca ou modelo" value="<?= sanitize($modelo); ?>" class="surface-field-compact">
                        </div>
                    </details>
                </form>
                <div class="flex gap-3 text-sm">
                    <a href="saida_registrar.php" class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-500">Registrar saída</a>
                    <a href="entrada_cadastrar.php" class="inline-flex items-center justify-center rounded-xl border border-blue-600 px-4 py-2 font-semibold text-blue-200 hover:bg-blue-500/10">Cadastrar entrada</a>
                </div>
            </div>
            <?php if ($search !== '' || $status !== '' || $condition !== '' || $modelo !== ''): ?>
                <div class="mt-4 flex flex-wrap gap-2">
                    <?php if ($search !== ''): ?>
                        <span class="surface-chip">Busca: <?= sanitize($search); ?></span>
                    <?php endif; ?>
                    <?php if ($status !== ''): ?>
                        <span class="surface-chip">Status: <?= sanitize($status); ?></span>
                    <?php endif; ?>
                    <?php if ($condition !== ''): ?>
                        <span class="surface-chip">Condição: <?= sanitize($condition); ?></span>
                    <?php endif; ?>
                    <?php if ($modelo !== ''): ?>
                        <span class="surface-chip">Modelo: <?= sanitize($modelo); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <details class="mt-4 rounded-2xl border border-slate-800 bg-slate-950/60 px-4 py-3 text-xs">
                <summary class="cursor-pointer text-xs uppercase tracking-wide text-slate-400">Colunas visíveis</summary>
                <div class="mt-3 flex flex-wrap gap-4 text-sm text-slate-300">
                    <label class="inline-flex items-center gap-2"><input type="checkbox" class="js-col-toggle" data-col="asset_tag" checked> Etiqueta</label>
                    <label class="inline-flex items-center gap-2"><input type="checkbox" class="js-col-toggle" data-col="model" checked> Modelo</label>
                    <label class="inline-flex items-center gap-2"><input type="checkbox" class="js-col-toggle" data-col="serial" checked> Série</label>
                    <label class="inline-flex items-center gap-2"><input type="checkbox" class="js-col-toggle" data-col="mac" checked> MAC</label>
                    <label class="inline-flex items-center gap-2"><input type="checkbox" class="js-col-toggle" data-col="status" checked> Status</label>
                    <label class="inline-flex items-center gap-2"><input type="checkbox" class="js-col-toggle" data-col="condition" checked> Condição</label>
                    <label class="inline-flex items-center gap-2"><input type="checkbox" class="js-col-toggle" data-col="client" checked> Cliente</label>
                </div>
            </details>
            <div class="mt-6 overflow-x-auto surface-table-wrapper">
                <table class="min-w-full text-sm">
                    <thead class="surface-table-head">
                        <tr>
                            <th class="surface-table-cell text-left" data-col="asset_tag">
                                <a href="<?= sanitize($buildSortLink('asset_tag')); ?>" class="inline-flex items-center gap-2">
                                    Etiqueta <span class="text-slate-400"><?= sanitize($sortIndicator('asset_tag')); ?></span>
                                </a>
                            </th>
                            <th class="surface-table-cell text-left" data-col="model">
                                <a href="<?= sanitize($buildSortLink('model')); ?>" class="inline-flex items-center gap-2">
                                    Modelo <span class="text-slate-400"><?= sanitize($sortIndicator('model')); ?></span>
                                </a>
                            </th>
                            <th class="surface-table-cell text-left" data-col="serial">
                                <a href="<?= sanitize($buildSortLink('serial')); ?>" class="inline-flex items-center gap-2">
                                    Número de Série <span class="text-slate-400"><?= sanitize($sortIndicator('serial')); ?></span>
                                </a>
                            </th>
                            <th class="surface-table-cell text-left" data-col="mac">
                                <a href="<?= sanitize($buildSortLink('mac')); ?>" class="inline-flex items-center gap-2">
                                    Endereço MAC <span class="text-slate-400"><?= sanitize($sortIndicator('mac')); ?></span>
                                </a>
                            </th>
                            <th class="surface-table-cell text-left" data-col="status">
                                <a href="<?= sanitize($buildSortLink('status')); ?>" class="inline-flex items-center gap-2">
                                    Status <span class="text-slate-400"><?= sanitize($sortIndicator('status')); ?></span>
                                </a>
                            </th>
                            <th class="surface-table-cell text-left" data-col="condition">
                                <a href="<?= sanitize($buildSortLink('condition')); ?>" class="inline-flex items-center gap-2">
                                    Condição <span class="text-slate-400"><?= sanitize($sortIndicator('condition')); ?></span>
                                </a>
                            </th>
                            <th class="surface-table-cell text-left" data-col="client">
                                <a href="<?= sanitize($buildSortLink('client')); ?>" class="inline-flex items-center gap-2">
                                    Cliente <span class="text-slate-400"><?= sanitize($sortIndicator('client')); ?></span>
                                </a>
                            </th>
                            <th class="surface-table-cell text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="surface-table-body">
                        <?php if (!$equipment): ?>
                            <tr>
                                <td colspan="8" class="surface-table-cell text-center surface-muted">Nenhum equipamento encontrado.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($equipment as $item): ?>
                            <tr class="hover:bg-slate-800/40">
                                <td class="surface-table-cell font-medium" data-col="asset_tag">
                                    <a href="equipamento_detalhe.php?id=<?= (int) $item['id']; ?>" class="text-blue-300 hover:text-blue-200">
                                        <?= sanitize($item['asset_tag']); ?>
                                    </a>
                                </td>
                                <td class="surface-table-cell" data-col="model">
                                    <?= sanitize($item['brand']); ?> <?= sanitize($item['model_name']); ?>
                                    <?php if ($item['monitor_size']): ?>
                                        <span class="text-xs surface-muted">- <?= sanitize($item['monitor_size']); ?>"</span>
                                    <?php endif; ?>
                                </td>
                                <td class="surface-table-cell surface-muted" data-col="serial"><?= sanitize($item['serial_number']); ?></td>
                                <td class="surface-table-cell surface-muted" data-col="mac"><?= sanitize($item['mac_address']); ?></td>
                                <td class="surface-table-cell" data-col="status">
                                    <span title="Status atual do equipamento" class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold <?= match ($item['status']) {
                                        'em_estoque' => 'bg-emerald-100 text-emerald-700',
                                        'alocado' => 'bg-blue-100 text-blue-700',
                                        'manutencao' => 'bg-amber-100 text-amber-700',
                                        default => 'bg-slate-200 text-slate-700'
                                    }; ?>">
                                        <?= sanitize($item['status']); ?>
                                    </span>
                                </td>
                                <td class="surface-table-cell surface-muted" data-col="condition"><?= sanitize($item['condition_status']); ?></td>
                                <td class="surface-table-cell surface-muted" data-col="client"><?= sanitize($item['cliente'] ?? '-'); ?></td>
                                <td class="surface-table-cell text-right">
                                    <a href="equipamento_detalhe.php?id=<?= (int) $item['id']; ?>" class="text-xs font-semibold text-blue-300 hover:text-blue-200">Detalhes</a>
                                </td>
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
    (function () {
        const form = document.getElementById('equipmentFiltersForm');
        const saveBtn = document.getElementById('saveEquipmentFilters');
        const applyBtn = document.getElementById('applyEquipmentFilters');
        const storageKey = 'equipamentosFilters';

        if (saveBtn && form) {
            saveBtn.addEventListener('click', () => {
                const data = new FormData(form);
                const payload = {};
                data.forEach((value, key) => { payload[key] = value; });
                localStorage.setItem(storageKey, JSON.stringify(payload));
                saveBtn.textContent = 'Filtros salvos';
                setTimeout(() => { saveBtn.textContent = 'Salvar filtros'; }, 1200);
            });
        }
        if (applyBtn && form) {
            applyBtn.addEventListener('click', () => {
                const saved = localStorage.getItem(storageKey);
                if (!saved) return;
                const payload = JSON.parse(saved);
                Object.keys(payload).forEach((key) => {
                    const field = form.querySelector('[name="' + key + '"]');
                    if (field) field.value = payload[key];
                });
                form.submit();
            });
        }

        const colKey = 'equipamentosColumns';
        const toggles = Array.from(document.querySelectorAll('.js-col-toggle'));
        const updateColumns = (config) => {
            toggles.forEach((toggle) => {
                const col = toggle.dataset.col;
                const visible = config[col] !== false;
                toggle.checked = visible;
                document.querySelectorAll('[data-col="' + col + '"]').forEach((el) => {
                    el.style.display = visible ? '' : 'none';
                });
            });
        };
        if (toggles.length) {
            const savedCols = localStorage.getItem(colKey);
            let config = {};
            if (savedCols) {
                try { config = JSON.parse(savedCols); } catch (e) { config = {}; }
            } else {
                toggles.forEach(t => { config[t.dataset.col] = true; });
            }
            updateColumns(config);
            toggles.forEach((toggle) => {
                toggle.addEventListener('change', () => {
                    config[toggle.dataset.col] = toggle.checked;
                    localStorage.setItem(colKey, JSON.stringify(config));
                    updateColumns(config);
                });
            });
        }
    })();
</script>
HTML;
include __DIR__ . '/../templates/footer.php';



