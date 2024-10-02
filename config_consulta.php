<?php
function getConsultaConnection() {
    $servername = "localhost";
    $username = "consulta";
    $password = "consulta_password";
    $dbname = "calibraciones";

    // Crear conexión
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Verificar conexión
    if ($conn->connect_error) {
        die("Conexión fallida: " . $conn->connect_error);
    }

    return $conn;
}
?>
