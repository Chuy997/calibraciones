<?php
function getConnection($role = 'consulta') {
    $servername = "localhost";
    $username   = "jmuro";
    $password   = "Monday.03";
    $dbname     = "calibraciones";

    $conn = new mysqli($servername, $username, $password, $dbname);
    

    if ($conn->connect_error) {
        die("Conexión fallida: " . $conn->connect_error);
    }
    return $conn;
}
?>
