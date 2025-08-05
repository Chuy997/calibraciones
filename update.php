<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require 'config.php';
$conn = getConnection('admin');

// 1) Obtener y validar ID
$id = $_REQUEST['id'] ?? null;
if (!$id || !preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
    die("ID inválido.");
}

// 2) Cargar datos actuales del instrumento
$stmt = $conn->prepare("SELECT * FROM instruments WHERE ID = ?");
$stmt->bind_param("s", $id);
$stmt->execute();
$instrument = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$instrument) {
    die("Instrumento no encontrado.");
}

// Rutas actuales y CertificateNo (asegurar no nulo)
$pdfPath       = $instrument['PdfPath'];
$picturePath   = $instrument['Picture'];
$certificateNo = $instrument['CertificateNo'] ?? '';
if ($certificateNo === null) {
    $certificateNo = '';
}

// 3) Si es POST, procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description  = trim($_POST['description']);
    $brand        = trim($_POST['brand']);
    $model        = trim($_POST['model']);
    $serialNumber = trim($_POST['serialNumber']);
    $calDate      = $_POST['calDate'];
    $dueDate      = $_POST['dueDate'];
    $status       = $_POST['status'];
    $comments     = trim($_POST['comments']);

    function handleUpload($field, $uploadDir) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION);
            $newName = uniqid($field . '_') . '.' . $ext;
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $dest = $uploadDir . $newName;
            if (move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) {
                return $dest;
            } else {
                throw new Exception("Error al mover el archivo $field.");
            }
        }
        return null;
    }

    try {
        $conn->begin_transaction();

        // c) Procesar PDF
        $newPdf = handleUpload('pdf', 'uploads/');
        if ($newPdf) {
            $pdfPath = $newPdf;
            $u = $conn->prepare("UPDATE instruments SET PdfPath = ? WHERE ID = ?");
            $u->bind_param("ss", $pdfPath, $id);
            $u->execute();
            $u->close();
        }

        // d) Procesar imagen
        $newPic = handleUpload('picture', 'uploads/');
        if ($newPic) {
            $picturePath = $newPic;
            $u = $conn->prepare("UPDATE instruments SET Picture = ? WHERE ID = ?");
            $u->bind_param("ss", $picturePath, $id);
            $u->execute();
            $u->close();
        }

        // e) Actualizar datos principales
        $upd = $conn->prepare("
            UPDATE instruments
            SET Description=?, Brand=?, Model=?, SerialNumber=?,
                CalDate=?, DueDate=?, Status=?, Comments=?
            WHERE ID=?
        ");
        $upd->bind_param(
            "sssssssss",
            $description,
            $brand,
            $model,
            $serialNumber,
            $calDate,
            $dueDate,
            $status,
            $comments,
            $id
        );
        $upd->execute();
        $upd->close();

        // f) Insertar historial
        $hst = $conn->prepare("
            INSERT INTO updatehistory
              (InstrumentID, Description, Brand, Model, SerialNumber,
               CalDate, DueDate, CertificateNo, Status, Comments, UpdatedAt, PdfPath, Picture)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        $hst->bind_param(
            "ssssssssssss",
            $id,
            $description,
            $brand,
            $model,
            $serialNumber,
            $calDate,
            $dueDate,
            $certificateNo,
            $status,
            $comments,
            $pdfPath,
            $picturePath
        );
        $hst->execute();
        $hst->close();

        $conn->commit();
        header('Location: admin.php');
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        die("Error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Actualizar Instrumento</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        body { background:#121212; color:#e0e0e0; }
        .navbar, .card, .modal-content { background:#1e1e1e; color:#e0e0e0; }
        .form-control { background:#2c2c2c; color:#e0e0e0; border:1px solid #444; }
        .form-control::placeholder { color:#e0e0e0; }
        .btn-primary { background:#007bff; border-color:#007bff; color:#fff; }
        .btn-primary:hover { background:#0056b3; border-color:#004085; }
        .container { margin-top:20px; }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>
    <div class="container">
        <h1 class="my-4">Actualizar Instrumento</h1>
        <form method="POST" action="update.php?id=<?= urlencode($id) ?>" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">

            <!-- Campos de texto -->
            <?php foreach ([
                'description'=>'Descripción',
                'brand'=>'Marca',
                'model'=>'Modelo',
                'serialNumber'=>'Número de Serie'
            ] as $field=>$label): ?>
                <div class="form-group">
                    <label for="<?= $field ?>"><?= $label ?></label>
                    <input type="text" class="form-control" id="<?= $field ?>"
                           name="<?= $field ?>"
                           value="<?= htmlspecialchars($instrument[ ucfirst($field) ]) ?>"
                           required>
                </div>
            <?php endforeach; ?>

            <!-- Fechas -->
            <?php foreach ([
                'calDate'=>'Fecha de Calibración',
                'dueDate'=>'Fecha de Vencimiento'
            ] as $field=>$label): ?>
                <div class="form-group">
                    <label for="<?= $field ?>"><?= $label ?></label>
                    <input type="date" class="form-control" id="<?= $field ?>"
                           name="<?= $field ?>"
                           value="<?= htmlspecialchars($instrument[ ucfirst($field) ]) ?>"
                           required>
                </div>
            <?php endforeach; ?>

            <!-- Upload PDF -->
            <div class="form-group">
                <label for="pdf">Subir PDF del Proveedor</label>
                <input type="file" class="form-control" id="pdf" name="pdf" accept="application/pdf">
                <?php if ($pdfPath): ?>
                    <small>Actual: <a href="<?= htmlspecialchars($pdfPath) ?>" target="_blank">Ver PDF</a></small>
                <?php endif; ?>
            </div>

            <!-- Upload Imagen -->
            <div class="form-group">
                <label for="picture">Subir Foto del Instrumento</label>
                <input type="file" class="form-control" id="picture" name="picture" accept="image/*">
                <?php if ($picturePath): ?>
                    <small>Actual: <a href="<?= htmlspecialchars($picturePath) ?>" target="_blank">Ver Foto</a></small>
                <?php endif; ?>
            </div>

            <!-- Estado -->
            <div class="form-group">
                <label for="status">Estado</label>
                <select class="form-control" id="status" name="status" required>
                    <?php foreach (['Calibrado','Próxima calibración','Vencido'] as $opt): ?>
                        <option value="<?= $opt ?>" <?= $instrument['Status'] === $opt ? 'selected' : '' ?>>
                            <?= $opt ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Comentarios -->
            <div class="form-group">
                <label for="comments">Comentarios</label>
                <textarea class="form-control" id="comments" name="comments" rows="3" required><?= 
                    htmlspecialchars($instrument['Comments']) 
                ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Guardar Cambios
            </button>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
$conn->close();
?>```
