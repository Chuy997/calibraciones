<?php
// /var/www/html/calibraciones/consulta.php

session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'consulta') {
    header('Location: login.php');
    exit();
}

require 'config.php';
$conn = getConnection('consulta');

// Traer lista de instrumentos con estado dinámico y último PDF
$sql = "
    SELECT
        i.ID,
        i.Picture,
        i.Description,
        i.Brand,
        i.Model,
        i.SerialNumber,
        i.CalDate,
        i.DueDate,
        CASE
            WHEN CURRENT_DATE() > i.DueDate THEN 'Vencido'
            WHEN i.DueDate BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY) THEN 'Próxima calibración'
            ELSE 'Calibrado'
        END AS Status,
        i.Comments,
        (
            SELECT uh.PdfPath
            FROM updatehistory uh
            WHERE uh.InstrumentID = i.ID
              AND uh.PdfPath IS NOT NULL
            ORDER BY uh.UpdatedAt DESC
            LIMIT 1
        ) AS LastPdfPath
    FROM instruments i
    ORDER BY i.ID
";
$result = $conn->query($sql);
if (!$result) {
    die("Error en la consulta: " . $conn->error);
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
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
            color: #ffffff;
        }

        th {
            background-color: #333333;
            cursor: pointer;
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

        /* Botón acceso a reportes */
        .btn-view-reports {
            display: inline-block;
            padding: 10px 15px;
            margin: 10px 5px;
            color: #ffffff;
            background-color: #28a745;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s, transform 0.3s;
        }

        .btn-view-reports:hover {
            background-color: #218838;
            transform: scale(1.05);
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body>
    <div class="navbar">
        <ul>
            <li><a href="consulta.php">Consulta</a></li>
            <li><a href="consulta_out_of_use.php">Fuera de Uso</a></li>
            <li><a href="logout.php">Cerrar Sesión</a></li>
        </ul>
    </div>
    <div class="container">
        <h1>Consulta de Instrumentos</h1>

        <!-- Botón para acceder al reporte -->
        <a href="report.php" class="btn-view-reports">
            <i class="fas fa-chart-bar"></i> Ver Reportes
        </a>
        <a href="consulta_out_of_use.php" class="btn-view-out-of-use">
            Ver Instrumentos Fuera de Uso
        </a>

        <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Buscar por cualquier campo">

        <table id="instrumentsTable" data-sort-dir="asc">
            <thead>
                <tr>
                    <th onclick="sortTable(0)">ID</th>
                    <th>Foto</th>
                    <th onclick="sortTable(2)">Descripción</th>
                    <th onclick="sortTable(3)">Marca</th>
                    <th onclick="sortTable(4)">Modelo</th>
                    <th onclick="sortTable(5)">Serial</th>
                    <th onclick="sortTable(6)">Fecha Cal.</th>
                    <th onclick="sortTable(7)">Fecha Ven.</th>
                    <th onclick="sortTable(8)">Estado</th>
                    <th>Comentarios</th>
                    <th>PDF</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['ID']) ?></td>
                        <td>
                            <?php if ($row['Picture']): ?>
                                <a href="<?= htmlspecialchars($row['Picture']) ?>" target="_blank">Ver Foto</a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['Description']) ?></td>
                        <td><?= htmlspecialchars($row['Brand']) ?></td>
                        <td><?= htmlspecialchars($row['Model']) ?></td>
                        <td><?= htmlspecialchars($row['SerialNumber']) ?></td>
                        <td><?= htmlspecialchars($row['CalDate']) ?></td>
                        <td><?= htmlspecialchars($row['DueDate']) ?></td>
                        <td><?= htmlspecialchars($row['Status']) ?></td>
                        <td><?= htmlspecialchars($row['Comments']) ?></td>
                        <td>
                            <?php if ($row['LastPdfPath']): ?>
                                <a href="<?= htmlspecialchars($row['LastPdfPath']) ?>" target="_blank">
                                    <i class="fas fa-file-pdf"></i>
                                </a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="history.php?id=<?= $row['ID'] ?>" class="btn btn-info btn-sm">
                                <i class="fas fa-history"></i> Historial
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <script>
    function filterTable() {
        const filter = document.getElementById("searchInput").value.toUpperCase();
        document.querySelectorAll("#instrumentsTable tbody tr").forEach(tr => {
            tr.style.display = [...tr.cells].some(td =>
                td.textContent.toUpperCase().includes(filter)
            ) ? "" : "none";
        });
    }

    function sortTable(colIndex) {
        const table = document.getElementById("instrumentsTable");
        const tbody = table.tBodies[0];
        const rows = Array.from(tbody.rows);
        const asc = table.getAttribute("data-sort-dir") !== "asc";
        rows.sort((a, b) => {
            const v1 = a.cells[colIndex].textContent.trim();
            const v2 = b.cells[colIndex].textContent.trim();
            return asc
                ? v1.localeCompare(v2, undefined, {numeric: true})
                : v2.localeCompare(v1, undefined, {numeric: true});
        });
        rows.forEach(r => tbody.appendChild(r));
        table.setAttribute("data-sort-dir", asc ? "asc" : "desc");
    }
    </script>
</body>
</html>
<?php
$conn->close();
?>
