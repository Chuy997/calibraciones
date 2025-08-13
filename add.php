<?php
// /var/www/html/calibraciones/add.php
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

// --- Estado permitido según ENUM de la tabla instruments ---
$STATUS_ALLOWED = [
  'calibrado' => 'Calibrado',
  'en proceso de calibracion' => 'En proceso de calibración',
  'fuera de calibracion' => 'Fuera de calibración',
];

// Valores por defecto del formulario
$values = [
  'id'            => '',
  'description'   => '',
  'brand'         => '',
  'model'         => '',
  'serialNumber'  => '',
  'certificateNo' => '',
  'calDate'       => '',
  'dueDate'       => '',
  'status'        => 'calibrado',
  'comments'      => '',
];

$errors = [];

/**
 * Sube un archivo validando tamaño/mime/ext y lo guarda en $destDir.
 * Retorna la RUTA RELATIVA (p.ej. 'uploads/DU7310/DU7310_cert.pdf') o null si no se subió.
 * $kind = 'pdf' | 'img' para validar diferente.
 */
function handleUpload(string $field, string $destDir, string $kind): ?string {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // Campo no enviado
    }
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Error en la subida de $field (código {$_FILES[$field]['error']}).");
    }

    $tmp = $_FILES[$field]['tmp_name'];
    $name = $_FILES[$field]['name'];
    $size = (int)$_FILES[$field]['size'];
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if ($kind === 'pdf') {
        if ($size > MAX_PDF_BYTES) throw new RuntimeException("El PDF excede el tamaño permitido.");
        if (!in_array($ext, $GLOBALS['ALLOWED_PDF_EXT'], true)) throw new RuntimeException("Extensión de PDF no permitida.");
        // MIME check básica
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmp) ?: '';
        finfo_close($finfo);
        if (stripos($mime, 'pdf') === false) throw new RuntimeException("El archivo no es un PDF válido.");
    } else {
        if ($size > MAX_IMG_BYTES) throw new RuntimeException("La imagen excede el tamaño permitido.");
        if (!in_array($ext, $GLOBALS['ALLOWED_IMG_EXT'], true)) throw new RuntimeException("Extensión de imagen no permitida.");
        // Verificación rápida de imagen
        $imgInfo = @getimagesize($tmp);
        if ($imgInfo === false) throw new RuntimeException("El archivo de imagen es inválido.");
    }

    if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
        throw new RuntimeException("No se pudo crear el directorio de destino.");
    }

    // Nombre seguro: campo + timestamp + ext
    $safeBase = preg_replace('/[^A-Za-z0-9_\-]/', '_', pathinfo($name, PATHINFO_FILENAME));
    $final    = $safeBase . '_' . time() . '.' . $ext;
    $destAbs  = rtrim($destDir, '/').'/'.$final;

    if (!move_uploaded_file($tmp, $destAbs)) {
        throw new RuntimeException("No se pudo mover el archivo subido.");
    }

    // Retorna ruta relativa desde la raíz del proyecto (web-accesible)
    $rel = ltrim(str_replace($_SERVER['DOCUMENT_ROOT'], '', $destAbs), '/');
    // Si el proyecto está debajo de /var/www/html/calibraciones, aseguramos prefijo relativo:
    if (!str_starts_with($rel, 'calibraciones/')) {
        // construimos ruta relativa desde el docroot:
        $docroot = rtrim($_SERVER['DOCUMENT_ROOT'], '/').'/';
        $rel = ltrim(str_replace($docroot, '', $destAbs), '/');
    }
    return $rel;
}

// POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (!isset($_POST['csrf']) || !csrf_validate($_POST['csrf'])) {
        $errors[] = 'Sesión expirada. Por favor, vuelve a intentar.';
    }

    // Recoger + sanitizar
    foreach ($values as $k => $_) {
        $values[$k] = trim($_POST[$k] ?? '');
    }

    // Validaciones
    if ($values['id'] === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $values['id'])) {
        $errors[] = 'ID inválido (solo letras, números, ".", "_" y "-").';
    }
    if ($values['certificateNo'] === '') {
        $errors[] = 'Debe ingresar el número de certificado.';
    }
    if ($values['calDate'] === '' || $values['dueDate'] === '') {
        $errors[] = 'Debes ingresar las fechas de calibración y vencimiento.';
    } elseif ($values['calDate'] > $values['dueDate']) {
        $errors[] = 'La fecha de calibración debe ser anterior a la de vencimiento.';
    }
    if (!array_key_exists($values['status'], $STATUS_ALLOWED)) {
        $errors[] = 'Estado inválido.';
    }

    // Si no hay errores hasta aquí, verificamos duplicado y hacemos INSERT
    if (!$errors) {
        try {
            $pdo = pdo();

            // Duplicado de ID
            $dup = $pdo->prepare('SELECT COUNT(*) FROM instruments WHERE ID = ?');
            $dup->execute([$values['id']]);
            if ((int)$dup->fetchColumn() > 0) {
                $errors[] = 'Ya existe un instrumento con ese ID.';
            }

            if (!$errors) {
                $pdo->beginTransaction();

                // Directorio de uploads por instrumento (dentro de la app)
                // Nota: ruta absoluta en FS
                $projectRoot = dirname(__FILE__); // /var/www/html/calibraciones
                $destDirAbs  = $projectRoot . '/uploads/' . $values['id'] . '/';

                // Subidas (devuelven ruta relativa web)
                $pdfPathRel     = handleUpload('pdf', $destDirAbs, 'pdf'); // e.g. calibraciones/uploads/X/file.pdf
                $picturePathRel = handleUpload('picture', $destDirAbs, 'img');

                // Opcional: DaysCounter (si deseas llenarlo)
                // $daysCounter = (new DateTimeImmutable($values['dueDate']))->diff(new DateTimeImmutable('today'))->days * ( (new DateTimeImmutable($values['dueDate'])) >= (new DateTimeImmutable('today')) ? 1 : -1 );

                // INSERT instruments
                $ins = $pdo->prepare("
                    INSERT INTO instruments
                      (ID, Picture, Description, Brand, Model, SerialNumber, HW_ZL,
                       CalDate, DueDate, DaysCounter, Status, Comments, PdfPath, CertificateNo)
                    VALUES
                      (:ID, :Picture, :Description, :Brand, :Model, :SerialNumber, :HW_ZL,
                       :CalDate, :DueDate, :DaysCounter, :Status, :Comments, :PdfPath, :CertificateNo)
                ");
                $ins->execute([
                    ':ID'            => $values['id'],
                    ':Picture'       => $picturePathRel,
                    ':Description'   => $values['description'],
                    ':Brand'         => $values['brand'],
                    ':Model'         => $values['model'],
                    ':SerialNumber'  => $values['serialNumber'],
                    ':HW_ZL'         => null,
                    ':CalDate'       => $values['calDate'],
                    ':DueDate'       => $values['dueDate'],
                    ':DaysCounter'   => null, // o $daysCounter si decides calcularlo
                    ':Status'        => $values['status'], // debe ser uno de los ENUM
                    ':Comments'      => $values['comments'],
                    ':PdfPath'       => $pdfPathRel,
                    ':CertificateNo' => $values['certificateNo'],
                ]);

                // INSERT updatehistory (primer snapshot)
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
                    ':InstrumentID' => $values['id'],
                    ':UpdatedColumn'=> 'create',
                    ':OldValue'     => null,
                    ':NewValue'     => null,
                    ':Description'  => $values['description'],
                    ':Brand'        => $values['brand'],
                    ':Model'        => $values['model'],
                    ':SerialNumber' => $values['serialNumber'],
                    ':CalDate'      => $values['calDate'],
                    ':DueDate'      => $values['dueDate'],
                    ':CertificateNo'=> $values['certificateNo'],
                    ':Status'       => $values['status'],
                    ':Comments'     => $values['comments'],
                    ':PdfPath'      => $pdfPathRel,
                    ':Picture'      => $picturePathRel,
                ]);

                $pdo->commit();
                header('Location: admin.php');
                exit;
            }
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Error al guardar: ' . $e->getMessage();
        }
    }
}
?>
<?php include __DIR__.'/partials/header.php'; ?>

<div class="row justify-content-center">
  <div class="col-12 col-lg-8 col-xl-7">
    <h1 class="h4 mb-3">Agregar nuevo instrumento</h1>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <ul class="m-0 ps-3">
          <?php foreach ($errors as $err): ?>
            <li><?= h($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

      <div class="row g-3">
        <div class="col-sm-6">
          <label for="id" class="form-label">ID</label>
          <input type="text" class="form-control" id="id" name="id" value="<?= h($values['id']) ?>" required>
        </div>
        <div class="col-sm-6">
          <label for="certificateNo" class="form-label">Número de Certificado</label>
          <input type="text" class="form-control" id="certificateNo" name="certificateNo" value="<?= h($values['certificateNo']) ?>" required>
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
          <label for="pdf" class="form-label">Subir PDF (certificado)</label>
          <input type="file" id="pdf" name="pdf" accept="application/pdf" class="form-control">
          <div class="mt-2 d-none" id="pdfPreviewBox">
            <iframe id="pdfPreview" title="PDF" style="width:100%;height:300px;border:1px solid #333;border-radius:8px;"></iframe>
          </div>
        </div>

        <div class="col-md-6">
          <label for="picture" class="form-label">Subir Imagen</label>
          <input type="file" id="picture" name="picture" accept="image/*" class="form-control">
          <div class="mt-2 d-none" id="imgPreviewBox">
            <img id="imgPreview" src="" alt="preview" class="img-fluid rounded" style="max-height:300px;border:1px solid #333;">
          </div>
        </div>
      </div>

      <div class="mt-4 d-flex gap-2">
        <a href="admin.php" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">
          <i class="fa fa-save me-2"></i>Guardar
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// Vista previa de imagen
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

// Vista previa de PDF
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

<?php include __DIR__.'/partials/footer.php'; ?>
