<?php

declare(strict_types=1);

?>
<header class="surface-topbar">
    <div>
        <?php if (!empty($breadcrumbs) && is_array($breadcrumbs)): ?>
            <nav class="surface-breadcrumbs mb-1">
                <?php foreach ($breadcrumbs as $index => $crumb): ?>
                    <?php if (!empty($crumb['href'])): ?>
                        <a class="hover:text-blue-300" href="<?= sanitize($crumb['href']); ?>"><?= sanitize($crumb['label'] ?? ''); ?></a>
                    <?php else: ?>
                        <span><?= sanitize($crumb['label'] ?? ''); ?></span>
                    <?php endif; ?>
                    <?php if ($index < count($breadcrumbs) - 1): ?>
                        <span class="mx-1">/</span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
        <h1 class="text-xl font-semibold text-slate-100">
            <?= sanitize($pageTitle ?? ''); ?>
        </h1>
        <?php if (!empty($pageSubtitle)): ?>
            <p class="text-sm text-slate-400 mt-1"><?= sanitize($pageSubtitle); ?></p>
        <?php endif; ?>
    </div>
    <?php if ($user): ?>
        <?php
            $initials = strtoupper(substr($user['name'], 0, 2));
            $logoutCsrf = ensure_csrf_token();
        ?>
        <div class="flex items-center gap-3 sm:gap-4"
             x-data="{
                 get theme() { return $store.uiTheme.theme; },
                 toggle() { $store.uiTheme.toggle(); }
             }">
            <?php if (!empty($showDensityToggle)): ?>
                <button type="button"
                        class="surface-icon-button"
                        onclick="window.UISettings && UISettings.toggleDensity()"
                        aria-label="Alternar densidade da tabela"
                        title="Alternar densidade da tabela">
                    <span class="material-icons-outlined text-lg">view_headline</span>
                </button>
            <?php endif; ?>
            <button type="button"
                    class="surface-icon-button"
                    @click="toggle()"
                    x-bind:aria-label="theme === 'dark' ? 'Ativar modo claro' : 'Ativar modo escuro'"
                    x-bind:title="theme === 'dark' ? 'Ativar modo claro' : 'Ativar modo escuro'">
                <span class="material-icons-outlined text-lg" x-show="theme === 'dark'" x-cloak>light_mode</span>
                <span class="material-icons-outlined text-lg" x-show="theme === 'light'" x-cloak>dark_mode</span>
            </button>
            <div class="text-right">
                <p class="text-sm font-medium text-slate-100"><?= sanitize($user['name']); ?></p>
                <p class="text-xs text-slate-400 uppercase tracking-wide"><?= sanitize($user['role']); ?></p>
            </div>
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-800 text-slate-200 font-semibold">
                <?= sanitize($initials); ?>
            </span>
            <form action="logout.php" method="post" class="ml-2">
                <input type="hidden" name="csrf_token" value="<?= sanitize($logoutCsrf); ?>">
                <button type="submit" class="surface-button-muted">
                    <span class="material-icons-outlined text-base">logout</span>
                    <span class="hidden sm:inline">Sair</span>
                </button>
            </form>
        </div>
    <?php endif; ?>
</header>

