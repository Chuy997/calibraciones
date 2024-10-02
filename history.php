<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

require 'config.php';
$conn = getConnection('admin');

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "SELECT * FROM UpdateHistory WHERE InstrumentID = ? ORDER BY UpdatedAt DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    die("ID no proporcionado.");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Historial de Actualizaciones</title>
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
        
        .container {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>
    <div class="container">
        <h1 class="my-4">Historial de Actualizaciones</h1>
        <?php if ($result->num_rows > 0): ?>
            <table class="table table-striped">
                <thead class="thead-dark">
                    <tr>
                        <th>ID del Instrumento</th>
                        <th>Descripción</th>
                        <th>Marca</th>
                        <th>Modelo</th>
                        <th>Número de Serie</th>
                        <th>Fecha de Calibración</th>
                        <th>Fecha de Vencimiento</th>
                        <th>Estado</th>
                        <th>Comentarios</th>
                        <th>Fecha de Actualización</th>
                        <th>Documento PDF</th>
                        <th>Imagen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['InstrumentID']); ?></td>
                            <td><?php echo htmlspecialchars($row['Description']); ?></td>
                            <td><?php echo htmlspecialchars($row['Brand']); ?></td>
                            <td><?php echo htmlspecialchars($row['Model']); ?></td>
                            <td><?php echo htmlspecialchars($row['SerialNumber']); ?></td>
                            <td><?php echo htmlspecialchars($row['CalDate']); ?></td>
                            <td><?php echo htmlspecialchars($row['DueDate']); ?></td>
                            <td><?php echo htmlspecialchars($row['Status']); ?></td>
                            <td><?php echo htmlspecialchars($row['Comments']); ?></td>
                            <td><?php echo htmlspecialchars($row['UpdatedAt']); ?></td>
                            <td>
                                <?php if (!empty($row['PdfPath'])): ?>
                                    <a href="<?php echo htmlspecialchars($row['PdfPath']); ?>" target="_blank">Ver PDF</a>
                                    <button class="btn btn-info btn-sm" onclick="openPdf('<?php echo htmlspecialchars($row['PdfPath']); ?>')">Abrir</button>
                                <?php else: ?>
                                    No disponible
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['Picture'])): ?>
                                    <a href="<?php echo htmlspecialchars($row['Picture']); ?>" target="_blank">Ver Imagen</a>
                                <?php else: ?>
                                    No disponible
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay historial de actualizaciones para este instrumento.</p>
        <?php endif; ?>
    </div>

    <!-- Modal para visualizar el PDF -->
    <div class="modal fade" id="pdfModal" tabindex="-1" role="dialog" aria-labelledby="pdfModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pdfModalLabel">Visualizar PDF</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <iframe id="pdfIframe" src="" frameborder="0" width="100%" height="600px"></iframe>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openPdf(pdfPath) {
            document.getElementById('pdfIframe').src = pdfPath;
            $('#pdfModal').modal('show');
        }
    </script>
</body>
</html>
<?php
$conn->close();
?>
