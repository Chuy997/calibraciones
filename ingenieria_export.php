<?php
declare(strict_types=1);
require_once __DIR__.'/config.php';
require_auth();

// Headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=ingenieria_inventory_' . date('Y-m-d') . '.csv');

// Open output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fwrite($output, "\xEF\xBB\xBF");

// CSV Column Headers
fputcsv($output, [
    'ID', 
    'Descripción', 
    'Marca', 
    'Modelo', 
    'Serie', 
    'Ubicación', 
    'Departamento', 
    'Responsable', 
    'Estado', 
    'Pedimento',
    'Foto (URL)',
    'Documento (URL)',
    'Comentarios', 
    'Creado', 
    'Actualizado'
]);

// SQL Query - fetching all ingenieria items
$sql = "SELECT 
    ID, 
    Description, 
    Brand, 
    Model, 
    SerialNumber, 
    Location, 
    Department, 
    Owner, 
    Status, 
    Pedimento,
    Picture,
    Document,
    Comments, 
    CreatedAt, 
    UpdatedAt 
FROM ingenieria_items 
ORDER BY ID ASC";

$stmt = pdo()->query($sql);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Basic formatting if needed, though raw data is usually best for CSV
    fputcsv($output, $row);
}

fclose($output);
exit;
