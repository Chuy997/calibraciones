<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

require 'config.php';
$conn = getConnection('admin');

$sql = "SELECT * FROM InstrumentsOutOfUse";
$result = $conn->query($sql);

if (!$result) {
    die("Error en la consulta: " . $conn->error);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Instrumentos Fuera de Uso</title>
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

        .container {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>
    <div class="container">
        <h1 class="my-4">Instrumentos Fuera de Uso</h1>
        <table class="table table-striped">
            <thead class="thead-dark">
                <tr>
                    <th>ID</th>
                    <th>Descripción</th>
                    <th>Marca</th>
                    <th>Modelo</th>
                    <th>Número de Serie</th>
                    <th>Fecha de Calibración</th>
                    <th>Fecha de Vencimiento</th>
                    <th>Estado</th>
                    <th>Comentarios</th>
                    <th>Razón</th>
                    <th>Fecha de Remoción</th>
                    <th>Foto</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['ID']); ?></td>
                        <td><?php echo htmlspecialchars($row['Description']); ?></td>
                        <td><?php echo htmlspecialchars($row['Brand']); ?></td>
                        <td><?php echo htmlspecialchars($row['Model']); ?></td>
                        <td><?php echo htmlspecialchars($row['SerialNumber']); ?></td>
                        <td><?php echo htmlspecialchars($row['CalDate']); ?></td>
                        <td><?php echo htmlspecialchars($row['DueDate']); ?></td>
                        <td><?php echo htmlspecialchars($row['Status']); ?></td>
                        <td><?php echo htmlspecialchars($row['Comments']); ?></td>
                        <td><?php echo htmlspecialchars($row['ReasonForRemoval']); ?></td>
                        <td><?php echo htmlspecialchars($row['DateRemoved']); ?></td>
                        <td><a href="<?php echo htmlspecialchars($row['Picture']); ?>" target="_blank">Ver Foto</a></td>
                        <td class="actions">
                            <form method="post" action="return_to_active.php" style="display:inline;">
                                <input type="hidden" name="id" value="<?php echo $row['ID']; ?>">
                                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-undo"></i> Regresar</button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</body>
</html>
<?php
$conn->close();
?>
