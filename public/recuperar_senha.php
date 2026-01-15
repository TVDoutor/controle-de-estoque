<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

session_bootstrap();

if (current_user()) {
    redirect('dashboard.php');
}

$pageTitle = 'Recuperar senha';
$errors = [];
$feedback = flash('auth_notice');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sessão expirada. Atualize a página e tente novamente.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));

        if ($email === '') {
            $errors[] = 'Informe o e-mail cadastrado.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Informe um e-mail válido.';
        } else {
            $pdo = get_pdo();
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $email]);
            $userId = $stmt->fetchColumn();

            if ($userId) {
                error_log(sprintf('[Recuperação de senha] Usuário %d solicitou redefinição em %s', $userId, date('c')));
            }

            flash('auth_notice', 'Se o e-mail informado estiver cadastrado, você receberá em instantes as instruções para redefinir a senha.');
            redirect('recuperar_senha.php');
        }
    }
}

$csrfToken = ensure_csrf_token();
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
            <h1 class="text-2xl font-semibold text-slate-100"><?= sanitize(APP_NAME); ?></h1>
            <p class="text-sm text-slate-400">Informe o e-mail cadastrado para receber o link de redefinição de senha.</p>
            <p class="text-xs text-slate-400">O envio pode levar até 5 minutos. Verifique também a pasta de spam/lixo eletrônico.</p>
        </div>

        <?php if ($feedback): ?>
            <div class="rounded-xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
                <?= sanitize($feedback['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="rounded-xl border border-red-500/50 bg-red-500/10 px-4 py-3 text-sm text-red-200 space-y-1">
                <?php foreach ($errors as $error): ?>
                    <p><?= sanitize($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken); ?>">
            <div>
                <label for="email" class="text-sm font-medium text-slate-200">E-mail cadastrado</label>
                <input type="email"
                       id="email"
                       name="email"
                       required
                       value="<?= sanitize($_POST['email'] ?? ''); ?>"
                       class="surface-field"
                       placeholder="seuemail@empresa.com">
            </div>
            <button type="submit" class="w-full rounded-lg bg-blue-500 px-4 py-3 font-semibold text-white transition hover:bg-blue-600">Enviar instruções</button>
        </form>

        <div class="flex flex-col gap-2 text-center text-sm text-slate-300">
            <a href="login.php" class="hover:text-blue-300">Voltar para a tela de login</a>
            <a href="mailto:suporte@<?= sanitize(parse_url(BASE_URL, PHP_URL_HOST) ?? 'empresa.com'); ?>" class="hover:text-blue-300">Precisa de ajuda? Fale com o suporte</a>
        </div>
    </div>
</body>
</html>
