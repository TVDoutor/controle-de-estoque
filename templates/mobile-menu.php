<?php

declare(strict_types=1);

?>
<?php
if (!isset($navigation)) {
    $navigation = [
        ['label' => 'Dashboard', 'path' => 'dashboard.php', 'icon' => 'space_dashboard', 'key' => 'dashboard'],
        ['label' => 'Equipamentos', 'path' => 'equipamentos.php', 'icon' => 'inventory_2', 'key' => 'equipamentos'],
        ['label' => 'Clientes', 'path' => 'clientes.php', 'icon' => 'group', 'key' => 'clientes'],
        ['label' => 'Relatorios', 'path' => 'relatorios.php', 'icon' => 'bar_chart', 'key' => 'relatorios'],
        ['label' => 'Configuracoes', 'path' => 'configuracoes.php', 'icon' => 'settings', 'key' => 'configuracoes'],
    ];

    if (user_has_role('admin')) {
        array_unshift($navigation, ['label' => 'Admin', 'path' => 'admin_dashboard.php', 'icon' => 'admin_panel_settings', 'key' => 'admin_dashboard']);
        $navigation[] = ['label' => 'Usuarios', 'path' => 'usuarios.php', 'icon' => 'manage_accounts', 'key' => 'usuarios'];
    }
}
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
            <?php foreach ($navigation as $item): ?>
                <?php $isActive = ($activeMenu ?? '') === $item['key']; ?>
                <a href="<?= sanitize($item['path']); ?>"
                   class="flex items-center gap-2 rounded-lg px-3 py-2 transition <?= $isActive ? 'bg-blue-500/15 text-blue-200' : 'text-slate-300 hover:bg-blue-500/10 hover:text-blue-500'; ?>">
                    <span class="material-icons-outlined text-base"><?= sanitize($item['icon']); ?></span>
                    <span><?= sanitize($item['label']); ?></span>
                </a>
            <?php endforeach; ?>
            <a href="perfil.php" class="flex items-center gap-2 rounded-lg px-3 py-2 text-slate-300 transition hover:bg-blue-500/10 hover:text-blue-500">
                <span class="material-icons-outlined text-base">account_circle</span>
                <span>Meu Perfil</span>
            </a>
            <form action="logout.php" method="post" class="pt-2">
                <input type="hidden" name="csrf_token" value="<?= sanitize(ensure_csrf_token()); ?>">
                <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-red-300 transition hover:text-red-400">
                    <span class="material-icons-outlined text-base">logout</span>
                    <span>Sair</span>
                </button>
            </form>
        </nav>
    </div>
</div>
