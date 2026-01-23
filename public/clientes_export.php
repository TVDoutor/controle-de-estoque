<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login();

$pdo = get_pdo();
$format = strtolower($_GET['format'] ?? 'csv');

if (!in_array($format, ['csv', 'xlsx'], true)) {
    $format = 'csv';
}

$query = 'SELECT 
    c.client_code AS "Código do Cliente",
    c.name AS "Nome do Cliente",
    COUNT(e.id) AS "Total de Telas",
    c.address AS "Endereço",
    c.city AS "Cidade",
    c.state AS "Estado"
FROM clients c
LEFT JOIN equipment e ON e.current_client_id = c.id
GROUP BY c.id
ORDER BY c.name';

$stmt = $pdo->query($query);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="clientes_' . date('Y-m-d_His') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    if (!empty($clients)) {
        fputcsv($output, array_keys($clients[0]), ';');
        foreach ($clients as $client) {
            fputcsv($output, $client, ';');
        }
    } else {
        fputcsv($output, ['Código do Cliente', 'Nome do Cliente', 'Total de Telas', 'Endereço', 'Cidade', 'Estado'], ';');
    }
    
    fclose($output);
    exit;
}

if ($format === 'xlsx') {
    if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        header('Content-Type: text/plain');
        die('Biblioteca PhpSpreadsheet não encontrada. Instale via: composer require phpoffice/phpspreadsheet');
    }

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Clientes');

    if (!empty($clients)) {
        $headers = array_keys($clients[0]);
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $col++;
        }

        $row = 2;
        foreach ($clients as $client) {
            $col = 'A';
            foreach ($client as $value) {
                $sheet->setCellValue($col . $row, $value ?? '');
                $col++;
            }
            $row++;
        }

        foreach (range('A', $col) as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
    } else {
        $sheet->setCellValue('A1', 'Código do Cliente');
        $sheet->setCellValue('B1', 'Nome do Cliente');
        $sheet->setCellValue('C1', 'Total de Telas');
        $sheet->setCellValue('D1', 'Endereço');
        $sheet->setCellValue('E1', 'Cidade');
        $sheet->setCellValue('F1', 'Estado');
    }

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="clientes_' . date('Y-m-d_His') . '.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer->save('php://output');
    exit;
}
