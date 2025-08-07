<?php
// /var/www/html/calibraciones/add.php

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

// Función para manejar subida de archivos
function handleUpload(string $field, string $uploadDir): ?string {
    if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
        $ext  = pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION);
        $name = uniqid($field . '_') . '.' . $ext;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $dest = $uploadDir . $name;
        if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) {
            throw new Exception("Error al mover el archivo {$field}.");
        }
        return $dest;
    }
    return null;
}

$errors = [];
$values = [
    'id'            => '',
    'description'   => '',
    'brand'         => '',
    'model'         => '',
    'serialNumber'  => '',
    'certificateNo' => '',
    'calDate'       => '',
    'dueDate'       => '',
    'status'        => '',
    'comments'      => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) Recoger + sanitizar
    foreach ($values as $field => &$val) {
        $val = trim($_POST[$field] ?? '');
    }
    unset($val);

    // 2) Validaciones
    if ($values['id'] === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $values['id'])) {
        $errors[] = 'ID inválido.';
    }
    if ($values['certificateNo'] === '') {
        $errors[] = 'Debe ingresar el número de certificado.';
    }
    if ($values['calDate'] > $values['dueDate']) {
        $errors[] = 'La fecha de calibración debe ser anterior a la de vencimiento.';
    }
    // duplicado de ID
    $dup = $conn->prepare("SELECT 1 FROM instruments WHERE ID = ?");
    $dup->bind_param("s", $values['id']);
    $dup->execute();
    $dup->store_result();
    if ($dup->num_rows) {
        $errors[] = 'Ya existe un instrumento con ese ID.';
    }
    $dup->close();

    if (empty($errors)) {
        try {
            $conn->begin_transaction();

            // 3) Subidas
            $pdfPath     = handleUpload('pdf', 'uploads/')     ?? '';
            $picturePath = handleUpload('picture', 'uploads/') ?? '';

            // 4) Insert en instruments (ahora con CertificateNo)
            $ins = $conn->prepare("
                INSERT INTO instruments
                  (ID, Description, Brand, Model, SerialNumber, CertificateNo,
                   CalDate, DueDate, Status, Comments, PdfPath, Picture)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $ins->bind_param(
                "ssssssssssss",
                $values['id'],
                $values['description'],
                $values['brand'],
                $values['model'],
                $values['serialNumber'],
                $values['certificateNo'],
                $values['calDate'],
                $values['dueDate'],
                $values['status'],
                $values['comments'],
                $pdfPath,
                $picturePath
            );
            $ins->execute();
            $ins->close();

            // 5) Primer registro en updatehistory
            $hst = $conn->prepare("
                INSERT INTO updatehistory
                  (InstrumentID, Description, Brand, Model, SerialNumber,
                   CertificateNo, CalDate, DueDate, Status, Comments,
                   UpdatedAt, PdfPath, Picture)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
            ");
            $hst->bind_param(
                "isssssssssss",
                $values['id'],
                $values['description'],
                $values['brand'],
                $values['model'],
                $values['serialNumber'],
                $values['certificateNo'],
                $values['calDate'],
                $values['dueDate'],
                $values['status'],
                $values['comments'],
                $pdfPath,
                $picturePath
            );
            $hst->execute();
            $hst->close();

            $conn->commit();
            header('Location: admin.php');
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Error al guardar: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Agregar Nuevo Instrumento</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { background:#121212; color:#e0e0e0; }
        .container { margin:20px auto; max-width:600px; }
        .form-control { background:#2c2c2c; color:#e0e0e0; border:1px solid #444; }
        .btn-primary { background:#007bff; color:#fff; }
        .alert { margin-top:1rem; }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>
    <div class="container">
        <h1 class="mb-4">Agregar Nuevo Instrumento</h1>

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <?php foreach ([
                'id'=>'ID',
                'description'=>'Descripción',
                'brand'=>'Marca',
                'model'=>'Modelo',
                'serialNumber'=>'Número de Serie',
                'certificateNo'=>'Número de Certificado'
            ] as $field=>$label): ?>
                <div class="form-group">
                    <label for="<?= $field ?>"><?= $label ?></label>
                    <input type="text"
                           class="form-control"
                           id="<?= $field ?>"
                           name="<?= $field ?>"
                           value="<?= htmlspecialchars($values[$field]) ?>"
                           required>
                </div>
            <?php endforeach; ?>

            <?php foreach ([
                'calDate'=>'Fecha de Calibración',
                'dueDate'=>'Fecha de Vencimiento'
            ] as $field=>$label): ?>
                <div class="form-group">
                    <label for="<?= $field ?>"><?= $label ?></label>
                    <input type="date"
                           class="form-control"
                           id="<?= $field ?>"
                           name="<?= $field ?>"
                           value="<?= htmlspecialchars($values[$field]) ?>"
                           required>
                </div>
            <?php endforeach; ?>

            <div class="form-group">
                <label for="status">Estado</label>
                <select id="status" name="status" class="form-control" required>
                    <?php foreach (['Calibrado','Próxima calibración','Vencido'] as $opt): ?>
                        <option value="<?= $opt ?>"
                          <?= $values['status'] === $opt ? 'selected' : '' ?>>
                          <?= $opt ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="comments">Comentarios</label>
                <textarea id="comments"
                          name="comments"
                          class="form-control"
                          rows="3"
                          required><?= htmlspecialchars($values['comments']) ?></textarea>
            </div>

            <div class="form-group">
                <label for="pdf">Subir PDF</label>
                <input type="file" id="pdf" name="pdf" accept="application/pdf" class="form-control">
            </div>
            <div class="form-group">
                <label for="picture">Subir Imagen</label>
                <input type="file" id="picture" name="picture" accept="image/*" class="form-control">
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Guardar
            </button>
        </form>
    </div>
</body>
</html>
<?php
$conn->close();
?>
