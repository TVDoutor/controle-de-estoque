<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login();

$pageTitle = 'Clientes';
$activeMenu = 'clientes';
$showDensityToggle = true;
$pdo = get_pdo();
$user = current_user();
$canManage = user_has_role(['admin', 'gestor']);
$errors = [];

if ($canManage && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sessão expirada. Recarregue a página.';
    } else {
        $action = $_POST['action'] ?? 'create';
        $clientCode = trim($_POST['client_code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $cnpj = trim($_POST['cnpj'] ?? '');
        $contactName = trim($_POST['contact_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');

        if ($clientCode === '' || $name === '') {
            $errors[] = 'Informe o código e o nome do cliente.';
        }

        if (!$errors) {
            try {
                if ($action === 'update') {
                    $clientId = (int) ($_POST['client_id'] ?? 0);
                    if ($clientId <= 0) {
                        throw new RuntimeException('Cliente inválido.');
                    }
                    $stmt = $pdo->prepare(<<<SQL
                        UPDATE clients
                        SET client_code = :client_code,
                            name = :name,
                            cnpj = :cnpj,
                            contact_name = :contact_name,
                            phone = :phone,
                            email = :email,
                            address = :address,
                            city = :city,
                            state = :state
                        WHERE id = :id
SQL);
                    $stmt->execute([
                        'client_code' => $clientCode,
                        'name' => $name,
                        'cnpj' => $cnpj ?: null,
                        'contact_name' => $contactName ?: null,
                        'phone' => $phone ?: null,
                        'email' => $email ?: null,
                        'address' => $address ?: null,
                        'city' => $city ?: null,
                        'state' => $state ?: null,
                        'id' => $clientId,
                    ]);
                    flash('success', 'Cliente atualizado com sucesso.', 'success');
                } else {
                    $stmt = $pdo->prepare(<<<SQL
                        INSERT INTO clients (client_code, name, cnpj, contact_name, phone, email, address, city, state)
                        VALUES (:client_code, :name, :cnpj, :contact_name, :phone, :email, :address, :city, :state)
SQL);
                    $stmt->execute([
                        'client_code' => $clientCode,
                        'name' => $name,
                        'cnpj' => $cnpj ?: null,
                        'contact_name' => $contactName ?: null,
                        'phone' => $phone ?: null,
                        'email' => $email ?: null,
                        'address' => $address ?: null,
                        'city' => $city ?: null,
                        'state' => $state ?: null,
                    ]);
                    flash('success', 'Cliente cadastrado com sucesso.', 'success');
                }
                redirect('clientes.php');
            } catch (PDOException $exception) {
                if ((int) $exception->errorInfo[1] === 1062) {
                    $errors[] = 'Já existe um cliente com este código.';
                } else {
                    $errors[] = 'Erro ao salvar cliente.';
                }
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }
    }
}

$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'name';
$dir = strtolower((string) ($_GET['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
$sortMap = [
    'code' => 'client_code',
    'name' => 'name',
    'contact' => 'contact_name',
    'phone' => 'phone',
    'city' => 'city',
];
$orderBy = $sortMap[$sort] ?? 'name';
$query = 'SELECT c.*, COUNT(e.id) as total_telas 
FROM clients c 
LEFT JOIN equipment e ON e.current_client_id = c.id 
';
$params = [];
if ($search !== '') {
    $query .= ' WHERE (c.client_code LIKE :term OR c.name LIKE :term OR c.cnpj LIKE :term)';
    $params['term'] = '%' . $search . '%';
}
$query .= ' GROUP BY c.id';
$orderByColumn = match($orderBy) {
    'client_code' => 'c.client_code',
    'name' => 'c.name',
    'contact_name' => 'c.contact_name',
    'phone' => 'c.phone',
    'city' => 'c.city',
    default => 'c.name',
};
$query .= sprintf(' ORDER BY %s %s', $orderByColumn, strtoupper($dir));
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$allClients = $stmt->fetchAll();

// Paginação
$perPage = 12;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$totalClients = count($allClients);
$totalPages = max(1, (int) ceil($totalClients / $perPage));
$currentPage = min($currentPage, $totalPages);
$offset = ($currentPage - 1) * $perPage;
$clients = array_slice($allClients, $offset, $perPage);

// Visualização (lista ou grid)
$viewMode = $_GET['view'] ?? 'list';
if (!in_array($viewMode, ['list', 'grid'], true)) {
    $viewMode = 'list';
}

$getParamsForSort = $_GET;
$buildSortLink = static function (string $key) use ($sort, $dir, $viewMode, $getParamsForSort): string {
    $params = $getParamsForSort;
    $params['sort'] = $key;
    $params['dir'] = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
    $params['view'] = $viewMode;
    $params['page'] = 1; // Reset para primeira página ao ordenar
    return '?' . http_build_query($params);
};

$sortIndicator = static function (string $key) use ($sort, $dir): string {
    if ($sort !== $key) {
        return '';
    }
    return $dir === 'asc' ? '^' : 'v';
};


include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>
<main class="flex-1 flex flex-col bg-slate-950 text-slate-100">
    <?php include __DIR__ . '/../templates/topbar.php'; ?>
    <section class="flex-1 overflow-y-auto px-6 pb-12 space-y-6">
        <?php if ($flash = flash('success')): ?>
            <div class="rounded-2xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-4 text-sm text-emerald-200">
                <?= sanitize($flash['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="rounded-2xl border border-red-500/40 bg-red-500/10 px-4 py-4 text-sm text-red-200">
                <ul class="list-disc space-y-1 pl-5">
                    <?php foreach ($errors as $error): ?>
                        <li><?= sanitize($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="surface-card">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <form method="get" class="flex gap-2 text-sm">
                    <input type="hidden" name="view" value="<?= sanitize($viewMode); ?>">
                    <input type="text" name="search" value="<?= sanitize($search); ?>" placeholder="Buscar por nome, código ou CNPJ" class="surface-field-compact w-64">
                    <button type="submit" class="rounded-xl bg-slate-800 px-4 py-2 font-semibold text-white hover:bg-slate-700">Pesquisar</button>
                </form>
                <div class="flex items-center gap-3">
                    <!-- Toggle de Visualização -->
                    <div class="flex items-center gap-2">
                        <a href="?<?= http_build_query(array_merge($_GET, ['view' => 'list'])); ?>" 
                           class="inline-flex items-center gap-1.5 rounded-xl border px-4 py-2 text-xs font-semibold transition <?= $viewMode === 'list' ? 'bg-blue-600 border-blue-600 text-white' : 'bg-slate-700 border-slate-600 text-slate-300 hover:bg-slate-600'; ?>">
                            <span class="material-icons-outlined text-sm">view_list</span>
                            Lista
                        </a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['view' => 'grid'])); ?>" 
                           class="inline-flex items-center gap-1.5 rounded-xl border px-4 py-2 text-xs font-semibold transition <?= $viewMode === 'grid' ? 'bg-blue-600 border-blue-600 text-white' : 'bg-slate-700 border-slate-600 text-slate-300 hover:bg-slate-600'; ?>">
                            <span class="material-icons-outlined text-sm">grid_view</span>
                            Grid
                        </a>
                    </div>
                    <?php if ($canManage): ?>
                        <a href="clientes.php" class="text-sm text-blue-300 hover:text-blue-200">Limpar filtros</a>
                        <button type="button" 
                                onclick="openCreateModal()"
                                class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 inline-flex items-center gap-2">
                            <span class="material-icons-outlined text-base">add</span>
                            Cadastrar Cliente
                        </button>
                    <?php endif; ?>
                    <a href="clientes_export.php?format=csv" class="rounded-xl border border-slate-600 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-800/40 inline-flex items-center gap-2">
                        <span class="material-icons-outlined text-base">download</span>
                        Exportar CSV
                    </a>
                    <?php if (user_has_role(['admin'])): ?>
                        <a href="clientes_import.php" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500 inline-flex items-center gap-2">
                            <span class="material-icons-outlined text-base">upload</span>
                            Importar
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($search !== ''): ?>
                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="surface-chip">Busca: <?= sanitize($search); ?></span>
                </div>
            <?php endif; ?>
            
            <!-- Informações de paginação -->
            <div class="mt-4 flex items-center justify-between text-sm text-slate-400">
                <span>Mostrando <?= count($clients); ?> de <?= $totalClients; ?> cliente(s)</span>
                <?php if ($totalPages > 1): ?>
                    <span>Página <?= $currentPage; ?> de <?= $totalPages; ?></span>
                <?php endif; ?>
            </div>

            <?php if (!$clients): ?>
                <div class="mt-6 surface-card text-center py-12">
                    <p class="text-slate-400">Nenhum cliente encontrado.</p>
                </div>
            <?php elseif ($viewMode === 'grid'): ?>
                <!-- Visualização em Grid -->
                <div class="mt-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <?php foreach ($clients as $client): ?>
                        <div class="surface-card hover:shadow-lg transition-shadow group">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex-1">
                                    <div class="font-semibold text-slate-100 text-sm mb-1"><?= sanitize($client['client_code']); ?></div>
                                    <div class="text-slate-300 text-sm font-medium line-clamp-2"><?= sanitize($client['name']); ?></div>
                                </div>
                            </div>
                            
                            <div class="space-y-2 mb-4 text-xs">
                                <?php if ($client['contact_name']): ?>
                                    <div class="flex items-center gap-2 text-slate-400">
                                        <span class="material-icons-outlined text-sm">person</span>
                                        <span><?= sanitize($client['contact_name']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($client['phone']): ?>
                                    <div class="flex items-center gap-2 text-slate-400">
                                        <span class="material-icons-outlined text-sm">phone</span>
                                        <span><?= sanitize($client['phone']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($client['city'] || $client['state']): ?>
                                    <div class="flex items-center gap-2 text-slate-400">
                                        <span class="material-icons-outlined text-sm">location_on</span>
                                        <span><?= sanitize(($client['city'] ?? '-') . '/' . ($client['state'] ?? '-')); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="flex items-center justify-between pt-3 border-t border-slate-700">
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-blue-500/20 text-blue-300 text-xs font-semibold">
                                    <span class="material-icons-outlined text-sm">tv</span>
                                    <?= (int) ($client['total_telas'] ?? 0); ?> telas
                                </span>
                                <?php if ($canManage): ?>
                                    <div class="flex items-center gap-2">
                                        <button type="button" 
                                                class="text-xs text-slate-400 hover:text-blue-300 transition"
                                                data-copy="<?= sanitize($client['client_code']); ?>"
                                                title="Copiar código">
                                            <span class="material-icons-outlined text-sm">content_copy</span>
                                        </button>
                                        <button type="button" 
                                                class="text-xs text-blue-400 hover:text-blue-300 transition"
                                                onclick='openEditModal(<?= htmlspecialchars(json_encode([
                                                    'id' => (int) $client['id'],
                                                    'client_code' => $client['client_code'],
                                                    'name' => $client['name'],
                                                    'cnpj' => $client['cnpj'] ?? '',
                                                    'contact_name' => $client['contact_name'] ?? '',
                                                    'phone' => $client['phone'] ?? '',
                                                    'email' => $client['email'] ?? '',
                                                    'address' => $client['address'] ?? '',
                                                    'city' => $client['city'] ?? '',
                                                    'state' => $client['state'] ?? ''
                                                ]), JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'
                                                title="Editar">
                                            <span class="material-icons-outlined text-sm">edit</span>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- Visualização em Lista (Tabela) -->
                <div class="mt-6 overflow-x-auto surface-table-wrapper">
                    <table class="min-w-full text-sm">
                        <thead class="surface-table-head">
                            <tr>
                                <th class="surface-table-cell text-left">
                                    <a href="<?= sanitize($buildSortLink('code')); ?>" class="inline-flex items-center gap-2">
                                        Código <span class="text-slate-400"><?= sanitize($sortIndicator('code')); ?></span>
                                    </a>
                                </th>
                                <th class="surface-table-cell text-left">
                                    <a href="<?= sanitize($buildSortLink('name')); ?>" class="inline-flex items-center gap-2">
                                        Nome <span class="text-slate-400"><?= sanitize($sortIndicator('name')); ?></span>
                                    </a>
                                </th>
                                <th class="surface-table-cell text-left">
                                    <a href="<?= sanitize($buildSortLink('contact')); ?>" class="inline-flex items-center gap-2">
                                        Contato <span class="text-slate-400"><?= sanitize($sortIndicator('contact')); ?></span>
                                    </a>
                                </th>
                                <th class="surface-table-cell text-left">
                                    <a href="<?= sanitize($buildSortLink('phone')); ?>" class="inline-flex items-center gap-2">
                                        Telefone <span class="text-slate-400"><?= sanitize($sortIndicator('phone')); ?></span>
                                    </a>
                                </th>
                                <th class="surface-table-cell text-left">
                                    <a href="<?= sanitize($buildSortLink('city')); ?>" class="inline-flex items-center gap-2">
                                        Cidade/UF <span class="text-slate-400"><?= sanitize($sortIndicator('city')); ?></span>
                                    </a>
                                </th>
                                <th class="surface-table-cell text-left">Telas</th>
                                <?php if ($canManage): ?>
                                    <th class="surface-table-cell text-right">Ações</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody class="surface-table-body">
                            <?php foreach ($clients as $client): ?>
                                <tr class="group hover:bg-slate-800/40">
                                    <td class="surface-table-cell font-semibold"><?= sanitize($client['client_code']); ?></td>
                                    <td class="surface-table-cell"><?= sanitize($client['name']); ?></td>
                                    <td class="surface-table-cell surface-muted"><?= sanitize($client['contact_name'] ?? '-'); ?></td>
                                    <td class="surface-table-cell surface-muted"><?= sanitize($client['phone'] ?? '-'); ?></td>
                                    <td class="surface-table-cell surface-muted"><?= sanitize(($client['city'] ?? '-') . '/' . ($client['state'] ?? '-')); ?></td>
                                    <td class="surface-table-cell">
                                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-blue-500/20 text-blue-300 text-xs font-semibold">
                                            <span class="material-icons-outlined text-sm">tv</span>
                                            <?= (int) ($client['total_telas'] ?? 0); ?>
                                        </span>
                                    </td>
                                    <?php if ($canManage): ?>
                                        <td class="surface-table-cell text-right">
                                            <div class="flex items-center justify-end gap-3">
                                                <button type="button" class="text-xs font-semibold text-slate-300 hover:text-blue-200 transition" data-copy="<?= sanitize($client['client_code']); ?>">Copiar código</button>
                                                <button type="button" 
                                                        class="inline-flex items-center gap-1 text-xs font-semibold text-blue-400 hover:text-blue-300 transition"
                                                        onclick='openEditModal(<?= htmlspecialchars(json_encode([
                                                            'id' => (int) $client['id'],
                                                            'client_code' => $client['client_code'],
                                                            'name' => $client['name'],
                                                            'cnpj' => $client['cnpj'] ?? '',
                                                            'contact_name' => $client['contact_name'] ?? '',
                                                            'phone' => $client['phone'] ?? '',
                                                            'email' => $client['email'] ?? '',
                                                            'address' => $client['address'] ?? '',
                                                            'city' => $client['city'] ?? '',
                                                            'state' => $client['state'] ?? ''
                                                        ]), JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                                    <span class="material-icons-outlined text-sm">edit</span>
                                                    Editar
                                                </button>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Paginação -->
            <?php if ($totalPages > 1): ?>
                <div class="mt-6 flex items-center justify-center gap-2">
                    <?php
                    $getParams = $_GET;
                    $buildPageLink = static function (int $page) use ($getParams): string {
                        $params = $getParams;
                        $params['page'] = $page;
                        return '?' . http_build_query($params);
                    };
                    ?>
                    <a href="<?= $buildPageLink(1); ?>" 
                       class="rounded-lg border border-slate-600 px-3 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/40 transition <?= $currentPage === 1 ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                       <?= $currentPage === 1 ? 'onclick="return false;"' : ''; ?>>
                        <span class="material-icons-outlined text-base">first_page</span>
                    </a>
                    <a href="<?= $buildPageLink(max(1, $currentPage - 1)); ?>" 
                       class="rounded-lg border border-slate-600 px-3 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/40 transition <?= $currentPage === 1 ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                       <?= $currentPage === 1 ? 'onclick="return false;"' : ''; ?>>
                        <span class="material-icons-outlined text-base">chevron_left</span>
                    </a>
                    
                    <?php
                    $startPage = max(1, $currentPage - 2);
                    $endPage = min($totalPages, $currentPage + 2);
                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                        <a href="<?= $buildPageLink($i); ?>" 
                           class="rounded-lg border px-4 py-2 text-sm font-semibold transition <?= $i === $currentPage ? 'bg-blue-600 text-white border-blue-600' : 'border-slate-600 text-slate-300 hover:bg-slate-800/40'; ?>">
                            <?= $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <a href="<?= $buildPageLink(min($totalPages, $currentPage + 1)); ?>" 
                       class="rounded-lg border border-slate-600 px-3 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/40 transition <?= $currentPage === $totalPages ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                       <?= $currentPage === $totalPages ? 'onclick="return false;"' : ''; ?>>
                        <span class="material-icons-outlined text-base">chevron_right</span>
                    </a>
                    <a href="<?= $buildPageLink($totalPages); ?>" 
                       class="rounded-lg border border-slate-600 px-3 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800/40 transition <?= $currentPage === $totalPages ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                       <?= $currentPage === $totalPages ? 'onclick="return false;"' : ''; ?>>
                        <span class="material-icons-outlined text-base">last_page</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>


        <!-- Modal de Edição de Cliente -->
        <?php if ($canManage): ?>
        <div x-data="editModal()" 
             x-show="isOpen"
             x-cloak
             @keydown.escape.window="closeModal()"
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             style="display: none;">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity" 
                 @click="closeModal()"></div>
            
            <!-- Modal Content -->
            <div class="relative z-10 w-full max-w-4xl max-h-[90vh] overflow-y-auto surface-card"
                 @click.stop>
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold text-slate-100">Editar Cliente</h2>
                    <button type="button" 
                            @click="closeModal()"
                            class="surface-icon-button">
                        <span class="material-icons-outlined text-lg">close</span>
                    </button>
                </div>

                <form id="editClientForm" 
                      method="post" 
                      @submit.prevent="handleSubmit()"
                      class="grid gap-4 md:grid-cols-2">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(ensure_csrf_token()); ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="client_id" x-model="clientData.id">
                    
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="modal_client_code">Código</label>
                        <input type="text" 
                               id="modal_client_code" 
                               name="client_code" 
                               required 
                               x-model="clientData.client_code"
                               class="surface-field">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="modal_name">Razão social / Nome</label>
                        <input type="text" 
                               id="modal_name" 
                               name="name" 
                               required 
                               x-model="clientData.name"
                               class="surface-field">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="modal_cnpj">CNPJ</label>
                        <input type="text" 
                               id="modal_cnpj" 
                               name="cnpj" 
                               x-model="clientData.cnpj"
                               class="surface-field" 
                               data-mask="cnpj" 
                               inputmode="numeric" 
                               placeholder="00.000.000/0000-00">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="modal_contact_name">Contato</label>
                        <input type="text" 
                               id="modal_contact_name" 
                               name="contact_name" 
                               x-model="clientData.contact_name"
                               class="surface-field">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="modal_phone">Telefone</label>
                        <input type="text" 
                               id="modal_phone" 
                               name="phone" 
                               x-model="clientData.phone"
                               class="surface-field" 
                               data-mask="phone" 
                               inputmode="tel" 
                               placeholder="(00) 00000-0000">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="modal_email">E-mail</label>
                        <input type="email" 
                               id="modal_email" 
                               name="email" 
                               x-model="clientData.email"
                               class="surface-field">
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium text-slate-300" for="modal_address">Endereço</label>
                        <input type="text" 
                               id="modal_address" 
                               name="address" 
                               x-model="clientData.address"
                               class="surface-field">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="modal_city">Cidade</label>
                        <input type="text" 
                               id="modal_city" 
                               name="city" 
                               x-model="clientData.city"
                               class="surface-field">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="modal_state">Estado</label>
                        <input type="text" 
                               id="modal_state" 
                               name="state" 
                               maxlength="2" 
                               x-model="clientData.state"
                               class="surface-field uppercase">
                    </div>
                    
                    <div class="md:col-span-2 flex justify-end gap-3 pt-4 border-t border-slate-700">
                        <button type="button" 
                                @click="closeModal()"
                                class="rounded-xl border border-slate-600 px-6 py-2.5 text-sm font-semibold text-slate-200 hover:bg-slate-800/40 transition">
                            Cancelar
                        </button>
                        <button type="submit" 
                                class="rounded-xl bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-blue-500 transition">
                            Confirmar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal de Confirmação -->
        <div x-data="confirmModal()" 
             x-show="showConfirm"
             x-cloak
             @keydown.escape.window="showConfirm = false"
             class="fixed inset-0 z-[60] flex items-center justify-center p-4"
             style="display: none;">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" 
                 @click="showConfirm = false"></div>
            
            <!-- Confirmation Dialog -->
            <div class="relative z-10 w-full max-w-md surface-card"
                 @click.stop>
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-slate-100 mb-2" x-text="isCreate ? 'Confirmar Cadastro' : 'Confirmar Alteração'"></h3>
                    <p class="text-sm text-slate-300" x-text="isCreate ? 'Deseja realmente cadastrar este cliente?' : 'Deseja realmente alterar os dados do cliente?'"></p>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" 
                            @click="showConfirm = false"
                            class="rounded-xl border border-slate-600 px-6 py-2.5 text-sm font-semibold text-slate-200 hover:bg-slate-800/40 transition">
                        Não
                    </button>
                    <button type="button" 
                            @click="submitForm()"
                            class="rounded-xl bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-blue-500 transition">
                        Sim
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal de Cadastro de Cliente -->
        <div x-data="createModal()" 
             x-show="isOpen"
             x-cloak
             @keydown.escape.window="closeModal()"
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             style="display: none;">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity" 
                 @click="closeModal()"></div>
            
            <!-- Modal Content -->
            <div class="relative z-10 w-full max-w-4xl max-h-[90vh] overflow-y-auto surface-card"
                 @click.stop>
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold text-slate-100">Cadastrar Novo Cliente</h2>
                    <button type="button" 
                            @click="closeModal()"
                            class="surface-icon-button">
                        <span class="material-icons-outlined text-lg">close</span>
                    </button>
                </div>

                <form id="createClientForm" 
                      method="post" 
                      @submit.prevent="handleSubmit()"
                      class="grid gap-4 md:grid-cols-2">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(ensure_csrf_token()); ?>">
                    <input type="hidden" name="action" value="create">
                    
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="create_client_code">Código</label>
                        <input type="text" 
                               id="create_client_code" 
                               name="client_code" 
                               required 
                               x-model="clientData.client_code"
                               class="surface-field">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="create_name">Razão social / Nome</label>
                        <input type="text" 
                               id="create_name" 
                               name="name" 
                               required 
                               x-model="clientData.name"
                               class="surface-field">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="create_cnpj">CNPJ</label>
                        <input type="text" 
                               id="create_cnpj" 
                               name="cnpj" 
                               x-model="clientData.cnpj"
                               class="surface-field" 
                               data-mask="cnpj" 
                               inputmode="numeric" 
                               placeholder="00.000.000/0000-00">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="create_contact_name">Contato</label>
                        <input type="text" 
                               id="create_contact_name" 
                               name="contact_name" 
                               x-model="clientData.contact_name"
                               class="surface-field">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="create_phone">Telefone</label>
                        <input type="text" 
                               id="create_phone" 
                               name="phone" 
                               x-model="clientData.phone"
                               class="surface-field" 
                               data-mask="phone" 
                               inputmode="tel" 
                               placeholder="(00) 00000-0000">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="create_email">E-mail</label>
                        <input type="email" 
                               id="create_email" 
                               name="email" 
                               x-model="clientData.email"
                               class="surface-field">
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium text-slate-300" for="create_address">Endereço</label>
                        <input type="text" 
                               id="create_address" 
                               name="address" 
                               x-model="clientData.address"
                               class="surface-field">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="create_city">Cidade</label>
                        <input type="text" 
                               id="create_city" 
                               name="city" 
                               x-model="clientData.city"
                               class="surface-field">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="create_state">Estado</label>
                        <input type="text" 
                               id="create_state" 
                               name="state" 
                               maxlength="2" 
                               x-model="clientData.state"
                               class="surface-field uppercase">
                    </div>
                    
                    <div class="md:col-span-2 flex justify-end gap-3 pt-4 border-t border-slate-700">
                        <button type="button" 
                                @click="closeModal()"
                                class="rounded-xl border border-slate-600 px-6 py-2.5 text-sm font-semibold text-slate-200 hover:bg-slate-800/40 transition">
                            Cancelar
                        </button>
                        <button type="submit" 
                                class="rounded-xl bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-blue-500 transition">
                            Confirmar
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </section>
<?php
$footerScripts = <<<HTML
<script>
    document.addEventListener('alpine:init', () => {
        // Store global para comunicação entre modais
        let editModalInstance = null;
        let createModalInstance = null;
        let confirmModalInstance = null;

        Alpine.data('editModal', () => ({
            isOpen: false,
            clientData: {
                id: 0,
                client_code: '',
                name: '',
                cnpj: '',
                contact_name: '',
                phone: '',
                email: '',
                address: '',
                city: '',
                state: ''
            },
            init() {
                editModalInstance = this;
                window.openEditModal = (data) => {
                    this.clientData = { ...data };
                    this.isOpen = true;
                    document.body.style.overflow = 'hidden';
                };
            },
            closeModal() {
                this.isOpen = false;
                document.body.style.overflow = '';
            },
            handleSubmit() {
                if (confirmModalInstance) {
                    confirmModalInstance.isCreate = false;
                    confirmModalInstance.showConfirm = true;
                }
            }
        }));

        Alpine.data('createModal', () => ({
            isOpen: false,
            clientData: {
                client_code: '',
                name: '',
                cnpj: '',
                contact_name: '',
                phone: '',
                email: '',
                address: '',
                city: '',
                state: ''
            },
            init() {
                createModalInstance = this;
                window.openCreateModal = () => {
                    this.clientData = {
                        client_code: '',
                        name: '',
                        cnpj: '',
                        contact_name: '',
                        phone: '',
                        email: '',
                        address: '',
                        city: '',
                        state: ''
                    };
                    this.isOpen = true;
                    document.body.style.overflow = 'hidden';
                };
            },
            closeModal() {
                this.isOpen = false;
                document.body.style.overflow = '';
            },
            handleSubmit() {
                if (confirmModalInstance) {
                    confirmModalInstance.isCreate = true;
                    confirmModalInstance.showConfirm = true;
                }
            }
        }));

        Alpine.data('confirmModal', () => ({
            showConfirm: false,
            isCreate: false,
            init() {
                confirmModalInstance = this;
            },
            submitForm() {
                const form = this.isCreate 
                    ? document.getElementById('createClientForm')
                    : document.getElementById('editClientForm');
                if (form) {
                    this.showConfirm = false;
                    form.submit();
                }
            }
        }));
    });

    // Copiar código
    document.querySelectorAll('[data-copy]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const value = btn.getAttribute('data-copy');
            if (!value) return;
            try {
                await navigator.clipboard.writeText(value);
                btn.textContent = 'Copiado';
                setTimeout(() => { btn.textContent = 'Copiar código'; }, 1200);
            } catch (e) {
                btn.textContent = 'Falha';
                setTimeout(() => { btn.textContent = 'Copiar código'; }, 1200);
            }
        });
    });
</script>
HTML;
include __DIR__ . '/../templates/footer.php';


