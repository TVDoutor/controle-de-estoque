<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login();

$pdo = get_pdo();
$equipmentId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(<<<SQL
    SELECT e.*, em.brand, em.model_name, em.category, em.monitor_size, c.name AS cliente_nome, c.client_code
    FROM equipment e
    INNER JOIN equipment_models em ON em.id = e.model_id
    LEFT JOIN clients c ON c.id = e.current_client_id
    WHERE e.id = :id
    LIMIT 1
SQL);
$stmt->execute(['id' => $equipmentId]);
$equipment = $stmt->fetch();

if (!$equipment) {
    http_response_code(404);
    exit('Equipamento no encontrado.');
}

$pageTitle = 'Equipamento ' . $equipment['asset_tag'];
$activeMenu = 'equipamentos';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sesso expirada. Recarregue a pgina.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'add_note') {
            $note = trim($_POST['note'] ?? '');
            if ($note === '') {
                $errors[] = 'Escreva uma anotao.';
            } else {
                $insert = $pdo->prepare('INSERT INTO equipment_notes (equipment_id, user_id, note) VALUES (:equipment_id, :user_id, :note)');
                $insert->execute([
                    'equipment_id' => $equipmentId,
                    'user_id' => current_user()['id'],
                    'note' => $note,
                ]);
                flash('success', 'Anotao adicionada.', 'success');
                redirect('equipamento_detalhe.php?id=' . $equipmentId);
            }
        } elseif ($action === 'update_status' && user_has_role(['admin', 'gestor'])) {
            $status = $_POST['status'] ?? '';
            if (!in_array($status, ['em_estoque', 'alocado', 'manutencao', 'baixado'], true)) {
                $errors[] = 'Status invlido.';
            } else {
                $update = $pdo->prepare('UPDATE equipment SET status = :status, updated_at = NOW() WHERE id = :id');
                $update->execute(['status' => $status, 'id' => $equipmentId]);
                flash('success', 'Status atualizado.', 'success');
                redirect('equipamento_detalhe.php?id=' . $equipmentId);
            }
        } elseif ($action === 'delete') {
            // Only admins can delete equipment
            if (!user_has_role('admin')) {
                $errors[] = 'Você não tem permissão para excluir este equipamento.';
            } else {
                try {
                    $pdo->beginTransaction();
                    // remove dependent records first
                    $del1 = $pdo->prepare('DELETE FROM equipment_operation_items WHERE equipment_id = :id');
                    $del1->execute(['id' => $equipmentId]);
                    $del2 = $pdo->prepare('DELETE FROM equipment_notes WHERE equipment_id = :id');
                    $del2->execute(['id' => $equipmentId]);
                    // finally remove equipment
                    $del3 = $pdo->prepare('DELETE FROM equipment WHERE id = :id');
                    $del3->execute(['id' => $equipmentId]);
                    $pdo->commit();
                    flash('success', 'Equipamento excluído com sucesso.', 'success');
                    redirect('equipamentos.php');
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log('Erro ao excluir equipamento: ' . $e->getMessage());
                    $errors[] = 'Erro ao excluir equipamento. Tente novamente ou contate o suporte.';
                }
            }
        }
    }
}

$notesStmt = $pdo->prepare(<<<SQL
    SELECT en.note, en.created_at, u.name
    FROM equipment_notes en
    INNER JOIN users u ON u.id = en.user_id
    WHERE en.equipment_id = :id
    ORDER BY en.created_at DESC
SQL);
$notesStmt->execute(['id' => $equipmentId]);
$notes = $notesStmt->fetchAll();

$historyStmt = $pdo->prepare(<<<SQL
    SELECT eo.operation_type,
           eo.operation_date,
           eo.notes,
           u.name AS usuario,
           c.name AS cliente,
           eoi.accessories_power,
           eoi.accessories_hdmi,
           eoi.accessories_remote,
           eoi.condition_after_return
    FROM equipment_operation_items eoi
    INNER JOIN equipment_operations eo ON eo.id = eoi.operation_id
    LEFT JOIN users u ON u.id = eo.performed_by
    LEFT JOIN clients c ON c.id = eo.client_id
    WHERE eoi.equipment_id = :id
    ORDER BY eo.operation_date DESC
SQL);
$historyStmt->execute(['id' => $equipmentId]);
$history = $historyStmt->fetchAll();

echo '';
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

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2 space-y-6">
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-800">Informações do equipamento</h2>
                    <dl class="mt-4 grid gap-4 md:grid-cols-2">
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">Etiqueta</dt>
                            <dd class="text-sm font-semibold text-slate-700"><?= sanitize($equipment['asset_tag']); ?></dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">Modelo</dt>
                            <dd class="text-sm text-slate-700"><?= sanitize($equipment['brand']); ?> <?= sanitize($equipment['model_name']); ?> <?= $equipment['monitor_size'] ? '(' . sanitize($equipment['monitor_size']) . '")' : ''; ?></dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">Número de série</dt>
                            <dd class="text-sm text-slate-700"><?= sanitize($equipment['serial_number']); ?></dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">MAC Address</dt>
                            <dd class="text-sm text-slate-700"><?= sanitize($equipment['mac_address']); ?></dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">Entrada</dt>
                            <dd class="text-sm text-slate-700"><?= format_date($equipment['entry_date']); ?></dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">Lote</dt>
                            <dd class="text-sm text-slate-700"><?= sanitize($equipment['batch']); ?></dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">Status</dt>
                            <dd class="text-sm font-semibold text-blue-700"><?= sanitize($equipment['status']); ?></dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">Condições</dt>
                            <dd class="text-sm text-slate-700"><?= sanitize($equipment['condition_status']); ?></dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">Cliente atual</dt>
                            <dd class="text-sm text-slate-700"><?= sanitize($equipment['cliente_nome'] ?? '-'); ?></dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500">Código do cliente</dt>
                            <dd class="text-sm text-slate-700"><?= sanitize($equipment['client_code'] ?? '-'); ?></dd>
                        </div>
                    </dl>
                    <?php if ($equipment['notes']): ?>
                        <div class="mt-4 rounded border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            <?= nl2br(sanitize($equipment['notes'])); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-800">Histórico</h2>
                    <div class="mt-4 space-y-4">
                        <?php if (!$history): ?>
                            <p class="text-sm text-slate-500">Sem movimentações registradas.</p>
                        <?php endif; ?>
                        <?php foreach ($history as $item): ?>
                            <div class="rounded-lg border border-slate-100 p-4">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="font-semibold text-slate-700"><?= sanitize($item['operation_type']); ?></span>
                                    <span class="text-slate-500"><?= format_datetime($item['operation_date']); ?></span>
                                </div>
                                <p class="mt-1 text-sm text-slate-600">Responsvel: <?= sanitize($item['usuario'] ?? '-'); ?></p>
                                <?php if ($item['cliente']): ?>
                                    <p class="text-sm text-slate-600">Cliente: <?= sanitize($item['cliente']); ?></p>
                                <?php endif; ?>
                                <?php if ($item['operation_type'] === 'RETORNO'): ?>
                                    <p class="text-xs text-slate-500 mt-2">Acessórios: Fonte <?= $item['accessories_power'] ? '' : ''; ?> - HDMI <?= $item['accessories_hdmi'] ? '' : ''; ?> - Controle <?= $item['accessories_remote'] ? '' : ''; ?></p>
                                    <p class="text-xs text-slate-500">Condições aps retorno: <?= sanitize($item['condition_after_return'] ?? '-'); ?></p>
                                <?php endif; ?>
                                <?php if ($item['notes']): ?>
                                    <p class="mt-2 text-xs text-slate-500">Obs: <?= sanitize($item['notes']); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="space-y-6">
                <?php if (user_has_role(['admin', 'gestor'])): ?>
                    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-slate-800">Atualizar status</h2>
                        <form method="post" class="mt-4 space-y-4">
                            <input type="hidden" name="csrf_token" value="<?= sanitize(ensure_csrf_token()); ?>">
                            <input type="hidden" name="action" value="update_status">
                            <select name="status" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                                <option value="em_estoque" <?= $equipment['status'] === 'em_estoque' ? 'selected' : ''; ?>>Em estoque</option>
                                <option value="alocado" <?= $equipment['status'] === 'alocado' ? 'selected' : ''; ?>>Alocado</option>
                                <option value="manutencao" <?= $equipment['status'] === 'manutencao' ? 'selected' : ''; ?>>Manutenção</option>
                                <option value="baixado" <?= $equipment['status'] === 'baixado' ? 'selected' : ''; ?>>Baixado</option>
                            </select>
                            <button type="submit" class="w-full rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">Salvar</button>
                        </form>
                    </div>
                <?php endif; ?>
                <?php if (user_has_role('admin')): ?>
                    <div class="rounded-xl border border-red-200 bg-red-50 p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-slate-800">Excluir equipamento</h2>
                        <p class="text-sm text-slate-600">Atenção: esta ação é irreversível e removerá o equipamento e registros relacionados.</p>
                        <form method="post" onsubmit="return confirm('Confirma exclusão deste equipamento? Esta ação não pode ser desfeita.');" class="mt-4">
                            <input type="hidden" name="csrf_token" value="<?= sanitize(ensure_csrf_token()); ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="w-full rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Excluir equipamento</button>
                        </form>
                    </div>
                <?php endif; ?>
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-800">Anotaes</h2>
                    <form method="post" class="mt-4 space-y-3">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(ensure_csrf_token()); ?>">
                        <input type="hidden" name="action" value="add_note">
                        <textarea name="note" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" placeholder="Registre observaes importantes..."></textarea>
                        <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Adicionar anotação</button>
                    </form>
                    <div class="mt-4 space-y-3 text-sm text-slate-600">
                        <?php if (!$notes): ?>
                            <p class="text-sm text-slate-500">Nenhuma anotação registrada.</p>
                        <?php endif; ?>
                        <?php foreach ($notes as $note): ?>
                            <div class="rounded border border-slate-100 bg-slate-50 px-3 py-2">
                                <p><?= nl2br(sanitize($note['note'])); ?></p>
                                <p class="mt-1 text-xs text-slate-500">Por <?= sanitize($note['name']); ?> em <?= format_datetime($note['created_at']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
<?php include __DIR__ . '/../templates/footer.php';




