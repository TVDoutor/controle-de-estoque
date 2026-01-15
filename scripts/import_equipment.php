<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/database.php';

if (PHP_SAPI !== 'cli') {
    exit("Este script deve ser executado via linha de comando.\n");
}

$rawArgs = array_slice($_SERVER['argv'], 1);
$options = getopt('', ['dry-run', 'delimiter:']);

$arguments = [];
foreach ($rawArgs as $value) {
    if (str_starts_with($value, '--')) {
        continue;
    }
    $arguments[] = $value;
}

if (!$arguments) {
    exit("Uso: php scripts/import_equipment.php [--dry-run] [--delimiter=;] caminho/arquivo.csv\n");
}

$dryRun = array_key_exists('dry-run', $options);
$delimiter = $options['delimiter'] ?? null;
$csvPath = $arguments[count($arguments) - 1];

if (!file_exists($csvPath) || !is_readable($csvPath)) {
    exit("Arquivo não encontrado ou sem permissão de leitura: {$csvPath}\n");
}

$pdo = get_pdo();
$defaultUserIdStmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
$defaultUserId = (int) ($defaultUserIdStmt->fetchColumn() ?: 1);

function normalize_header(string $header): string
{
    $header = trim($header);
    if ($header === '') {
        return '';
    }
    $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $header);
    if ($converted !== false) {
        $header = $converted;
    }
    $header = strtolower($header);
    $header = preg_replace(['/[\s\-]+/u', '/[^a-z0-9_]+/u'], ['_', '_'], $header);
    return trim($header, '_');
}

function detect_delimiter(string $line): string
{
    $candidates = [';', ',', "\t"];
    $bestDelimiter = ',';
    $bestCount = 0;
    foreach ($candidates as $candidate) {
        $count = substr_count($line, $candidate);
        if ($count > $bestCount) {
            $bestDelimiter = $candidate;
            $bestCount = $count;
        }
    }
    return $bestDelimiter;
}

function format_mac(string $mac): string
{
    $clean = preg_replace('/[^0-9a-fA-F]/', '', $mac);
    if (strlen($clean) !== 12) {
        return '';
    }
    return implode(':', str_split(strtoupper($clean), 2));
}

function parse_bool(string $value): bool
{
    $normalized = strtolower(trim($value));
    return in_array($normalized, ['1', 'sim', 'true', 'verdadeiro', 'yes'], true);
}

function parse_client(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '') {
        return ['name' => null, 'extra' => []];
    }
    $parts = array_map('trim', explode('|', $raw));
    $name = array_shift($parts) ?: $raw;
    return ['name' => $name, 'extra' => $parts];
}

function ensure_client(PDO $pdo, array $clientInfo): ?int
{
    if (!$clientInfo['name']) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id FROM clients WHERE name = :name LIMIT 1');
    $stmt->execute(['name' => $clientInfo['name']]);
    $found = $stmt->fetchColumn();
    if ($found) {
        return (int) $found;
    }
    $insert = $pdo->prepare('INSERT INTO clients (name, address) VALUES (:name, :address)');
    $address = $clientInfo['extra'] ? implode(' | ', $clientInfo['extra']) : null;
    $insert->execute([
        'name' => $clientInfo['name'],
        'address' => $address,
    ]);
    return (int) $pdo->lastInsertId();
}

function ensure_model(PDO $pdo, string $rawModel): int
{
    $rawModel = trim($rawModel);
    if ($rawModel === '') {
        throw new RuntimeException('Modelo do aparelho não informado.');
    }
    $parts = preg_split('/\s+/', $rawModel, 2);
    $brand = $parts[0] ?? $rawModel;
    $modelName = $parts[1] ?? $rawModel;

    $stmt = $pdo->prepare('SELECT id FROM equipment_models WHERE brand = :brand AND model_name = :model LIMIT 1');
    $stmt->execute([
        'brand' => $brand,
        'model' => $modelName,
    ]);
    $modelId = $stmt->fetchColumn();
    if ($modelId) {
        return (int) $modelId;
    }

    $insert = $pdo->prepare('INSERT INTO equipment_models (category, brand, model_name) VALUES (:category, :brand, :model)');
    $category = stripos($rawModel, 'monitor') !== false ? 'monitor' : 'android_box';
    $insert->execute([
        'category' => $category,
        'brand' => $brand,
        'model' => $modelName,
    ]);

    return (int) $pdo->lastInsertId();
}

function find_equipment(PDO $pdo, string $serial, string $assetTag): ?int
{
    $query = 'SELECT id FROM equipment WHERE serial_number = :serial OR asset_tag = :asset_tag LIMIT 1';
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'serial' => $serial ?: null,
        'asset_tag' => $assetTag,
    ]);
    $id = $stmt->fetchColumn();
    return $id ? (int) $id : null;
}

$handle = fopen($csvPath, 'r');
if (!$handle) {
    exit("Não foi possível abrir o arquivo {$csvPath}.\n");
}

$firstLine = fgets($handle);
if ($firstLine === false) {
    exit("Arquivo CSV vazio.\n");
}
fseek($handle, 0);

$delimiter = $delimiter ?: detect_delimiter($firstLine);
$headers = fgetcsv($handle, 0, $delimiter);
if ($headers === false) {
    exit("Não foi possível ler o cabeçalho do CSV.\n");
}

$normalizedHeaders = array_map('normalize_header', $headers);
$expected = [
    'dados_do_cliente_alocado' => 'cliente',
    'id_do_player' => 'player_id',
    'id_legado_do_player' => 'player_legacy_id',
    'modelo_do_aparelho' => 'model',
    'versao_do_os' => 'os_version',
    'versao_do_app' => 'app_version',
    'numero_de_serie' => 'serial_number',
    'numero_do_serie' => 'serial_number',
    'serial_number' => 'serial_number',
    'endereco_mac' => 'mac_address',
    'endereco_mac_' => 'mac_address',
    'localizacao_lat_long' => 'location',
    'equipamento_desvinculado' => 'is_unlinked',
];

$fieldMap = [];
foreach ($normalizedHeaders as $index => $header) {
    if (isset($expected[$header])) {
        $fieldMap[$expected[$header]] = $index;
    }
}

$requiredFields = ['serial_number', 'model'];
foreach ($requiredFields as $field) {
    if (!isset($fieldMap[$field])) {
        exit("Campo obrigatório não encontrado no CSV: {$field}.\n");
    }
}

$lineNumber = 1;
$summary = [
    'total' => 0,
    'skipped' => 0,
    'created' => 0,
    'updated' => 0,
    'errors' => [],
];

while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
    $lineNumber++;
    if ($row === [null] || $row === false) {
        continue;
    }
    $summary['total']++;

    $extract = static function (string $key) use ($fieldMap, $row): string {
        if (!isset($fieldMap[$key])) {
            return '';
        }
        $index = $fieldMap[$key];
        return isset($row[$index]) ? trim((string) $row[$index]) : '';
    };

    $serial = strtoupper($extract('serial_number'));
    $modelRaw = $extract('model');
    $mac = $extract('mac_address');
    $clientRaw = $extract('cliente');
    $location = $extract('location');
    $playerId = $extract('player_id');
    $playerLegacy = $extract('player_legacy_id');
    $osVersion = $extract('os_version');
    $appVersion = $extract('app_version');
    $isUnlinked = parse_bool($extract('is_unlinked'));

    if ($serial === '') {
        $summary['errors'][] = "Linha {$lineNumber}: número de série não informado.";
        $summary['skipped']++;
        continue;
    }

    try {
        $macFormatted = $mac !== '' ? format_mac($mac) : '';
        if ($mac !== '' && $macFormatted === '') {
            throw new RuntimeException('Endereço MAC inválido.');
        }

        $clientInfo = parse_client($clientRaw);
        $clientId = null;

        if (!$dryRun) {
            $pdo->beginTransaction();
        }

        $modelId = ensure_model($pdo, $modelRaw);

        if ($clientInfo['name']) {
            $clientId = ensure_client($pdo, $clientInfo);
        }

        $assetTag = $serial !== '' ? $serial : 'TAG-' . strtoupper(bin2hex(random_bytes(3)));
        $existingId = find_equipment($pdo, $serial, $assetTag);

        $status = $isUnlinked ? 'baixado' : ($clientId ? 'alocado' : 'em_estoque');
        $condition = 'usado';
        $entryDate = date('Y-m-d');

        if ($existingId) {
            $update = $pdo->prepare(<<<SQL
                UPDATE equipment
                SET model_id = :model_id,
                    mac_address = :mac_address,
                    status = :status,
                    current_client_id = :client_id,
                    notes = :notes,
                    updated_at = NOW()
                WHERE id = :id
SQL);
            $notes = trim('Importado em ' . date('d/m/Y H:i') . ' via script.');
            $update->execute([
                'model_id' => $modelId,
                'mac_address' => $macFormatted ?: null,
                'status' => $status,
                'client_id' => $clientId,
                'notes' => $notes !== '' ? $notes : null,
                'id' => $existingId,
            ]);
            $equipmentId = $existingId;
            $summary['updated']++;
        } else {
            $insertEquipment = $pdo->prepare(<<<SQL
                INSERT INTO equipment (asset_tag, model_id, serial_number, mac_address, condition_status, status, entry_date, batch, notes, current_client_id, created_by)
                VALUES (:asset_tag, :model_id, :serial_number, :mac_address, :condition_status, :status, :entry_date, :batch, :notes, :client_id, :created_by)
SQL);
            $notes = [];
            if ($location !== '') {
                $notes[] = 'Localização: ' . $location;
            }
            $notes[] = 'Importado em ' . date('d/m/Y H:i');
            $notesText = implode("\n", $notes);
            $insertEquipment->execute([
                'asset_tag' => $assetTag,
                'model_id' => $modelId,
                'serial_number' => $serial,
                'mac_address' => $macFormatted ?: null,
                'condition_status' => $condition,
                'status' => $status,
                'entry_date' => $entryDate,
                'batch' => null,
                'notes' => $notesText,
                'client_id' => $clientId,
                'created_by' => $defaultUserId,
            ]);
            $equipmentId = (int) $pdo->lastInsertId();

            $operation = $pdo->prepare('INSERT INTO equipment_operations (operation_type, operation_date, notes, client_id, performed_by) VALUES (:type, NOW(), :notes, :client_id, :performed_by)');
            $operation->execute([
                'type' => 'ENTRADA',
                'notes' => 'Importação massiva de equipamentos.',
                'client_id' => $clientId,
                'performed_by' => $defaultUserId,
            ]);
            $operationId = (int) $pdo->lastInsertId();

            $insertItem = $pdo->prepare('INSERT INTO equipment_operation_items (operation_id, equipment_id) VALUES (:operation_id, :equipment_id)');
            $insertItem->execute([
                'operation_id' => $operationId,
                'equipment_id' => $equipmentId,
            ]);

            $summary['created']++;
        }

        $details = array_filter([
            'ID do Player' => $playerId,
            'ID legado do Player' => $playerLegacy,
            'Versão do OS' => $osVersion,
            'Versão do App' => $appVersion,
            'Equipamento desvinculado' => $isUnlinked ? 'Sim' : 'Não',
        ], static fn ($value) => $value !== '' && $value !== null);

        if ($details) {
            $noteText = "Dados técnicos importados:\n";
            foreach ($details as $label => $value) {
                $noteText .= "- {$label}: {$value}\n";
            }
            if (!$dryRun) {
                $noteStmt = $pdo->prepare('INSERT INTO equipment_notes (equipment_id, user_id, note) VALUES (:equipment_id, :user_id, :note)');
                $noteStmt->execute([
                    'equipment_id' => $equipmentId,
                'user_id' => $defaultUserId,
                    'note' => trim($noteText),
                ]);
            }
        }

        if ($dryRun && $pdo->inTransaction()) {
            $pdo->rollBack();
        } elseif (!$dryRun && $pdo->inTransaction()) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if (!$dryRun && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $summary['errors'][] = 'Linha ' . $lineNumber . ': ' . $exception->getMessage();
        $summary['skipped']++;
    }
}
fclose($handle);

echo "Arquivo: {$csvPath}\n";
echo 'Modo: ' . ($dryRun ? 'simulação (dry-run)' : 'importação real') . "\n";
echo "Total de linhas processadas: {$summary['total']}\n";
echo "Inseridos: {$summary['created']} | Atualizados: {$summary['updated']} | Ignorados: {$summary['skipped']}\n";

if ($summary['errors']) {
    echo "Erros:\n";
    foreach ($summary['errors'] as $error) {
        echo ' - ' . $error . "\n";
    }
}
