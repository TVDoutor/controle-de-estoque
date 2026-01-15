<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login('admin');

$pageTitle = 'Usuários';
$activeMenu = 'usuarios';
$pdo = get_pdo();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sesso expirada. Recarregue a pgina.';
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
                $errors[] = 'E-mail invlido.';
            } elseif (!in_array($role, ['admin', 'gestor', 'usuario'], true)) {
                $errors[] = 'Perfil invlido.';
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
                $errors[] = 'Usuário invlido.';
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

$stmt = $pdo->query('SELECT id, name, email, role, is_active, last_login, created_at FROM users ORDER BY name');
$users = $stmt->fetchAll();

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>
<main class="flex-1 flex flex-col bg-slate-950 text-slate-100">
    <?php include __DIR__ . '/../templates/topbar.php'; ?>
    <section class="flex-1 overflow-y-auto px-6 pb-12 space-y-6">
        <?php if ($flash = flash('success')): ?>
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                <?= sanitize($flash['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <ul class="list-disc space-y-1 pl-5">
                    <?php foreach ($errors as $error): ?>
                        <li><?= sanitize($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="grid gap-6 md:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-800">Criar novo usuário</h2>
                <form action="" method="post" class="mt-4 space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(ensure_csrf_token()); ?>">
                    <input type="hidden" name="action" value="create">
                    <div>
                        <label class="text-sm font-medium text-slate-600" for="name">Nome</label>
                        <input type="text" id="name" name="name" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-600" for="email">E-mail</label>
                        <input type="email" id="email" name="email" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-600" for="role">Perfil</label>
                        <select id="role" name="role" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                            <option value="gestor">Gestor</option>
                            <option value="usuario">Usuário</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-600" for="password">Senha temporria</label>
                        <input type="password" id="password" name="password" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <p class="mt-1 text-xs text-slate-500">Recomende ao usuário alterar a senha no primeiro acesso.</p>
                    </div>
                    <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Salvar usuário</button>
                </form>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-800">Usuários cadastrados</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2">Nome</th>
                                <th class="px-3 py-2">E-mail</th>
                                <th class="px-3 py-2">Perfil</th>
                                <th class="px-3 py-2">Último acesso</th>
                                <th class="px-3 py-2 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($users as $userRow): ?>
                                <tr>
                                    <td class="px-3 py-2 font-medium text-slate-700"><?= sanitize($userRow['name']); ?></td>
                                    <td class="px-3 py-2 text-slate-500"><?= sanitize($userRow['email']); ?></td>
                                    <td class="px-3 py-2 text-slate-500 text-xs uppercase"><?= sanitize($userRow['role']); ?></td>
                                    <td class="px-3 py-2 text-slate-500"><?= format_datetime($userRow['last_login']); ?></td>
                                    <td class="px-3 py-2">
                                        <div class="flex justify-end gap-2 text-xs">
                                            <form method="post" class="inline-flex items-center gap-2">
                                                <input type="hidden" name="csrf_token" value="<?= sanitize(ensure_csrf_token()); ?>">
                                                <input type="hidden" name="action" value="reset_password">
                                                <input type="hidden" name="user_id" value="<?= (int) $userRow['id']; ?>">
                                                <input type="password" name="new_password" placeholder="Nova senha" class="rounded border border-slate-300 px-2 py-1 text-xs focus:border-blue-500 focus:outline-none">
                                                <button type="submit" class="rounded bg-slate-200 px-2 py-1 text-slate-700 hover:bg-slate-300">Resetar</button>
                                            </form>
                                            <?php if ($userRow['id'] !== current_user()['id']): ?>
                                                <form method="post" onsubmit="return confirm('Deseja remover este usuário?');">
                                                    <input type="hidden" name="csrf_token" value="<?= sanitize(ensure_csrf_token()); ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="user_id" value="<?= (int) $userRow['id']; ?>">
                                                    <button type="submit" class="rounded bg-red-500 px-2 py-1 text-white hover:bg-red-600">Excluir</button>
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
<?php include __DIR__ . '/../templates/footer.php';


