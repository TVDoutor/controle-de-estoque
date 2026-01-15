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
$query = 'SELECT * FROM clients';
$params = [];
if ($search !== '') {
    $query .= ' WHERE client_code LIKE :term OR name LIKE :term OR cnpj LIKE :term';
    $params['term'] = '%' . $search . '%';
}
$query .= sprintf(' ORDER BY %s %s', $orderBy, strtoupper($dir));
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$clients = $stmt->fetchAll();

$buildSortLink = static function (string $key) use ($sort, $dir): string {
    $params = $_GET;
    $params['sort'] = $key;
    $params['dir'] = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
    return '?' . http_build_query($params);
};

$sortIndicator = static function (string $key) use ($sort, $dir): string {
    if ($sort !== $key) {
        return '';
    }
    return $dir === 'asc' ? '^' : 'v';
};

$editingClient = null;
if ($canManage && isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    foreach ($clients as $client) {
        if ((int) $client['id'] === $editId) {
            $editingClient = $client;
            break;
        }
    }
}

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
                    <input type="text" name="search" value="<?= sanitize($search); ?>" placeholder="Buscar por nome, código ou CNPJ" class="surface-field-compact w-64">
                    <button type="submit" class="rounded-xl bg-slate-800 px-4 py-2 font-semibold text-white hover:bg-slate-700">Pesquisar</button>
                </form>
                <?php if ($canManage): ?>
                    <a href="clientes.php" class="text-sm text-blue-300 hover:text-blue-200">Limpar filtros</a>
                <?php endif; ?>
            </div>
            <?php if ($search !== ''): ?>
                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="surface-chip">Busca: <?= sanitize($search); ?></span>
                </div>
            <?php endif; ?>
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
                            <?php if ($canManage): ?>
                                <th class="surface-table-cell text-right">Ações</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="surface-table-body">
                        <?php if (!$clients): ?>
                            <tr>
                                <td colspan="<?= $canManage ? '6' : '5'; ?>" class="surface-table-cell text-center surface-muted">Nenhum cliente encontrado.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($clients as $client): ?>
                            <tr class="group hover:bg-slate-800/40">
                                <td class="surface-table-cell font-semibold"><?= sanitize($client['client_code']); ?></td>
                                <td class="surface-table-cell"><?= sanitize($client['name']); ?></td>
                                <td class="surface-table-cell surface-muted"><?= sanitize($client['contact_name'] ?? '-'); ?></td>
                                <td class="surface-table-cell surface-muted"><?= sanitize($client['phone'] ?? '-'); ?></td>
                                <td class="surface-table-cell surface-muted"><?= sanitize(($client['city'] ?? '-') . '/' . ($client['state'] ?? '-')); ?></td>
                                <?php if ($canManage): ?>
                                    <td class="surface-table-cell text-right">
                                        <div class="flex items-center justify-end gap-3 opacity-0 transition group-hover:opacity-100">
                                            <button type="button" class="text-xs font-semibold text-slate-200 hover:text-blue-200" data-copy="<?= sanitize($client['client_code']); ?>">Copiar código</button>
                                            <a href="clientes.php?edit=<?= (int) $client['id']; ?>" class="text-xs font-semibold text-blue-300 hover:text-blue-200">Editar</a>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($canManage): ?>
            <div class="surface-card">
                <h2 class="surface-heading">
                    <?= $editingClient ? 'Editar cliente' : 'Cadastrar novo cliente'; ?>
                </h2>
                <form method="post" class="mt-4 grid gap-4 md:grid-cols-2">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(ensure_csrf_token()); ?>">
                    <input type="hidden" name="action" value="<?= $editingClient ? 'update' : 'create'; ?>">
                    <?php if ($editingClient): ?>
                        <input type="hidden" name="client_id" value="<?= (int) $editingClient['id']; ?>">
                    <?php endif; ?>
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="client_code">Código</label>
                        <input type="text" id="client_code" name="client_code" required value="<?= sanitize($editingClient['client_code'] ?? ($_POST['client_code'] ?? '')); ?>" class="surface-field">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="name">Razão social / Nome</label>
                        <input type="text" id="name" name="name" required value="<?= sanitize($editingClient['name'] ?? ($_POST['name'] ?? '')); ?>" class="surface-field">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="cnpj">CNPJ</label>
                        <input type="text" id="cnpj" name="cnpj" value="<?= sanitize($editingClient['cnpj'] ?? ($_POST['cnpj'] ?? '')); ?>" class="surface-field" data-mask="cnpj" inputmode="numeric" placeholder="00.000.000/0000-00" pattern="\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}" title="Formato esperado: 00.000.000/0000-00">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="contact_name">Contato</label>
                        <input type="text" id="contact_name" name="contact_name" value="<?= sanitize($editingClient['contact_name'] ?? ($_POST['contact_name'] ?? '')); ?>" class="surface-field">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="phone">Telefone</label>
                        <input type="text" id="phone" name="phone" value="<?= sanitize($editingClient['phone'] ?? ($_POST['phone'] ?? '')); ?>" class="surface-field" data-mask="phone" inputmode="tel" placeholder="(00) 00000-0000">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="email">E-mail</label>
                        <input type="email" id="email" name="email" value="<?= sanitize($editingClient['email'] ?? ($_POST['email'] ?? '')); ?>" class="surface-field">
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium text-slate-300" for="address">Endereço</label>
                        <input type="text" id="address" name="address" value="<?= sanitize($editingClient['address'] ?? ($_POST['address'] ?? '')); ?>" class="surface-field">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="city">Cidade</label>
                        <input type="text" id="city" name="city" value="<?= sanitize($editingClient['city'] ?? ($_POST['city'] ?? '')); ?>" class="surface-field">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="state">Estado</label>
                        <input type="text" id="state" name="state" maxlength="2" value="<?= sanitize($editingClient['state'] ?? ($_POST['state'] ?? '')); ?>" class="surface-field uppercase">
                    </div>
                    <div class="md:col-span-2 flex justify-end gap-3">
                        <?php if ($editingClient): ?>
                            <a href="clientes.php" class="rounded-xl border border-slate-600 px-4 py-2 text-sm text-slate-200 hover:bg-slate-800/40">Cancelar</a>
                        <?php endif; ?>
                        <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">Salvar cliente</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </section>
<?php
$footerScripts = <<<HTML
<script>
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


