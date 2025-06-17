<?php
function getConnection($role = 'consulta') {
    $servername = "localhost";
    $username = ($role == 'admin') ? 'admin' : 'consulta';
    $password = ($role == 'admin') ? 'admin_password' : 'consulta_password';
    $dbname = "calibraciones"; // Asegúrate de que el nombre de la base de datos sea correcto
    
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        die("Conexión fallida: " . $conn->connect_error);
    }
    return $conn;
}
?>
