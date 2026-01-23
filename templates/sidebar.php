<?php

declare(strict_types=1);

$activeMenu = $activeMenu ?? '';

if (!isset($user)) {
    if (!function_exists('current_user')) {
        require_once __DIR__ . '/../includes/auth.php';
    }
    session_bootstrap();
    $user = current_user();
}

$roleLabels = [
    'admin' => 'Administrador',
    'gestor' => 'Gestor',
    'usuario' => 'Usuario'
];

$navigation = [
    ['label' => 'Dashboard', 'path' => 'dashboard.php', 'icon' => 'space_dashboard', 'key' => 'dashboard'],
    ['label' => 'Equipamentos', 'path' => 'equipamentos.php', 'icon' => 'inventory_2', 'key' => 'equipamentos'],
    ['label' => 'Clientes', 'path' => 'clientes.php', 'icon' => 'group', 'key' => 'clientes'],
    ['label' => 'Relatorios', 'path' => 'relatorios.php', 'icon' => 'bar_chart', 'key' => 'relatorios'],
    ['label' => 'Configuracoes', 'path' => 'configuracoes.php', 'icon' => 'settings', 'key' => 'configuracoes'],
];

// Admin-only navigation entries
if (user_has_role('admin')) {
    // add admin dashboard link at the top
    array_unshift($navigation, ['label' => 'Admin', 'path' => 'admin_dashboard.php', 'icon' => 'admin_panel_settings', 'key' => 'admin_dashboard']);
    // add user management link
    $navigation[] = ['label' => 'Usuarios', 'path' => 'usuarios.php', 'icon' => 'manage_accounts', 'key' => 'usuarios'];
}

$appInitials = '';
if (APP_NAME !== '') {
    $parts = preg_split('/\s+/', APP_NAME);
    $initials = [];
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        $initials[] = strtoupper(substr($part, 0, 1));
    }
    $appInitials = implode('', $initials);
}
?>
<aside x-data="{
        collapsed: false,
        init() {
            if (typeof window !== 'undefined' && window.localStorage) {
                var saved = window.localStorage.getItem('sidebarCollapsed');
                this.collapsed = saved === '1';
            }
        },
        toggle() {
            this.collapsed = !this.collapsed;
            if (typeof window !== 'undefined' && window.localStorage) {
                window.localStorage.setItem('sidebarCollapsed', this.collapsed ? '1' : '0');
            }
        }
    }"
    x-init="init()"
    x-bind:class="collapsed ? 'md:w-20' : 'md:w-64'"
    class="surface-sidebar md:w-64">
    <div class="flex items-center justify-between px-6 py-5 border-b surface-divider">
        <div class="relative h-6">
            <span x-show="!collapsed" x-transition.opacity x-cloak class="text-lg font-semibold tracking-wide"><?= sanitize(APP_NAME); ?></span>
            <?php if ($appInitials !== ''): ?>
                <span x-show="collapsed" x-transition.opacity x-cloak class="text-lg font-semibold tracking-wide"><?= sanitize($appInitials); ?></span>
            <?php endif; ?>
        </div>
        <button type="button"
            @click="toggle()"
            x-bind:aria-label="collapsed ? 'Expandir menu' : 'Recolher menu'"
            class="surface-icon-button h-9 w-9">
            <span class="material-icons-outlined text-lg" x-text="collapsed ? 'chevron_right' : 'chevron_left'"></span>
        </button>
    </div>
    <nav class="flex-1 overflow-y-auto">
        <ul class="py-4 space-y-1">
            <?php foreach ($navigation as $item): ?>
                <?php
                    $isActive = $item['key'] === $activeMenu;
                    $linkClasses = $isActive
                        ? 'surface-sidebar-link surface-sidebar-link-active'
                        : 'surface-sidebar-link';
                ?>
                <li>
                    <a href="<?= sanitize($item['path']); ?>"
                       title="<?= sanitize($item['label']); ?>"
                       class="<?= $linkClasses; ?>"
                       x-bind:class="{
                           '!px-2 !justify-center !gap-0': collapsed,
                           '!px-6 !gap-3': !collapsed
                       }">
                        <span class="material-icons-outlined text-xl" x-bind:class="collapsed ? 'text-2xl' : ''"><?= sanitize($item['icon']); ?></span>
                        <span class="truncate" x-show="!collapsed" x-transition.opacity x-cloak><?= sanitize($item['label']); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
    <div class="mt-auto px-6 py-5 border-t surface-divider" x-show="!collapsed" x-transition.opacity x-cloak>
        <?php if ($user): ?>
            <div class="text-sm">
                <p class="font-semibold text-white"><?= sanitize($user['name']); ?></p>
                <p class="text-slate-400 mt-1">Perfil: <?= sanitize($roleLabels[$user['role']] ?? $user['role']); ?></p>
                <a href="perfil.php" class="inline-block mt-3 text-xs text-blue-300 hover:text-blue-200">Ver perfil</a>
            </div>
            <form action="logout.php" method="post" class="mt-3">
                <input type="hidden" name="csrf_token" value="<?= sanitize(ensure_csrf_token()); ?>">
                <button type="submit" class="text-xs text-slate-400 hover:text-red-300">Sair</button>
            </form>
        <?php endif; ?>
    </div>
</aside>
<?php include __DIR__ . '/mobile-menu.php'; ?>
