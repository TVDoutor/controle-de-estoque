<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login('admin');

$pageTitle = 'Usuários';
$activeMenu = 'usuarios';
$showDensityToggle = true;
$pdo = get_pdo();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sessão expirada. Recarregue a página.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role = $_POST['role'] ?? 'usuario';
            $password = $_POST['password'] ?? '';

            if ($name === '' || $email === '' || $password === '') {
                $errors[] = 'Preencha todos os campos obrigatrios.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'E-mail inválido.';
            } elseif (!in_array($role, ['admin', 'gestor', 'usuario'], true)) {
                $errors[] = 'Perfil inválido.';
            } else {
                try {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :hash, :role)');
                    $stmt->execute([
                        'name' => $name,
                        'email' => $email,
                        'hash' => $hash,
                        'role' => $role,
                    ]);
                    flash('success', 'Usuário criado com sucesso.', 'success');
                    redirect('usuarios.php');
                } catch (PDOException $exception) {
                    if ((int) $exception->errorInfo[1] === 1062) {
                        $errors[] = 'J existe um usuário com este e-mail.';
                    } else {
                        $errors[] = 'Erro ao salvar usuário.';
                    }
                }
            }
        } elseif ($action === 'delete') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            $current = current_user();
            if ($userId === 0) {
                $errors[] = 'Usuário inválido.';
            } elseif ($userId === $current['id']) {
                $errors[] = 'Você não pode remover a si mesmo.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $userId]);
                flash('success', 'Usuário removido com sucesso.', 'success');
                redirect('usuarios.php');
            }
        } elseif ($action === 'reset_password') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            $newPassword = $_POST['new_password'] ?? '';
            if ($userId === 0 || $newPassword === '') {
                $errors[] = 'Informe uma nova senha.';
            } else {
                $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
                $stmt->execute(['hash' => $hash, 'id' => $userId]);
                flash('success', 'Senha atualizada.', 'success');
                redirect('usuarios.php');
            }
        }
    }
}

$sort = $_GET['sort'] ?? 'name';
$dir = strtolower((string) ($_GET['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
$sortMap = [
    'name' => 'name',
    'email' => 'email',
    'role' => 'role',
    'last_login' => 'last_login',
];
$orderBy = $sortMap[$sort] ?? 'name';
$stmt = $pdo->prepare(sprintf('SELECT id, name, email, role, is_active, last_login, created_at FROM users ORDER BY %s %s', $orderBy, strtoupper($dir)));
$stmt->execute();
$users = $stmt->fetchAll();

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

        <div class="grid gap-6 md:grid-cols-2">
            <div class="surface-card">
                <h2 class="surface-heading">Criar novo usuário</h2>
                <form action="" method="post" class="mt-4 space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(ensure_csrf_token()); ?>">
                    <input type="hidden" name="action" value="create">
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="name">Nome</label>
                        <input type="text" id="name" name="name" required class="surface-field">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="email">E-mail</label>
                        <input type="email" id="email" name="email" required class="surface-field">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="role">Perfil</label>
                        <select id="role" name="role" class="surface-select">
                            <option value="gestor">Gestor</option>
                            <option value="usuario">Usuário</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="password">Senha temporária</label>
                        <input type="password" id="password" name="password" required class="surface-field">
                        <div class="mt-2 h-1 w-full rounded-full bg-slate-800">
                            <div id="passwordStrengthBar" class="h-1 w-1/4 rounded-full bg-red-500"></div>
                        </div>
                        <p id="passwordStrengthText" class="mt-1 text-xs surface-muted">Força da senha: fraca</p>
                        <p class="mt-1 text-xs surface-muted">Use pelo menos 8 caracteres com letras e números.</p>
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">Salvar usuário</button>
                </form>
            </div>
            <div class="surface-card">
                <h2 class="surface-heading">Usuários cadastrados</h2>
                <div class="mt-4 overflow-x-auto surface-table-wrapper">
                    <table class="min-w-full text-left text-sm">
                        <thead class="surface-table-head">
                            <tr>
                                <th class="surface-table-cell">
                                    <a href="<?= sanitize($buildSortLink('name')); ?>" class="inline-flex items-center gap-2">
                                        Nome <span class="text-slate-400"><?= sanitize($sortIndicator('name')); ?></span>
                                    </a>
                                </th>
                                <th class="surface-table-cell">
                                    <a href="<?= sanitize($buildSortLink('email')); ?>" class="inline-flex items-center gap-2">
                                        E-mail <span class="text-slate-400"><?= sanitize($sortIndicator('email')); ?></span>
                                    </a>
                                </th>
                                <th class="surface-table-cell">
                                    <a href="<?= sanitize($buildSortLink('role')); ?>" class="inline-flex items-center gap-2">
                                        Perfil <span class="text-slate-400"><?= sanitize($sortIndicator('role')); ?></span>
                                    </a>
                                </th>
                                <th class="surface-table-cell">
                                    <a href="<?= sanitize($buildSortLink('last_login')); ?>" class="inline-flex items-center gap-2">
                                        Último acesso <span class="text-slate-400"><?= sanitize($sortIndicator('last_login')); ?></span>
                                    </a>
                                </th>
                                <th class="surface-table-cell text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="surface-table-body">
                            <?php foreach ($users as $userRow): ?>
                                <tr>
                                    <td class="surface-table-cell font-medium"><?= sanitize($userRow['name']); ?></td>
                                    <td class="surface-table-cell surface-muted"><?= sanitize($userRow['email']); ?></td>
                                    <td class="surface-table-cell text-xs uppercase"><?= sanitize($userRow['role']); ?></td>
                                    <td class="surface-table-cell surface-muted"><?= format_datetime($userRow['last_login']); ?></td>
                                    <td class="surface-table-cell">
                                        <div class="flex justify-end gap-2 text-xs">
                                            <form method="post" class="inline-flex items-center gap-2" onsubmit="return confirm('Resetar a senha deste usuário?');">
                                                <input type="hidden" name="csrf_token" value="<?= sanitize(ensure_csrf_token()); ?>">
                                                <input type="hidden" name="action" value="reset_password">
                                                <input type="hidden" name="user_id" value="<?= (int) $userRow['id']; ?>">
                                                <input type="password" name="new_password" placeholder="Nova senha" class="surface-field-compact">
                                                <button type="submit" class="rounded-lg bg-slate-700 px-2 py-1 text-white hover:bg-slate-600">Resetar</button>
                                            </form>
                                            <?php if ($userRow['id'] !== current_user()['id']): ?>
                                                <form method="post" onsubmit="return confirm('Deseja remover este usuário?');">
                                                    <input type="hidden" name="csrf_token" value="<?= sanitize(ensure_csrf_token()); ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="user_id" value="<?= (int) $userRow['id']; ?>">
                                                    <button type="submit" class="rounded-lg bg-red-600 px-2 py-1 text-white hover:bg-red-500">Excluir</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
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
        const input = document.getElementById('password');
        const bar = document.getElementById('passwordStrengthBar');
        const text = document.getElementById('passwordStrengthText');
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


