<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login();

$pageTitle = 'Clientes';
$activeMenu = 'clientes';
$pdo = get_pdo();
$user = current_user();
$canManage = user_has_role(['admin', 'gestor']);
$errors = [];

if ($canManage && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sesso expirada. Recarregue a pgina.';
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
            $errors[] = 'Informe o cdigo e o nome do cliente.';
        }

        if (!$errors) {
            try {
                if ($action === 'update') {
                    $clientId = (int) ($_POST['client_id'] ?? 0);
                    if ($clientId <= 0) {
                        throw new RuntimeException('Cliente invlido.');
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
                    $errors[] = 'J existe um cliente com este cdigo.';
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
$query = 'SELECT * FROM clients';
$params = [];
if ($search !== '') {
    $query .= ' WHERE client_code LIKE :term OR name LIKE :term OR cnpj LIKE :term';
    $params['term'] = '%' . $search . '%';
}
$query .= ' ORDER BY name';
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$clients = $stmt->fetchAll();

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
            <div class="rounded border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                <?= sanitize($flash['message']); ?>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <ul class="list-disc space-y-1 pl-5">
                    <?php foreach ($errors as $error): ?>
                        <li><?= sanitize($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <form method="get" class="flex gap-2 text-sm">
                    <input type="text" name="search" value="<?= sanitize($search); ?>" placeholder="Buscar por nome, código ou CNPJ" class="w-64 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 font-semibold text-white hover:bg-slate-900">Pesquisar</button>
                </form>
                <?php if ($canManage): ?>
                    <a href="clientes.php" class="text-sm text-blue-600 hover:text-blue-700">Limpar filtros</a>
                <?php endif; ?>
            </div>
            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-3 text-left">Código</th>
                            <th class="px-3 py-3 text-left">Nome</th>
                            <th class="px-3 py-3 text-left">Contato</th>
                            <th class="px-3 py-3 text-left">Telefone</th>
                            <th class="px-3 py-3 text-left">Cidade/UF</th>
                            <?php if ($canManage): ?>
                                <th class="px-3 py-3 text-right">Ações</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (!$clients): ?>
                            <tr>
                                <td colspan="<?= $canManage ? '6' : '5'; ?>" class="px-3 py-4 text-center text-slate-500">Nenhum cliente encontrado.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($clients as $client): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-3 font-semibold text-slate-700"><?= sanitize($client['client_code']); ?></td>
                                <td class="px-3 py-3 text-slate-600"><?= sanitize($client['name']); ?></td>
                                <td class="px-3 py-3 text-slate-500"><?= sanitize($client['contact_name'] ?? '-'); ?></td>
                                <td class="px-3 py-3 text-slate-500"><?= sanitize($client['phone'] ?? '-'); ?></td>
                                <td class="px-3 py-3 text-slate-500"><?= sanitize(($client['city'] ?? '-') . '/' . ($client['state'] ?? '-')); ?></td>
                                <?php if ($canManage): ?>
                                    <td class="px-3 py-3 text-right">
                                        <a href="clientes.php?edit=<?= (int) $client['id']; ?>" class="text-xs font-semibold text-blue-600 hover:text-blue-700">Editar</a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($canManage): ?>
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-800">
                    <?= $editingClient ? 'Editar cliente' : 'Cadastrar novo cliente'; ?>
                </h2>
                <form method="post" class="mt-4 grid gap-4 md:grid-cols-2">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(ensure_csrf_token()); ?>">
                    <input type="hidden" name="action" value="<?= $editingClient ? 'update' : 'create'; ?>">
                    <?php if ($editingClient): ?>
                        <input type="hidden" name="client_id" value="<?= (int) $editingClient['id']; ?>">
                    <?php endif; ?>
                    <div>
                        <label class="text-sm font-medium text-slate-600" for="client_code">Cdigo</label>
                        <input type="text" id="client_code" name="client_code" required value="<?= sanitize($editingClient['client_code'] ?? ($_POST['client_code'] ?? '')); ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-600" for="name">Razo social / Nome</label>
                        <input type="text" id="name" name="name" required value="<?= sanitize($editingClient['name'] ?? ($_POST['name'] ?? '')); ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-600" for="cnpj">CNPJ</label>
                        <input type="text" id="cnpj" name="cnpj" value="<?= sanitize($editingClient['cnpj'] ?? ($_POST['cnpj'] ?? '')); ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-600" for="contact_name">Contato</label>
                        <input type="text" id="contact_name" name="contact_name" value="<?= sanitize($editingClient['contact_name'] ?? ($_POST['contact_name'] ?? '')); ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-600" for="phone">Telefone</label>
                        <input type="text" id="phone" name="phone" value="<?= sanitize($editingClient['phone'] ?? ($_POST['phone'] ?? '')); ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-600" for="email">E-mail</label>
                        <input type="email" id="email" name="email" value="<?= sanitize($editingClient['email'] ?? ($_POST['email'] ?? '')); ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium text-slate-600" for="address">Endereo</label>
                        <input type="text" id="address" name="address" value="<?= sanitize($editingClient['address'] ?? ($_POST['address'] ?? '')); ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-600" for="city">Cidade</label>
                        <input type="text" id="city" name="city" value="<?= sanitize($editingClient['city'] ?? ($_POST['city'] ?? '')); ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-600" for="state">Estado</label>
                        <input type="text" id="state" name="state" maxlength="2" value="<?= sanitize($editingClient['state'] ?? ($_POST['state'] ?? '')); ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase focus:border-blue-500 focus:outline-none">
                    </div>
                    <div class="md:col-span-2 flex justify-end gap-3">
                        <?php if ($editingClient): ?>
                            <a href="clientes.php" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Cancelar</a>
                        <?php endif; ?>
                        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Salvar cliente</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </section>
<?php include __DIR__ . '/../templates/footer.php';


