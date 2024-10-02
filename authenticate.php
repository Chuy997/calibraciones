<?php
session_start();
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (($username == 'admin' && $password == 'admin_password') || ($username == 'consulta' && $password == 'consulta_password')) {
        $_SESSION['username'] = $username;
        $_SESSION['role'] = ($username == 'admin') ? 'admin' : 'consulta';
        header('Location: index.php');
        exit();
    } else {
        header('Location: login.php?error=1');
        exit();
    }
} else {
    header('Location: login.php');
    exit();
}
?>
