<?php

declare(strict_types=1);

?>
<div class="md:hidden w-full" x-data="{ open: false }">
    <div class="surface-topbar px-4 py-3 md:hidden">
        <div>
            <p class="text-sm uppercase tracking-wide text-slate-400">Menu</p>
            <p class="font-semibold text-lg"><?= sanitize(APP_NAME); ?></p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button"
                    class="surface-icon-button h-9 w-9"
                    x-data="{
                        get theme() { return $store.uiTheme.theme; },
                        toggle() { $store.uiTheme.toggle(); }
                    }"
                    @click.stop="toggle()"
                    x-bind:aria-label="theme === 'dark' ? 'Ativar modo claro' : 'Ativar modo escuro'"
                    x-bind:title="theme === 'dark' ? 'Ativar modo claro' : 'Ativar modo escuro'">
                <span class="material-icons-outlined text-base" x-show="theme === 'dark'" x-cloak>light_mode</span>
                <span class="material-icons-outlined text-base" x-show="theme === 'light'" x-cloak>dark_mode</span>
            </button>
            <button type="button" class="surface-icon-button h-10 w-10" @click="open = !open">
            <span class="material-icons-outlined" x-show="!open">menu</span>
            <span class="material-icons-outlined" x-show="open">close</span>
            </button>
        </div>
    </div>
    <div class="surface-panel border-t surface-divider" x-show="open" x-cloak>
        <nav class="px-4 py-3 space-y-2 text-sm">
            <a href="dashboard.php" class="block rounded-lg px-3 py-2 text-slate-300 transition hover:bg-blue-500/10 hover:text-blue-500">Dashboard</a>
            <a href="equipamentos.php" class="block rounded-lg px-3 py-2 text-slate-300 transition hover:bg-blue-500/10 hover:text-blue-500">Equipamentos</a>
            <a href="entrada_cadastrar.php" class="block rounded-lg px-3 py-2 text-slate-300 transition hover:bg-blue-500/10 hover:text-blue-500">Entradas</a>
            <a href="saida_registrar.php" class="block rounded-lg px-3 py-2 text-slate-300 transition hover:bg-blue-500/10 hover:text-blue-500">Saídas</a>
            <a href="retornos.php" class="block rounded-lg px-3 py-2 text-slate-300 transition hover:bg-blue-500/10 hover:text-blue-500">Retornos</a>
            <a href="clientes.php" class="block rounded-lg px-3 py-2 text-slate-300 transition hover:bg-blue-500/10 hover:text-blue-500">Clientes</a>
            <a href="relatorios.php" class="block rounded-lg px-3 py-2 text-slate-300 transition hover:bg-blue-500/10 hover:text-blue-500">Relatórios</a>
            <a href="configuracoes.php" class="block rounded-lg px-3 py-2 text-slate-300 transition hover:bg-blue-500/10 hover:text-blue-500">Configurações</a>
            <?php if (user_has_role('admin')): ?>
                <a href="usuarios.php" class="block rounded-lg px-3 py-2 text-slate-300 transition hover:bg-blue-500/10 hover:text-blue-500">Usuários</a>
            <?php endif; ?>
            <a href="perfil.php" class="block rounded-lg px-3 py-2 text-slate-300 transition hover:bg-blue-500/10 hover:text-blue-500">Meu Perfil</a>
            <form action="logout.php" method="post" class="pt-2">
                <input type="hidden" name="csrf_token" value="<?= sanitize(ensure_csrf_token()); ?>">
                <button type="submit" class="w-full text-left text-sm text-red-300 transition hover:text-red-400">Sair</button>
            </form>
        </nav>
    </div>
</div>
