<?php
require 'config.php';
$conn = getConnection('admin');

// Hashear la contraseña
$adminPasswordHash = password_hash('admin_password', PASSWORD_DEFAULT);

$sql = "INSERT INTO users (username, password, role) VALUES (?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $adminUsername, $adminPasswordHash, $adminRole);

// Datos de usuario admin
$adminUsername = 'admin';
$adminRole = 'admin';

if ($stmt->execute()) {
    echo "Usuario admin agregado exitosamente.";
} else {
    echo "Error al agregar usuario admin: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
