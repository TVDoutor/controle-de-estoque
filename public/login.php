<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

session_bootstrap();

if (current_user()) {
    redirect('dashboard.php');
}

$pageTitle = 'Acessar conta';
$errors = [];
$feedback = flash('auth_notice');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sessão expirada. Atualize a página e tente novamente.';
    } elseif ($email === '' || $password === '') {
        $errors[] = 'Informe e-mail e senha.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'E-mail inválido.';
    } elseif (!login($email, $password)) {
        $errors[] = 'Credenciais inválidas.';
    } else {
        redirect('dashboard.php');
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
    <div class="w-full max-w-md surface-panel p-10 shadow-2xl space-y-6">
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
            <p class="text-sm text-slate-400">Faça login para acessar o painel de controle.</p>
        </div>

        <?php if ($feedback): ?>
            <?php $feedbackClass = ($feedback['type'] ?? 'info') === 'success' ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-200' : 'border-blue-500/40 bg-blue-500/10 text-blue-200'; ?>
            <div class="rounded-xl border <?= $feedbackClass; ?> px-4 py-3 text-sm">
                <?= sanitize($feedback['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="rounded-xl border border-red-500/60 bg-red-500/10 px-4 py-3 text-sm text-red-200 space-y-1">
                <?php foreach ($errors as $error): ?>
                    <p><?= sanitize($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken); ?>">
            <div>
                <label for="email" class="text-sm font-medium text-slate-200">E-mail</label>
                <input type="email"
                       id="email"
                       name="email"
                       required
                       value="<?= sanitize($_POST['email'] ?? ''); ?>"
                       class="surface-field"
                       autocomplete="email">
                <p id="loginEmailHint" class="mt-1 text-xs text-slate-400">Use o e-mail corporativo cadastrado.</p>
            </div>
            <div x-data="{ show: false }">
                <label for="password" class="text-sm font-medium text-slate-200">Senha</label>
                <div class="relative">
                    <input :type="show ? 'text' : 'password'"
                           id="password"
                           name="password"
                           required
                           class="surface-field pr-12"
                           autocomplete="current-password">
                    <button type="button"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-300 hover:text-slate-100"
                            @click="show = !show"
                            :aria-label="show ? 'Ocultar senha' : 'Mostrar senha'">
                        <span class="material-icons-outlined text-base" x-show="!show" x-cloak>visibility</span>
                        <span class="material-icons-outlined text-base" x-show="show" x-cloak>visibility_off</span>
                    </button>
                </div>
                <p id="loginPasswordHint" class="mt-1 text-xs text-slate-400">Senha mínima de 6 caracteres.</p>
            </div>
            <button type="submit" class="w-full rounded-lg bg-blue-500 px-4 py-3 font-semibold text-white transition hover:bg-blue-600">Entrar</button>
        </form>

        <div class="text-center text-sm">
            <a href="recuperar_senha.php" class="text-blue-300 hover:text-blue-200">Esqueci minha senha</a>
        </div>
    </div>
    <script>
        (function () {
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            const emailHint = document.getElementById('loginEmailHint');
            const passwordHint = document.getElementById('loginPasswordHint');
            if (!emailInput || !passwordInput || !emailHint || !passwordHint) {
                return;
            }
            const isValidEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
            const updateEmail = () => {
                if (emailInput.value === '') {
                    emailHint.textContent = 'Use o e-mail corporativo cadastrado.';
                    emailHint.className = 'mt-1 text-xs text-slate-400';
                    return;
                }
                if (isValidEmail(emailInput.value)) {
                    emailHint.textContent = 'E-mail válido.';
                    emailHint.className = 'mt-1 text-xs text-emerald-300';
                } else {
                    emailHint.textContent = 'E-mail inválido.';
                    emailHint.className = 'mt-1 text-xs text-red-300';
                }
            };
            const updatePassword = () => {
                if (passwordInput.value.length >= 6) {
                    passwordHint.textContent = 'Senha ok.';
                    passwordHint.className = 'mt-1 text-xs text-emerald-300';
                } else {
                    passwordHint.textContent = 'Senha mínima de 6 caracteres.';
                    passwordHint.className = 'mt-1 text-xs text-slate-400';
                }
            };
            emailInput.addEventListener('input', updateEmail);
            passwordInput.addEventListener('input', updatePassword);
            updateEmail();
            updatePassword();
        })();
    </script>
</body>
</html>
