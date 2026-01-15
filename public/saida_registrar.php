<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login(['admin', 'gestor']);

$pageTitle = 'Registrar Saída';
$activeMenu = 'saidas';
$pdo = get_pdo();
$user = current_user();
$errors = [];

$clients = $pdo->query('SELECT id, client_code, name FROM clients ORDER BY name')->fetchAll();
$availableEquipment = $pdo->query(<<<SQL
    SELECT e.id,
           e.asset_tag,
           e.condition_status,
           em.brand,
           em.model_name,
           em.monitor_size
    FROM equipment e
    INNER JOIN equipment_models em ON em.id = e.model_id
    WHERE e.status = 'em_estoque'
    ORDER BY e.entry_date ASC
SQL)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sessão expirada. Recarregue a página.';
    } else {
        $equipmentIds = array_filter(array_map('intval', $_POST['equipment_ids'] ?? []));
        $clientId = (int) ($_POST['client_id'] ?? 0);
        $operationDateInput = $_POST['operation_date'] ?? '';
        $notes = trim($_POST['notes'] ?? '');

        $newClientName = trim($_POST['new_client_name'] ?? '');
        $newClientCode = trim($_POST['new_client_code'] ?? '');
        $newClientCnpj = trim($_POST['new_client_cnpj'] ?? '');

        if (!$equipmentIds) {
            $errors[] = 'Selecione pelo menos um equipamento.';
        }

        if ($clientId === 0 && $newClientName === '') {
            $errors[] = 'Escolha um cliente existente ou cadastre um novo.';
        }

        $operationDate = $operationDateInput ? DateTime::createFromFormat('Y-m-d\TH:i', $operationDateInput) : new DateTime();
        if (!$operationDate) {
            $errors[] = 'Data da saída inválida.';
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();

                if ($clientId === 0) {
                    if ($newClientCode === '') {
                        throw new RuntimeException('Informe o código do novo cliente.');
                    }
                    $insertClient = $pdo->prepare('INSERT INTO clients (client_code, name, cnpj) VALUES (:code, :name, :cnpj)');
                    $insertClient->execute([
                        'code' => $newClientCode,
                        'name' => $newClientName,
                        'cnpj' => $newClientCnpj ?: null,
                    ]);
                    $clientId = (int) $pdo->lastInsertId();
                }

                $placeholders = implode(',', array_fill(0, count($equipmentIds), '?'));
                $stmtCheck = $pdo->prepare("SELECT id FROM equipment WHERE id IN ($placeholders) AND status = 'em_estoque'");
                $stmtCheck->execute($equipmentIds);
                $validIds = $stmtCheck->fetchAll(PDO::FETCH_COLUMN);

                if (count($validIds) !== count($equipmentIds)) {
                    throw new RuntimeException('Alguns equipamentos não estão mais disponíveis em estoque.');
                }

                $insertOperation = $pdo->prepare('INSERT INTO equipment_operations (operation_type, operation_date, client_id, notes, performed_by) VALUES (\'SAIDA\', :date, :client_id, :notes, :performed_by)');
                $insertOperation->execute([
                    'date' => $operationDate->format('Y-m-d H:i:s'),
                    'client_id' => $clientId,
                    'notes' => $notes ?: null,
                    'performed_by' => $user['id'],
                ]);
                $operationId = (int) $pdo->lastInsertId();

                $insertItem = $pdo->prepare('INSERT INTO equipment_operation_items (operation_id, equipment_id) VALUES (:operation_id, :equipment_id)');
                $updateEquipment = $pdo->prepare('UPDATE equipment SET status = \'alocado\', current_client_id = :client_id, updated_by = :user_id, updated_at = NOW() WHERE id = :id');

                foreach ($equipmentIds as $equipmentId) {
                    $insertItem->execute([
                        'operation_id' => $operationId,
                        'equipment_id' => $equipmentId,
                    ]);
                    $updateEquipment->execute([
                        'client_id' => $clientId,
                        'user_id' => $user['id'],
                        'id' => $equipmentId,
                    ]);
                }

                $pdo->commit();
                flash('success', 'Saída registrada com sucesso.', 'success');
                redirect('saida_registrar.php');
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = $exception->getMessage();
            }
        }
    }
}

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>
<main class="flex-1 flex flex-col bg-slate-950 text-slate-100">
    <?php include __DIR__ . '/../templates/topbar.php'; ?>
    <section class="flex-1 overflow-y-auto px-6 pb-12">
        <?php if ($flash = flash('success')): ?>
            <div class="rounded-2xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-4 text-sm text-emerald-200">
                <?= sanitize($flash['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="rounded-2xl border border-red-500/40 bg-red-500/10 px-4 py-4 text-sm text-red-200">
                <ul class="list-disc space-y-1 pl-5 text-red-100">
                    <?php foreach ($errors as $error): ?>
                        <li><?= sanitize($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="grid gap-6 lg:grid-cols-3">
            <input type="hidden" name="csrf_token" value="<?= sanitize(ensure_csrf_token()); ?>">
            <div class="lg:col-span-2 surface-card space-y-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Equipamentos disponíveis</h2>
                        <p class="mt-2 text-sm text-slate-400">Marque os itens que serão enviados ao cliente.</p>
                    </div>
                    <div class="text-sm text-slate-300">
                        Selecionados: <span id="selectedCount" class="font-semibold text-white">0</span>
                    </div>
                </div>
                <div class="mt-5 max-h-96 overflow-y-auto rounded-2xl border border-slate-800 bg-slate-950/60">
                    <table class="min-w-full text-left text-sm text-slate-200">
                        <thead class="bg-slate-900/80 text-xs uppercase tracking-wide text-slate-400">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-slate-300">
                                    <label class="inline-flex items-center gap-2">
                                        <input id="selectAll" type="checkbox" class="h-4 w-4 rounded border-slate-700 bg-slate-950 text-blue-400 focus:ring-blue-500">
                                        Selecionar todos
                                    </label>
                                </th>
                                <th class="px-4 py-3 text-left font-medium text-slate-300">Etiqueta</th>
                                <th class="px-4 py-3 text-left font-medium text-slate-300">Modelo</th>
                                <th class="px-4 py-3 text-left font-medium text-slate-300">Condição</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <?php if (!$availableEquipment): ?>
                                <tr>
                                    <td colspan="4" class="px-4 py-5 text-center text-slate-400">Nenhum equipamento disponível em estoque.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($availableEquipment as $item): ?>
                                <tr>
                                    <td class="px-4 py-3 text-center text-slate-300">
                                        <input type="checkbox" name="equipment_ids[]" value="<?= (int) $item['id']; ?>" class="js-equipment-checkbox h-4 w-4 rounded border-slate-700 bg-slate-950 text-blue-400 focus:ring-blue-500">
                                    </td>
                                    <td class="px-4 py-3 font-medium text-slate-200"><?= sanitize($item['asset_tag']); ?></td>
                                    <td class="px-4 py-3 text-slate-300">
                                        <?= sanitize($item['brand']); ?> <?= sanitize($item['model_name']); ?><?php if ($item['monitor_size']): ?> (<?= sanitize($item['monitor_size']); ?>")<?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-slate-400"><?= sanitize($item['condition_status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="space-y-6">
                <div class="surface-card space-y-3">
                    <h2 class="text-lg font-semibold text-white">Resumo</h2>
                    <div class="flex items-center justify-between text-sm text-slate-300">
                        <span>Itens selecionados</span>
                        <span id="summaryCount" class="font-semibold text-white">0</span>
                    </div>
                    <div class="flex items-center justify-between text-sm text-slate-300">
                        <span>Cliente</span>
                        <span id="summaryClient" class="font-semibold text-white">Nenhum</span>
                    </div>
                </div>
                <div class="surface-card space-y-5">
                    <h2 class="text-lg font-semibold text-white">Cliente</h2>
                    <label class="block text-sm font-medium text-slate-300" for="client_id">Selecionar cliente existente</label>
                    <select id="client_id" name="client_id" class="surface-select">
                        <option value="0">-- Escolher --</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= (int) $client['id']; ?>" <?= ((int) ($_POST['client_id'] ?? 0) === (int) $client['id']) ? 'selected' : ''; ?>>
                                <?= sanitize($client['client_code']); ?> - <?= sanitize($client['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="rounded-xl border border-slate-800 bg-slate-950/60 px-4 py-3 text-xs uppercase tracking-wide text-slate-400">Ou cadastre um novo cliente</div>
                    <input type="text" name="new_client_code" placeholder="Código do novo cliente" value="<?= sanitize($_POST['new_client_code'] ?? ''); ?>" class="surface-field">
                    <input type="text" name="new_client_name" placeholder="Nome do novo cliente" value="<?= sanitize($_POST['new_client_name'] ?? ''); ?>" class="surface-field">
                    <input type="text" name="new_client_cnpj" placeholder="CNPJ" value="<?= sanitize($_POST['new_client_cnpj'] ?? ''); ?>" class="surface-field" data-mask="cnpj" inputmode="numeric" pattern="\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}" title="Formato esperado: 00.000.000/0000-00">
                </div>
                <div class="surface-card space-y-5">
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="operation_date">Data e hora da saída</label>
                        <input type="datetime-local" id="operation_date" name="operation_date" value="<?= sanitize($_POST['operation_date'] ?? date('Y-m-d\TH:i')); ?>" class="surface-field">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="notes">Observações</label>
                        <textarea id="notes" name="notes" rows="4" class="surface-field"><?= sanitize($_POST['notes'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-500">Registrar saída</button>
                </div>
            </div>
        </form>
    </section>
<?php
$footerScripts = <<<HTML
<script>
    const checkboxes = Array.from(document.querySelectorAll('.js-equipment-checkbox'));
    const selectAll = document.getElementById('selectAll');
    const selectedCount = document.getElementById('selectedCount');
    const summaryCount = document.getElementById('summaryCount');
    const summaryClient = document.getElementById('summaryClient');
    const clientSelect = document.getElementById('client_id');
    const newClientName = document.querySelector('input[name="new_client_name"]');

    function updateSelectedCount() {
        const count = checkboxes.filter(cb => cb.checked).length;
        if (selectedCount) {
            selectedCount.textContent = String(count);
        }
        if (summaryCount) {
            summaryCount.textContent = String(count);
        }
        if (selectAll) {
            selectAll.checked = count > 0 && count === checkboxes.length;
            selectAll.indeterminate = count > 0 && count < checkboxes.length;
        }
    }

    function updateClientSummary() {
        let label = 'Nenhum';
        if (clientSelect && clientSelect.value !== '0') {
            const selected = clientSelect.options[clientSelect.selectedIndex];
            label = selected ? selected.text : label;
        } else if (newClientName && newClientName.value.trim() !== '') {
            label = newClientName.value.trim();
        }
        if (summaryClient) {
            summaryClient.textContent = label;
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', () => {
            checkboxes.forEach(cb => { cb.checked = selectAll.checked; });
            updateSelectedCount();
        });
    }
    checkboxes.forEach(cb => cb.addEventListener('change', updateSelectedCount));
    if (clientSelect) {
        clientSelect.addEventListener('change', updateClientSummary);
    }
    if (newClientName) {
        newClientName.addEventListener('input', updateClientSummary);
    }
    updateSelectedCount();
    updateClientSummary();
</script>
HTML;
include __DIR__ . '/../templates/footer.php';


