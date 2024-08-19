<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sistema de Gestión de Calibraciones</title>
    <link rel="stylesheet" type="text/css" href="styles.css">
    <style>
        body {
            background-color: #121212;
            color: #e0e0e0;
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .container {
            text-align: center;
            max-width: 90%;
            width: 90%;
            height: 100%;
            background-color: #1e1e1e;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        h1 {
            font-size: 36px;
            margin-bottom: 20px;
            color: #ffffff;
            font-weight: 300;
        }

        .nav {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            list-style-type: none;
            padding: 0;
        }

        .nav li {
            margin: 0 15px;
        }

        .nav a {
            display: block;
            padding: 10px 20px;
            font-size: 18px;
            color: #ffffff;
            background-color: #007bff;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s, transform 0.3s;
        }

        .nav a:hover {
            background-color: #0056b3;
            transform: scale(1.05);
        }

        .image-container {
            margin-top: 20px;
        }

        .image-container img {
            max-width: 90%;
            height: auto;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s;
        }

        .image-container img:hover {
            transform: scale(1.05);
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <h1>Bienvenido al Sistema de Gestión de Calibraciones</h1>
        <ul class="nav">
            <li><a href="admin.php">Administrar Instrumentos</a></li>
            <li><a href="out_of_use.php">Instrumentos Fuera de Uso</a></li>
            <li><a href="logout.php">Cerrar Sesión</a></li>
        </ul>
        <div class="image-container">
            <img src="imagenes/calibracion.png" alt="Calibración">
        </div>
    </div>
</body>
</html>
