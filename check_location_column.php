<?php
// Script para verificar estructura de la tabla instruments
require_once __DIR__ . '/config.php';

try {
    $pdo = pdo();
    
    echo "=== Estructura de la tabla instruments ===\n\n";
    
    $stmt = $pdo->query("DESCRIBE instruments");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasLocation = false;
    
    foreach ($columns as $col) {
        echo sprintf("%-20s %-15s %-10s %-10s\n", 
            $col['Field'], 
            $col['Type'], 
            $col['Null'], 
            $col['Key']
        );
        
        if ($col['Field'] === 'Location') {
            $hasLocation = true;
        }
    }
    
    echo "\n";
    
    if ($hasLocation) {
        echo "✓ La columna 'Location' YA EXISTE en la tabla instruments\n";
    } else {
        echo "✗ La columna 'Location' NO EXISTE en la tabla instruments\n";
        echo "\nPara agregarla, ejecuta:\n";
        echo "ALTER TABLE instruments ADD COLUMN Location VARCHAR(255) DEFAULT NULL AFTER SerialNumber;\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
