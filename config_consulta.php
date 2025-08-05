<?php
function getConsultaConnection() {
    $servername = "localhost";
    $username = "jmuro";
    $password = "Monday.03";
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
