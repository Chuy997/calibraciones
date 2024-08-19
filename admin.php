<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

require 'config.php';
$conn = getConnection('admin');
$sql = "SELECT * FROM Instruments";
$result = $conn->query($sql);

if (!$result) {
    die("Error en la consulta: " . $conn->error);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Administrar Instrumentos</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        body {
            background-color: #121212;
            color: #e0e0e0;
        }

        .navbar, .card, .modal-content {
            background-color: #1e1e1e;
            color: #e0e0e0;
        }

        .table thead.thead-dark th {
            background-color: #333333;
            border-color: #444444;
            color: #ffffff;
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #2c2c2c;
        }

        .table-striped tbody tr:nth-of-type(even) {
            background-color: #1e1e1e;
        }

        .table th, .table td {
            border-color: #444444;
            color: #e0e0e0;
        }

        .btn, .btn-primary, .btn-info, .btn-warning, .btn-success {
            color: #ffffff;
        }

        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }

        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #004085;
        }

        .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
        }

        .btn-info:hover {
            background-color: #138496;
            border-color: #117a8b;
        }

        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #000;
        }

        .btn-warning:hover {
            background-color: #e0a800;
            border-color: #d39e00;
        }

        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }

        .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }

        .report-form select, .report-form input {
            margin-right: 10px;
            background-color: #2c2c2c;
            color: #e0e0e0;
            border: 1px solid #444444;
        }

        .form-control {
            background-color: #2c2c2c;
            color: #e0e0e0;
            border: 1px solid #444444;
        }
        
        .form-control::placeholder {
            color: #e0e0e0;
        }
        
        .container {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>
    <div class="container">
        <h1 class="my-4">Instrumentos de Medición</h1>
        <div class="d-flex justify-content-between mb-3">
            <a class="btn btn-success" href="add.php"><i class="fas fa-plus"></i> Agregar Nuevo Instrumento</a>
            <form class="form-inline report-form" action="generate_report.php" method="get">
                <label for="month" class="mr-2">Mes:</label>
                <select class="form-control mr-2" name="month" id="month" required>
                    <?php
                    for ($m = 1; $m <= 12; $m++) {
                        $monthName = date('F', mktime(0, 0, 0, $m, 1));
                        echo "<option value='$m'>$monthName</option>";
                    }
                    ?>
                </select>

                <label for="year" class="mr-2">Año:</label>
                <select class="form-control mr-2" name="year" id="year" required>
                    <?php
                    $currentYear = date('Y');
                    for ($y = $currentYear; $y >= $currentYear - 10; $y--) {
                        echo "<option value='$y'>$y</option>";
                    }
                    ?>
                </select>

                <button type="submit" class="btn btn-info"><i class="fas fa-file-download"></i> Generar Reporte</button>
            </form>
            <input class="form-control w-25" type="text" id="searchInput" onkeyup="filterTable()" placeholder="Buscar por cualquier campo">
        </div>

        <table class="table table-striped" id="instrumentsTable">
            <thead class="thead-dark">
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
            <?php while($row = $result->fetch_assoc()) { ?>
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
                        <div class="btn-group" role="group">
                            <a class="btn btn-primary btn-sm" href="update.php?id=<?php echo $row['ID']; ?>"><i class="fas fa-edit"></i> Actualizar</a>
                            <a class="btn btn-info btn-sm" href="history.php?id=<?php echo $row['ID']; ?>"><i class="fas fa-history"></i> Ver Historial</a>
                            <a class="btn btn-warning btn-sm" href="move_out_of_use.php?id=<?php echo $row['ID']; ?>" title="Mover a Fuera de Uso"><i class="fas fa-exclamation-triangle"></i> Mover</a>
                        </div>
                    </td>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
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

        function sortTable(columnIndex) {
            var table, rows, switching, i, x, y, shouldSwitch, dir, switchCount = 0;
            table = document.getElementById("instrumentsTable");
            switching = true;
            dir = "asc"; 
            
            while (switching) {
                switching = false;
                rows = table.rows;
                
                for (i = 1; i < (rows.length - 1); i++) {
                    shouldSwitch = false;
                    x = rows[i].getElementsByTagName("TD")[columnIndex];
                    y = rows[i + 1].getElementsByTagName("TD")[columnIndex];
                    
                    if (dir == "asc") {
                        if (x.innerHTML.toLowerCase() > y.innerHTML.toLowerCase()) {
                            shouldSwitch = true;
                            break;
                        }
                    } else if (dir == "desc") {
                        if (x.innerHTML.toLowerCase() < y.innerHTML.toLowerCase()) {
                            shouldSwitch = true;
                            break;
                        }
                    }
                }
                if (shouldSwitch) {
                    rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
                    switching = true;
                    switchCount++;
                } else {
                    if (switchCount == 0 && dir == "asc") {
                        dir = "desc";
                        switching = true;
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
