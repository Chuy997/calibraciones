<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'consulta') {
    header('Location: login.php');
    exit();
}

require 'config_consulta.php';
$conn = getConsultaConnection();

// Modificar la consulta SQL para incluir la columna Picture
$sql = "SELECT ID, Picture, Description, Brand, Model, SerialNumber, CalDate, DueDate, Status, Comments FROM Instruments";
$result = $conn->query($sql);

if (!$result) {
    die("Error en la consulta: " . $conn->error);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Consulta de Instrumentos</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        body {
            background-color: #121212;
            color: #e0e0e0;
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
        }

        .navbar {
            background-color: #1e1e1e;
            color: #e0e0e0;
            padding: 10px;
        }

        .navbar ul {
            list-style-type: none;
            padding: 0;
            margin: 0;
            display: flex;
            justify-content: center;
        }

        .navbar li {
            margin: 0 15px;
        }

        .navbar a {
            display: block;
            padding: 10px 20px;
            font-size: 18px;
            color: #ffffff;
            background-color: #007bff;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s, transform 0.3s;
        }

        .navbar a:hover {
            background-color: #0056b3;
            transform: scale(1.05);
        }

        .container {
            text-align: center;
            max-width: 1200px;
            width: 90%;
            background-color: #1e1e1e;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            margin: 20px auto;
        }

        h1 {
            font-size: 36px;
            margin-bottom: 20px;
            color: #ffffff;
            font-weight: 300;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        table, th, td {
            border: 1px solid #444444;
        }

        th, td {
            padding: 10px;
            text-align: left;
        }

        th {
            background-color: #333333;
        }

        tr:nth-child(even) {
            background-color: #2c2c2c;
        }

        tr:nth-child(odd) {
            background-color: #1e1e1e;
        }

        #searchInput {
            padding: 10px;
            margin-bottom: 20px;
            width: 100%;
            max-width: 400px;
            border: 1px solid #444;
            border-radius: 5px;
            background-color: #1e1e1e;
            color: #e0e0e0;
        }
        
        .btn-view-out-of-use {
            display: inline-block;
            padding: 10px 15px;
            margin: 20px 0;
            color: #ffffff;
            background-color: #17a2b8;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s, transform 0.3s;
        }

        .btn-view-out-of-use:hover {
            background-color: #138496;
            transform: scale(1.05);
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body>
    <div class="navbar">
        <ul>
            <?php if ($_SESSION['role'] == 'admin'): ?>
                <li><a href="admin.php">Administrar Instrumentos</a></li>
                <li><a href="out_of_use.php">Instrumentos Fuera de Uso</a></li>
                <li><a href="history.php">Historial de Actualizaciones</a></li>
            <?php endif; ?>
            <?php if ($_SESSION['role'] == 'consulta'): ?>
                <li><a href="consulta.php">Consulta</a></li>
            <?php endif; ?>
            <li><a href="logout.php">Cerrar Sesión</a></li>
        </ul>
    </div>
    <div class="container">
        <h1>Consulta de Instrumentos</h1>
        <a href="consulta_out_of_use.php" class="btn-view-out-of-use">Ver Instrumentos Fuera de Uso</a>
        <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Buscar por cualquier campo">
        <table id="instrumentsTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Picture</th>
                    <th>Descripción</th>
                    <th>Marca</th>
                    <th>Modelo</th>
                    <th>Número de Serie</th>
                    <th>Fecha de Calibración</th>
                    <th>Fecha de Vencimiento</th>
                    <th>Estado</th>
                    <th>Comentarios</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['ID']); ?></td>
                        <td><a href="<?php echo htmlspecialchars($row['Picture']); ?>" target="_blank">Ver Foto</a></td>
                        <td><?php echo htmlspecialchars($row['Description']); ?></td>
                        <td><?php echo htmlspecialchars($row['Brand']); ?></td>
                        <td><?php echo htmlspecialchars($row['Model']); ?></td>
                        <td><?php echo htmlspecialchars($row['SerialNumber']); ?></td>
                        <td><?php echo htmlspecialchars($row['CalDate']); ?></td>
                        <td><?php echo htmlspecialchars($row['DueDate']); ?></td>
                        <td><?php echo htmlspecialchars($row['Status']); ?></td>
                        <td><?php echo htmlspecialchars($row['Comments']); ?></td>
                        <td class="actions">
                            <a href="history.php?id=<?php echo $row['ID']; ?>" class="btn btn-info btn-sm">Ver Historial</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <script>
        function filterTable() {
            var input, filter, table, tr, td, i, j, txtValue;
            input = document.getElementById("searchInput");
            filter = input.value.toUpperCase();
            table = document.getElementById("instrumentsTable");
            tr = table.getElementsByTagName("tr");

            for (i = 1; i < tr.length; i++) {
                tr[i].style.display = "none";
                td = tr[i].getElementsByTagName("td");
                for (j = 0; j < td.length; j++) {
                    if (td[j]) {
                        txtValue = td[j].textContent || td[j].innerText;
                        if (txtValue.toUpperCase().indexOf(filter) > -1) {
                            tr[i].style.display = "";
                            break;
                        }
                    }
                }
            }
        }
    </script>
</body>
</html>
<?php
$conn->close();
?>
