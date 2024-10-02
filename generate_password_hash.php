<?php
$password = 'consulta_password'; // La contraseña que deseas hashear
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Password hash: " . $hash;
?>
