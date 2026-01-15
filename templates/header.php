<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

session_bootstrap();
$user = current_user();
$pageTitle = $pageTitle ?? APP_NAME;
$fullTitle = $pageTitle === APP_NAME ? APP_NAME : $pageTitle . ' - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= sanitize($fullTitle); ?></title>
    <script>
        (function () {
            var storageKey = 'ui-theme';
            var doc = document.documentElement;

            function readStored() {
                try {
                    return window.localStorage ? localStorage.getItem(storageKey) : null;
                } catch (error) {
                    return null;
                }
            }

            function prefersDark() {
                return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            }

            function notify(theme) {
                window.dispatchEvent(new CustomEvent('ui-theme-change', { detail: theme }));
            }

            function apply(theme) {
                doc.dataset.theme = theme;
                notify(theme);
            }

            var stored = readStored();
            var initial = stored || (prefersDark() ? 'dark' : 'light');
            apply(initial);

            window.UITheme = {
                get: function () {
                    return doc.dataset.theme || 'dark';
                },
                set: function (theme) {
                    apply(theme);
                    try {
                        window.localStorage && localStorage.setItem(storageKey, theme);
                    } catch (error) {
                        // ignore storage errors
                    }
                },
                toggle: function () {
                    var next = this.get() === 'dark' ? 'light' : 'dark';
                    this.set(next);
                    return next;
                },
                clearPreference: function () {
                    try {
                        window.localStorage && localStorage.removeItem(storageKey);
                    } catch (error) {
                        // ignore storage errors
                    }
                }
            };

            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').addEventListener) {
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (event) {
                    if (!readStored()) {
                        window.UITheme.set(event.matches ? 'dark' : 'light');
                    }
                });
            }
        })();
    </script>
    <script>
        document.addEventListener('alpine:init', function () {
            var store = {
                theme: window.UITheme ? window.UITheme.get() : 'dark',
                set: function (theme) {
                    if (window.UITheme) {
                        window.UITheme.set(theme);
                    } else {
                        document.documentElement.dataset.theme = theme;
                    }
                    this.theme = theme;
                },
                toggle: function () {
                    var next = this.theme === 'dark' ? 'light' : 'dark';
                    this.set(next);
                }
            };

            Alpine.store('uiTheme', store);

            window.addEventListener('ui-theme-change', function (event) {
                store.theme = event.detail;
            });
        });
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <script>
        window.tailwind = window.tailwind || {};
        window.tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui']
                    }
                }
            }
        };
    </script>
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
    <style type="text/tailwindcss">
        @layer base {
            :root {
                color-scheme: dark;
                --ui-font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                --ui-body-bg: radial-gradient(circle at top, rgba(30, 41, 59, 0.6), rgba(2, 6, 23, 0.95) 55%);
                --ui-body-text: #e2e8f0;
                --ui-selection-bg: #3b82f6;
                --ui-selection-text: #ffffff;
                --ui-surface-card-bg: rgba(15, 23, 42, 0.8);
                --ui-surface-card-border: rgba(51, 65, 85, 0.8);
                --ui-surface-card-shadow: 0 28px 80px -40px rgba(15, 23, 42, 0.85), 0 12px 35px -20px rgba(15, 23, 42, 0.65);
                --ui-surface-panel-bg: rgba(15, 23, 42, 0.7);
                --ui-surface-panel-border: rgba(51, 65, 85, 0.85);
                --ui-surface-muted: #94a3b8;
                --ui-surface-heading: #f8fafc;
                --ui-surface-subheading: #94a3b8;
                --ui-surface-pill-bg: rgba(30, 41, 59, 0.6);
                --ui-surface-pill-text: #e2e8f0;
                --ui-field-bg: rgba(2, 6, 23, 0.95);
                --ui-field-text: #f8fafc;
                --ui-field-border: rgba(51, 65, 85, 0.85);
                --ui-field-placeholder: rgba(148, 163, 184, 0.85);
                --ui-field-focus: #60a5fa;
                --ui-table-head-bg: rgba(15, 23, 42, 0.85);
                --ui-table-head-text: rgba(148, 163, 184, 0.85);
                --ui-table-row-border: rgba(30, 41, 59, 0.6);
                --ui-border-soft: rgba(71, 85, 105, 0.7);
                --ui-border-strong: rgba(51, 65, 85, 0.85);
                --ui-tag-bg-positive: rgba(16, 185, 129, 0.16);
                --ui-tag-border-positive: rgba(16, 185, 129, 0.38);
                --ui-tag-text-positive: #bbf7d0;
                --ui-tag-bg-negative: rgba(239, 68, 68, 0.18);
                --ui-tag-border-negative: rgba(239, 68, 68, 0.4);
                --ui-tag-text-negative: #fecaca;
                --ui-divider-color: rgba(51, 65, 85, 0.6);
                --ui-topbar-bg: rgba(2, 6, 23, 0.7);
                --ui-topbar-border: rgba(30, 41, 59, 0.6);
                --ui-topbar-text: #e2e8f0;
                --ui-sidebar-bg: rgba(2, 6, 23, 0.65);
                --ui-sidebar-border: rgba(30, 41, 59, 0.8);
                --ui-sidebar-text: #cbd5f5;
                --ui-sidebar-active-bg: rgba(59, 130, 246, 0.16);
                --ui-sidebar-active-text: #f8fafc;
                --ui-button-muted-bg: rgba(15, 23, 42, 0.7);
                --ui-button-muted-text: #e2e8f0;
            }

            :root[data-theme='light'] {
                color-scheme: light;
                --ui-body-bg: radial-gradient(circle at top, rgba(226, 232, 240, 0.85), rgba(241, 245, 249, 0.95) 55%);
                --ui-body-text: #0f172a;
                --ui-selection-bg: #2563eb;
                --ui-selection-text: #ffffff;
                --ui-surface-card-bg: rgba(255, 255, 255, 0.92);
                --ui-surface-card-border: rgba(148, 163, 184, 0.45);
                --ui-surface-card-shadow: 0 25px 60px -40px rgba(15, 23, 42, 0.3), 0 12px 30px -25px rgba(15, 23, 42, 0.2);
                --ui-surface-panel-bg: rgba(255, 255, 255, 0.88);
                --ui-surface-panel-border: rgba(203, 213, 225, 0.8);
                --ui-surface-muted: #64748b;
                --ui-surface-heading: #0f172a;
                --ui-surface-subheading: #475569;
                --ui-surface-pill-bg: rgba(226, 232, 240, 0.85);
                --ui-surface-pill-text: #0f172a;
                --ui-field-bg: rgba(255, 255, 255, 0.95);
                --ui-field-text: #0f172a;
                --ui-field-border: rgba(148, 163, 184, 0.45);
                --ui-field-placeholder: rgba(100, 116, 139, 0.65);
                --ui-field-focus: #2563eb;
                --ui-table-head-bg: rgba(226, 232, 240, 0.9);
                --ui-table-head-text: #475569;
                --ui-table-row-border: rgba(203, 213, 225, 0.7);
                --ui-border-soft: rgba(226, 232, 240, 0.9);
                --ui-border-strong: rgba(148, 163, 184, 0.6);
                --ui-tag-bg-positive: rgba(16, 185, 129, 0.12);
                --ui-tag-border-positive: rgba(16, 185, 129, 0.35);
                --ui-tag-text-positive: #047857;
                --ui-tag-bg-negative: rgba(248, 113, 113, 0.12);
                --ui-tag-border-negative: rgba(248, 113, 113, 0.35);
                --ui-tag-text-negative: #b91c1c;
                --ui-divider-color: rgba(203, 213, 225, 0.7);
                --ui-topbar-bg: rgba(255, 255, 255, 0.87);
                --ui-topbar-border: rgba(203, 213, 225, 0.85);
                --ui-topbar-text: #1e293b;
                --ui-sidebar-bg: rgba(248, 250, 252, 0.92);
                --ui-sidebar-border: rgba(203, 213, 225, 0.9);
                --ui-sidebar-text: #1f2937;
                --ui-sidebar-active-bg: rgba(37, 99, 235, 0.12);
                --ui-sidebar-active-text: #1f2937;
                --ui-button-muted-bg: rgba(226, 232, 240, 0.9);
                --ui-button-muted-text: #1e293b;
            }

            body {
                background: var(--ui-body-bg);
                color: var(--ui-body-text);
                font-family: var(--ui-font-family);
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
            }

            ::selection {
                background: var(--ui-selection-bg);
                color: var(--ui-selection-text);
            }
        }

        @layer components {
            .surface-card {
                @apply rounded-3xl p-6;
                border: 1px solid var(--ui-surface-card-border);
                background: var(--ui-surface-card-bg);
                box-shadow: var(--ui-surface-card-shadow);
                backdrop-filter: blur(16px);
            }

            .surface-card-tight {
                @apply rounded-3xl p-4;
                border: 1px solid var(--ui-surface-card-border);
                background: var(--ui-surface-card-bg);
                box-shadow: var(--ui-surface-card-shadow);
                backdrop-filter: blur(16px);
            }

            .surface-panel {
                @apply rounded-2xl;
                border: 1px solid var(--ui-surface-panel-border);
                background: var(--ui-surface-panel-bg);
                backdrop-filter: blur(14px);
            }

            .surface-field {
                @apply mt-2 w-full rounded-xl px-4 py-3 text-sm transition focus:outline-none focus:ring-0;
                border: 1px solid var(--ui-field-border);
                background-color: var(--ui-field-bg);
                color: var(--ui-field-text);
            }

            .surface-field-compact {
                @apply mt-1 w-full rounded-lg px-3 py-2 text-sm transition focus:outline-none focus:ring-0;
                border: 1px solid var(--ui-field-border);
                background-color: var(--ui-field-bg);
                color: var(--ui-field-text);
            }

            .surface-select {
                @apply mt-2 w-full rounded-xl px-4 py-3 text-sm transition focus:outline-none focus:ring-0;
                border: 1px solid var(--ui-field-border);
                background-color: var(--ui-field-bg);
                color: var(--ui-field-text);
            }

            .surface-select-compact {
                @apply mt-1 w-full rounded-lg px-3 py-2 text-sm transition focus:outline-none focus:ring-0;
                border: 1px solid var(--ui-field-border);
                background-color: var(--ui-field-bg);
                color: var(--ui-field-text);
            }

            .surface-field::placeholder,
            .surface-field-compact::placeholder,
            .surface-select::placeholder,
            .surface-select-compact::placeholder {
                color: var(--ui-field-placeholder);
            }

            .surface-field:focus,
            .surface-field-compact:focus,
            .surface-select:focus,
            .surface-select-compact:focus {
                border-color: var(--ui-field-focus);
            }

            .surface-toggle {
                @apply flex items-center justify-between rounded-xl px-4 py-3 transition;
                border: 1px solid var(--ui-surface-panel-border);
                background-color: var(--ui-surface-panel-bg);
                color: var(--ui-body-text);
            }

            .surface-table-wrapper {
                @apply overflow-hidden rounded-2xl;
                border: 1px solid var(--ui-surface-panel-border);
                background-color: var(--ui-surface-panel-bg);
            }

            .surface-table-head {
                @apply text-xs font-semibold uppercase tracking-wide;
                background-color: var(--ui-table-head-bg);
                color: var(--ui-table-head-text);
            }

            .surface-table-body > tr + tr {
                border-top: 1px solid var(--ui-table-row-border);
            }

            .surface-table-cell {
                @apply px-4 py-3 text-sm;
                color: var(--ui-body-text);
            }

            .surface-muted {
                color: var(--ui-surface-muted);
            }

            .surface-heading {
                @apply text-lg font-semibold;
                color: var(--ui-surface-heading);
            }

            .surface-subheading {
                @apply text-sm;
                color: var(--ui-surface-subheading);
            }

            .surface-pill {
                @apply inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold;
                background-color: var(--ui-surface-pill-bg);
                color: var(--ui-surface-pill-text);
            }

            .surface-topbar {
                @apply flex items-center justify-between px-6 py-4 border-b backdrop-blur;
                background-color: var(--ui-topbar-bg);
                border-color: var(--ui-topbar-border);
                color: var(--ui-topbar-text);
            }

            .surface-sidebar {
                @apply hidden md:flex md:flex-col border-r transition-all duration-300;
                background-color: var(--ui-sidebar-bg);
                border-color: var(--ui-sidebar-border);
                color: var(--ui-sidebar-text);
            }

            .surface-sidebar-link {
                @apply flex items-center gap-3 px-6 py-2.5 text-sm font-medium transition;
                color: inherit;
                border-radius: 0.875rem;
            }

            .surface-sidebar-link:hover {
                background-color: var(--ui-sidebar-active-bg);
                color: var(--ui-sidebar-active-text);
            }

            .surface-sidebar-link-active {
                background-color: var(--ui-sidebar-active-bg);
                color: var(--ui-sidebar-active-text);
            }

            .surface-button-muted {
                @apply inline-flex items-center justify-center gap-2 rounded-lg border px-3 py-2 text-xs font-semibold uppercase tracking-wide transition;
                background-color: var(--ui-button-muted-bg);
                border-color: var(--ui-border-strong);
                color: var(--ui-button-muted-text);
            }

            .surface-divider {
                border-color: var(--ui-divider-color);
            }

            .surface-icon-button {
                @apply inline-flex h-10 w-10 items-center justify-center rounded-full border transition;
                background-color: var(--ui-button-muted-bg);
                border-color: var(--ui-border-strong);
                color: var(--ui-button-muted-text);
            }

            .surface-icon-button:hover {
                filter: brightness(1.05);
            }
        }
    </style>
    <style>
        html[data-theme='dark'] main {
            background: transparent;
        }

        html[data-theme='dark'] .bg-white.shadow-sm,
        html[data-theme='dark'] .bg-white.border,
        html[data-theme='dark'] .bg-slate-50,
        html[data-theme='dark'] .bg-slate-50.shadow-sm,
        html[data-theme='dark'] .bg-slate-50.border,
        html[data-theme='dark'] .bg-slate-200,
        html[data-theme='dark'] .bg-slate-100 {
            background-color: var(--ui-surface-panel-bg) !important;
            color: var(--ui-body-text) !important;
        }

        html[data-theme='dark'] .bg-green-50 {
            background-color: var(--ui-tag-bg-positive) !important;
            color: var(--ui-tag-text-positive) !important;
        }

        html[data-theme='dark'] .border-green-200 {
            border-color: var(--ui-tag-border-positive) !important;
        }

        html[data-theme='dark'] .bg-red-50 {
            background-color: var(--ui-tag-bg-negative) !important;
            color: var(--ui-tag-text-negative) !important;
        }

        html[data-theme='dark'] .border-red-200 {
            border-color: var(--ui-tag-border-negative) !important;
        }

        html[data-theme='dark'] .rounded-xl.bg-white.shadow-sm,
        html[data-theme='dark'] .rounded-xl.bg-white.border,
        html[data-theme='dark'] .rounded-xl.bg-slate-50.shadow-sm,
        html[data-theme='dark'] .rounded-xl.bg-slate-50.border {
            border-radius: 1.75rem !important;
        }

        html[data-theme='dark'] .border-slate-100,
        html[data-theme='dark'] .border-slate-200,
        html[data-theme='dark'] .border-slate-300 {
            border-color: var(--ui-border-soft) !important;
        }

        html[data-theme='dark'] .shadow-sm {
            box-shadow: var(--ui-surface-card-shadow) !important;
        }

        html[data-theme='dark'] .divide-slate-200 > :not([hidden]) ~ :not([hidden]),
        html[data-theme='dark'] .divide-slate-100 > :not([hidden]) ~ :not([hidden]) {
            border-color: var(--ui-divider-color) !important;
        }

        html[data-theme='dark'] .hover\:bg-slate-50:hover,
        html[data-theme='dark'] .hover\:bg-slate-300:hover,
        html[data-theme='dark'] .hover\:bg-white:hover {
            background-color: rgba(51, 65, 85, 0.6) !important;
            color: var(--ui-body-text) !important;
        }

        html[data-theme='dark'] .text-slate-900,
        html[data-theme='dark'] .text-slate-800 {
            color: var(--ui-body-text) !important;
        }

        html[data-theme='dark'] .text-slate-700 {
            color: var(--ui-body-text) !important;
        }

        html[data-theme='dark'] .text-slate-600 {
            color: var(--ui-surface-muted) !important;
        }

        html[data-theme='dark'] .text-slate-500 {
            color: var(--ui-surface-muted) !important;
        }

        html[data-theme='dark'] input[type="text"],
        html[data-theme='dark'] input[type="email"],
        html[data-theme='dark'] input[type="password"],
        html[data-theme='dark'] input[type="number"],
        html[data-theme='dark'] input[type="search"],
        html[data-theme='dark'] input[type="datetime-local"],
        html[data-theme='dark'] input[type="date"],
        html[data-theme='dark'] select,
        html[data-theme='dark'] textarea {
            background-color: var(--ui-field-bg) !important;
            border-color: var(--ui-field-border) !important;
            color: var(--ui-field-text) !important;
        }

        html[data-theme='dark'] input::placeholder,
        html[data-theme='dark'] textarea::placeholder {
            color: var(--ui-field-placeholder) !important;
        }

        html[data-theme='light'] body {
            background: var(--ui-body-bg);
            color: var(--ui-body-text);
        }

        html[data-theme='light'] main {
            background: transparent;
        }

        html[data-theme='light'] .bg-slate-950,
        html[data-theme='light'] .bg-slate-950\/60,
        html[data-theme='light'] .bg-slate-950\/70,
        html[data-theme='light'] .bg-slate-950\/80 {
            background-color: rgba(255, 255, 255, 0.95) !important;
            color: var(--ui-body-text) !important;
        }

        html[data-theme='light'] .bg-slate-900,
        html[data-theme='light'] .bg-slate-900\/70,
        html[data-theme='light'] .bg-slate-900\/80 {
            background-color: rgba(248, 250, 252, 0.92) !important;
            color: var(--ui-body-text) !important;
        }

        html[data-theme='light'] .bg-slate-800,
        html[data-theme='light'] .bg-slate-800\/70,
        html[data-theme='light'] .bg-slate-800\/80 {
            background-color: rgba(226, 232, 240, 0.85) !important;
            color: var(--ui-body-text) !important;
        }

        html[data-theme='light'] .bg-slate-700 {
            background-color: rgba(203, 213, 225, 0.8) !important;
            color: var(--ui-body-text) !important;
        }

        html[data-theme='light'] .bg-slate-300 {
            background-color: rgba(203, 213, 225, 0.65) !important;
        }

        html[data-theme='light'] .bg-slate-200 {
            background-color: rgba(226, 232, 240, 0.8) !important;
            color: var(--ui-body-text) !important;
        }

        html[data-theme='light'] .bg-slate-100 {
            background-color: rgba(241, 245, 249, 0.9) !important;
            color: var(--ui-body-text) !important;
        }

        html[data-theme='light'] .bg-green-50 {
            background-color: rgba(134, 239, 172, 0.25) !important;
            color: #065f46 !important;
        }

        html[data-theme='light'] .border-green-200 {
            border-color: rgba(16, 185, 129, 0.4) !important;
        }

        html[data-theme='light'] .bg-red-50 {
            background-color: rgba(254, 205, 211, 0.3) !important;
            color: #9f1239 !important;
        }

        html[data-theme='light'] .border-red-200 {
            border-color: rgba(248, 113, 113, 0.4) !important;
        }

        html[data-theme='light'] .border-slate-800,
        html[data-theme='light'] .border-slate-800\/80,
        html[data-theme='light'] .border-slate-700 {
            border-color: rgba(203, 213, 225, 0.85) !important;
        }

        html[data-theme='light'] .border-slate-200,
        html[data-theme='light'] .border-slate-300,
        html[data-theme='light'] .border-slate-100 {
            border-color: rgba(203, 213, 225, 0.8) !important;
        }

        html[data-theme='light'] .text-slate-100,
        html[data-theme='light'] .text-slate-200 {
            color: #0f172a !important;
        }

        html[data-theme='light'] .text-slate-300 {
            color: #1e293b !important;
        }

        html[data-theme='light'] .text-slate-400 {
            color: #475569 !important;
        }

        html[data-theme='light'] .text-slate-500 {
            color: #64748b !important;
        }

        html[data-theme='light'] .text-slate-600 {
            color: #475569 !important;
        }

        html[data-theme='light'] .text-slate-700 {
            color: #334155 !important;
        }

        html[data-theme='light'] .text-slate-800 {
            color: #1e293b !important;
        }

        html[data-theme='light'] .text-slate-900 {
            color: #0f172a !important;
        }

        html[data-theme='light'] .hover\:bg-slate-50:hover,
        html[data-theme='light'] .hover\:bg-slate-300:hover {
            background-color: rgba(226, 232, 240, 0.95) !important;
            color: #1e293b !important;
        }

        html[data-theme='light'] .hover\:bg-white:hover {
            background-color: rgba(255, 255, 255, 0.95) !important;
            color: #1e293b !important;
        }

        html[data-theme='light'] input[type="text"],
        html[data-theme='light'] input[type="email"],
        html[data-theme='light'] input[type="password"],
        html[data-theme='light'] input[type="number"],
        html[data-theme='light'] input[type="search"],
        html[data-theme='light'] input[type="datetime-local"],
        html[data-theme='light'] input[type="date"],
        html[data-theme='light'] select,
        html[data-theme='light'] textarea {
            background-color: var(--ui-field-bg) !important;
            border-color: var(--ui-field-border) !important;
            color: var(--ui-field-text) !important;
        }

        html[data-theme='light'] input::placeholder,
        html[data-theme='light'] textarea::placeholder {
            color: var(--ui-field-placeholder) !important;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js" defer></script>
    <style>[x-cloak]{display:none!important;}</style>
</head>
<body class="antialiased">
<div class="min-h-screen flex">










