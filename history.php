<?php
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
    <title>Historial de Actualizaciones</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        body { background:#121212; color:#e0e0e0; }
        .container { margin-top:20px; }
        .thead-dark th { background:#333; color:#fff; border-color:#444; }
        .table-striped tbody tr:nth-of-type(odd)  { background:#2c2c2c; }
        .table-striped tbody tr:nth-of-type(even) { background:#1e1e1e; }
        .table th, .table td { border-color:#444; }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>
    <div class="container">
        <h1 class="my-4">Historial de Instrumento #<?= htmlspecialchars($id) ?></h1>
        <?php if ($result->num_rows): ?>
            <table class="table table-striped">
                <thead class="thead-dark">
                    <tr>
                        <th>Fecha</th>
                        <th>Descripción</th>
                        <th>Marca</th>
                        <th>Modelo</th>
                        <th>Serial</th>
                        <th>Cal Date</th>
                        <th>Due Date</th>
                        <th>Estado</th>
                        <th>Comentarios</th>
                        <th>PDF</th>
                        <th>Imagen</th>
                    </tr>
                </thead>
                <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['UpdatedAt']) ?></td>
                        <td><?= htmlspecialchars($row['Description']) ?></td>
                        <td><?= htmlspecialchars($row['Brand']) ?></td>
                        <td><?= htmlspecialchars($row['Model']) ?></td>
                        <td><?= htmlspecialchars($row['SerialNumber']) ?></td>
                        <td><?= htmlspecialchars($row['CalDate']) ?></td>
                        <td><?= htmlspecialchars($row['DueDate']) ?></td>
                        <td><?= htmlspecialchars($row['Status']) ?></td>
                        <td><?= htmlspecialchars($row['Comments']) ?></td>
                        <td>
                            <?php if ($row['PdfPath']): ?>
                                <a href="<?= htmlspecialchars($row['PdfPath']) ?>" target="_blank">
                                    <i class="fas fa-file-pdf"></i> Ver
                                </a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['Picture']): ?>
                                <a href="<?= htmlspecialchars($row['Picture']) ?>" target="_blank">
                                    <i class="fas fa-image"></i> Ver
                                </a>
                            <?php else: ?>
                                —
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
</body>
</html>
<?php
$conn->close();
?>
