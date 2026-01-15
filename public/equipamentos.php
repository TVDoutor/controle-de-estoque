<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login();

$pageTitle = 'Equipamentos';
$activeMenu = 'equipamentos';
$pdo = get_pdo();

$status = $_GET['status'] ?? '';
$condition = $_GET['condition'] ?? '';
$search = trim($_GET['search'] ?? '');
$modelo = trim($_GET['modelo'] ?? '');

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

$query .= ' ORDER BY e.entry_date DESC, e.id DESC LIMIT 200';

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$equipment = $stmt->fetchAll();

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>
<main class="flex-1 flex flex-col bg-slate-950 text-slate-100">
    <?php include __DIR__ . '/../templates/topbar.php'; ?>
    <section class="flex-1 overflow-y-auto px-6 pb-12 space-y-6">
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <form method="get" class="flex flex-1 flex-wrap gap-3 text-sm">
                    <input type="text" name="search" placeholder="Pesquisar etiqueta, srie ou MAC" value="<?= sanitize($search); ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-blue-500 focus:outline-none md:w-64">
                    <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                        <option value="">Status (todos)</option>
                        <option value="em_estoque" <?= $status === 'em_estoque' ? 'selected' : ''; ?>>Em estoque</option>
                        <option value="alocado" <?= $status === 'alocado' ? 'selected' : ''; ?>>Alocado</option>
                        <option value="manutencao" <?= $status === 'manutencao' ? 'selected' : ''; ?>>Manutenção</option>
                        <option value="baixado" <?= $status === 'baixado' ? 'selected' : ''; ?>>Baixado</option>
                    </select>
                    <select name="condition" class="rounded-lg border border-slate-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                        <option value="">Condio</option>
                        <option value="novo" <?= $condition === 'novo' ? 'selected' : ''; ?>>Novo</option>
                        <option value="usado" <?= $condition === 'usado' ? 'selected' : ''; ?>>Usado</option>
                    </select>
                    <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 font-semibold text-white hover:bg-slate-900">Filtrar</button>
                </form>
                <div class="flex gap-3 text-sm">
                    <a href="entrada_cadastrar.php" class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700">Cadastrar entrada</a>
                    <a href="saida_registrar.php" class="inline-flex items-center justify-center rounded-lg border border-blue-600 px-4 py-2 font-semibold text-blue-600 hover:bg-blue-50">Registrar sada</a>
                </div>
            </div>
            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-3 text-left">Etiqueta</th>
                            <th class="px-3 py-3 text-left">Modelo</th>
                            <th class="px-3 py-3 text-left">Número de Série</th>
                            <th class="px-3 py-3 text-left">Endereço MAC</th>
                            <th class="px-3 py-3 text-left">Status</th>
                            <th class="px-3 py-3 text-left">Condições</th>
                            <th class="px-3 py-3 text-left">Cliente</th>
                            <th class="px-3 py-3 text-right">Observações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (!$equipment): ?>
                            <tr>
                                <td colspan="8" class="px-3 py-6 text-center text-sm text-slate-500">Nenhum equipamento encontrado.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($equipment as $item): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-3 font-medium text-slate-700">
                                    <a href="equipamento_detalhe.php?id=<?= (int) $item['id']; ?>" class="text-blue-600 hover:underline">
                                        <?= sanitize($item['asset_tag']); ?>
                                    </a>
                                </td>
                                <td class="px-3 py-3 text-slate-600">
                                    <?= sanitize($item['brand']); ?> <?= sanitize($item['model_name']); ?>
                                    <?php if ($item['monitor_size']): ?>
                                        <span class="text-xs text-slate-400">- <?= sanitize($item['monitor_size']); ?>"</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-3 text-slate-500"><?= sanitize($item['serial_number']); ?></td>
                                <td class="px-3 py-3 text-slate-500"><?= sanitize($item['mac_address']); ?></td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold <?= match ($item['status']) {
                                        'em_estoque' => 'bg-emerald-100 text-emerald-700',
                                        'alocado' => 'bg-blue-100 text-blue-700',
                                        'manutencao' => 'bg-amber-100 text-amber-700',
                                        default => 'bg-slate-200 text-slate-700'
                                    }; ?>">
                                        <?= sanitize($item['status']); ?>
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-slate-500"><?= sanitize($item['condition_status']); ?></td>
                                <td class="px-3 py-3 text-slate-500"><?= sanitize($item['cliente'] ?? '-'); ?></td>
                                <td class="px-3 py-3 text-right">
                                    <a href="equipamento_detalhe.php?id=<?= (int) $item['id']; ?>" class="text-xs font-semibold text-blue-600 hover:text-blue-700">Detalhes</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
<?php include __DIR__ . '/../templates/footer.php';



