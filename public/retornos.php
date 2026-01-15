<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login(['admin', 'gestor']);

$pageTitle = 'Registrar Retorno';
$activeMenu = 'retornos';
$showDensityToggle = true;
$pdo = get_pdo();
$user = current_user();
$errors = [];

$allocatedEquipment = $pdo->query(<<<SQL
    SELECT e.id,
           e.asset_tag,
           e.condition_status,
           em.brand,
           em.model_name,
           em.monitor_size,
           c.id AS client_id,
           c.name AS client_name,
           c.client_code
    FROM equipment e
    INNER JOIN equipment_models em ON em.id = e.model_id
    INNER JOIN clients c ON c.id = e.current_client_id
    WHERE e.status = 'alocado'
    ORDER BY c.name, e.asset_tag
SQL)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sessão expirada. Recarregue a página.';
    } else {
        $items = $_POST['items'] ?? [];
        $operationDateInput = $_POST['operation_date'] ?? '';
        $generalNotes = trim($_POST['general_notes'] ?? '');

        $selectedItems = [];
        foreach ($items as $id => $data) {
            if (!empty($data['selected'])) {
                $selectedItems[(int) $id] = $data;
            }
        }

        if (!$selectedItems) {
            $errors[] = 'Selecione ao menos um equipamento para retorno.';
        }

        $operationDate = $operationDateInput ? DateTime::createFromFormat('Y-m-d\TH:i', $operationDateInput) : new DateTime();
        if (!$operationDate) {
            $errors[] = 'Data do retorno inválida.';
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();

                $placeholders = implode(',', array_fill(0, count($selectedItems), '?'));
                $stmt = $pdo->prepare(<<<SQL
                    SELECT e.id, e.current_client_id, c.name AS client_name
                    FROM equipment e
                    LEFT JOIN clients c ON c.id = e.current_client_id
                    WHERE e.id IN ($placeholders)
                      AND e.status = 'alocado'
SQL);
                $stmt->execute(array_keys($selectedItems));
                $equipmentsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($equipmentsData) !== count($selectedItems)) {
                    throw new RuntimeException('Alguns equipamentos não estão mais marcados como alocados.');
                }

                $clientIds = array_unique(array_column($equipmentsData, 'current_client_id'));
                if (count($clientIds) !== 1) {
                    throw new RuntimeException('Selecione equipamentos do mesmo cliente por operação.');
                }
                $clientId = (int) $clientIds[0];

                $insertOperation = $pdo->prepare('INSERT INTO equipment_operations (operation_type, operation_date, client_id, notes, performed_by) VALUES (\'RETORNO\', :date, :client_id, :notes, :performed_by)');
                $insertOperation->execute([
                    'date' => $operationDate->format('Y-m-d H:i:s'),
                    'client_id' => $clientId ?: null,
                    'notes' => $generalNotes ?: null,
                    'performed_by' => $user['id'],
                ]);
                $operationId = (int) $pdo->lastInsertId();

                $insertItem = $pdo->prepare(<<<SQL
                    INSERT INTO equipment_operation_items (
                        operation_id,
                        equipment_id,
                        accessories_power,
                        accessories_hdmi,
                        accessories_remote,
                        condition_after_return,
                        remarks
                    ) VALUES (
                        :operation_id,
                        :equipment_id,
                        :power,
                        :hdmi,
                        :remote,
                        :condition,
                        :remarks
                    )
SQL);

                $updateEquipment = $pdo->prepare(<<<SQL
                    UPDATE equipment
                    SET status = :status,
                        condition_status = 'usado',
                        current_client_id = NULL,
                        updated_by = :user_id,
                        updated_at = NOW()
                    WHERE id = :id
SQL);

                foreach ($selectedItems as $equipmentId => $data) {
                    $condition = $data['condition'] ?? 'ok';
                    if (!in_array($condition, ['ok', 'manutencao', 'descartar'], true)) {
                        $condition = 'ok';
                    }
                    $statusAfter = match ($condition) {
                        'ok' => 'em_estoque',
                        'manutencao' => 'manutencao',
                        'descartar' => 'baixado',
                    };

                    $insertItem->execute([
                        'operation_id' => $operationId,
                        'equipment_id' => $equipmentId,
                        'power' => !empty($data['power']) ? 1 : 0,
                        'hdmi' => !empty($data['hdmi']) ? 1 : 0,
                        'remote' => !empty($data['remote']) ? 1 : 0,
                        'condition' => $condition,
                        'remarks' => trim($data['remarks'] ?? '') ?: null,
                    ]);

                    $updateEquipment->execute([
                        'status' => $statusAfter,
                        'user_id' => $user['id'],
                        'id' => $equipmentId,
                    ]);
                }

                $pdo->commit();
                flash('success', 'Retorno registrado com sucesso.', 'success');
                redirect('retornos.php');
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
    <section class="flex-1 overflow-y-auto px-6 pb-12 space-y-6">
        <?php if ($flash = flash('success')): ?>
            <div class="rounded-2xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-4 text-sm text-emerald-200">
                <?= sanitize($flash['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="rounded-2xl border border-red-500/40 bg-red-500/10 px-4 py-4 text-sm text-red-200">
                <ul class="list-disc space-y-1 pl-5">
                    <?php foreach ($errors as $error): ?>
                        <li><?= sanitize($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?> 

        <form method="post" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?= sanitize(ensure_csrf_token()); ?>">
            <div class="surface-card">
                <h2 class="surface-heading">Equipamentos alocados</h2>
                <div class="mt-4 overflow-x-auto surface-table-wrapper">
                    <table class="min-w-full text-sm">
                        <thead class="surface-table-head">
                            <tr>
                                <th class="surface-table-cell">Retorno</th>
                                <th class="surface-table-cell text-left">Etiqueta</th>
                                <th class="surface-table-cell text-left">Cliente</th>
                                <th class="surface-table-cell text-left">Acessórios</th>
                                <th class="surface-table-cell text-left">Condição após retorno</th>
                                <th class="surface-table-cell text-left">Observações</th>
                            </tr>
                        </thead>
                        <tbody class="surface-table-body">
                            <?php if (!$allocatedEquipment): ?>
                                <tr>
                                    <td colspan="6" class="surface-table-cell text-center surface-muted">Nenhum equipamento alocado no momento.</td>
                                </tr>
                            <?php endif; ?>
                            <?php $currentClientId = null; ?>
                            <?php foreach ($allocatedEquipment as $item): ?>
                                <?php if ($currentClientId !== $item['client_id']): ?>
                                    <?php $currentClientId = $item['client_id']; ?>
                                    <tr class="bg-slate-900/70">
                                        <td colspan="6" class="surface-table-cell text-xs font-semibold uppercase tracking-wide text-blue-200">
                                            <div class="flex items-center justify-between gap-3">
                                                <span>Cliente: <?= sanitize($item['client_code']); ?> - <?= sanitize($item['client_name']); ?></span>
                                                <label class="inline-flex items-center gap-2 text-xs text-blue-200">
                                                    <input type="checkbox" class="js-client-toggle rounded border-slate-300 text-blue-600 focus:ring-blue-500" data-client-id="<?= (int) $item['client_id']; ?>">
                                                    Selecionar cliente
                                                </label>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <td class="surface-table-cell text-center align-top">
                                        <input type="checkbox" name="items[<?= (int) $item['id']; ?>][selected]" value="1" class="js-return-item rounded border-slate-300 text-blue-600 focus:ring-blue-500" data-client-id="<?= (int) $item['client_id']; ?>">
                                    </td>
                                    <td class="surface-table-cell align-top">
                                        <p class="font-semibold"><?= sanitize($item['asset_tag']); ?></p>
                                        <p class="text-xs surface-muted">Modelo: <?= sanitize($item['brand']); ?> <?= sanitize($item['model_name']); ?><?php if ($item['monitor_size']): ?> (<?= sanitize($item['monitor_size']); ?>")<?php endif; ?></p>
                                        <p class="text-xs surface-muted">Condição atual: <?= sanitize($item['condition_status']); ?></p>
                                    </td>
                                    <td class="surface-table-cell align-top text-sm">
                                        <?= sanitize($item['client_code']); ?> - <?= sanitize($item['client_name']); ?>
                                    </td>
                                    <td class="surface-table-cell align-top space-y-2 text-xs">
                                        <label class="flex items-center gap-2">
                                            <input type="checkbox" name="items[<?= (int) $item['id']; ?>][power]" value="1" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                            Fonte de alimentação
                                        </label>
                                        <label class="flex items-center gap-2">
                                            <input type="checkbox" name="items[<?= (int) $item['id']; ?>][hdmi]" value="1" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                            Cabo HDMI
                                        </label>
                                        <label class="flex items-center gap-2">
                                            <input type="checkbox" name="items[<?= (int) $item['id']; ?>][remote]" value="1" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                            Controle remoto
                                        </label>
                                    </td>
                                    <td class="surface-table-cell align-top">
                                        <select name="items[<?= (int) $item['id']; ?>][condition]" class="surface-select-compact js-return-condition">
                                            <option value="ok">OK - Pronto para uso</option>
                                            <option value="manutencao">Manutenção necessária</option>
                                            <option value="descartar">Descartar / Baixar</option>
                                        </select>
                                    </td>
                                    <td class="surface-table-cell align-top">
                                        <textarea name="items[<?= (int) $item['id']; ?>][remarks]" rows="2" class="surface-field-compact"></textarea>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="surface-card space-y-4">
                <div>
                    <label class="text-sm font-medium text-slate-300" for="operation_date">Data e hora do retorno</label>
                    <input type="datetime-local" id="operation_date" name="operation_date" value="<?= sanitize($_POST['operation_date'] ?? date('Y-m-d\TH:i')); ?>" class="surface-field">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-300" for="general_notes">Observações gerais</label>
                    <textarea id="general_notes" name="general_notes" rows="4" class="surface-field"><?= sanitize($_POST['general_notes'] ?? ''); ?></textarea>
                </div>
                <button type="submit" class="w-full rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Registrar retorno</button>
            </div>
        </form>
    </section>
<?php
$footerScripts = <<<HTML
<script>
    (function () {
        const clientToggles = Array.from(document.querySelectorAll('.js-client-toggle'));
        const itemCheckboxes = Array.from(document.querySelectorAll('.js-return-item'));
        const conditionSelects = Array.from(document.querySelectorAll('.js-return-condition'));
        if (!clientToggles.length || !itemCheckboxes.length) {
            return;
        }
        function updateClientState(clientId) {
            const items = itemCheckboxes.filter(cb => cb.dataset.clientId === clientId);
            const checked = items.filter(cb => cb.checked);
            const toggle = clientToggles.find(cb => cb.dataset.clientId === clientId);
            if (!toggle) return;
            toggle.checked = checked.length > 0 && checked.length === items.length;
            toggle.indeterminate = checked.length > 0 && checked.length < items.length;
        }
        clientToggles.forEach(toggle => {
            toggle.addEventListener('change', () => {
                const clientId = toggle.dataset.clientId;
                itemCheckboxes
                    .filter(cb => cb.dataset.clientId === clientId)
                    .forEach(cb => { cb.checked = toggle.checked; });
                updateClientState(clientId);
            });
        });
        itemCheckboxes.forEach(cb => {
            cb.addEventListener('change', () => updateClientState(cb.dataset.clientId));
        });
        clientToggles.forEach(toggle => updateClientState(toggle.dataset.clientId));

        const applyConditionStyle = (select) => {
            select.classList.remove('border-emerald-400', 'border-amber-400', 'border-red-400', 'text-emerald-200', 'text-amber-200', 'text-red-200');
            if (select.value === 'ok') {
                select.classList.add('border-emerald-400', 'text-emerald-200');
            } else if (select.value === 'manutencao') {
                select.classList.add('border-amber-400', 'text-amber-200');
            } else if (select.value === 'descartar') {
                select.classList.add('border-red-400', 'text-red-200');
            }
        };
        conditionSelects.forEach(select => {
            select.addEventListener('change', () => applyConditionStyle(select));
            applyConditionStyle(select);
        });
    })();
</script>
HTML;
include __DIR__ . '/../templates/footer.php';


