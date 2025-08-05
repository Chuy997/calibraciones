<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'consulta') {
    header('Location: login.php');
    exit();
}

require 'config.php';
$conn = getConnection('consulta');

// Consulta con cálculo dinámico de estado:
// - “Vencido” si DueDate < hoy
// - “Próxima calibración” si DueDate entre hoy y hoy+30 días
// - “Calibrado” en el resto de casos
$sql = "
    SELECT 
        ID,
        Description,
        Brand,
        Model,
        SerialNumber,
        CalDate,
        DueDate,
        CertificateNo,
        CASE
            WHEN CURRENT_DATE() > DueDate THEN 'Vencido'
            WHEN DueDate BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY) THEN 'Próxima calibración'
            ELSE 'Calibrado'
        END AS status_calculado
    FROM instruments
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
    <title>Ver Instrumentos</title>
    <link rel="stylesheet" type="text/css" href="styles.css">
</head>
<body>
    <?php include 'menu.php'; ?>
    <div class="container">
        <h1>Instrumentos</h1>
        <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Buscar por cualquier campo">
        <table id="instrumentsTable">
            <thead>
                <tr>
                    <th onclick="sortTable(0)">ID</th>
                    <th onclick="sortTable(1)">Descripción</th>
                    <th onclick="sortTable(2)">Marca</th>
                    <th onclick="sortTable(3)">Modelo</th>
                    <th onclick="sortTable(4)">Serial</th>
                    <th onclick="sortTable(5)">Fecha Cal.</th>
                    <th onclick="sortTable(6)">Fecha Venc.</th>
                    <th onclick="sortTable(7)">Certificado</th>
                    <th onclick="sortTable(8)">Estado</th>
                </tr>
            </thead>
            <tbody>
            <?php while($row = $result->fetch_assoc()) { ?>
                <tr>
                    <td><?= htmlspecialchars($row['ID']) ?></td>
                    <td><?= htmlspecialchars($row['Description']) ?></td>
                    <td><?= htmlspecialchars($row['Brand']) ?></td>
                    <td><?= htmlspecialchars($row['Model']) ?></td>
                    <td><?= htmlspecialchars($row['SerialNumber']) ?></td>
                    <td><?= htmlspecialchars($row['CalDate']) ?></td>
                    <td><?= htmlspecialchars($row['DueDate']) ?></td>
                    <td><?= htmlspecialchars($row['CertificateNo']) ?></td>
                    <td><?= htmlspecialchars($row['status_calculado']) ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>

    <script>
    // ——————— Funciones de búsqueda y orden ———————

    function filterTable() {
        const filter = document.getElementById("searchInput").value.toUpperCase();
        const rows = document.querySelectorAll("#instrumentsTable tbody tr");
        rows.forEach(tr => {
            tr.style.display = [...tr.cells].some(td =>
                td.textContent.toUpperCase().includes(filter)
            ) ? "" : "none";
        });
    }

    function sortTable(colIndex) {
        const table = document.getElementById("instrumentsTable");
        const tbody = table.tBodies[0];
        const rows = Array.from(tbody.rows);
        let asc = table.getAttribute("data-sort-dir") !== "asc";
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
