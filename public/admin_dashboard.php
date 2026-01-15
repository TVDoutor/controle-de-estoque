<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/helpers.php';

// Only admins
require_login('admin');

$pageTitle = 'Admin Dashboard';
$pageSubtitle = 'Visao geral administrativa';
$activeMenu = 'admin_dashboard';
// fetch metrics
$pdo = get_pdo();

// total users and by role
$totUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$usersByRoleStmt = $pdo->query("SELECT role, COUNT(*) AS cnt FROM users GROUP BY role");
$usersByRole = $usersByRoleStmt->fetchAll();

// equipment status counts
$equipStatusStmt = $pdo->query("SELECT status, COUNT(*) AS cnt FROM equipment GROUP BY status");
$equipStatus = $equipStatusStmt->fetchAll();

// recent operations
$opsStmt = $pdo->query("SELECT eo.id, eo.operation_type, eo.operation_date, u.name AS user_name, c.name AS client_name
    FROM equipment_operations eo
    LEFT JOIN users u ON eo.performed_by = u.id
    LEFT JOIN clients c ON eo.client_id = c.id
    ORDER BY eo.operation_date DESC LIMIT 10");
$recentOps = $opsStmt->fetchAll();

// operations per day (last 14 days)
$opsPerDayStmt = $pdo->prepare("SELECT DATE(operation_date) as d, COUNT(*) AS cnt
    FROM equipment_operations
    WHERE operation_date >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
    GROUP BY DATE(operation_date)
    ORDER BY DATE(operation_date) ASC");
$opsPerDayStmt->execute();
$opsPerDay = $opsPerDayStmt->fetchAll();

$opsPerDayLabelsJson = json_encode(array_column($opsPerDay, 'd'), JSON_UNESCAPED_UNICODE);
$opsPerDayCountsJson = json_encode(array_column($opsPerDay, 'cnt'), JSON_UNESCAPED_UNICODE);

$footerScripts = <<<HTML
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    (function () {
        const labels = $opsPerDayLabelsJson;
        const data = $opsPerDayCountsJson;

        const ctx = document.getElementById('opsChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Operacoes por dia',
                    data: data,
                    borderColor: 'rgba(59,130,246,0.9)',
                    backgroundColor: 'rgba(59,130,246,0.2)',
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { display: true },
                    y: { display: true, beginAtZero: true }
                }
            }
        });
    })();
</script>
HTML;

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>
<main class="flex-1 flex flex-col bg-slate-950 text-slate-100 min-h-0">
    <?php include __DIR__ . '/../templates/topbar.php'; ?>
    <section class="flex-1 overflow-y-auto">
        <div class="mx-auto max-w-7xl px-6 pb-12 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="surface-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="surface-heading">Usuários</div>
                            <div class="surface-subheading">Total de usuários</div>
                            <div class="text-3xl font-bold mt-3"><?= sanitize((string) $totUsers); ?></div>
                        </div>
                    </div>
                    <div class="mt-4 text-sm">
                        <?php foreach ($usersByRole as $r): ?>
                            <?php $percent = $totUsers > 0 ? round(((int) $r['cnt'] / $totUsers) * 100) : 0; ?>
                            <div class="flex justify-between py-1">
                                <span><?= sanitize($r['role']); ?></span>
                                <span><?= sanitize((string) $r['cnt']); ?> (<?= $percent; ?>%)</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="surface-card">
                    <div class="surface-heading">Equipamentos</div>
                    <div class="surface-subheading">Status atual</div>
                    <div class="mt-4 text-sm">
                        <?php foreach ($equipStatus as $s): ?>
                            <div class="flex justify-between py-1"><span><?= sanitize($s['status']); ?></span><span><?= sanitize((string) $s['cnt']); ?></span></div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="surface-card">
                    <div class="surface-heading">Operações (14d)</div>
                    <div class="mt-4 h-56">
                        <canvas id="opsChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="surface-card">
                    <div class="surface-heading">Operações recentes</div>
                    <div class="surface-table-wrapper mt-3">
                        <table class="w-full">
                            <thead class="surface-table-head">
                            <tr>
                                <th class="surface-table-cell">Tipo</th>
                                <th class="surface-table-cell">Data</th>
                                <th class="surface-table-cell">Usuário</th>
                                <th class="surface-table-cell">Cliente</th>
                            </tr>
                            </thead>
                            <tbody class="surface-table-body">
                            <?php foreach ($recentOps as $op): ?>
                                <tr>
                                    <td class="surface-table-cell"><?= sanitize($op['operation_type']); ?></td>
                                    <td class="surface-table-cell"><?= sanitize(format_datetime($op['operation_date'])); ?></td>
                                    <td class="surface-table-cell"><?= sanitize($op['user_name'] ?? '-'); ?></td>
                                    <td class="surface-table-cell"><?= sanitize($op['client_name'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="surface-card">
                    <div class="surface-heading">Ações rápidas</div>
                    <div class="mt-3 flex flex-col gap-3">
                    <a href="usuarios.php" title="Ir para usuários" class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">Gerenciar usuários</a>
                    <a href="equipamentos.php" title="Ir para equipamentos" class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">Gerenciar equipamentos</a>
                    <a href="relatorios.php" title="Ir para relatórios" class="inline-flex items-center justify-center rounded-xl bg-slate-700 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-600">Relatórios</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php include __DIR__ . '/../templates/footer.php'; ?>
