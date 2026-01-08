<?php
require_once 'config.php';
$pdo = pdo();
echo "PHP Time: " . date('Y-m-d H:i:s') . "\n";
echo "MySQL Time: " . $pdo->query("SELECT NOW()")->fetchColumn() . "\n";
