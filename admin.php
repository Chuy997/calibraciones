<?php
// /var/www/html/calibraciones/admin.php

// Mostrar errores en desarrollo (quítalo en producción)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require 'config.php';
$conn = getConnection('admin');

// Query con estado dinámico y último PDF certificado
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
        /* Último PdfPath no nulo */
        (
            SELECT uh.PdfPath
            FROM updatehistory uh
            WHERE uh.InstrumentID = i.ID
              AND uh.PdfPath IS NOT NULL
            ORDER BY uh.UpdatedAt DESC
            LIMIT 1
        ) AS LastPdfPath,
        i.Comments,
        CASE
            WHEN CURRENT_DATE() > i.DueDate THEN 'Vencido'
            WHEN i.DueDate BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY) THEN 'Próxima calibración'
            ELSE 'Calibrado'
        END AS status_calculado
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
    <title>Administrar Instrumentos</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        body {
            background-color: #121212;
            color: #e0e0e0;
            font-family: Arial, sans-serif;
            margin: 0; padding: 0;
        }
        .container {
            margin: 20px auto;
            max-width: 1200px;
            padding: 20px;
        }
        h1 {
            text-align: center;
            margin-bottom: 1.5rem;
            color: #fff;
        }
        .table {
            color: #e0e0e0;
        }
        .thead-dark th {
            background: #333;
            color: #fff;
            border-bottom: 2px solid #444;
        }
        .table-striped tbody tr:nth-of-type(odd)  { background: #2c2c2c; }
        .table-striped tbody tr:nth-of-type(even) { background: #1e1e1e; }
        .table th, .table td {
            padding: .75rem;
            border: 1px solid #444;
        }
        .btn { font-weight: 600; text-transform: uppercase; }
        .btn-success { background: #28a745; color: #fff; }
        .btn-info    { background: #17a2b8; color: #fff; }
        .btn-warning { background: #ffc107; color: #000; }
        .btn-primary { background: #007bff; color: #fff; }
        .form-control {
            background: #2c2c2c; color: #e0e0e0;
            border: 1px solid #444;
        }
        @media(max-width:768px){
            .form-inline { flex-direction: column; }
            .form-inline .form-control, .form-inline .btn { width: 100%; margin-bottom: .5rem; }
        }

        /* Resaltado por estado */
        .vencido td     { background-color: #441111 !important; }
        .proxima td     { background-color: #443a11 !important; }
        /* calibrado permanece con estilo por defecto */
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>
    <div class="container">
        <h1>Instrumentos de Medición</h1>
        <div class="d-flex justify-content-between flex-wrap mb-3">
            <a class="btn btn-success mb-2" href="add.php">
                <i class="fas fa-plus"></i> Nuevo Instrumento
            </a>
            <form class="form-inline mb-2" action="generate_report.php" method="get">
                <label for="month" class="mr-2">Mes:</label>
                <select name="month" id="month" class="form-control mr-2" required>
                    <?php for($m=1;$m<=12;$m++): ?>
                        <option value="<?= $m ?>"><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                    <?php endfor; ?>
                </select>
                <label for="year" class="mr-2">Año:</label>
                <select name="year" id="year" class="form-control mr-2" required>
                    <?php for($y=date('Y'); $y>=date('Y')-10; $y--): ?>
                        <option value="<?= $y ?>"><?= $y ?></option>
                    <?php endfor; ?>
                </select>
                <button class="btn btn-info" type="submit">
                    <i class="fas fa-file-download"></i> Reporte
                </button>
            </form>
            <input id="searchInput" class="form-control w-25 mb-2" type="text" placeholder="Buscar…" onkeyup="filterTable()">
        </div>

        <table class="table table-striped" id="instrumentsTable" data-sort-dir="asc">
            <thead class="thead-dark">
                <tr>
                    <th onclick="sortTable(0)">ID</th>
                    <th>Foto</th>
                    <th onclick="sortTable(2)">Descripción</th>
                    <th onclick="sortTable(3)">Marca</th>
                    <th onclick="sortTable(4)">Modelo</th>
                    <th onclick="sortTable(5)">Serial</th>
                    <th onclick="sortTable(6)">Cal Date</th>
                    <th onclick="sortTable(7)">Due Date</th>
                    <th onclick="sortTable(8)">Estado</th>
                    <th>Último PDF</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php while($row = $result->fetch_assoc()): 
                // Sólo aplicar clase para 'Vencido' o 'Próxima calibración'
                $cls = '';
                if ($row['status_calculado'] === 'Vencido') {
                    $cls = 'vencido';
                } elseif ($row['status_calculado'] === 'Próxima calibración') {
                    $cls = 'proxima';
                }
            ?>
                <tr class="<?= $cls ?>">
                    <td><?= htmlspecialchars($row['ID']) ?></td>
                    <td>
                        <?= $row['Picture']
                            ? "<a href=\"".htmlspecialchars($row['Picture'])."\" target=\"_blank\">Ver</a>"
                            : '—' ?>
                    </td>
                    <td><?= htmlspecialchars($row['Description']) ?></td>
                    <td><?= htmlspecialchars($row['Brand']) ?></td>
                    <td><?= htmlspecialchars($row['Model']) ?></td>
                    <td><?= htmlspecialchars($row['SerialNumber']) ?></td>
                    <td><?= htmlspecialchars($row['CalDate']) ?></td>
                    <td><?= htmlspecialchars($row['DueDate']) ?></td>
                    <td><?= htmlspecialchars($row['status_calculado']) ?></td>
                    <td>
                        <?= $row['LastPdfPath']
                            ? "<a href=\"".htmlspecialchars($row['LastPdfPath'])."\" target=\"_blank\">
                                 <i class=\"fas fa-file-pdf\"></i>
                               </a>"
                            : '—' ?>
                    </td>
                    <td>
                        <div class="btn-group" role="group">
                            <a class="btn btn-primary btn-sm" href="update.php?id=<?= urlencode($row['ID']) ?>">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a class="btn btn-info btn-sm" href="history.php?id=<?= urlencode($row['ID']) ?>">
                                <i class="fas fa-history"></i>
                            </a>
                            <a class="btn btn-warning btn-sm" href="move_out_of_use.php?id=<?= urlencode($row['ID']) ?>">
                                <i class="fas fa-exclamation-triangle"></i>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <script>
    function filterTable() {
        const q = document.getElementById("searchInput").value.toUpperCase();
        document.querySelectorAll("#instrumentsTable tbody tr").forEach(tr => {
            tr.style.display = [...tr.cells].some(td =>
                td.textContent.toUpperCase().includes(q)
            ) ? "" : "none";
        });
    }

    function sortTable(col) {
        const table = document.getElementById("instrumentsTable"),
              body  = table.tBodies[0],
              rows  = Array.from(body.rows),
              asc   = table.getAttribute("data-sort-dir") !== "asc";

        rows.sort((a,b) => {
            const v1 = a.cells[col].textContent.trim(),
                  v2 = b.cells[col].textContent.trim();
            return asc
                ? v1.localeCompare(v2, undefined, {numeric:true})
                : v2.localeCompare(v1, undefined, {numeric:true});
        });

        rows.forEach(r => body.appendChild(r));
        table.setAttribute("data-sort-dir", asc ? "asc" : "desc");
    }
    </script>
</body>
</html>
<?php
$conn->close();
?>
