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
    exit('Equipamento não encontrado.');
}

$pageTitle = 'Equipamento ' . $equipment['asset_tag'];
$activeMenu = 'equipamentos';
$breadcrumbs = [
    ['label' => 'Equipamentos', 'href' => 'equipamentos.php'],
    ['label' => 'Detalhe']
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sessão expirada. Recarregue a página.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'add_note') {
            $note = trim($_POST['note'] ?? '');
            if ($note === '') {
                $errors[] = 'Escreva uma anotação.';
            } else {
                $insert = $pdo->prepare('INSERT INTO equipment_notes (equipment_id, user_id, note) VALUES (:equipment_id, :user_id, :note)');
                $insert->execute([
                    'equipment_id' => $equipmentId,
                    'user_id' => current_user()['id'],
                    'note' => $note,
                ]);
                flash('success', 'Anotação adicionada.', 'success');
                redirect('equipamento_detalhe.php?id=' . $equipmentId);
            }
        } elseif ($action === 'update_status' && user_has_role(['admin', 'gestor'])) {
            $status = $_POST['status'] ?? '';
            if (!in_array($status, ['em_estoque', 'alocado', 'manutencao', 'baixado'], true)) {
                $errors[] = 'Status inválido.';
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

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2 space-y-6">
                <div class="surface-card xl:sticky xl:top-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="surface-heading">Informações do equipamento</h2>
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <?php
                                $statusBadge = match ($equipment['status']) {
                                    'em_estoque' => 'bg-emerald-500/15 text-emerald-200 border border-emerald-500/40',
                                    'alocado' => 'bg-blue-500/15 text-blue-200 border border-blue-500/40',
                                    'manutencao' => 'bg-amber-500/15 text-amber-200 border border-amber-500/40',
                                    'baixado' => 'bg-red-500/15 text-red-200 border border-red-500/40',
                                    default => 'bg-slate-500/20 text-slate-200 border border-slate-500/40',
                                };
                            ?>
                            <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 font-semibold <?= $statusBadge; ?>">
                                Status: <?= sanitize($equipment['status']); ?>
                            </span>
                            <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 font-semibold bg-slate-500/20 text-slate-200 border border-slate-500/40">
                                Condição: <?= sanitize($equipment['condition_status']); ?>
                            </span>
                        </div>
                    </div>
                    <dl class="mt-4 grid gap-4 md:grid-cols-2">
                        <div>
                            <dt class="text-xs uppercase tracking-wide surface-muted">Etiqueta</dt>
                            <dd class="text-sm font-semibold"><?= sanitize($equipment['asset_tag']); ?></dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide surface-muted">Modelo</dt>
                            <dd class="text-sm"><?= sanitize($equipment['brand']); ?> <?= sanitize($equipment['model_name']); ?> <?= $equipment['monitor_size'] ? '(' . sanitize($equipment['monitor_size']) . '")' : ''; ?></dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide surface-muted">Número de série</dt>
                            <dd class="text-sm"><?= sanitize($equipment['serial_number']); ?></dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide surface-muted">MAC Address</dt>
                            <dd class="text-sm"><?= sanitize($equipment['mac_address']); ?></dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide surface-muted">Entrada</dt>
                            <dd class="text-sm"><?= format_date($equipment['entry_date']); ?></dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide surface-muted">Lote</dt>
                            <dd class="text-sm"><?= sanitize($equipment['batch']); ?></dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide surface-muted">Status</dt>
                            <dd class="text-sm"><?= sanitize($equipment['status']); ?></dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide surface-muted">Cliente atual</dt>
                            <dd class="text-sm"><?= sanitize($equipment['cliente_nome'] ?? '-'); ?></dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide surface-muted">Código do cliente</dt>
                            <dd class="text-sm"><?= sanitize($equipment['client_code'] ?? '-'); ?></dd>
                        </div>
                    </dl>
                    <?php if ($equipment['notes']): ?>
                        <div class="mt-4 rounded-2xl border border-slate-700/50 bg-slate-950/60 px-4 py-3 text-sm surface-muted">
                            <?= nl2br(sanitize($equipment['notes'])); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="surface-card">
                    <h2 class="surface-heading">Histórico</h2>
                    <div class="mt-4 space-y-4">
                        <?php if (!$history): ?>
                            <p class="text-sm surface-muted">Sem movimentações registradas.</p>
                        <?php endif; ?>
                        <?php foreach ($history as $item): ?>
                            <div class="relative rounded-2xl border border-slate-800/60 bg-slate-950/60 p-4 pl-8">
                                <span class="absolute left-3 top-4 h-2 w-2 rounded-full bg-blue-400"></span>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="font-semibold"><?= sanitize($item['operation_type']); ?></span>
                                    <span class="surface-muted"><?= format_datetime($item['operation_date']); ?></span>
                                </div>
                                <p class="mt-1 text-sm surface-muted">Responsável: <?= sanitize($item['usuario'] ?? '-'); ?></p>
                                <?php if ($item['cliente']): ?>
                                    <p class="text-sm surface-muted">Cliente: <?= sanitize($item['cliente']); ?></p>
                                <?php endif; ?>
                                <?php if ($item['operation_type'] === 'RETORNO'): ?>
                                    <p class="text-xs surface-muted mt-2">
                                        Acessórios:
                                        Fonte <?= $item['accessories_power'] ? 'Sim' : 'Não'; ?> ·
                                        HDMI <?= $item['accessories_hdmi'] ? 'Sim' : 'Não'; ?> ·
                                        Controle <?= $item['accessories_remote'] ? 'Sim' : 'Não'; ?>
                                    </p>
                                    <p class="text-xs surface-muted">Condição após retorno: <?= sanitize($item['condition_after_return'] ?? '-'); ?></p>
                                <?php endif; ?>
                                <?php if ($item['notes']): ?>
                                    <p class="mt-2 text-xs surface-muted">Obs: <?= sanitize($item['notes']); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="space-y-6">
                <?php if (user_has_role(['admin', 'gestor'])): ?>
                    <div class="surface-card">
                        <h2 class="surface-heading">Atualizar status</h2>
                        <form method="post" class="mt-4 space-y-4">
                            <input type="hidden" name="csrf_token" value="<?= sanitize(ensure_csrf_token()); ?>">
                            <input type="hidden" name="action" value="update_status">
                            <select name="status" class="surface-select">
                                <option value="em_estoque" <?= $equipment['status'] === 'em_estoque' ? 'selected' : ''; ?>>Em estoque</option>
                                <option value="alocado" <?= $equipment['status'] === 'alocado' ? 'selected' : ''; ?>>Alocado</option>
                                <option value="manutencao" <?= $equipment['status'] === 'manutencao' ? 'selected' : ''; ?>>Manutenção</option>
                                <option value="baixado" <?= $equipment['status'] === 'baixado' ? 'selected' : ''; ?>>Baixado</option>
                            </select>
                            <button type="submit" class="w-full rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Salvar</button>
                        </form>
                    </div>
                <?php endif; ?>
                <?php if (user_has_role('admin')): ?>
                    <div class="rounded-3xl border border-red-500/40 bg-red-500/10 p-6 shadow-xl shadow-red-900/20">
                        <h2 class="text-lg font-semibold text-red-100">Excluir equipamento</h2>
                        <p class="text-sm text-red-100/70">Atenção: esta ação é irreversível e removerá o equipamento e registros relacionados.</p>
                        <form method="post" onsubmit="return confirm('Confirma exclusão deste equipamento? Esta ação não pode ser desfeita.');" class="mt-4">
                            <input type="hidden" name="csrf_token" value="<?= sanitize(ensure_csrf_token()); ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="w-full rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500">Excluir equipamento</button>
                        </form>
                    </div>
                <?php endif; ?>
                <div class="surface-card">
                    <h2 class="surface-heading">Anotações</h2>
                    <form method="post" class="mt-4 space-y-3">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(ensure_csrf_token()); ?>">
                        <input type="hidden" name="action" value="add_note">
                        <textarea id="equipmentNote" name="note" rows="3" class="surface-field" placeholder="Registre observações importantes..." data-equipment-id="<?= (int) $equipmentId; ?>"></textarea>
                        <button type="submit" class="w-full rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">Adicionar anotação</button>
                    </form>
                    <div class="mt-4 space-y-3 text-sm">
                        <?php if (!$notes): ?>
                            <p class="text-sm surface-muted">Nenhuma anotação registrada.</p>
                        <?php endif; ?>
                        <?php foreach ($notes as $note): ?>
                            <div class="rounded-2xl border border-slate-800/50 bg-slate-950/60 px-3 py-2">
                                <p><?= nl2br(sanitize($note['note'])); ?></p>
                                <p class="mt-1 text-xs surface-muted">Por <?= sanitize($note['name']); ?> em <?= format_datetime($note['created_at']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
<?php
$footerScripts = <<<HTML
<script>
    (function () {
        const note = document.getElementById('equipmentNote');
        if (!note) return;
        const equipmentId = note.dataset.equipmentId || 'default';
        const key = 'equipmentNoteDraft:' + equipmentId;
        const saved = localStorage.getItem(key);
        if (saved && note.value.trim() === '') {
            note.value = saved;
        }
        let timer;
        note.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => {
                localStorage.setItem(key, note.value);
            }, 300);
        });
        note.form?.addEventListener('submit', () => {
            localStorage.removeItem(key);
        });
    })();
</script>
HTML;
include __DIR__ . '/../templates/footer.php';




