<?php
// /var/www/html/calibraciones/golden_update.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_auth('admin'); // solo admin

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$pdo = pdo();

// LÍMITES Y VALIDACIONES DE ARCHIVOS (igual estilo que en golden_add)
const MAX_IMG_BYTES = 8 * 1024 * 1024;   // 8MB
const MAX_PDF_BYTES = 20 * 1024 * 1024;  // 20MB
$ALLOWED_IMG_EXT = ['jpg','jpeg','png','webp','heic','heif'];
$ALLOWED_PDF_EXT = ['pdf'];

function uploadErrMsg(int $code): string {
    return match ($code) {
        UPLOAD_ERR_INI_SIZE   => 'El archivo excede el tamaño permitido por el servidor (upload_max_filesize).',
        UPLOAD_ERR_FORM_SIZE  => 'El archivo excede el tamaño permitido por el formulario (MAX_FILE_SIZE).',
        UPLOAD_ERR_PARTIAL    => 'El archivo se subió parcialmente.',
        UPLOAD_ERR_NO_FILE    => 'No se subió ningún archivo.',
        UPLOAD_ERR_NO_TMP_DIR => 'Falta el directorio temporal en el servidor.',
        UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo en disco.',
        UPLOAD_ERR_EXTENSION  => 'Una extensión de PHP detuvo la subida.',
        default               => 'Error desconocido en la subida.',
    };
}

// --- Obtener y validar ID ---
$id = $_GET['id'] ?? $_POST['id'] ?? '';
if ($id === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $id)) {
  http_response_code(400);
  exit('ID inválido.');
}

// --- Cargar registro actual ---
$st = $pdo->prepare("
  SELECT
    ID, Description, Brand, Model, SerialNumber,
    Pedimento,
    Location, Department, Owner, Status,
    Picture, Document, Comments, CreatedAt, UpdatedAt
  FROM golden_items
  WHERE ID = ?
");
$st->execute([$id]);
$item = $st->fetch();
if (!$item) {
  http_response_code(404);
  exit('Material Golden no encontrado.');
}

$errors = [];
// Valores iniciales
$values = [
  'description'  => (string)($item['Description']  ?? ''),
  'brand'        => (string)($item['Brand']        ?? ''),
  'model'        => (string)($item['Model']        ?? ''),
  'serialNumber' => (string)($item['SerialNumber'] ?? ''),
  'pedimento'    => (string)($item['Pedimento']    ?? ''),
  'location'     => (string)($item['Location']     ?? ''),
  'department'   => (string)($item['Department']   ?? ''),
  'owner'        => (string)($item['Owner']        ?? ''),
  'status'       => (string)($item['Status']       ?? 'Activo'),
  'comments'     => (string)($item['Comments']     ?? ''),
];

$currPicture  = (string)($item['Picture']  ?? '');
$currDocument = (string)($item['Document'] ?? '');

/**
 * Sube archivo al directorio $destDirAbs. $kind: 'img' | 'pdf'
 * Retorna ruta web ABSOLUTA (/calibraciones/uploads/...) o null.
 */
function handleUpload(string $field, string $destDirAbs, string $kind): ?string {
  if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;
  if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
    throw new RuntimeException("Error al subir {$field}: " . uploadErrMsg((int)$_FILES[$field]['error']));
  }

  $tmp  = $_FILES[$field]['tmp_name'];
  $name = $_FILES[$field]['name'] ?? $kind;
  $size = (int)($_FILES[$field]['size'] ?? 0);
  $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION) ?: '');

  if ($kind === 'pdf') {
    if ($size > MAX_PDF_BYTES) throw new RuntimeException("El documento excede 20MB.");
    global $ALLOWED_PDF_EXT;
    if (!in_array($ext, $ALLOWED_PDF_EXT, true)) throw new RuntimeException("Extensión de documento no permitida.");
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $tmp) ?: '';
    finfo_close($finfo);
    if (stripos($mime, 'pdf') === false) throw new RuntimeException("El archivo no es un PDF válido.");
  } else {
    if ($size > MAX_IMG_BYTES) throw new RuntimeException("La imagen excede 8MB.");
    global $ALLOWED_IMG_EXT;
    if (!in_array($ext, $ALLOWED_IMG_EXT, true)) {
      $finfo = finfo_open(FILEINFO_MIME_TYPE);
      $mime  = finfo_file($finfo, $tmp) ?: '';
      finfo_close($finfo);
      if (!str_starts_with((string)$mime, 'image/')) throw new RuntimeException("El archivo de imagen es inválido.");
      $ext = 'jpg';
    } else {
      if (@getimagesize($tmp) === false) throw new RuntimeException("La imagen es inválida.");
    }
  }

  if (!is_dir($destDirAbs) && !mkdir($destDirAbs, 0755, true) && !is_dir($destDirAbs)) {
    throw new RuntimeException("No se pudo crear el directorio de carga.");
  }

  $base = pathinfo($name, PATHINFO_FILENAME) ?: $kind;
  $safe = preg_replace('/[^A-Za-z0-9_\-]/', '_', $base);
  $final = $safe . '_' . time() . '.' . $ext;
  $destAbs = rtrim($destDirAbs, '/') . '/' . $final;

  if (!move_uploaded_file($tmp, $destAbs)) {
    throw new RuntimeException("No se pudo mover el archivo subido.");
  }

  // Convertir HEIC/HEIF → JPG si hay Imagick
  if ($kind === 'img' && in_array($ext, ['heic','heif'], true) && class_exists('\\Imagick')) {
    try {
      $img = new \Imagick($destAbs);
      $img->setImageFormat('jpeg');
      $jpgPathAbs = preg_replace('/\.(heic|heif)$/i', '.jpg', $destAbs);
      $img->writeImage($jpgPathAbs);
      $img->clear(); $img->destroy();
      @unlink($destAbs);
      $destAbs = $jpgPathAbs;
    } catch (\Throwable $e) {
      // ignorar conversión fallida
    }
  }

  // Ruta web absoluta
  $docroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '/var/www/html', '/') . '/';
  $rel     = ltrim(str_replace($docroot, '', $destAbs), '/');
  $rel     = '/' . $rel; // /calibraciones/uploads/...
  return $rel;
}

// --- POST: actualizar ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // CSRF
  if (!isset($_POST['csrf']) || !csrf_validate($_POST['csrf'])) {
    $errors[] = 'Sesión expirada. Vuelve a intentarlo.';
  }

  // Recoger (ID no editable)
  foreach (['description','brand','model','serialNumber','pedimento','location','department','owner','comments'] as $k) {
    $values[$k] = trim((string)($_POST[$k] ?? ''));
  }
  // Status no se edita aquí; si lo envían por error, se ignora
  $values['status'] = (string)$item['Status'];

  // Validaciones mínimas
  if ($values['description'] === '') $errors[] = 'La descripción es obligatoria.';
  if ($values['location'] === '')    $errors[] = 'La ubicación es obligatoria.';
  if ($values['department'] === '')  $errors[] = 'El departamento es obligatorio.';
  if ($values['owner'] === '')       $errors[] = 'El responsable es obligatorio.';
  // pedimento es opcional, sin validación extra

  // Si ya está en Scrap → no permitir modificación
  $isScrap = (string)$item['Status'] === 'Scrap';
  if ($isScrap) {
    $errors[] = 'Este material está en Scrap. No es posible editarlo.';
  }

  if (!$errors) {
    try {
      $pdo->beginTransaction();

      // Directorio por ID
      $destDirAbs = __DIR__ . '/uploads/golden/' . $id . '/';

      // Subidas opcionales
      $newPic  = handleUpload('picture',  $destDirAbs, 'img'); // null si no hay
      $newDoc  = handleUpload('document', $destDirAbs, 'pdf'); // null si no hay

      if ($newPic !== null)  $currPicture  = $newPic;
      if ($newDoc !== null)  $currDocument = $newDoc;

      // UPDATE principal (incluye Pedimento)
      $upd = $pdo->prepare("
        UPDATE golden_items
           SET Description  = :Description,
               Brand        = :Brand,
               Model        = :Model,
               SerialNumber = :SerialNumber,
               Pedimento    = :Pedimento,
               Location     = :Location,
               Department   = :Department,
               Owner        = :Owner,
               Comments     = :Comments,
               Picture      = :Picture,
               Document     = :Document,
               UpdatedAt    = NOW()
         WHERE ID = :ID
      ");
      $upd->execute([
        ':Description'  => $values['description'],
        ':Brand'        => $values['brand'] ?: null,
        ':Model'        => $values['model'] ?: null,
        ':SerialNumber' => $values['serialNumber'] ?: null,
        ':Pedimento'    => $values['pedimento'] ?: null,
        ':Location'     => $values['location'],
        ':Department'   => $values['department'],
        ':Owner'        => $values['owner'],
        ':Comments'     => $values['comments'] ?: null,
        ':Picture'      => $currPicture ?: null,
        ':Document'     => $currDocument ?: null,
        ':ID'           => $id,
      ]);

      // Historial de actualización (auditoría) — incluye Pedimento
      $hst = $pdo->prepare("
        INSERT INTO golden_history
          (GoldenID, Action, Description, Brand, Model, SerialNumber, Pedimento, Location, Department, Owner, Status, Picture, Document, Comments, CreatedAt)
        VALUES
          (:GoldenID,'update',:Description,:Brand,:Model,:SerialNumber,:Pedimento,:Location,:Department,:Owner,:Status,:Picture,:Document,:Comments,NOW())
      ");
      $hst->execute([
        ':GoldenID'     => $id,
        ':Description'  => $values['description'],
        ':Brand'        => $values['brand'] ?: null,
        ':Model'        => $values['model'] ?: null,
        ':SerialNumber' => $values['serialNumber'] ?: null,
        ':Pedimento'    => $values['pedimento'] ?: null,
        ':Location'     => $values['location'],
        ':Department'   => $values['department'],
        ':Owner'        => $values['owner'],
        ':Status'       => $values['status'], // se mantiene (Activo) aquí
        ':Picture'      => $currPicture ?: null,
        ':Document'     => $currDocument ?: null,
        ':Comments'     => $values['comments'] ?: null,
      ]);

      $pdo->commit();
      header('Location: golden_admin.php');
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
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h4 m-0 fw-bold">✏️ Editar Material</h1>
      <a href="golden_admin.php" class="btn btn-outline-secondary">
        <i class="fa fa-arrow-left me-1"></i> Volver
      </a>
    </div>

    <?php if ($item['Status'] === 'Scrap'): ?>
      <div class="alert alert-warning">
        Este material está en <strong>Scrap</strong>. La edición está deshabilitada para mantener la trazabilidad.
      </div>
    <?php endif; ?>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <ul class="m-0 ps-3"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <form method="POST" action="golden_update.php?id=<?= h($id) ?>" enctype="multipart/form-data" class="needs-validation" novalidate>
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="id" value="<?= h($id) ?>">

      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">ID</label>
          <input type="text" class="form-control" value="<?= h($id) ?>" disabled>
          <div class="form-text">El ID no se puede cambiar.</div>
        </div>

        <div class="col-12">
          <label for="description" class="form-label">Descripción *</label>
          <input type="text" id="description" name="description" class="form-control" value="<?= h($values['description']) ?>" required <?= $item['Status']==='Scrap'?'disabled':'' ?>>
        </div>

        <div class="col-sm-4">
          <label for="brand" class="form-label">Marca</label>
          <input type="text" id="brand" name="brand" class="form-control" value="<?= h($values['brand']) ?>" <?= $item['Status']==='Scrap'?'disabled':'' ?>>
        </div>
        <div class="col-sm-4">
          <label for="model" class="form-label">Modelo</label>
          <input type="text" id="model" name="model" class="form-control" value="<?= h($values['model']) ?>" <?= $item['Status']==='Scrap'?'disabled':'' ?>>
        </div>
        <div class="col-sm-4">
          <label for="serialNumber" class="form-label">Número de Serie</label>
          <input type="text" id="serialNumber" name="serialNumber" class="form-control" value="<?= h($values['serialNumber']) ?>" <?= $item['Status']==='Scrap'?'disabled':'' ?>>
        </div>

        <!-- Pedimento (opcional) -->
        <div class="col-md-6">
          <label for="pedimento" class="form-label">Pedimento (opcional)</label>
          <input type="text" id="pedimento" name="pedimento" class="form-control" value="<?= h($values['pedimento']) ?>" <?= $item['Status']==='Scrap'?'disabled':'' ?>>
        </div>

        <div class="col-md-6">
          <label for="location" class="form-label">Ubicación *</label>
          <input type="text" id="location" name="location" class="form-control" value="<?= h($values['location']) ?>" required <?= $item['Status']==='Scrap'?'disabled':'' ?>>
        </div>

        <div class="col-12 col-md-4">
          <label for="department" class="form-label">Departamento *</label>
          <input type="text" id="department" name="department" class="form-control form-control-lg" value="<?= h($values['department']) ?>" required <?= $item['Status']==='Scrap'?'disabled':'' ?>>
        </div>
        <div class="col-12 col-md-4">
          <label for="owner" class="form-label">Responsable *</label>
          <input type="text" id="owner" name="owner" class="form-control form-control-lg" value="<?= h($values['owner']) ?>" required <?= $item['Status']==='Scrap'?'disabled':'' ?>>
        </div>

        <div class="col-sm-6">
          <label class="form-label">Estado</label>
          <input type="text" class="form-control" value="<?= h($values['status']) ?>" disabled>
          <div class="form-text">
            Para dar de baja, usa <a href="golden_scrap.php?id=<?= urlencode($id) ?>">Enviar a Scrap</a>.
          </div>
        </div>

        <div class="col-12">
          <label for="comments" class="form-label">Comentarios</label>
          <textarea id="comments" name="comments" class="form-control" rows="3" <?= $item['Status']==='Scrap'?'disabled':'' ?>><?= h($values['comments']) ?></textarea>
        </div>

        <!-- FOTO -->
        <div class="col-md-6">
          <label class="form-label d-flex align-items-center justify-content-between">
            <span class="fw-bold"><i class="fa fa-camera me-1"></i> Foto del material</span>
            <?php if ($currPicture): ?>
              <a href="#" class="small text-decoration-none" data-bs-toggle="modal" data-bs-target="#imagePreviewModal">
                <i class="fa fa-image me-1"></i>Ver actual
              </a>
            <?php endif; ?>
          </label>
          <!-- Removed capture="environment" to allow gallery selection -->
          <input type="file" id="picture" name="picture" accept="image/*" class="form-control form-control-lg" <?= $item['Status']==='Scrap'?'disabled':'' ?>>
          <div class="form-text text-muted small">
             <i class="fa fa-info-circle"></i> Se comprimirá automáticamente si es muy grande.
          </div>
          <div class="mt-3 d-none text-center bg-dark rounded p-2" id="imgPreviewBox">
            <img id="imgPreview" src="" alt="preview" class="img-fluid rounded" style="max-height:300px;">
            <div class="text-white small mt-1" id="compressionInfo"></div>
          </div>
        </div>

        <!-- DOCUMENTO -->
        <div class="col-md-6">
          <label class="form-label d-flex align-items-center justify-content-between">
            <span>Documento (PDF)</span>
            <?php if ($currDocument): ?>
              <a href="<?= h($currDocument) ?>" target="_blank" class="small text-decoration-none">
                <i class="fa fa-file-pdf me-1"></i>Ver actual
              </a>
            <?php endif; ?>
          </label>
          <input type="file" id="document" name="document" accept="application/pdf" class="form-control" <?= $item['Status']==='Scrap'?'disabled':'' ?>>
          <div class="mt-2 d-none" id="pdfPreviewBox">
            <iframe id="pdfPreview" title="PDF" style="width:100%;height:320px;border:1px solid #333;border-radius:8px;"></iframe>
          </div>
        </div>
      </div>

      <div class="mt-4 d-flex gap-2">
        <a href="golden_admin.php" class="btn btn-outline-secondary btn-lg flex-fill">Cancelar</a>
        <button type="submit" class="btn btn-primary btn-lg flex-fill" <?= $item['Status']==='Scrap'?'disabled':'' ?>>
          <i class="fa fa-save me-2"></i> Guardar
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal imagen actual -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content bg-dark">
      <div class="modal-header border-0">
        <h5 class="modal-title">Vista previa</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body d-flex justify-content-center">
        <img id="previewImage" src="<?= h($currPicture) ?>" alt="Imagen" class="img-fluid rounded" style="max-height:75vh;">
      </div>
    </div>
  </div>
</div>

<script>
// Vista previa de imagen nueva
// CLIENT-SIDE COMPRESSION & PREVIEW
const pictureInput = document.getElementById('picture');
if (pictureInput) {
  pictureInput.addEventListener('change', async () => {
    const file = pictureInput.files?.[0];
    const box  = document.getElementById('imgPreviewBox');
    const img  = document.getElementById('imgPreview');
    const info = document.getElementById('compressionInfo');

    if (!file) {
        img.src = '';
        box.classList.add('d-none');
        return;
    }

    // Show preview immediately for UX
    box.classList.remove('d-none');
    img.src = URL.createObjectURL(file);
    info.textContent = `Original: ${(file.size/1024/1024).toFixed(2)} MB`;

    // Only compress if > 1MB or if it's HEIC (though browser might convert HEIC to PNG on read, safe to process)
    if (file.size > 1024 * 1024 || file.type === 'image/heic' || file.type === 'image/heif') {
        info.textContent += " ⏳ Optimizando...";
        try {
            const compressedBlob = await compressImage(file);
            // Replace file in input
            const dt = new DataTransfer();
            const newFile = new File([compressedBlob], file.name.replace(/\.[^/.]+$/, "") + ".jpg", { type: "image/jpeg" });
            dt.items.add(newFile);
            pictureInput.files = dt.files;
            
            // Update preview/info
            img.src = URL.createObjectURL(compressedBlob);
            info.textContent = `Optimizado: ${(compressedBlob.size/1024/1024).toFixed(2)} MB (Listo para subir)`;
            
        } catch (e) {
            console.error("Compression failed", e);
            info.textContent += " ❌ Error al optimizar (se intentará subir original)";
        }
    }
  });
}

function compressImage(file) {
    return new Promise((resolve, reject) => {
        const maxWidth = 1200;
        const maxHeight = 1200;
        const reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onload = event => {
            const img = new Image();
            img.src = event.target.result;
            img.onload = () => {
                let width = img.width;
                let height = img.height;
                
                if (width > maxWidth || height > maxHeight) {
                    if (width > height) {
                        height = Math.round(height * (maxWidth / width));
                        width = maxWidth;
                    } else {
                        width = Math.round(width * (maxHeight / height));
                        height = maxHeight;
                    }
                }
                
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);
                
                canvas.toBlob(blob => {
                    resolve(blob);
                }, 'image/jpeg', 0.8); // 80% quality JPG
            };
            img.onerror = err => reject(err);
        };
        reader.onerror = err => reject(err);
    });
}


// Vista previa de PDF nuevo
const pdfInput = document.getElementById('document');
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

// Validación Bootstrap
(() => {
  const form = document.querySelector('.needs-validation');
  if (!form) return;
  form.addEventListener('submit', (e) => {
    if (!form.checkValidity()) {
      e.preventDefault();
      e.stopPropagation();
    }
    form.classList.add('was-validated');
  });
})();
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
