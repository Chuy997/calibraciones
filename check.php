<?php
require 'config.php';
$pdo = pdo();

echo "** golden_items schema:\n";
foreach ($pdo->query('SHOW COLUMNS FROM golden_items') as $r) { echo $r['Field'] . ' ' . $r['Type'] . "\n"; }

echo "\n** ingenieria_items schema:\n";
foreach ($pdo->query('SHOW COLUMNS FROM ingenieria_items') as $r) { echo $r['Field'] . ' ' . $r['Type'] . "\n"; }

echo "\n** Items to move:\n";
$stmt = $pdo->prepare('SELECT * FROM golden_items WHERE ID IN (?,?,?)');
$stmt->execute(['GLDTE-189', 'GLDTE-190', 'GLDTE-191']);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
