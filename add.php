<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

require 'config.php';
$conn = getConnection('admin');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $description = $_POST['description'];
    $brand = $_POST['brand'];
    $model = $_POST['model'];
    $serialNumber = $_POST['serialNumber'];
    $calDate = $_POST['calDate'];
    $dueDate = $_POST['dueDate'];
    $status = $_POST['status'];
    $comments = $_POST['comments'];
    $pdfPath = '';
    $picturePath = '';

    // Manejo de la subida del archivo PDF
    if (isset($_FILES['pdf']) && $_FILES['pdf']['error'] == UPLOAD_ERR_OK) {
        $pdfTmpPath = $_FILES['pdf']['tmp_name'];
        $pdfName = basename($_FILES['pdf']['name']);
        $uploadDir = 'uploads/';
        $pdfPath = $uploadDir . $pdfName;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        if (!move_uploaded_file($pdfTmpPath, $pdfPath)) {
            die("Error al mover el archivo subido.");
        }
    }

    // Manejo de la subida de la imagen
    if (isset($_FILES['picture']) && $_FILES['picture']['error'] == UPLOAD_ERR_OK) {
        $pictureTmpPath = $_FILES['picture']['tmp_name'];
        $pictureName = basename($_FILES['picture']['name']);
        $uploadDir = 'uploads/';
        $picturePath = $uploadDir . $pictureName;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        if (!move_uploaded_file($pictureTmpPath, $picturePath)) {
            die("Error al mover la imagen subida.");
        }
    }

    $sql = "INSERT INTO Instruments (ID, Description, Brand, Model, SerialNumber, CalDate, DueDate, Status, Comments, PdfPath, Picture) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssssss", $id, $description, $brand, $model, $serialNumber, $calDate, $dueDate, $status, $comments, $pdfPath, $picturePath);

    if ($stmt->execute()) {
        header('Location: admin.php');
        exit();
    } else {
        echo "Error al agregar el instrumento: " . $stmt->error;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Agregar Nuevo Instrumento</title>
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
        <h1 class="my-4">Agregar Nuevo Instrumento</h1>
        <form method="POST" action="add.php" enctype="multipart/form-data">
            <div class="form-group">
                <label for="id">ID</label>
                <input type="text" class="form-control" id="id" name="id" required>
            </div>
            <div class="form-group">
                <label for="description">Descripción</label>
                <input type="text" class="form-control" id="description" name="description" required>
            </div>
            <div class="form-group">
                <label for="brand">Marca</label>
                <input type="text" class="form-control" id="brand" name="brand" required>
            </div>
            <div class="form-group">
                <label for="model">Modelo</label>
                <input type="text" class="form-control" id="model" name="model" required>
            </div>
            <div class="form-group">
                <label for="serialNumber">Número de Serie</label>
                <input type="text" class="form-control" id="serialNumber" name="serialNumber" required>
            </div>
            <div class="form-group">
                <label for="calDate">Fecha de Calibración</label>
                <input type="date" class="form-control" id="calDate" name="calDate" required>
            </div>
            <div class="form-group">
                <label for="dueDate">Fecha de Vencimiento</label>
                <input type="date" class="form-control" id="dueDate" name="dueDate" required>
            </div>
            <div class="form-group">
                <label for="status">Estado</label>
                <select class="form-control" id="status" name="status" required>
                    <option value="Calibrado">Calibrado</option>
                    <option value="Fuera de Calibración">Fuera de Calibración</option>
                    <option value="En Proceso de Calibración">En Proceso de Calibración</option>
                </select>
            </div>
            <div class="form-group">
                <label for="comments">Comentarios</label>
                <textarea class="form-control" id="comments" name="comments" rows="3" required></textarea>
            </div>
            <div class="form-group">
                <label for="pdf">Subir PDF del Proveedor</label>
                <input type="file" class="form-control" id="pdf" name="pdf" accept="application/pdf">
            </div>
            <div class="form-group">
                <label for="picture">Subir Imagen del Instrumento</label>
                <input type="file" class="form-control" id="picture" name="picture" accept="image/*">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Instrumento</button>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
$conn->close();
?>
