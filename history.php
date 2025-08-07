<?php
// /var/www/html/calibraciones/history.php

session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

require 'config.php';
$conn = getConnection('admin');

if (empty($_GET['id'])) {
    die("ID no proporcionado.");
}

$id = $_GET['id'];
$sql = "
    SELECT 
        uh.InstrumentID,
        uh.Description,
        uh.Brand,
        uh.Model,
        uh.SerialNumber,
        uh.CertificateNo,
        uh.CalDate,
        uh.DueDate,
        uh.Status,
        uh.Comments,
        uh.PdfPath,
        uh.Picture,
        uh.UpdatedAt
    FROM updatehistory uh
    WHERE uh.InstrumentID = ?
    ORDER BY uh.UpdatedAt DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Historial de Instrumento #<?= htmlspecialchars($id) ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css">
    <style>
        body { background:#121212; color:#e0e0e0; }
        .container { margin:20px auto; max-width:1000px; }
        table.dataTable tbody tr.table-danger td { background-color: #5a1e1e; }
        table.dataTable tbody tr.table-warning td { background-color: #4d421e; }
        table.dataTable tbody tr.table-success td { background-color: #1e3a1e; }
        .table th, .table td { border-color: #444; color: #ffffff; }
        .thead-dark th { background-color: #333; }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>
    <div class="container">
        <h1 class="mb-4">Historial de Instrumento #<?= htmlspecialchars($id) ?></h1>

        <?php if ($result->num_rows): ?>
            <table id="historyTable" class="table table-striped table-bordered">
                <thead class="thead-dark">
                    <tr>
                        <th>Fecha</th>
                        <th>Certificado</th>
                        <th>Descripción</th>
                        <th>Marca</th>
                        <th>Modelo</th>
                        <th>Serial</th>
                        <th>Cal Date</th>
                        <th>Due Date</th>
                        <th>Comentarios</th>
                        <th>PDF</th>
                        <th>Imagen</th>
                    </tr>
                </thead>
                <tbody>
                <?php while($row = $result->fetch_assoc()): 
                    // Determinar clase de fila según estado
                    switch ($row['Status']) {
                        case 'Vencido': $cls = 'table-danger'; break;
                        case 'Próxima calibración': $cls = 'table-warning'; break;
                        default: $cls = 'table-success'; break;
                    }
                ?>
                    <tr class="<?= $cls ?>">
                        <td><?= htmlspecialchars($row['UpdatedAt']) ?></td>
                        <td><?= htmlspecialchars($row['CertificateNo']) ?></td>
                        <td><?= htmlspecialchars($row['Description']) ?></td>
                        <td><?= htmlspecialchars($row['Brand']) ?></td>
                        <td><?= htmlspecialchars($row['Model']) ?></td>
                        <td><?= htmlspecialchars($row['SerialNumber']) ?></td>
                        <td><?= htmlspecialchars($row['CalDate']) ?></td>
                        <td><?= htmlspecialchars($row['DueDate']) ?></td>
                        <td><?= htmlspecialchars($row['Comments']) ?></td>
                        <td class="text-center">
                            <?php if ($row['PdfPath']): ?>
                                <a href="<?= htmlspecialchars($row['PdfPath']) ?>" target="_blank">
                                    <i class="fas fa-file-pdf fa-lg text-danger"></i>
                                </a>
                            <?php else: ?>
                                &mdash;
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($row['Picture']): ?>
                                <a href="<?= htmlspecialchars($row['Picture']) ?>" target="_blank">
                                    <i class="fas fa-image fa-lg text-info"></i>
                                </a>
                            <?php else: ?>
                                &mdash;
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-secondary">
                No hay historial de actualizaciones para este instrumento.
            </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>
    <script>
        $(document).ready(function(){
            $('#historyTable').DataTable({
                lengthChange: false,
                pageLength: 10,
                ordering: true,
                order: [[0, 'desc']],
                language: {
                    search: "Filtro:",
                    zeroRecords: "No se encontraron registros",
                    info: "Mostrando _START_ a _END_ de _TOTAL_",
                    infoEmpty: "Sin registros",
                    paginate: {
                        first: "Primero",
                        last: "Último",
                        next: "Siguiente",
                        previous: "Anterior"
                    }
                }
            });
        });
    </script>
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>
