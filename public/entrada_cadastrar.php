<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login(['admin', 'gestor']);

$pageTitle = 'Cadastrar Entrada';
$activeMenu = 'entradas';
$pdo = get_pdo();
$user = current_user();
$errors = [];

$modelsStmt = $pdo->query('SELECT id, brand, model_name, category, monitor_size FROM equipment_models WHERE is_active = 1 ORDER BY category, brand, model_name');
$models = $modelsStmt->fetchAll();

$modelOptions = array_map(static function (array $model): array {
    $category = strtoupper((string) $model['category']) === 'MONITOR' ? 'Monitor' : 'Box';
    $suffix = $model['monitor_size'] ? ' (' . $model['monitor_size'] . ")" : '';
    return [
        'id' => (int) $model['id'],
        'label' => sprintf('%s · %s %s%s', $category, $model['brand'], $model['model_name'], $suffix),
    ];
}, $models);

$formValues = [
    'asset_tag' => $_POST['asset_tag'] ?? '',
    'model_id' => (int) ($_POST['model_id'] ?? 0),
    'serial_number' => $_POST['serial_number'] ?? '',
    'mac_address' => $_POST['mac_address'] ?? '',
    'purchase_date' => $_POST['purchase_date'] ?? '',
    'supplier' => $_POST['supplier'] ?? '',
    'condition_status' => $_POST['condition_status'] ?? 'novo',
    'entry_date' => $_POST['entry_date'] ?? date('Y-m-d'),
    'batch' => $_POST['batch'] ?? '',
    'notes' => $_POST['notes'] ?? '',
    'player_id' => $_POST['player_id'] ?? '',
    'player_legacy_id' => $_POST['player_legacy_id'] ?? '',
    'os_version' => $_POST['os_version'] ?? '',
    'app_version' => $_POST['app_version'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sessão expirada. Recarregue a página.';
    } else {
        $assetTag = strtoupper(trim((string) ($_POST['asset_tag'] ?? '')));
        $modelId = (int) ($_POST['model_id'] ?? 0);
        $serial = strtoupper(trim((string) ($_POST['serial_number'] ?? '')));
        $macInput = trim((string) ($_POST['mac_address'] ?? ''));
        $purchaseDate = trim((string) ($_POST['purchase_date'] ?? ''));
        $supplier = trim((string) ($_POST['supplier'] ?? ''));
        $condition = $_POST['condition_status'] ?? 'novo';
        $entryDate = $_POST['entry_date'] ?? date('Y-m-d');
        $batch = trim((string) ($_POST['batch'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $playerId = trim((string) ($_POST['player_id'] ?? ''));
        $playerLegacyId = trim((string) ($_POST['player_legacy_id'] ?? ''));
        $osVersion = trim((string) ($_POST['os_version'] ?? ''));
        $appVersion = trim((string) ($_POST['app_version'] ?? ''));

        if ($serial === '') {
            $errors[] = 'Informe o número de série.';
        }

        if ($modelId === 0) {
            $errors[] = 'Selecione o modelo do equipamento.';
        }

        if ($assetTag === '' && $serial !== '') {
            $assetTag = $serial;
        }

        if ($assetTag === '') {
            $assetTag = 'TAG-' . strtoupper(bin2hex(random_bytes(3)));
        }

        if ($macInput !== '') {
            $normalizedMac = strtoupper(str_replace(['-', ':', ' '], '', $macInput));
            if (!preg_match('/^[0-9A-Z]{12}$/', $normalizedMac)) {
                $errors[] = 'Endereço MAC inválido. Use o formato XX:XX:XX:XX:XX:XX com caracteres alfanuméricos.';
            } else {
                $macInput = implode(':', str_split($normalizedMac, 2));
            }
        }

        $formValues['asset_tag'] = $assetTag;
        $formValues['serial_number'] = $serial;
        $formValues['mac_address'] = $macInput;
        $formValues['purchase_date'] = $purchaseDate;
        $formValues['supplier'] = $supplier;
        $formValues['condition_status'] = $condition;
        $formValues['entry_date'] = $entryDate;
        $formValues['batch'] = $batch;
        $formValues['notes'] = $notes;
        $formValues['player_id'] = $playerId;
        $formValues['player_legacy_id'] = $playerLegacyId;
        $formValues['os_version'] = $osVersion;
        $formValues['app_version'] = $appVersion;

        if (!in_array($condition, ['novo', 'usado'], true)) {
            $errors[] = 'Condição inválida.';
            $condition = 'usado';
        }

        $dateObject = DateTime::createFromFormat('Y-m-d', $entryDate);
        if (!$dateObject || $dateObject->format('Y-m-d') !== $entryDate) {
            $errors[] = 'Data de entrada inválida.';
        }

        $modelExists = array_filter($models, static fn (array $model): bool => (int) $model['id'] === $modelId);
        if (!$modelExists) {
            $errors[] = 'Modelo inválido.';
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();

                $status = 'em_estoque';

                $insertEquipment = $pdo->prepare(<<<SQL
                    INSERT INTO equipment (asset_tag, model_id, serial_number, mac_address, condition_status, status, entry_date, batch, notes, created_by)
                    VALUES (:asset_tag, :model_id, :serial_number, :mac_address, :condition_status, :status, :entry_date, :batch, :notes, :created_by)
SQL);
                $insertEquipment->execute([
                    'asset_tag' => $assetTag,
                    'model_id' => $modelId,
                    'serial_number' => $serial ?: null,
                    'mac_address' => $macInput ?: null,
                    'condition_status' => $condition,
                    'status' => $status,
                    'entry_date' => $entryDate,
                    'batch' => $batch ?: null,
                    'notes' => $notes ?: null,
                    'created_by' => $user['id'],
                ]);

                $equipmentId = (int) $pdo->lastInsertId();

                $insertOperation = $pdo->prepare(<<<SQL
                    INSERT INTO equipment_operations (operation_type, operation_date, notes, performed_by)
                    VALUES ('ENTRADA', NOW(), :notes, :performed_by)
SQL);
                $insertOperation->execute([
                    'notes' => $notes ?: null,
                    'performed_by' => $user['id'],
                ]);
                $operationId = (int) $pdo->lastInsertId();

                $insertItem = $pdo->prepare('INSERT INTO equipment_operation_items (operation_id, equipment_id) VALUES (:operation_id, :equipment_id)');
                $insertItem->execute([
                    'operation_id' => $operationId,
                    'equipment_id' => $equipmentId,
                ]);

                $technicalDetails = array_filter([
                    'ID do Player' => $playerId,
                    'ID legado do Player' => $playerLegacyId,
                    'Versão do OS' => $osVersion,
                    'Versão do App' => $appVersion,
                ], static fn (string $value): bool => $value !== '');

                if ($technicalDetails) {
                    $lines = [];
                    foreach ($technicalDetails as $label => $value) {
                        $lines[] = $label . ': ' . $value;
                    }
                    $extraNote = "Detalhes técnicos registrados no cadastro:
" . implode("
", $lines);
                    $insertNote = $pdo->prepare('INSERT INTO equipment_notes (equipment_id, user_id, note) VALUES (:equipment_id, :user_id, :note)');
                    $insertNote->execute([
                        'equipment_id' => $equipmentId,
                        'user_id' => $user['id'],
                        'note' => $extraNote,
                    ]);
                }

                $pdo->commit();

                $serialForMessage = $serial !== '' ? $serial : $assetTag;
                flash('success', "Equipamento {$serialForMessage} cadastrado com sucesso!", 'success');
                redirect('entrada_cadastrar.php');
            } catch (PDOException $exception) {
                $pdo->rollBack();
                if ((int) $exception->errorInfo[1] === 1062) {
                    $errors[] = 'Já existe um equipamento com esta etiqueta.';
                } else {
                    $errors[] = 'Não foi possível salvar o equipamento.';
                }
            }
        }
    }
}

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>
<main class="flex-1 flex flex-col bg-slate-950 text-slate-100">
    <?php include __DIR__ . '/../templates/topbar.php'; ?>
    <section class="flex-1 overflow-y-auto px-6 pb-12">
        <div class="mx-auto max-w-5xl space-y-6">
            <?php if ($flash = flash('success')): ?>
                <div class="rounded-2xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-4 text-sm text-emerald-200 shadow-lg shadow-emerald-900/20">
                    <?= sanitize($flash['message']); ?>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="rounded-2xl border border-red-500/40 bg-red-500/10 px-4 py-4 text-sm text-red-200 shadow-lg shadow-red-900/20">
                    <ul class="list-disc space-y-1 pl-5">
                        <?php foreach ($errors as $error): ?>
                            <li><?= sanitize($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" class="space-y-8" x-data="equipmentForm(equipmentFormInitial)" @submit="handleSubmit" @model-selected.window="form.model = $event.detail">
                <input type="hidden" name="csrf_token" value="<?= sanitize(ensure_csrf_token()); ?>">

                <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-slate-900/30">
                    <header class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Seção 1</p>
                            <h2 class="text-lg font-semibold text-slate-800 dark:text-white">Cadastro de Equipamentos</h2>
                        </div>
                        <p class="text-sm text-slate-400 sm:max-w-sm">Preciso registrar o aparelho que estou segurando.</p>
                    </header>
                    <div class="mt-6 grid gap-5 md:grid-cols-2">
                        <div class="md:col-span-1">
                            <label class="text-sm font-medium text-slate-300" for="serial_number">Número de série <span class="text-rose-400">*</span></label>
                            <input type="text" id="serial_number" name="serial_number" required autofocus x-model="form.serial" value="<?= sanitize($formValues['serial_number']); ?>" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white focus:border-blue-400 focus:outline-none focus:ring-0" placeholder="Ex.: SN123456789">
                            <p class="mt-1 text-xs text-slate-400">Utilize o leitor de código de barras para preencher automaticamente.</p>
                        </div>
                        <div class="md:col-span-1">
                            <label class="text-sm font-medium text-slate-300" for="mac_address">Endereço MAC</label>
                            <input type="text" id="mac_address" name="mac_address" x-model="form.mac" @input="formatMac($event.target.value)" value="<?= sanitize($formValues['mac_address']); ?>" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white focus:border-blue-400 focus:outline-none focus:ring-0" placeholder="XX:XX:XX:XX:XX:XX" pattern="^([0-9A-Za-z]{2}:){5}[0-9A-Za-z]{2}$" inputmode="text" autocomplete="off">
                            <p class="mt-1 text-xs text-slate-400">Formato automático: letras maiúsculas e separação por dois pontos.</p>
                        </div>
                        <div class="md:col-span-1">
                            <label class="text-sm font-medium text-slate-300" for="asset_tag">Etiqueta interna</label>
                            <input type="text" id="asset_tag" name="asset_tag" x-model="form.assetTag" value="<?= sanitize($formValues['asset_tag']); ?>" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white focus:border-blue-400 focus:outline-none focus:ring-0" placeholder="Opcional">
                            <p class="mt-1 text-xs text-slate-400">Se estiver vazio, utilizaremos o número de série como etiqueta.</p>
                        </div>
                        <div class="md:col-span-1">
                            <label class="text-sm font-medium text-slate-300">Modelo <span class="text-rose-400">*</span></label>
                            <div class="relative mt-2" x-data="modelPicker(modelPickerOptions, equipmentFormInitial.model)">
                                <button type="button" class="flex w-full items-center justify-between rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-slate-200 transition hover:border-blue-400" @click="toggle()" :aria-expanded="open">
                                    <span x-text="selected ? selected.label : 'Selecione ou pesquise um modelo'"></span>
                                    <span class="material-icons-outlined text-base text-slate-500">expand_more</span>
                                </button>
                                <input type="hidden" name="model_id" :value="selected ? selected.id : ''" required>
                                <div x-cloak x-show="open" @click.outside="open = false" x-transition class="absolute z-20 mt-2 w-full overflow-hidden rounded-2xl border border-slate-700 bg-slate-950 shadow-2xl shadow-slate-900/40">
                                    <div class="border-b border-slate-800 bg-slate-900/70 p-2">
                                        <input x-ref="search" x-model="query" type="search" placeholder="Pesquisar modelos" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white focus:border-blue-400 focus:outline-none focus:ring-0">
                                    </div>
                                    <ul class="max-h-64 overflow-y-auto">
                                        <template x-if="filtered.length === 0">
                                            <li class="px-4 py-3 text-sm text-slate-400">Nenhum modelo encontrado.</li>
                                        </template>
                                        <template x-for="option in filtered" :key="option.id">
                                            <li>
                                                <button type="button" class="flex w-full items-start gap-2 px-4 py-2 text-left text-sm text-slate-200 hover:bg-slate-800/70" @click="choose(option)">
                                                    <span class="material-icons-outlined text-base text-blue-300">precision_manufacturing</span>
                                                    <span x-text="option.label"></span>
                                                </button>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="md:col-span-1">
                            <label class="text-sm font-medium text-slate-300" for="purchase_date">Data da compra</label>
                            <input type="date" id="purchase_date" name="purchase_date" value="<?= sanitize($formValues['purchase_date']); ?>" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white focus:border-blue-400 focus:outline-none focus:ring-0">
                        </div>
                        <div class="md:col-span-1">
                            <label class="text-sm font-medium text-slate-300" for="supplier">Fornecedor</label>
                            <input type="text" id="supplier" name="supplier" value="<?= sanitize($formValues['supplier']); ?>" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white focus:border-blue-400 focus:outline-none focus:ring-0" placeholder="Nome do fornecedor">
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-slate-900/30">
                    <header class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Seção 2</p>
                            <h2 class="text-lg font-semibold text-slate-800 dark:text-white">Diagnóstico e status</h2>
                        </div>
                    </header>
                    <div class="mt-6 grid gap-5 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-slate-300" for="condition_status">Condição do Equipamento</label>
                            <select id="condition_status" name="condition_status" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white focus:border-blue-400 focus:outline-none focus:ring-0">
                                <option value="novo" <?= $formValues['condition_status'] === 'novo' ? 'selected' : ''; ?>>Novo</option>
                                <option value="usado" <?= $formValues['condition_status'] === 'usado' ? 'selected' : ''; ?>>Usado</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-300" for="entry_date">Data de entrada</label>
                            <input type="date" id="entry_date" name="entry_date" value="<?= sanitize($formValues['entry_date']); ?>" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white focus:border-blue-400 focus:outline-none focus:ring-0">
                        </div>
                    </div>
                </section>

                <details class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-slate-900/30">
                    <summary class="cursor-pointer list-none">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Seção 3</p>
                                <h2 class="text-lg font-semibold text-slate-800 dark:text-white">Detalhes de software e controle (avançado)</h2>
                            </div>
                            <p class="text-sm text-slate-400 sm:max-w-sm">Opcional. Expanda para registrar dados internos.</p>
                        </div>
                    </summary>
                    <div class="mt-6 grid gap-5 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-slate-300" for="player_id">ID do Player</label>
                            <input type="text" id="player_id" name="player_id" value="<?= sanitize($formValues['player_id']); ?>" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white focus:border-blue-400 focus:outline-none focus:ring-0" placeholder="Opcional">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-300" for="player_legacy_id">ID legado do Player</label>
                            <input type="text" id="player_legacy_id" name="player_legacy_id" value="<?= sanitize($formValues['player_legacy_id']); ?>" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white focus:border-blue-400 focus:outline-none focus:ring-0" placeholder="Opcional">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-300" for="os_version">Versão do OS</label>
                            <input type="text" id="os_version" name="os_version" value="<?= sanitize($formValues['os_version']); ?>" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white focus:border-blue-400 focus:outline-none focus:ring-0" placeholder="Ex.: Android 12">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-300" for="app_version">Versão do App</label>
                            <input type="text" id="app_version" name="app_version" value="<?= sanitize($formValues['app_version']); ?>" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white focus:border-blue-400 focus:outline-none focus:ring-0" placeholder="Ex.: 3.2.1">
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-sm font-medium text-slate-300" for="batch">Lote</label>
                            <input type="text" id="batch" name="batch" value="<?= sanitize($formValues['batch']); ?>" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white focus:border-blue-400 focus:outline-none focus:ring-0" placeholder="Número ou identificação do lote">
                        </div>
                    </div>
                </details>

                <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-slate-900/30">
                    <header>
                        <p class="text-xs uppercase tracking-[0.3em] text-slate-400">Seção 4</p>
                        <h2 class="mt-2 text-lg font-semibold text-slate-800 dark:text-white">Informações adicionais</h2>
                        <p class="mt-2 text-sm text-slate-400">Deixe observações específicas sobre o equipamento.</p>
                    </header>
                    <div class="mt-6">
                        <label class="text-sm font-medium text-slate-300" for="notes">Observações</label>
                        <textarea id="notes" name="notes" rows="4" class="mt-2 w-full resize-y rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white focus:border-blue-400 focus:outline-none focus:ring-0" placeholder="Use este espaço para anotações internas."><?= sanitize($formValues['notes']); ?></textarea>
                    </div>
                </section>

                <div class="flex items-center justify-end gap-3">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:bg-slate-700 disabled:text-slate-300" :disabled="!canSubmit || isSubmitting">
                        <span class="material-icons-outlined text-base" x-show="!isSubmitting">inventory_2</span>
                        <svg x-cloak x-show="isSubmitting" class="h-4 w-4 animate-spin text-white" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        <span>Cadastrar equipamento</span>
                    </button>
                </div>
            </form>
        </div>
    </section>
<?php
$equipmentFormInitial = [
    'serial' => $formValues['serial_number'],
    'model' => $formValues['model_id'],
    'mac' => $formValues['mac_address'],
    'assetTag' => $formValues['asset_tag'],
];
$equipmentFormInitialJson = json_encode($equipmentFormInitial, JSON_UNESCAPED_UNICODE);
$modelOptionsJson = json_encode($modelOptions, JSON_UNESCAPED_UNICODE);
$footerScripts = <<<HTML
<script>
const equipmentFormInitial = {$equipmentFormInitialJson};
const modelPickerOptions = {$modelOptionsJson};
document.addEventListener('alpine:init', () => {
    Alpine.data('equipmentForm', (initial) => ({
        form: {
            serial: initial.serial ?? '',
            model: initial.model ?? '',
            mac: initial.mac ?? '',
            assetTag: initial.assetTag ?? '',
        },
        isSubmitting: false,
        get canSubmit() {
            return this.form.serial.trim() !== '' && String(this.form.model || '').trim() !== '';
        },
        handleSubmit(event) {
            if (!this.canSubmit || this.isSubmitting) {
                event.preventDefault();
                return;
            }
            this.isSubmitting = true;
        },
        formatMac(value) {
            const cleaned = (value || '').replace(/[^0-9a-zA-Z]/g, '').slice(0, 12).toUpperCase();
            const parts = cleaned.match(/.{1,2}/g) || [];
            const formatted = parts.join(':');
            this.form.mac = formatted;
        },
    }));

    Alpine.data('modelPicker', (options, initial) => ({
        open: false,
        query: '',
        options: options.map(option => ({ ...option, search: option.label.toLowerCase() })),
        filtered: [],
        selected: null,
        init() {
            if (initial) {
                this.selected = this.options.find(opt => opt.id === initial) || null;
            }
            this.filtered = this.options;
            if (this.selected) {
                this.\$dispatch('model-selected', this.selected.id);
            }
            this.\$watch('query', (value) => {
                const term = value.trim().toLowerCase();
                this.filtered = term ? this.options.filter(opt => opt.search.includes(term)) : this.options;
            });
        },
        toggle() {
            this.open = !this.open;
            if (this.open) {
                this.\$nextTick(() => this.\$refs.search?.focus());
            }
        },
        choose(option) {
            this.selected = option;
            this.open = false;
            this.query = '';
            this.filtered = this.options;
            this.\$dispatch('model-selected', option.id);
        },
    }));
});
</script>
HTML;
include __DIR__ . '/../templates/footer.php';
