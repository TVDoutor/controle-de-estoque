<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

session_bootstrap();
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $usuario = current_user();
        logout();
        $nomeUsuario = $usuario['name'] ?? '';
        $mensagem = 'Até breve! Você saiu da conta com segurança.';
        if ($nomeUsuario !== '') {
            $mensagem = sprintf('Até breve, %s! Você saiu da conta com segurança.', $nomeUsuario);
        }
        flash('auth_notice', $mensagem, 'success');
        redirect('login.php');
    }

    flash('auth_notice', 'Não foi possível encerrar a sessão. Tente novamente.', 'error');
    redirect('logout.php');
}

$user = current_user();
$csrfToken = ensure_csrf_token();
$pageTitle = 'Encerrar sessão';
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= sanitize($pageTitle) . ' - ' . sanitize(APP_NAME); ?></title>
    <?php require __DIR__ . '/../templates/theme-resources.php'; ?>
</head>
<body class="antialiased min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-lg surface-panel p-10 shadow-2xl space-y-6">
        <div class="flex justify-end"
             x-data="{
                 get theme() { return $store.uiTheme.theme; },
                 toggle() { $store.uiTheme.toggle(); }
             }">
            <button type="button"
                    class="surface-icon-button h-9 w-9"
                    @click="toggle()"
                    x-bind:aria-label="theme === 'dark' ? 'Ativar modo claro' : 'Ativar modo escuro'"
                    x-bind:title="theme === 'dark' ? 'Ativar modo claro' : 'Ativar modo escuro'">
                <span class="material-icons-outlined text-base" x-show="theme === 'dark'" x-cloak>light_mode</span>
                <span class="material-icons-outlined text-base" x-show="theme === 'light'" x-cloak>dark_mode</span>
            </button>
        </div>

        <div class="text-center space-y-2">
            <h1 class="text-2xl font-semibold text-slate-100"><?= sanitize($pageTitle); ?></h1>
            <p class="text-sm text-slate-400">Tem certeza de que deseja sair da sua conta?</p>
        </div>

        <div class="surface-card-tight space-y-2 text-sm text-slate-300">
            <p class="text-slate-200 font-medium">Usuário conectado</p>
            <p class="mt-1 text-base text-slate-100"><?= sanitize($user['name'] ?? ''); ?></p>
            <p class="text-slate-400"><?= sanitize($user['email'] ?? ''); ?></p>
        </div>

        <form method="post" class="flex flex-col gap-4">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken); ?>">
            <button type="submit" class="w-full rounded-lg bg-red-500 px-4 py-3 font-semibold text-white transition hover:bg-red-600">Sim, encerrar sessão</button>
            <a href="dashboard.php" class="w-full text-center rounded-lg border surface-divider px-4 py-3 text-sm text-slate-300 transition hover:border-blue-400 hover:text-blue-300">Cancelar e voltar ao sistema</a>
        </form>
    </div>
</body>
</html>
