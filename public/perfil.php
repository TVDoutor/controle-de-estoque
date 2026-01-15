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

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="surface-card">
                <h2 class="surface-heading">Dados pessoais</h2>
                <form method="post" class="mt-4 space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(ensure_csrf_token()); ?>">
                    <input type="hidden" name="action" value="update_profile">
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="name">Nome completo</label>
                        <input type="text" id="name" name="name" required value="<?= sanitize($user['name']); ?>" class="surface-field">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="email">E-mail</label>
                        <input type="email" id="email" value="<?= sanitize($user['email']); ?>" disabled class="mt-1 w-full rounded-lg border border-slate-800 bg-slate-900/60 px-3 py-2 text-sm text-slate-400">
                        <p class="mt-1 text-xs surface-muted">Entre em contato com o administrador para alterar seu e-mail.</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="phone">Telefone</label>
                        <input type="text" id="phone" name="phone" value="<?= sanitize($user['phone'] ?? ''); ?>" class="surface-field" data-mask="phone" inputmode="tel" placeholder="(00) 00000-0000">
                    </div>
                    <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">Salvar alterações</button>
                </form>
            </div>
            <div class="surface-card">
                <h2 class="surface-heading">Alterar senha</h2>
                <form method="post" class="mt-4 space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(ensure_csrf_token()); ?>">
                    <input type="hidden" name="action" value="change_password">
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="current_password">Senha atual</label>
                        <input type="password" id="current_password" name="current_password" required class="surface-field">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="new_password">Nova senha</label>
                        <input type="password" id="new_password" name="new_password" required class="surface-field">
                        <div class="mt-2 h-1 w-full rounded-full bg-slate-800">
                            <div id="profilePasswordStrengthBar" class="h-1 w-1/4 rounded-full bg-red-500"></div>
                        </div>
                        <p id="profilePasswordStrengthText" class="mt-1 text-xs surface-muted">Força da senha: fraca</p>
                        <p class="mt-1 text-xs surface-muted">Use pelo menos 8 caracteres com letras e números.</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="confirm_password">Confirmar nova senha</label>
                        <input type="password" id="confirm_password" name="confirm_password" required class="surface-field">
                    </div>
                    <button type="submit" class="rounded-xl bg-slate-700 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-600">Atualizar senha</button>
                </form>
            </div>
        </div>
    </section>
<?php
$footerScripts = <<<HTML
<script>
    (function () {
        const input = document.getElementById('new_password');
        const bar = document.getElementById('profilePasswordStrengthBar');
        const text = document.getElementById('profilePasswordStrengthText');
        if (!input || !bar || !text) {
            return;
        }
        const update = () => {
            const value = input.value || '';
            let score = 0;
            if (value.length >= 8) score += 1;
            if (/[A-Z]/.test(value)) score += 1;
            if (/[0-9]/.test(value)) score += 1;
            if (/[^A-Za-z0-9]/.test(value)) score += 1;
            const map = [
                { label: 'fraca', width: '25%', color: 'bg-red-500' },
                { label: 'razoável', width: '50%', color: 'bg-amber-400' },
                { label: 'boa', width: '75%', color: 'bg-blue-500' },
                { label: 'forte', width: '100%', color: 'bg-emerald-500' }
            ];
            const current = map[Math.min(score, map.length - 1)];
            bar.className = 'h-1 rounded-full ' + current.color;
            bar.style.width = current.width;
            text.textContent = 'Força da senha: ' + current.label;
        };
        input.addEventListener('input', update);
        update();
    })();
</script>
HTML;
include __DIR__ . '/../templates/footer.php';


