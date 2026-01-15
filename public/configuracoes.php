<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login(['admin', 'gestor']);

$pageTitle = 'Configurações';
$activeMenu = 'configuracoes';
$pdo = get_pdo();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sessão expirada. Recarregue a página.';
    } else {
        $action = $_POST['action'] ?? 'create';
        if ($action === 'toggle_status') {
            $modelId = (int) ($_POST['model_id'] ?? 0);
            $isActive = isset($_POST['is_active']) && $_POST['is_active'] === '1';
            if ($modelId > 0) {
                $stmt = $pdo->prepare('UPDATE equipment_models SET is_active = :is_active WHERE id = :id');
                $stmt->execute([
                    'is_active' => $isActive ? 1 : 0,
                    'id' => $modelId,
                ]);
                flash('success', 'Status atualizado.', 'success');
                redirect('configuracoes.php');
            }
            $errors[] = 'Modelo inválido.';
        } else {
            $brand = trim($_POST['brand'] ?? '');
            $modelName = trim($_POST['model_name'] ?? '');
            $category = $_POST['category'] ?? 'android_box';
            $monitorSize = trim($_POST['monitor_size'] ?? '');

            if ($brand === '' || $modelName === '') {
                $errors[] = 'Informe a marca e o modelo.';
            }

            if (!in_array($category, ['android_box', 'monitor', 'outro'], true)) {
                $category = 'android_box';
            }

            if (!$errors) {
                try {
                    $stmt = $pdo->prepare('INSERT INTO equipment_models (category, brand, model_name, monitor_size) VALUES (:category, :brand, :model_name, :monitor_size)');
                    $stmt->execute([
                        'category' => $category,
                        'brand' => $brand,
                        'model_name' => $modelName,
                        'monitor_size' => $monitorSize !== '' ? $monitorSize : null,
                    ]);
                    flash('success', 'Modelo cadastrado com sucesso.', 'success');
                    redirect('configuracoes.php');
                } catch (PDOException $exception) {
                    if ((int) $exception->errorInfo[1] === 1062) {
                        $errors[] = 'Este modelo já está cadastrado.';
                    } else {
                        $errors[] = 'Erro ao salvar modelo.';
                    }
                }
            }
        }
    }
}

$models = $pdo->query('SELECT * FROM equipment_models ORDER BY category, brand, model_name')->fetchAll();

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

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-1 surface-card">
                <h2 class="surface-heading">Adicionar modelo</h2>
                <form method="post" class="mt-4 space-y-4 text-sm" id="modelForm">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(ensure_csrf_token()); ?>">
                    <input type="hidden" name="action" value="create">
                    <div>
                        <label class="text-xs font-medium text-slate-300" for="category">Categoria</label>
                        <select id="category" name="category" class="surface-select">
                            <option value="android_box">Android Player</option>
                            <option value="monitor">Monitor</option>
                            <option value="outro">Outros</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-300" for="brand">Marca</label>
                        <input type="text" id="brand" name="brand" required class="surface-field">
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-300" for="model_name">Modelo</label>
                        <input type="text" id="model_name" name="model_name" required class="surface-field">
                    </div>
                    <div id="monitorSizeField">
                        <label class="text-xs font-medium text-slate-300" for="monitor_size">Polegadas (para monitores)</label>
                        <input type="text" id="monitor_size" name="monitor_size" class="surface-field" placeholder="Ex.: 42">
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-500">Salvar modelo</button>
                </form>
            </div>
            <div class="lg:col-span-2 surface-card">
                <h2 class="surface-heading">Modelos cadastrados</h2>
                <div class="mt-4 overflow-x-auto surface-table-wrapper">
                    <table class="min-w-full text-sm">
                        <thead class="surface-table-head">
                            <tr>
                                <th class="surface-table-cell text-left">Categoria</th>
                                <th class="surface-table-cell text-left">Marca</th>
                                <th class="surface-table-cell text-left">Modelo</th>
                                <th class="surface-table-cell text-left">Polegadas</th>
                                <th class="surface-table-cell text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody class="surface-table-body">
                            <?php if (!$models): ?>
                                <tr>
                                    <td colspan="5" class="surface-table-cell text-center surface-muted">Nenhum modelo cadastrado.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($models as $model): ?>
                                <tr>
                                    <td class="surface-table-cell"><?= sanitize($model['category']); ?></td>
                                    <td class="surface-table-cell"><?= sanitize($model['brand']); ?></td>
                                    <td class="surface-table-cell"><?= sanitize($model['model_name']); ?></td>
                                    <td class="surface-table-cell"><?= sanitize($model['monitor_size'] ?? '-'); ?></td>
                                    <td class="surface-table-cell">
                                        <form method="post" class="inline-flex items-center gap-2">
                                            <input type="hidden" name="csrf_token" value="<?= sanitize(ensure_csrf_token()); ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="model_id" value="<?= (int) $model['id']; ?>">
                                            <input type="hidden" name="is_active" value="<?= $model['is_active'] ? '0' : '1'; ?>">
                                            <button type="submit" class="rounded-full px-3 py-1 text-xs font-semibold <?= $model['is_active'] ? 'bg-emerald-500/20 text-emerald-200' : 'bg-slate-500/20 text-slate-200'; ?>">
                                                <?= $model['is_active'] ? 'Ativo' : 'Inativo'; ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
<?php
$footerScripts = <<<HTML
<script>
    (function () {
        const category = document.getElementById('category');
        const monitorField = document.getElementById('monitorSizeField');
        if (!category || !monitorField) return;
        const update = () => {
            const show = category.value === 'monitor';
            monitorField.style.display = show ? '' : 'none';
        };
        category.addEventListener('change', update);
        update();
    })();
</script>
HTML;
include __DIR__ . '/../templates/footer.php';


