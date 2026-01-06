<?php
declare(strict_types=1);
require_once __DIR__.'/config.php';
require_auth();

// Headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=assets_hw_inventory_' . date('Y-m-d') . '.csv');

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
    'Asset No',
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
FROM assets_hw_items 
ORDER BY ID ASC";

$stmt = pdo()->query($sql);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Basic formatting if needed, though raw data is usually best for CSV
    fputcsv($output, $row);
}

fclose($output);
exit;
