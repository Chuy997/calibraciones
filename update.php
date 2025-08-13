<?php
// /var/www/html/calibraciones/update.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_auth('admin'); // solo admins

// Helper de escape
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// --- Config de subida ---
const MAX_IMG_BYTES = 5 * 1024 * 1024;     // 5MB
const MAX_PDF_BYTES = 20 * 1024 * 1024;    // 20MB
$ALLOWED_IMG_EXT = ['jpg','jpeg','png','webp'];
$ALLOWED_PDF_EXT = ['pdf'];

// Estados permitidos según tu ENUM en BD
$STATUS_ALLOWED = [
  'calibrado' => 'Calibrado',
  'en proceso de calibracion' => 'En proceso de calibración',
  'fuera de calibracion' => 'Fuera de calibración',
];

// 1) Obtener y validar ID
$id = $_GET['id'] ?? $_POST['id'] ?? null;
if (!$id || !preg_match('/^[A-Za-z0-9._-]+$/', $id)) {
    http_response_code(400);
    exit('ID inválido.');
}

// 2) Cargar datos actuales del instrumento
try {
    $pdo = pdo();
    $stmt = $pdo->prepare('SELECT * FROM instruments WHERE ID = ?');
    $stmt->execute([$id]);
    $instrument = $stmt->fetch();
    if (!$instrument) {
        http_response_code(404);
        exit('Instrumento no encontrado.');
    }
} catch (Throwable $e) {
    http_response_code(500);
    exit('Error al cargar el instrumento.');
}

// Rutas actuales y CertificateNo (asegurar no nulo)
$pdfPath       = (string)($instrument['PdfPath'] ?? '');
$picturePath   = (string)($instrument['Picture'] ?? '');
$certificateNo = (string)($instrument['CertificateNo'] ?? '');

// Valores “del formulario” (para re-llenar si hay errores)
$values = [
  'description'  => (string)($instrument['Description'] ?? ''),
  'brand'        => (string)($instrument['Brand'] ?? ''),
  'model'        => (string)($instrument['Model'] ?? ''),
  'serialNumber' => (string)($instrument['SerialNumber'] ?? ''),
  'calDate'      => (string)($instrument['CalDate'] ?? ''),
  'dueDate'      => (string)($instrument['DueDate'] ?? ''),
  'status'       => (string)($instrument['Status'] ?? 'calibrado'),
  'comments'     => (string)($instrument['Comments'] ?? ''),
];

$errors = [];

/**
 * Maneja una subida validando tamaño/mime/ext. Guarda en $destDir (ABSOLUTO).
 * Devuelve una RUTA RELATIVA web (p.ej. calibraciones/uploads/ID/archivo.ext) o null si no hay archivo.
 * $kind = 'pdf' | 'img'
 */
function handleUpload(string $field, string $destDir, string $kind): ?string {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Error al subir $field (código {$_FILES[$field]['error']}).");
    }

    $tmp  = $_FILES[$field]['tmp_name'];
    $name = $_FILES[$field]['name'];
    $size = (int)$_FILES[$field]['size'];
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if ($kind === 'pdf') {
        if ($size > MAX_PDF_BYTES) throw new RuntimeException("El PDF excede el tamaño permitido.");
        if (!in_array($ext, $GLOBALS['ALLOWED_PDF_EXT'], true)) throw new RuntimeException("Extensión de PDF no permitida.");
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $tmp) ?: '';
        finfo_close($finfo);
        if (stripos($mime, 'pdf') === false) throw new RuntimeException("El archivo no es un PDF válido.");
    } else {
        if ($size > MAX_IMG_BYTES) throw new RuntimeException("La imagen excede el tamaño permitido.");
        if (!in_array($ext, $GLOBALS['ALLOWED_IMG_EXT'], true)) throw new RuntimeException("Extensión de imagen no permitida.");
        if (@getimagesize($tmp) === false) throw new RuntimeException("El archivo de imagen es inválido.");
    }

    if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
        throw new RuntimeException("No se pudo crear el directorio de destino.");
    }

    $safeBase = preg_replace('/[^A-Za-z0-9_\-]/', '_', pathinfo($name, PATHINFO_FILENAME));
    $final    = $safeBase . '_' . time() . '.' . $ext;
    $destAbs  = rtrim($destDir, '/').'/'.$final;

    if (!move_uploaded_file($tmp, $destAbs)) {
        throw new RuntimeException("No se pudo mover el archivo subido.");
    }

    // Retornar ruta relativa web
    $docroot = rtrim($_SERVER['DOCUMENT_ROOT'], '/').'/';
    $rel     = ltrim(str_replace($docroot, '', $destAbs), '/');
    if (!str_starts_with($rel, 'calibraciones/')) {
        // según tu estructura, servimos desde /calibraciones
        $rel = 'calibraciones/' . ltrim($rel, '/');
    }
    return $rel;
}

// 3) Si es POST, procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (!isset($_POST['csrf']) || !csrf_validate($_POST['csrf'])) {
        $errors[] = 'Sesión expirada. Por favor, vuelve a intentar.';
    }

    // Recoger valores
    foreach ($values as $k => $_) {
        $values[$k] = trim($_POST[$k] ?? '');
    }

    // Validaciones
    if ($values['calDate'] === '' || $values['dueDate'] === '') {
        $errors[] = 'Debes ingresar las fechas de calibración y vencimiento.';
    } elseif ($values['calDate'] > $values['dueDate']) {
        $errors[] = 'La fecha de calibración debe ser anterior a la de vencimiento.';
    }
    if (!array_key_exists($values['status'], $STATUS_ALLOWED)) {
        $errors[] = 'Estado inválido.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Carpeta de este instrumento
            $projectRoot = __DIR__; // /var/www/html/calibraciones
            $destDirAbs  = $projectRoot . '/uploads/' . $id . '/';

            // Subir archivos si se enviaron
            $newPdfRel     = handleUpload('pdf',     $destDirAbs, 'pdf'); // null si no hay nuevo
            $newPictureRel = handleUpload('picture', $destDirAbs, 'img'); // null si no hay nuevo

            if ($newPdfRel !== null)   $pdfPath     = $newPdfRel;
            if ($newPictureRel !== null) $picturePath = $newPictureRel;

            // Actualizar instrumentos
            $upd = $pdo->prepare("
                UPDATE instruments
                SET Description=:Description, Brand=:Brand, Model=:Model, SerialNumber=:SerialNumber,
                    CalDate=:CalDate, DueDate=:DueDate, Status=:Status, Comments=:Comments,
                    PdfPath=:PdfPath, Picture=:Picture
                WHERE ID=:ID
            ");
            $upd->execute([
                ':Description'  => $values['description'],
                ':Brand'        => $values['brand'],
                ':Model'        => $values['model'],
                ':SerialNumber' => $values['serialNumber'],
                ':CalDate'      => $values['calDate'],
                ':DueDate'      => $values['dueDate'],
                ':Status'       => $values['status'],
                ':Comments'     => $values['comments'],
                ':PdfPath'      => $pdfPath ?: null,
                ':Picture'      => $picturePath ?: null,
                ':ID'           => $id,
            ]);

            // Insertar snapshot en updatehistory
            $hst = $pdo->prepare("
                INSERT INTO updatehistory
                  (InstrumentID, UpdatedColumn, OldValue, NewValue,
                   Description, Brand, Model, SerialNumber,
                   CalDate, DueDate, CertificateNo, Status, Comments, PdfPath, Picture)
                VALUES
                  (:InstrumentID, :UpdatedColumn, :OldValue, :NewValue,
                   :Description, :Brand, :Model, :SerialNumber,
                   :CalDate, :DueDate, :CertificateNo, :Status, :Comments, :PdfPath, :Picture)
            ");
            $hst->execute([
                ':InstrumentID' => $id,
                ':UpdatedColumn'=> 'update',
                ':OldValue'     => null,
                ':NewValue'     => null,
                ':Description'  => $values['description'],
                ':Brand'        => $values['brand'],
                ':Model'        => $values['model'],
                ':SerialNumber' => $values['serialNumber'],
                ':CalDate'      => $values['calDate'],
                ':DueDate'      => $values['dueDate'],
                ':CertificateNo'=> $certificateNo,
                ':Status'       => $values['status'],
                ':Comments'     => $values['comments'],
                ':PdfPath'      => $pdfPath ?: null,
                ':Picture'      => $picturePath ?: null,
            ]);

            $pdo->commit();
            header('Location: admin.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Error al guardar: ' . $e->getMessage();
        }
    }
}
?>
<?php include __DIR__ . '/partials/header.php'; ?>

<div class="row justify-content-center">
  <div class="col-12 col-lg-8 col-xl-7">
    <h1 class="h4 my-3">Actualizar instrumento</h1>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <ul class="m-0 ps-3">
          <?php foreach ($errors as $err): ?>
            <li><?= h($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="POST" action="update.php?id=<?= h($id) ?>" enctype="multipart/form-data" class="needs-validation" novalidate>
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="id" value="<?= h($id) ?>">

      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">ID</label>
          <input type="text" class="form-control" value="<?= h($id) ?>" disabled>
          <div class="form-text">El ID no se puede cambiar.</div>
        </div>

        <div class="col-12">
          <label for="description" class="form-label">Descripción</label>
          <input type="text" class="form-control" id="description" name="description" value="<?= h($values['description']) ?>" required>
        </div>

        <div class="col-sm-4">
          <label for="brand" class="form-label">Marca</label>
          <input type="text" class="form-control" id="brand" name="brand" value="<?= h($values['brand']) ?>">
        </div>
        <div class="col-sm-4">
          <label for="model" class="form-label">Modelo</label>
          <input type="text" class="form-control" id="model" name="model" value="<?= h($values['model']) ?>">
        </div>
        <div class="col-sm-4">
          <label for="serialNumber" class="form-label">Número de Serie</label>
          <input type="text" class="form-control" id="serialNumber" name="serialNumber" value="<?= h($values['serialNumber']) ?>">
        </div>

        <div class="col-sm-6">
          <label for="calDate" class="form-label">Fecha de Calibración</label>
          <input type="date" class="form-control" id="calDate" name="calDate" value="<?= h($values['calDate']) ?>" required>
        </div>
        <div class="col-sm-6">
          <label for="dueDate" class="form-label">Fecha de Vencimiento</label>
          <input type="date" class="form-control" id="dueDate" name="dueDate" value="<?= h($values['dueDate']) ?>" required>
        </div>

        <div class="col-sm-6">
          <label for="status" class="form-label">Estado</label>
          <select id="status" name="status" class="form-select" required>
            <?php foreach ($STATUS_ALLOWED as $val => $label): ?>
              <option value="<?= h($val) ?>" <?= $values['status'] === $val ? 'selected' : '' ?>>
                <?= h($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12">
          <label for="comments" class="form-label">Comentarios</label>
          <textarea id="comments" name="comments" class="form-control" rows="3" required><?= h($values['comments']) ?></textarea>
        </div>

        <div class="col-md-6">
          <label class="form-label d-flex align-items-center justify-content-between">
            <span>PDF del proveedor</span>
            <?php if ($pdfPath): ?>
              <a href="<?= h($pdfPath) ?>" target="_blank" class="small text-decoration-none"><i class="fa fa-file-pdf me-1"></i>Ver actual</a>
            <?php endif; ?>
          </label>
          <input type="file" id="pdf" name="pdf" accept="application/pdf" class="form-control">
          <div class="mt-2 d-none" id="pdfPreviewBox">
            <iframe id="pdfPreview" title="PDF" style="width:100%;height:300px;border:1px solid #333;border-radius:8px;"></iframe>
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label d-flex align-items-center justify-content-between">
            <span>Foto del instrumento</span>
            <?php if ($picturePath): ?>
              <a href="<?= h($picturePath) ?>" target="_blank" class="small text-decoration-none"><i class="fa fa-image me-1"></i>Ver actual</a>
            <?php endif; ?>
          </label>
          <input type="file" id="picture" name="picture" accept="image/*" class="form-control">
          <div class="mt-2 d-none" id="imgPreviewBox">
            <img id="imgPreview" src="" alt="preview" class="img-fluid rounded" style="max-height:300px;border:1px solid #333;">
          </div>
        </div>
      </div>

      <div class="mt-4 d-flex gap-2">
        <a href="admin.php" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">
          <i class="fa fa-save me-2"></i>Guardar cambios
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// Vista previa de imagen nueva
const pictureInput = document.getElementById('picture');
if (pictureInput) {
  pictureInput.addEventListener('change', () => {
    const file = pictureInput.files?.[0];
    const box  = document.getElementById('imgPreviewBox');
    const img  = document.getElementById('imgPreview');
    if (file) {
      const url = URL.createObjectURL(file);
      img.src = url;
      box.classList.remove('d-none');
    } else {
      img.src = '';
      box.classList.add('d-none');
    }
  });
}

// Vista previa de PDF nuevo
const pdfInput = document.getElementById('pdf');
if (pdfInput) {
  pdfInput.addEventListener('change', () => {
    const file = pdfInput.files?.[0];
    const box  = document.getElementById('pdfPreviewBox');
    const frame= document.getElementById('pdfPreview');
    if (file) {
      const url = URL.createObjectURL(file);
      frame.src = url;
      box.classList.remove('d-none');
    } else {
      frame.src = '';
      box.classList.add('d-none');
    }
  });
}
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
