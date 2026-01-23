<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login(['admin']);

// Aumentar timeout para importações grandes
set_time_limit(300); // 5 minutos
ini_set('max_execution_time', '300');

$pdo = get_pdo();
$user = current_user();
$errors = [];
$success = [];
$summary = [
    'created' => 0,
    'updated' => 0,
    'equipment_linked' => 0,
    'equipment_errors' => 0,
    'errors' => [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sessão expirada. Recarregue a página.';
    } elseif (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Erro ao fazer upload do arquivo. Verifique se o arquivo foi selecionado.';
    } else {
        $file = $_FILES['import_file'];
        $fileName = $file['name'];
        $fileTmpPath = $file['tmp_name'];
        $fileSize = $file['size'];
        $fileType = $file['type'];

        if ($fileSize > 10 * 1024 * 1024) {
            $errors[] = 'Arquivo muito grande. Tamanho máximo: 10MB.';
        } else {
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (!in_array($extension, ['csv', 'xlsx', 'xls'], true)) {
                $errors[] = 'Formato de arquivo não suportado. Use CSV, XLS ou XLSX.';
            } else {
                try {
                    $pdo->beginTransaction();

                    $rows = [];
                    if ($extension === 'csv') {
                        $rows = read_csv_file($fileTmpPath);
                    } else {
                        $rows = read_excel_file($fileTmpPath);
                    }

                    if (empty($rows)) {
                        throw new RuntimeException('Arquivo vazio ou sem dados válidos.');
                    }

                    $firstRow = $rows[0];
                    $originalHeaders = array_keys($firstRow);
                    
                    $headerMap = normalize_headers($originalHeaders);

                    // Otimização: carregar todos os clientes existentes de uma vez
                    $existingClients = [];
                    $stmt = $pdo->query('SELECT id, client_code FROM clients');
                    while ($client = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $existingClients[$client['client_code']] = (int) $client['id'];
                    }

                    foreach ($rows as $rowNum => $row) {
                        $rowNum = $rowNum + 2;
                        try {
                            $clientCode = get_value_by_header($row, $headerMap, 'codigo');
                            $name = get_value_by_header($row, $headerMap, 'nome');
                            $totalTelas = (int) get_value_by_header($row, $headerMap, 'total_telas', '0');
                            $address = get_value_by_header($row, $headerMap, 'endereco');
                            $city = get_value_by_header($row, $headerMap, 'cidade');
                            $state = strtoupper(trim(get_value_by_header($row, $headerMap, 'estado')));

                            // Debug: se não encontrou os valores, tentar buscar diretamente
                            if ($clientCode === '' || $name === '') {
                            // Tentar busca alternativa nos cabeçalhos originais
                            foreach ($originalHeaders as $header) {
                                $headerLower = safe_strtolower($header);
                                if (($clientCode === '' || $name === '') && isset($row[$header])) {
                                    $value = trim((string) $row[$header]);
                                    if ($value !== '') {
                                        if (str_contains($headerLower, 'codigo') || str_contains($headerLower, 'code')) {
                                            $clientCode = $value;
                                        } elseif (str_contains($headerLower, 'nome') || str_contains($headerLower, 'name') || str_contains($headerLower, 'razão') || str_contains($headerLower, 'razao')) {
                                            $name = $value;
                                        }
                                    }
                                }
                            }
                            }
                            
                            if ($clientCode === '' || $name === '') {
                                $availableHeaders = implode(', ', array_keys($row));
                                $summary['errors'][] = "Linha {$rowNum}: Código do cliente e nome são obrigatórios. Cabeçalhos encontrados: {$availableHeaders}";
                                continue;
                            }

                            $clientId = find_or_create_client_optimized($pdo, $clientCode, $name, $address, $city, $state, $existingClients);

                            if ($totalTelas > 0) {
                                $linked = link_equipment_to_client($pdo, $clientId, $totalTelas, $user['id']);
                                $summary['equipment_linked'] += $linked['success'];
                                $summary['equipment_errors'] += $linked['errors'];
                                if ($linked['errors'] > 0) {
                                    $summary['errors'][] = "Linha {$rowNum}: {$linked['error_message']}";
                                }
                            }
                        } catch (Throwable $e) {
                            $summary['errors'][] = "Linha {$rowNum}: " . $e->getMessage();
                        }
                    }

                    $pdo->commit();
                    $success[] = sprintf(
                        'Importação concluída: %d clientes processados. %d equipamentos vinculados.',
                        count($rows),
                        $summary['equipment_linked']
                    );
                    if ($summary['equipment_errors'] > 0) {
                        $success[] = "Atenção: {$summary['equipment_errors']} equipamentos não puderam ser vinculados.";
                    }
                    if (!empty($summary['errors'])) {
                        $errors = array_merge($errors, $summary['errors']);
                    }
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errors[] = 'Erro ao processar importação: ' . $e->getMessage();
                }
            }
        }
    }
}

// Helper para strtolower compatível
function safe_strtolower(string $str): string
{
    if (function_exists('mb_strtolower')) {
        return mb_strtolower(trim($str), 'UTF-8');
    }
    return strtolower(trim($str));
}

function normalize_headers(array $originalHeaders): array
{
    $map = [];
    
    // Remover acentos para comparação
    $removeAccents = function($str) {
        $str = safe_strtolower($str);
        $str = str_replace(
            ['á', 'à', 'ã', 'â', 'ä', 'é', 'è', 'ê', 'ë', 'í', 'ì', 'î', 'ï', 'ó', 'ò', 'õ', 'ô', 'ö', 'ú', 'ù', 'û', 'ü', 'ç'],
            ['a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'c'],
            $str
        );
        return preg_replace('/[^a-z0-9_]/', '_', $str);
    };
    
    $variations = [
        'codigo' => ['codigo', 'codigo_cliente', 'codigo_do_cliente', 'code', 'client_code'],
        'nome' => ['nome', 'nome_cliente', 'nome_do_cliente', 'name', 'client_name', 'razao_social'],
        'total_telas' => ['total_telas', 'total_de_telas', 'telas', 'screens', 'total_screens'],
        'endereco' => ['endereco', 'endereco_completo', 'address', 'rua'],
        'cidade' => ['cidade', 'city'],
        'estado' => ['estado', 'state', 'uf'],
    ];

    foreach ($variations as $key => $options) {
        foreach ($originalHeaders as $originalHeader) {
            $headerNormalized = $removeAccents($originalHeader);
            
            foreach ($options as $option) {
                $optionNormalized = $removeAccents($option);
                
                // Match exato, parcial ou contém
                if ($headerNormalized === $optionNormalized || 
                    str_contains($headerNormalized, $optionNormalized) || 
                    str_contains($optionNormalized, $headerNormalized)) {
                    $map[$key] = $originalHeader;
                    break 2;
                }
            }
        }
    }

    return $map;
}

function get_value_by_header(array $row, array $headerMap, string $key, string $default = ''): string
{
    if (isset($headerMap[$key])) {
        $headerKey = $headerMap[$key];
        if (isset($row[$headerKey])) {
            $value = trim((string) $row[$headerKey]);
            return $value !== '' ? $value : $default;
        }
    }
    
    // Fallback: tentar encontrar por similaridade se não encontrou no map
    if (!isset($headerMap[$key])) {
        $searchTerms = match($key) {
            'codigo' => ['codigo', 'code'],
            'nome' => ['nome', 'name'],
            'total_telas' => ['telas', 'screens'],
            'endereco' => ['endereco', 'address'],
            'cidade' => ['cidade', 'city'],
            'estado' => ['estado', 'state', 'uf'],
            default => [],
        };
        
        foreach ($row as $header => $value) {
            $headerLower = safe_strtolower($header);
            foreach ($searchTerms as $term) {
                if (str_contains($headerLower, $term)) {
                    $val = trim((string) $value);
                    return $val !== '' ? $val : $default;
                }
            }
        }
    }
    
    return $default;
}

function read_csv_file(string $filePath): array
{
    $rows = [];
    if (($handle = fopen($filePath, 'r')) !== false) {
        $headers = [];
        $firstRow = true;
        $delimiter = detect_csv_delimiter($filePath);
        
        // Detectar encoding (com fallback se mbstring não estiver disponível)
        $encoding = 'UTF-8';
        if (function_exists('mb_detect_encoding')) {
            $sample = file_get_contents($filePath, false, null, 0, 1000);
            if ($sample !== false) {
                $detected = mb_detect_encoding($sample, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
                if ($detected !== false) {
                    $encoding = $detected;
                }
            }
        }
        
        while (($data = fgetcsv($handle, 1000, $delimiter)) !== false) {
            // Converter encoding se necessário e se mbstring estiver disponível
            if ($encoding !== 'UTF-8' && function_exists('mb_convert_encoding')) {
                $data = array_map(function($cell) use ($encoding) {
                    return mb_convert_encoding($cell, 'UTF-8', $encoding);
                }, $data);
            }
            
            if ($firstRow) {
                $headers = array_map('trim', array_filter($data, fn($h) => trim($h) !== ''));
                $firstRow = false;
                continue;
            }
            
            if (count($data) >= count($headers)) {
                $row = [];
                foreach ($headers as $index => $header) {
                    if ($header !== '') {
                        $value = $data[$index] ?? '';
                        $row[$header] = trim((string) $value);
                    }
                }
                // Só adiciona se tiver pelo menos um campo preenchido
                if (!empty(array_filter($row))) {
                    $rows[] = $row;
                }
            }
        }
        fclose($handle);
    }
    return $rows;
}

function detect_csv_delimiter(string $filePath): string
{
    $delimiters = [',', ';', "\t"];
    $handle = fopen($filePath, 'r');
    if ($handle === false) {
        return ',';
    }
    $firstLine = fgets($handle);
    fclose($handle);
    
    $maxCount = 0;
    $detected = ',';
    foreach ($delimiters as $delimiter) {
        $count = substr_count($firstLine, $delimiter);
        if ($count > $maxCount) {
            $maxCount = $count;
            $detected = $delimiter;
        }
    }
    return $detected;
}

function read_excel_file(string $filePath): array
{
    if (!class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
        throw new RuntimeException('Biblioteca PhpSpreadsheet não encontrada. Instale via: composer require phpoffice/phpspreadsheet');
    }

    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = [];
    $headers = [];

    $highestRow = $worksheet->getHighestRow();
    $highestColumn = $worksheet->getHighestColumn();
    $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

    for ($row = 1; $row <= $highestRow; $row++) {
        $rowData = [];
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $cell = $worksheet->getCellByColumnAndRow($col, $row);
            $value = $cell->getValue();
            // Se for fórmula, pegar o valor calculado
            if ($cell->getDataType() === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA) {
                $value = $cell->getCalculatedValue();
            }
            // Converter para string e limpar
            $rowData[] = $value !== null ? trim((string) $value) : '';
        }

        if ($row === 1) {
            $headers = array_map('trim', array_filter($rowData, fn($h) => $h !== ''));
            // Remover colunas vazias do array
            $rowData = array_slice($rowData, 0, count($headers));
        } else {
            $rowAssoc = [];
            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $rowAssoc[$header] = $rowData[$index] ?? '';
                }
            }
            // Só adiciona se tiver pelo menos um campo preenchido
            if (!empty(array_filter($rowAssoc))) {
                $rows[] = $rowAssoc;
            }
        }
    }

    return $rows;
}

function find_or_create_client_optimized(PDO $pdo, string $clientCode, string $name, string $address, string $city, string $state, array &$existingClients): int
{
    // Verificar em memória primeiro (muito mais rápido)
    if (isset($existingClients[$clientCode])) {
        $clientId = $existingClients[$clientCode];
        // Atualizar apenas se necessário
        $update = $pdo->prepare(<<<SQL
            UPDATE clients
            SET name = :name,
                address = :address,
                city = :city,
                state = :state,
                updated_at = NOW()
            WHERE id = :id
SQL);
        $update->execute([
            'name' => $name,
            'address' => $address ?: null,
            'city' => $city ?: null,
            'state' => $state ?: null,
            'id' => $clientId,
        ]);
        return $clientId;
    }

    // Cliente não existe, criar novo
    $insert = $pdo->prepare(<<<SQL
        INSERT INTO clients (client_code, name, address, city, state)
        VALUES (:code, :name, :address, :city, :state)
SQL);
    $insert->execute([
        'code' => $clientCode,
        'name' => $name,
        'address' => $address ?: null,
        'city' => $city ?: null,
        'state' => $state ?: null,
    ]);
    $clientId = (int) $pdo->lastInsertId();
    
    // Adicionar ao cache em memória
    $existingClients[$clientCode] = $clientId;
    
    return $clientId;
}

function link_equipment_to_client(PDO $pdo, int $clientId, int $totalTelas, int $userId): array
{
    $result = ['success' => 0, 'errors' => 0, 'error_message' => ''];

    if ($totalTelas <= 0) {
        return $result;
    }

    // Otimização: SELECT primeiro, depois UPDATE em lote (mais compatível)
    $selectStmt = $pdo->prepare(<<<SQL
        SELECT id FROM equipment
        WHERE (current_client_id IS NULL OR current_client_id = 0)
        AND status = 'em_estoque'
        ORDER BY id ASC
        LIMIT ?
SQL);
    $selectStmt->bindValue(1, $totalTelas, PDO::PARAM_INT);
    $selectStmt->execute();
    $equipmentIds = $selectStmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($equipmentIds)) {
        $result['error_message'] = 'Nenhum equipamento disponível em estoque.';
        return $result;
    }

    if (count($equipmentIds) < $totalTelas) {
        $result['errors'] = $totalTelas - count($equipmentIds);
        $result['error_message'] = sprintf(
            'Apenas %d de %d equipamentos disponíveis em estoque.',
            count($equipmentIds),
            $totalTelas
        );
    }

    // UPDATE em lote usando IN (muito mais eficiente que loop)
    $placeholders = implode(',', array_fill(0, count($equipmentIds), '?'));
    $updateStmt = $pdo->prepare(<<<SQL
        UPDATE equipment
        SET current_client_id = ?,
            status = 'alocado',
            updated_by = ?,
            updated_at = NOW()
        WHERE id IN ($placeholders)
SQL);
    
    $params = array_merge([$clientId, $userId], array_map('intval', $equipmentIds));
    
    try {
        $updateStmt->execute($params);
        $result['success'] = $updateStmt->rowCount();
    } catch (PDOException $e) {
        $result['error_message'] = 'Erro ao vincular equipamentos: ' . $e->getMessage();
        $result['errors'] = count($equipmentIds);
    }

    return $result;
}

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>
<main class="flex-1 flex flex-col bg-slate-950 text-slate-100">
    <?php include __DIR__ . '/../templates/topbar.php'; ?>
    <section class="flex-1 overflow-y-auto px-6 pb-12 space-y-6">
        <div class="surface-card">
            <h2 class="surface-heading">Importar Clientes</h2>
            <p class="mt-2 text-sm text-slate-400">
                Faça upload de um arquivo Excel (XLSX, XLS) ou CSV com os dados dos clientes.
            </p>

            <?php if ($success): ?>
                <div class="mt-4 rounded-2xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-4 text-sm text-emerald-200">
                    <ul class="list-disc space-y-1 pl-5">
                        <?php foreach ($success as $msg): ?>
                            <li><?= sanitize($msg); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="mt-4 rounded-2xl border border-red-500/40 bg-red-500/10 px-4 py-4 text-sm text-red-200">
                    <ul class="list-disc space-y-1 pl-5">
                        <?php foreach ($errors as $error): ?>
                            <li><?= sanitize($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="mt-6">
                <input type="hidden" name="csrf_token" value="<?= sanitize(ensure_csrf_token()); ?>">
                
                <div class="space-y-4">
                    <div>
                        <label class="text-sm font-medium text-slate-300" for="import_file">
                            Arquivo (CSV, XLS ou XLSX)
                        </label>
                        <input type="file" id="import_file" name="import_file" accept=".csv,.xls,.xlsx" required class="mt-2 surface-field">
                        <p class="mt-1 text-xs text-slate-400">
                            Tamanho máximo: 10MB. Formatos aceitos: CSV, XLS, XLSX
                        </p>
                    </div>

                    <div class="rounded-xl border border-slate-700 bg-slate-900/50 p-4">
                        <h3 class="text-sm font-semibold text-slate-200 mb-2">Formato esperado do arquivo:</h3>
                        <ul class="text-xs text-slate-400 space-y-1 list-disc pl-5">
                            <li><strong>Código do Cliente</strong> (obrigatório)</li>
                            <li><strong>Nome do Cliente</strong> (obrigatório)</li>
                            <li><strong>Total de Telas</strong> (número de equipamentos a vincular)</li>
                            <li><strong>Endereço</strong> (opcional)</li>
                            <li><strong>Cidade</strong> (opcional)</li>
                            <li><strong>Estado</strong> (opcional)</li>
                        </ul>
                        <p class="mt-3 text-xs text-slate-400">
                            <strong>Nota:</strong> O sistema vinculará automaticamente equipamentos disponíveis em estoque ao cliente conforme o número informado em "Total de Telas".
                        </p>
                        <div class="mt-3">
                            <a href="../data/import/clientes_import_sample.csv" download class="inline-flex items-center gap-2 text-xs text-blue-300 hover:text-blue-200">
                                <span class="material-icons-outlined text-base">download</span>
                                Baixar arquivo de exemplo (CSV)
                            </a>
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" class="rounded-xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white hover:bg-blue-500">
                            Importar Clientes
                        </button>
                        <a href="clientes.php" class="rounded-xl border border-slate-600 px-6 py-3 text-sm text-slate-200 hover:bg-slate-800/40">
                            Cancelar
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </section>
<?php
include __DIR__ . '/../templates/footer.php';
