<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login(['admin', 'gestor']);

$pageTitle = 'Registrar Retorno';
$activeMenu = 'retornos';
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
        $errors[] = 'Sesso expirada. Recarregue a pgina.';
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
            $errors[] = 'Data do retorno invlida.';
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
                    throw new RuntimeException('Alguns equipamentos no esto mais marcados como alocados.');
                }

                $clientIds = array_unique(array_column($equipmentsData, 'current_client_id'));
                if (count($clientIds) !== 1) {
                    throw new RuntimeException('Selecione equipamentos do mesmo cliente por operao.');
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
            <div class="rounded border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                <?= sanitize($flash['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <ul class="list-disc space-y-1 pl-5">
                    <?php foreach ($errors as $error): ?>
                        <li><?= sanitize($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?> 

        <form method="post" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?= sanitize(ensure_csrf_token()); ?>">
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-800">Equipamentos alocados</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2">Retorno</th>
                                <th class="px-3 py-2 text-left">Etiqueta</th>
                                <th class="px-3 py-2 text-left">Cliente</th>
                                <th class="px-3 py-2 text-left">Acessórios</th>
                                <th class="px-3 py-2 text-left">Condições após retorno</th>
                                <th class="px-3 py-2 text-left">Observações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (!$allocatedEquipment): ?>
                                <tr>
                                    <td colspan="6" class="px-3 py-4 text-center text-slate-500">Nenhum equipamento alocado no momento.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($allocatedEquipment as $item): ?>
                                <tr>
                                    <td class="px-3 py-2 text-center align-top">
                                        <input type="checkbox" name="items[<?= (int) $item['id']; ?>][selected]" value="1" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                    </td>
                                    <td class="px-3 py-2 align-top">
                                        <p class="font-semibold text-slate-700"><?= sanitize($item['asset_tag']); ?></p>
                                        <p class="text-xs text-slate-500">Modelo: <?= sanitize($item['brand']); ?> <?= sanitize($item['model_name']); ?><?php if ($item['monitor_size']): ?> (<?= sanitize($item['monitor_size']); ?>")<?php endif; ?></p>
                                        <p class="text-xs text-slate-500">Condio atual: <?= sanitize($item['condition_status']); ?></p>
                                    </td>
                                    <td class="px-3 py-2 align-top text-sm text-slate-600">
                                        <?= sanitize($item['client_code']); ?> - <?= sanitize($item['client_name']); ?>
                                    </td>
                                    <td class="px-3 py-2 align-top space-y-2 text-xs text-slate-600">
                                        <label class="flex items-center gap-2">
                                            <input type="checkbox" name="items[<?= (int) $item['id']; ?>][power]" value="1" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                            Fonte de alimentao
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
                                    <td class="px-3 py-2 align-top">
                                        <select name="items[<?= (int) $item['id']; ?>][condition]" class="rounded-lg border border-slate-300 px-2 py-1 text-xs focus:border-blue-500 focus:outline-none">
                                            <option value="ok">Revisado - Pronto para uso</option>
                                            <option value="manutencao">Necessita manutenção</option>
                                            <option value="descartar">Descartar / Baixar</option>
                                        </select>
                                    </td>
                                    <td class="px-3 py-2 align-top">
                                        <textarea name="items[<?= (int) $item['id']; ?>][remarks]" rows="2" class="w-full rounded-lg border border-slate-300 px-2 py-1 text-xs focus:border-blue-500 focus:outline-none"></textarea>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
                <div>
                    <label class="text-sm font-medium text-slate-600" for="operation_date">Data e hora do retorno</label>
                    <input type="datetime-local" id="operation_date" name="operation_date" value="<?= sanitize($_POST['operation_date'] ?? date('Y-m-d\TH:i')); ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-600" for="general_notes">Observações gerais</label>
                    <textarea id="general_notes" name="general_notes" rows="4" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"><?= sanitize($_POST['general_notes'] ?? ''); ?></textarea>
                </div>
                <button type="submit" class="w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Registrar retorno</button>
            </div>
        </form>
    </section>
<?php include __DIR__ . '/../templates/footer.php';


