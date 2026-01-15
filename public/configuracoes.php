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
        $errors[] = 'Sesso expirada. Recarregue a pgina.';
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
                    $errors[] = 'Este modelo j est cadastrado.';
                } else {
                    $errors[] = 'Erro ao salvar modelo.';
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

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-1 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-800">Adicionar modelo</h2>
                <form method="post" class="mt-4 space-y-4 text-sm">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(ensure_csrf_token()); ?>">
                    <div>
                        <label class="text-xs font-medium text-slate-600" for="category">Categoria</label>
                        <select id="category" name="category" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                            <option value="android_box">Android Player</option>
                            <option value="monitor">Monitor</option>
                            <option value="outro">Outros</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-600" for="brand">Marca</label>
                        <input type="text" id="brand" name="brand" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-600" for="model_name">Modelo</label>
                        <input type="text" id="model_name" name="model_name" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-600" for="monitor_size">Polegadas (para monitores)</label>
                        <input type="text" id="monitor_size" name="monitor_size" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-blue-500 focus:outline-none" placeholder="Ex.: 42">
                    </div>
                    <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700">Salvar modelo</button>
                </form>
            </div>
            <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-800">Modelos cadastrados</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-3 text-left">Categoria</th>
                                <th class="px-3 py-3 text-left">Marca</th>
                                <th class="px-3 py-3 text-left">Modelo</th>
                                <th class="px-3 py-3 text-left">Polegadas</th>
                                <th class="px-3 py-3 text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (!$models): ?>
                                <tr>
                                    <td colspan="5" class="px-3 py-4 text-center text-slate-500">Nenhum modelo cadastrado.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($models as $model): ?>
                                <tr>
                                    <td class="px-3 py-3 text-slate-600"><?= sanitize($model['category']); ?></td>
                                    <td class="px-3 py-3 text-slate-600"><?= sanitize($model['brand']); ?></td>
                                    <td class="px-3 py-3 text-slate-600"><?= sanitize($model['model_name']); ?></td>
                                    <td class="px-3 py-3 text-slate-600"><?= sanitize($model['monitor_size'] ?? '-'); ?></td>
                                    <td class="px-3 py-3 text-slate-600"><?= $model['is_active'] ? 'Ativo' : 'Inativo'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
<?php include __DIR__ . '/../templates/footer.php';


