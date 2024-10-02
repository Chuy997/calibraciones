<?php
require 'config.php';

$conn = getConnection('admin'); // Prueba con el usuario admin

if ($conn) {
    echo "Conexión exitosa con el usuario admin.";
} else {
    echo "Error en la conexión.";
}

$conn->close();
?>
