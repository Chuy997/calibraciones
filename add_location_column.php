<?php
// Script para agregar la columna Location a la tabla instruments
require_once __DIR__ . '/config.php';

try {
    $pdo = pdo();
    
    echo "Agregando columna 'Location' a la tabla instruments...\n\n";
    
    $sql = "ALTER TABLE instruments ADD COLUMN Location VARCHAR(255) DEFAULT NULL AFTER SerialNumber";
    
    $pdo->exec($sql);
    
    echo "✓ Columna 'Location' agregada exitosamente!\n\n";
    
    // Verificar
    $stmt = $pdo->query("DESCRIBE instruments");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Columnas de la tabla instruments:\n";
    foreach ($columns as $col) {
        $marker = ($col['Field'] === 'Location') ? ' ← NUEVA' : '';
        echo "  - " . $col['Field'] . $marker . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
