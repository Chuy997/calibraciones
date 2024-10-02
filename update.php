<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

require 'config.php';
$conn = getConnection('admin');

// Verificar si el ID se ha proporcionado mediante GET (al cargar el formulario) o POST (al enviar el formulario)
if (isset($_GET['id']) || isset($_POST['id'])) {
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
    } else {
        $id = $_POST['id'];
    }

    $sql = "SELECT * FROM Instruments WHERE ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $instrument = $result->fetch_assoc();
    if (!$instrument) {
        die("Instrumento no encontrado.");
    }
} else {
    die("ID no proporcionado.");
}

$pdfPath = $instrument['PdfPath']; // Inicializar pdfPath con el valor actual de la base de datos
$picturePath = $instrument['Picture']; // Inicializar picturePath con el valor actual de la base de datos

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $description = $_POST['description'];
    $brand = $_POST['brand'];
    $model = $_POST['model'];
    $serialNumber = $_POST['serialNumber'];
    $calDate = $_POST['calDate'];
    $dueDate = $_POST['dueDate'];
    $status = $_POST['status'];
    $comments = $_POST['comments'];

    // Manejo de la subida del archivo PDF
    if (isset($_FILES['pdf']) && $_FILES['pdf']['error'] == UPLOAD_ERR_OK) {
        $pdfTmpPath = $_FILES['pdf']['tmp_name'];
        $pdfName = basename($_FILES['pdf']['name']);
        $uploadDir = 'uploads/';
        $pdfPath = $uploadDir . $pdfName;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        if (move_uploaded_file($pdfTmpPath, $pdfPath)) {
            // Actualizar la ruta del archivo PDF en la base de datos
            $sql = "UPDATE Instruments SET PdfPath = ? WHERE ID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $pdfPath, $id);
            $stmt->execute();
        } else {
            die("Error al mover el archivo subido.");
        }
    }

    // Manejo de la subida del archivo de imagen
    if (isset($_FILES['picture']) && $_FILES['picture']['error'] == UPLOAD_ERR_OK) {
        $pictureTmpPath = $_FILES['picture']['tmp_name'];
        $pictureName = basename($_FILES['picture']['name']);
        $uploadDir = 'uploads/';
        $picturePath = $uploadDir . $pictureName;
    
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
    
        if (move_uploaded_file($pictureTmpPath, $picturePath)) {
            // Actualizar la ruta del archivo de imagen en la base de datos
            $sql = "UPDATE Instruments SET Picture = ? WHERE ID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $picturePath, $id);
            $stmt->execute();
        } else {
            die("Error al mover el archivo de imagen subido.");
        }
    }

    // Update other fields in Instruments table
    $sql = "UPDATE Instruments SET Description = ?, Brand = ?, Model = ?, SerialNumber = ?, CalDate = ?, DueDate = ?, Status = ?, Comments = ? WHERE ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssss", $description, $brand, $model, $serialNumber, $calDate, $dueDate, $status, $comments, $id);
    if ($stmt->execute()) {
        // Insertar el historial de cambios
        $sql_history = "INSERT INTO UpdateHistory (InstrumentID, Description, Brand, Model, SerialNumber, CalDate, DueDate, Status, Comments, UpdatedAt, PdfPath, Picture) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)";
        $stmt_history = $conn->prepare($sql_history);
        $stmt_history->bind_param("sssssssssss", $id, $description, $brand, $model, $serialNumber, $calDate, $dueDate, $status, $comments, $pdfPath, $picturePath);
        $stmt_history->execute();


        header('Location: admin.php');
        exit();
    } else {
        echo "Error al actualizar el instrumento: " . $stmt->error;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Actualizar Instrumento</title>
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

        .form-control {
            background-color: #2c2c2c;
            color: #e0e0e0;
            border: 1px solid #444444;
        }

        .form-control::placeholder {
            color: #e0e0e0;
        }

        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            color: #ffffff;
        }

        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #004085;
        }
        
        .container {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>
    <div class="container">
        <h1 class="my-4">Actualizar Instrumento</h1>
        <form method="POST" action="update.php" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($instrument['ID']); ?>">

            <div class="form-group">
                <label for="id">ID</label>
                <input type="text" class="form-control" id="id" value="<?php echo htmlspecialchars($instrument['ID']); ?>" readonly>
            </div>
            <div class="form-group">
                <label for="description">Descripción</label>
                <input type="text" class="form-control" id="description" name="description" value="<?php echo htmlspecialchars($instrument['Description']); ?>" required>
            </div>
            <div class="form-group">
                <label for="brand">Marca</label>
                <input type="text" class="form-control" id="brand" name="brand" value="<?php echo htmlspecialchars($instrument['Brand']); ?>" required>
            </div>
            <div class="form-group">
                <label for="model">Modelo</label>
                <input type="text" class="form-control" id="model" name="model" value="<?php echo htmlspecialchars($instrument['Model']); ?>" required>
            </div>
            <div class="form-group">
                <label for="serialNumber">Número de Serie</label>
                <input type="text" class="form-control" id="serialNumber" name="serialNumber" value="<?php echo htmlspecialchars($instrument['SerialNumber']); ?>" required>
            </div>
            <div class="form-group">
                <label for="calDate">Fecha de Calibración</label>
                <input type="date" class="form-control" id="calDate" name="calDate" value="<?php echo htmlspecialchars($instrument['CalDate']); ?>" required>
            </div>
            <div class="form-group">
                <label for="dueDate">Fecha de Vencimiento</label>
                <input type="date" class="form-control" id="dueDate" name="dueDate" value="<?php echo htmlspecialchars($instrument['DueDate']); ?>" required>
            </div>
            <div class="form-group">
                <label for="picture">Subir Imagen del Instrumento</label>
                <input type="file" class="form-control" id="picture" name="picture" accept="image/*">
                <?php if (!empty($instrument['Picture'])): ?>
                    <p>Imagen actual: <a href="<?php echo htmlspecialchars($instrument['Picture']); ?>" target="_blank">Ver Imagen</a></p>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="status">Estado</label>
                <select class="form-control" id="status" name="status" required>
                    <option value="Calibrado" <?php if ($instrument['Status'] == 'Calibrado') echo 'selected'; ?>>Calibrado</option>
                    <option value="Fuera de Calibración" <?php if ($instrument['Status'] == 'Fuera de Calibración') echo 'selected'; ?>>Fuera de Calibración</option>
                    <option value="En Proceso de Calibración" <?php if ($instrument['Status'] == 'En Proceso de Calibración') echo 'selected'; ?>>En Proceso de Calibración</option>
                </select>
            </div>
            <div class="form-group">
                <label for="comments">Comentarios</label>
                <textarea class="form-control" id="comments" name="comments" rows="3" required><?php echo htmlspecialchars($instrument['Comments']); ?></textarea>
            </div>
            <div class="form-group">
                <label for="pdf">Subir PDF del Proveedor</label>
                <input type="file" class="form-control" id="pdf" name="pdf" accept="application/pdf">
                <?php if (!empty($instrument['PdfPath'])): ?>
                    <p>Archivo actual: <a href="<?php echo htmlspecialchars($instrument['PdfPath']); ?>" target="_blank">Ver PDF</a></p>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="picture">Subir Foto del Instrumento</label>
                <input type="file" class="form-control" id="picture" name="picture" accept="image/*">
                <?php if (!empty($instrument['Picture'])): ?>
                    <p>Foto actual: <a href="<?php echo htmlspecialchars($instrument['Picture']); ?>" target="_blank">Ver Foto</a></p>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Cambios</button>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
$conn->close();
?>
