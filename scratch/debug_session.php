<?php
require_once __DIR__.'/config.php';
secure_session_start();
header('Content-Type: text/plain');
echo "Session Status: " . session_status() . "\n";
echo "Session ID: " . session_id() . "\n";
echo "Username: " . ($_SESSION['username'] ?? 'NOT SET') . "\n";
echo "Role: " . ($_SESSION['role'] ?? 'NOT SET') . "\n";
echo "PHP_SELF: " . $_SERVER['PHP_SELF'] . "\n";
echo "basename: " . basename($_SERVER['PHP_SELF']) . "\n";
?>
