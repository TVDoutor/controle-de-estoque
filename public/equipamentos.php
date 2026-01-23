<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login();

$pageTitle = 'Equipamentos';
$activeMenu = 'equipamentos';
$showDensityToggle = true;
$pdo = get_pdo();
$user = current_user();
$canManage = user_has_role(['admin', 'gestor']);

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
                        <div class="mt-3 space-y-3">
                            <div class="grid gap-3 md:grid-cols-3">
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
                            <div class="flex justify-end pt-2 border-t border-slate-700">
                                <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-500 transition inline-flex items-center gap-2">
                                    <span class="material-icons-outlined text-base">check</span>
                                    Aplicar Filtros
                                </button>
                            </div>
                        </div>
                    </details>
                </form>
                <?php if ($canManage): ?>
                <div class="flex flex-wrap gap-3 text-sm">
                    <a href="entrada_cadastrar.php" 
                       class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 font-semibold text-white hover:bg-emerald-500 transition shadow-lg shadow-emerald-500/20">
                        <span class="material-icons-outlined text-lg">move_to_inbox</span>
                        <span>Cadastrar Entrada</span>
                    </a>
                    <a href="saida_registrar.php" 
                       class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 font-semibold text-white hover:bg-blue-500 transition shadow-lg shadow-blue-500/20">
                        <span class="material-icons-outlined text-lg">outbox</span>
                        <span>Registrar Saída</span>
                    </a>
                    <a href="retornos.php" 
                       class="inline-flex items-center gap-2 rounded-xl bg-amber-600 px-5 py-2.5 font-semibold text-white hover:bg-amber-500 transition shadow-lg shadow-amber-500/20">
                        <span class="material-icons-outlined text-lg">assignment_return</span>
                        <span>Registrar Retorno</span>
                    </a>
                </div>
                <?php endif; ?>
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
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="equipamento_detalhe.php?id=<?= (int) $item['id']; ?>" 
                                           class="text-xs font-semibold text-blue-300 hover:text-blue-200 transition">
                                            Detalhes
                                        </a>
                                        <?php if ($canManage): ?>
                                            <a href="equipamento_detalhe.php?id=<?= (int) $item['id']; ?>&edit=1" 
                                               class="text-xs font-semibold text-emerald-300 hover:text-emerald-200 transition inline-flex items-center gap-1">
                                                <span class="material-icons-outlined text-sm">edit</span>
                                                Editar
                                            </a>
                                        <?php endif; ?>
                                        <?php if (user_has_role('admin')): ?>
                                            <button type="button"
                                                    onclick="confirmDelete(<?= (int) $item['id']; ?>, '<?= sanitize($item['asset_tag']); ?>')"
                                                    class="text-xs font-semibold text-red-300 hover:text-red-200 transition inline-flex items-center gap-1">
                                                <span class="material-icons-outlined text-sm">delete</span>
                                                Excluir
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal de Confirmação de Exclusão -->
        <div id="deleteEquipmentModal" 
             x-data="{ 
                isOpen: false,
                equipmentId: 0,
                equipmentTag: '',
                submitDelete() {
                    const form = document.getElementById('deleteEquipmentForm');
                    if (form) {
                        form.submit();
                    }
                }
             }"
             x-show="isOpen"
             x-cloak
             @keydown.escape.window="isOpen = false"
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             style="display: none;">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" 
                 @click="isOpen = false"></div>
            
            <!-- Modal Content -->
            <div class="relative z-10 w-full max-w-md surface-card"
                 @click.stop>
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-red-100 mb-2">Confirmar Exclusão</h3>
                    <p class="text-sm text-slate-300 mb-1">Deseja realmente excluir o equipamento <strong x-text="equipmentTag"></strong>?</p>
                    <p class="text-xs text-red-300/80 mt-2">⚠️ Esta ação é irreversível e removerá o equipamento e todos os registros relacionados.</p>
                </div>
                <form id="deleteEquipmentForm" 
                      method="post" 
                      x-bind:action="'equipamento_detalhe.php?id=' + equipmentId"
                      class="space-y-3">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(ensure_csrf_token()); ?>">
                    <input type="hidden" name="action" value="delete">
                    <div class="flex justify-end gap-3">
                        <button type="button" 
                                @click="isOpen = false"
                                class="rounded-xl border border-slate-600 px-6 py-2.5 text-sm font-semibold text-slate-200 hover:bg-slate-800/40 transition">
                            Cancelar
                        </button>
                        <button type="button" 
                                @click="submitDelete()"
                                class="rounded-xl bg-red-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-red-500 transition">
                            Excluir
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
<?php
$footerScripts = <<<HTML
<script>
    // Função para confirmar exclusão de equipamento
    function confirmDelete(equipmentId, equipmentTag) {
        const modal = document.getElementById('deleteEquipmentModal');
        if (modal && modal._x_dataStack && modal._x_dataStack[0]) {
            modal._x_dataStack[0].equipmentId = equipmentId;
            modal._x_dataStack[0].equipmentTag = equipmentTag;
            modal._x_dataStack[0].isOpen = true;
            document.body.style.overflow = 'hidden';
        }
    }

    // Fechar modal ao clicar fora ou pressionar ESC
    document.addEventListener('alpine:init', () => {
        Alpine.data('deleteEquipmentModal', () => ({
            isOpen: false,
            equipmentId: 0,
            equipmentTag: '',
            init() {
                window.addEventListener('click', (e) => {
                    if (e.target.id === 'deleteEquipmentModal' && this.isOpen) {
                        this.isOpen = false;
                        document.body.style.overflow = '';
                    }
                });
            },
            submitDelete() {
                const form = document.getElementById('deleteEquipmentForm');
                if (form) {
                    this.isOpen = false;
                    document.body.style.overflow = '';
                    form.submit();
                }
            }
        }));
    });

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



