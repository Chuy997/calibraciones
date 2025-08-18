<?php
// /var/www/html/calibraciones/golden_add.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_auth('admin'); // solo admin

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$pdo = pdo();

// LÍMITES Y VALIDACIONES DE ARCHIVOS
const MAX_IMG_BYTES = 8 * 1024 * 1024;   // 8MB para foto
const MAX_PDF_BYTES = 20 * 1024 * 1024;  // 20MB para documento
$ALLOWED_IMG_EXT = ['jpg','jpeg','png','webp','heic','heif']; // móviles iOS/Android
$ALLOWED_PDF_EXT = ['pdf'];

// --- Utilidad: siguiente ID GLDTE-XXX ---
function next_golden_id(PDO $pdo): string {
    // Toma el máximo numérico de IDs GLDTE-###
    $st = $pdo->query("SELECT MAX(CAST(SUBSTRING(ID, 7) AS UNSIGNED)) AS maxnum
                       FROM golden_items
                       WHERE ID LIKE 'GLDTE-%'");
    $max = (int)($st->fetchColumn() ?: 0);
    $n   = $max + 1;
    return sprintf('GLDTE-%03d', $n);
}

// ID sugerido (preview en el formulario)
$suggestedId = next_golden_id($pdo);

$errors = [];
$values = [
  // ID se asigna automáticamente; solo se muestra el sugerido
  'description'  => '',
  'brand'        => '',
  'model'        => '',
  'serialNumber' => '',
  'pedimento'    => '',   // opcional
  'location'     => '',
  'department'   => '',
  'owner'        => '',
  'status'       => 'Activo',    // por defecto
  'comments'     => '',
];

// --- Helpers subida ---
function uploadErrMsg(int $code): string {
    return match ($code) {
        UPLOAD_ERR_INI_SIZE   => 'El archivo excede upload_max_filesize del servidor.',
        UPLOAD_ERR_FORM_SIZE  => 'El archivo excede MAX_FILE_SIZE del formulario.',
        UPLOAD_ERR_PARTIAL    => 'El archivo se subió parcialmente.',
        UPLOAD_ERR_NO_FILE    => 'No se subió ningún archivo.',
        UPLOAD_ERR_NO_TMP_DIR => 'Falta el directorio temporal del servidor.',
        UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo en disco.',
        UPLOAD_ERR_EXTENSION  => 'Una extensión de PHP detuvo la subida.',
        default               => 'Error desconocido en la subida.',
    };
}

/**
 * Sube archivo al directorio $destDirAbs.
 * $kind: 'img' | 'pdf'
 * Devuelve ruta web ABSOLUTA (empieza con /calibraciones/...) o null si no hubo archivo.
 * Convierte HEIC/HEIF a JPG si existe Imagick.
 */
function handleUpload(string $field, string $destDirAbs, string $kind): ?string {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    // Errores amigables
    $err = (int)$_FILES[$field]['error'];
    if ($err !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Error al subir {$field}: " . uploadErrMsg($err));
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
        $isHeic = in_array($ext, ['heic','heif'], true);

        if (!in_array($ext, $ALLOWED_IMG_EXT, true)) {
            // Extensión desconocida: valida por MIME
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $tmp) ?: '';
            finfo_close($finfo);
            if (!str_starts_with((string)$mime, 'image/')) {
                throw new RuntimeException("El archivo de imagen es inválido.");
            }
            // asigna extensión segura si no vino
            $ext = 'jpg';
        } else {
            // Para HEIC/HEIF no uses getimagesize (suele fallar). Para el resto sí.
            if (!$isHeic && @getimagesize($tmp) === false) {
                throw new RuntimeException("La imagen es inválida.");
            }
        }
    }

    // Asegura carpeta
    if (!is_dir($destDirAbs) && !mkdir($destDirAbs, 0755, true) && !is_dir($destDirAbs)) {
        throw new RuntimeException("No se pudo crear el directorio de carga.");
    }

    // Nombre seguro
    $base = pathinfo($name, PATHINFO_FILENAME) ?: $kind;
    $safe = preg_replace('/[^A-Za-z0-9_\-]/', '_', $base);
    if ($safe === '') $safe = $kind;
    $final   = $safe . '_' . time() . '.' . $ext;
    $destAbs = rtrim($destDirAbs, '/') . '/' . $final;

    if (!move_uploaded_file($tmp, $destAbs)) {
        throw new RuntimeException("No se pudo mover el archivo subido.");
    }

    // Conversión HEIC/HEIF → JPG si hay Imagick
    if ($kind === 'img' && in_array($ext, ['heic','heif'], true) && class_exists('Imagick')) {
        try {
            $img = new Imagick($destAbs);
            $img->setImageFormat('jpeg');
            $jpgPathAbs = preg_replace('/\.(heic|heif)$/i', '.jpg', $destAbs);
            $img->writeImage($jpgPathAbs);
            $img->clear(); $img->destroy();
            @unlink($destAbs);            // borra el HEIC original
            $destAbs = $jpgPathAbs;       // usa el JPG como definitivo
        } catch (Throwable $e) {
            // Si falla la conversión, dejamos el HEIC tal cual (algunos navegadores no lo mostrarán)
        }
    }

    // Ruta web ABSOLUTA (con / inicial)
    $docroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '/var/www/html', '/') . '/';
    $rel     = ltrim(str_replace($docroot, '', $destAbs), '/'); // calibraciones/uploads/...
    if (!str_starts_with($rel, 'calibraciones/')) {
        $rel = 'calibraciones/' . $rel;
    }
    return '/' . ltrim($rel, '/');
}

// --- POST: guardar ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (!isset($_POST['csrf']) || !csrf_validate($_POST['csrf'])) {
        $errors[] = 'Sesión expirada. Vuelve a intentar.';
    }

    // Recoger (sin ID: se genera automáticamente)
    foreach (['description','brand','model','serialNumber','pedimento','location','department','owner','status','comments'] as $k) {
        $values[$k] = trim((string)($_POST[$k] ?? ''));
    }

    // Validaciones
    if ($values['description'] === '') $errors[] = 'La descripción es obligatoria.';
    if ($values['location'] === '')    $errors[] = 'La ubicación es obligatoria.';
    if ($values['department'] === '')  $errors[] = 'El departamento es obligatorio.';
    if ($values['owner'] === '')       $errors[] = 'El responsable es obligatorio.';
    // pedimento: opcional (sin validación)

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Recalcular el siguiente ID dentro de la transacción (mitiga condiciones de carrera)
            $newId = next_golden_id($pdo);

            // Carpeta por ID
            $destDirAbs = __DIR__ . '/uploads/golden/' . $newId . '/';

            // Subir archivos (opcionales)
            $pictureRel  = handleUpload('picture',  $destDirAbs, 'img'); // puede ser null
            $documentRel = handleUpload('document', $destDirAbs, 'pdf'); // puede ser null

            // Insert principal (incluye Pedimento e ID auto)
            $ins = $pdo->prepare("
                INSERT INTO golden_items
                  (ID, Description, Brand, Model, SerialNumber, Pedimento, Location, Department, Owner, Status, Picture, Document, Comments, CreatedAt, UpdatedAt)
                VALUES
                  (:ID,:Description,:Brand,:Model,:SerialNumber,:Pedimento,:Location,:Department,:Owner,:Status,:Picture,:Document,:Comments,NOW(),NOW())
            ");
            $ins->execute([
              ':ID'           => $newId,
              ':Description'  => $values['description'],
              ':Brand'        => $values['brand'] ?: null,
              ':Model'        => $values['model'] ?: null,
              ':SerialNumber' => $values['serialNumber'] ?: null,
              ':Pedimento'    => $values['pedimento'] ?: null,
              ':Location'     => $values['location'],
              ':Department'   => $values['department'],
              ':Owner'        => $values['owner'],
              ':Status'       => $values['status'] ?: 'Activo',
              ':Picture'      => $pictureRel,
              ':Document'     => $documentRel,
              ':Comments'     => $values['comments'] ?: null,
            ]);

            // Historial inicial (incluye Pedimento)
            $hst = $pdo->prepare("
                INSERT INTO golden_history
                  (GoldenID, Action, Description, Brand, Model, SerialNumber, Pedimento, Location, Department, Owner, Status, Picture, Document, Comments, CreatedAt)
                VALUES
                  (:GoldenID,'create',:Description,:Brand,:Model,:SerialNumber,:Pedimento,:Location,:Department,:Owner,:Status,:Picture,:Document,:Comments,NOW())
            ");
            $hst->execute([
              ':GoldenID'     => $newId,
              ':Description'  => $values['description'],
              ':Brand'        => $values['brand'] ?: null,
              ':Model'        => $values['model'] ?: null,
              ':SerialNumber' => $values['serialNumber'] ?: null,
              ':Pedimento'    => $values['pedimento'] ?: null,
              ':Location'     => $values['location'],
              ':Department'   => $values['department'],
              ':Owner'        => $values['owner'],
              ':Status'       => $values['status'] ?: 'Activo',
              ':Picture'      => $pictureRel,
              ':Document'     => $documentRel,
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
    <h1 class="h4 my-3">Nuevo material Golden</h1>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <ul class="m-0 ps-3">
          <?php foreach ($errors as $err): ?>
            <li><?= h($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="POST" action="golden_add.php" enctype="multipart/form-data" class="needs-validation" novalidate>
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

      <div class="row g-3">
        <!-- ID (auto-asignado, solo lectura) -->
        <div class="col-12">
          <label class="form-label">ID (se asigna automáticamente)</label>
          <input type="text" class="form-control" value="<?= h($suggestedId) ?>" disabled>
          <div class="form-text">Formato: GLDTE-###. El valor final se confirma al guardar.</div>
        </div>

        <!-- Descripción -->
        <div class="col-12">
          <label for="description" class="form-label">Descripción<span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="description" name="description" value="<?= h($values['description']) ?>" required placeholder="Nombre del material">
        </div>

        <!-- Marca / Modelo / Serie -->
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

        <!-- Pedimento (opcional) -->
        <div class="col-sm-6">
          <label for="pedimento" class="form-label">Pedimento (opcional)</label>
          <input type="text" class="form-control" id="pedimento" name="pedimento" value="<?= h($values['pedimento']) ?>" placeholder="Ej. 21 48 1234 0001234">
        </div>

        <!-- Ubicación / Depto / Responsable -->
        <div class="col-md-6">
          <label for="location" class="form-label">Ubicación<span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="location" name="location" value="<?= h($values['location']) ?>" required placeholder="Ej. Línea 3 / Almacén">
        </div>
        <div class="col-md-4">
          <label for="department" class="form-label">Departamento<span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="department" name="department" value="<?= h($values['department']) ?>" required placeholder="Testing / Producción">
        </div>
        <div class="col-md-4">
          <label for="owner" class="form-label">Responsable<span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="owner" name="owner" value="<?= h($values['owner']) ?>" required placeholder="Nombre / Puesto">
        </div>

        <!-- Estado -->
        <div class="col-sm-6">
          <label for="status" class="form-label">Estado</label>
          <select id="status" name="status" class="form-select">
            <option <?= $values['status']==='Activo'?'selected':'' ?>>Activo</option>
            <option <?= $values['status']==='Scrap'?'selected':'' ?>>Scrap</option>
          </select>
          <div class="form-text">Si das de baja, usa “Scrap” para reflejar que ya no está disponible.</div>
        </div>

        <!-- Comentarios -->
        <div class="col-12">
          <label for="comments" class="form-label">Comentarios</label>
          <textarea id="comments" name="comments" class="form-control" rows="3" placeholder="Notas adicionales"><?= h($values['comments']) ?></textarea>
        </div>

        <!-- FOTO (CÁMARA MÓVIL) -->
        <div class="col-md-6">
          <label class="form-label">Foto del material</label>
          <input
            type="file"
            id="picture"
            name="picture"
            accept="image/*"
            capture="environment"
            class="form-control"
          >
          <div class="form-text">En móviles, se abrirá la cámara tras seleccionar “Tomar foto”.</div>
          <div class="mt-2 d-none" id="imgPreviewBox">
            <img id="imgPreview" src="" alt="preview" class="img-fluid rounded" style="max-height:320px;border:1px solid #333;">
          </div>
        </div>

        <!-- DOCUMENTO (PDF opcional) -->
        <div class="col-md-6">
          <label class="form-label">Documento (PDF opcional)</label>
          <input type="file" id="document" name="document" accept="application/pdf" class="form-control">
          <div class="mt-2 d-none" id="pdfPreviewBox">
            <iframe id="pdfPreview" title="PDF" style="width:100%;height:320px;border:1px solid #333;border-radius:8px;"></iframe>
          </div>
        </div>
      </div>

      <div class="mt-4 d-flex gap-2">
        <a href="golden_admin.php" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-success">
          <i class="fa fa-save me-2"></i>Guardar
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// Vista previa de imagen (móvil/escritorio)
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

// Vista previa de PDF (si el navegador lo permite)
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
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
