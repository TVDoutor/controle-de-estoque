<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login();

$pageTitle = 'Meu Perfil';
$activeMenu = 'configuracoes';
$user = current_user();
$pdo = get_pdo();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sessão expirada. Recarregue a página.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'update_profile') {
            $name = trim($_POST['name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            if ($name === '') {
                $errors[] = 'Informe seu nome completo.';
            } else {
                $stmt = $pdo->prepare('UPDATE users SET name = :name, phone = :phone, updated_at = NOW() WHERE id = :id');
                $stmt->execute([
                    'name' => $name,
                    'phone' => $phone ?: null,
                    'id' => $user['id'],
                ]);
                flash('success', 'Perfil atualizado com sucesso.', 'success');
                redirect('perfil.php');
            }
        } elseif ($action === 'change_password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if ($newPassword === '' || $confirmPassword === '' || $currentPassword === '') {
                $errors[] = 'Preencha todos os campos de senha.';
            } elseif ($newPassword !== $confirmPassword) {
                $errors[] = 'As senhas novas não coincidem.';
            } else {
                $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
                $stmt->execute(['id' => $user['id']]);
                $hash = $stmt->fetchColumn();
                if (!$hash || !password_verify($currentPassword, $hash)) {
                    $errors[] = 'Senha atual incorreta.';
                } else {
                    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')->execute([
                        'hash' => $newHash,
                        'id' => $user['id'],
                    ]);
                    flash('success', 'Senha atualizada com sucesso.', 'success');
                    redirect('perfil.php');
                }
            }
        }
    }
}

$user = current_user();

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

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-800">Dados pessoais</h2>
                <form method="post" class="mt-4 space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(ensure_csrf_token()); ?>">
                    <input type="hidden" name="action" value="update_profile">
                    <div>
                        <label class="text-sm font-medium text-slate-600" for="name">Nome completo</label>
                        <input type="text" id="name" name="name" required value="<?= sanitize($user['name']); ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-600" for="email">E-mail</label>
                        <input type="email" id="email" value="<?= sanitize($user['email']); ?>" disabled class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-sm text-slate-500">
                        <p class="mt-1 text-xs text-slate-500">Entre em contato com o administrador para alterar seu e-mail.</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-600" for="phone">Telefone</label>
                        <input type="text" id="phone" name="phone" value="<?= sanitize($user['phone'] ?? ''); ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    </div>
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Salvar alterações</button>
                </form>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-800">Alterar senha</h2>
                <form method="post" class="mt-4 space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(ensure_csrf_token()); ?>">
                    <input type="hidden" name="action" value="change_password">
                    <div>
                        <label class="text-sm font-medium text-slate-600" for="current_password">Senha atual</label>
                        <input type="password" id="current_password" name="current_password" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-600" for="new_password">Nova senha</label>
                        <input type="password" id="new_password" name="new_password" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-600" for="confirm_password">Confirmar nova senha</label>
                        <input type="password" id="confirm_password" name="confirm_password" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    </div>
                    <button type="submit" class="rounded-lg bg-slate-700 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Atualizar senha</button>
                </form>
            </div>
        </div>
    </section>
<?php include __DIR__ . '/../templates/footer.php';


