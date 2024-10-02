<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

require 'config.php';
$conn = getConnection('admin');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['ID'];
    $reasonForRemoval = $_POST['ReasonForRemoval'];

    $sql = "UPDATE Instruments_Out_Of_Use SET ReasonForRemoval = ? WHERE ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $reasonForRemoval, $id);

    if ($stmt->execute()) {
        header('Location: out_of_use.php');
        exit();
    } else {
        echo "Error al actualizar la razón: " . $stmt->error;
    }

    $stmt->close();
}

$conn->close();
?>
