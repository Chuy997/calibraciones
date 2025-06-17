<?php
if (!isset($_SESSION)) {
    session_start();
}
?>

<nav>
  
    <ul class="nav">
        <li><a href="index.php">Inicio</a></li>
        <?php if ($_SESSION['role'] == 'admin'): ?>
            <li><a href="admin.php">Administrar Instrumentos</a></li>
            <li><a href="out_of_use.php">Instrumentos Fuera de Uso</a></li>
        <?php endif; ?>
        <?php if ($_SESSION['role'] == 'consulta'): ?>
            <li><a href="consulta.php">Consulta</a></li>
        <?php endif; ?>
        <li><a href="logout.php">Cerrar Sesión</a></li>
    </ul>
</nav>

<style>
    nav {
        background-color: #1f1f1f;
        overflow: hidden;
    }
    nav ul {
        list-style-type: none;
        margin: 0;
        padding: 0;
    }
    nav ul li {
        float: left;
    }
    nav ul li a {
        display: block;
        color: #e0e0e0;
        text-align: center;
        padding: 14px 16px;
        text-decoration: none;
        transition: background-color 0.3s;
    }
    nav ul li a:hover {
        background-color: #333;
    }
</style>
