<?php
// /var/www/html/calibraciones/mant_equipos_update.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_auth(['admin','ingenieria']);

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

const MAX_IMG_BYTES_MU = 5 * 1024 * 1024;
const MAX_PDF_BYTES_MU = 20 * 1024 * 1024;
$ALLOWED_IMG_EXT_MU = ['jpg','jpeg','png','webp','heic'];
$ALLOWED_PDF_EXT_MU = ['pdf'];

$PERIOD_ALLOWED_U = [
    '3M' => 'Cada 3 meses',
    '6M' => 'Cada 6 meses',
    '1Y' => 'Anual (cada 12 meses)',
];

// 1) Obtener y validar ID
$id = $_GET['id'] ?? $_POST['id'] ?? null;
if (!$id || !preg_match('/^[A-Za-z0-9._-]+$/', $id)) {
    http_response_code(400);
    exit('ID inválido.');
}

// 2) Cargar datos actuales del equipo
try {
    $pdo   = pdo();
    $stmt  = $pdo->prepare('SELECT * FROM mant_equipos WHERE ID = ?');
    $stmt->execute([$id]);
    $equipo = $stmt->fetch();
    if (!$equipo) {
        http_response_code(404);
        exit('Equipo no encontrado.');
    }
} catch (Throwable $e) {
    http_response_code(500);
    exit('Error al cargar el equipo.');
}

// Campos de solo lectura
$description  = (string)($equipo['Description']  ?? '');
$brand        = (string)($equipo['Brand']        ?? '');
$model        = (string)($equipo['Model']        ?? '');
$serialNumber = (string)($equipo['SerialNumber'] ?? '');

// Rutas actuales
$pdfPath     = (string)($equipo['PdfPath'] ?? '');
$picturePath = (string)($equipo['Picture'] ?? '');
$norm        = fn(string $p) => $p ? ('/'.ltrim($p,'/')) : '';
$pdfPathView     = $norm($pdfPath);
$picturePathView = $norm($picturePath);

// Campos editables
$values = [
    'lastMaintDate' => (string)($equipo['LastMaintDate'] ?? ''),
    'nextMaintDate' => (string)($equipo['NextMaintDate'] ?? ''),
    'maintPeriod'   => (string)($equipo['MaintPeriod']   ?? '1Y'),
    'location'      => (string)($equipo['Location']      ?? ''),
    'comments'      => (string)($equipo['Comments']      ?? ''),
];

$errors = [];

/**
 * Calcula NextMaintDate según el periodo elegido.
 */
function calcNextMaintDateU(string $lastDate, string $period): string {
    $dt = DateTime::createFromFormat('Y-m-d', $lastDate);
    if (!$dt) throw new RuntimeException('Fecha de último mantenimiento inválida.');
    match ($period) {
        '3M' => $dt->modify('+3 months'),
        '6M' => $dt->modify('+6 months'),
        '1Y' => $dt->modify('+1 year'),
        default => throw new RuntimeException("Período inválido: $period"),
    };
    return $dt->format('Y-m-d');
}

/**
 * Estado automático basado en NextMaintDate.
 */
function calcStatusU(string $nextDate): string {
    $today = (new DateTime('today'))->format('Y-m-d');
    $limit = (new DateTime('today'))->modify('+30 days')->format('Y-m-d');
    if ($nextDate < $today) return 'Vencido';
    if ($nextDate <= $limit) return 'Próximo mantenimiento';
    return 'Al corriente';
}

function uploadErrMsgMU(int $code): string {
    return match ($code) {
        UPLOAD_ERR_INI_SIZE  => 'El archivo excede el tamaño permitido por el servidor.',
        UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño permitido por el formulario.',
        UPLOAD_ERR_PARTIAL   => 'El archivo se subió parcialmente.',
        UPLOAD_ERR_NO_FILE   => 'No se subió ningún archivo.',
        UPLOAD_ERR_NO_TMP_DIR=> 'Falta el directorio temporal en el servidor.',
        UPLOAD_ERR_CANT_WRITE=> 'No se pudo escribir el archivo en disco.',
        default              => 'Error desconocido en la subida.',
    };
}

function handleUploadMantU(string $field, string $destDir, string $kind): ?string {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Error al subir $field: " . uploadErrMsgMU((int)$_FILES[$field]['error']));
    }

    $tmp  = $_FILES[$field]['tmp_name'];
    $name = $_FILES[$field]['name'] ?? $kind;
    $size = (int)($_FILES[$field]['size'] ?? 0);

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $tmp) ?: '';
    finfo_close($finfo);

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION) ?: '');
    if ($ext === '') {
        if (stripos($mime, 'pdf')  !== false) $ext = 'pdf';
        elseif (stripos($mime, 'jpeg') !== false) $ext = 'jpg';
        elseif (stripos($mime, 'png')  !== false) $ext = 'png';
        elseif (stripos($mime, 'webp') !== false) $ext = 'webp';
        elseif (stripos($mime, 'heic') !== false) $ext = 'heic';
    }

    if ($kind === 'pdf_or_img') {
        $isPdf = stripos($mime, 'pdf') !== false;
        $isImg = stripos($mime, 'image') !== false;
        if ($isPdf) {
            if ($size > MAX_PDF_BYTES_MU) throw new RuntimeException("El PDF excede 20MB.");
            if (!in_array($ext, $GLOBALS['ALLOWED_PDF_EXT_MU'], true)) throw new RuntimeException("Extensión PDF no permitida.");
        } elseif ($isImg) {
            if ($size > MAX_IMG_BYTES_MU) throw new RuntimeException("La imagen excede 5MB.");
            if (!in_array($ext, $GLOBALS['ALLOWED_IMG_EXT_MU'], true)) throw new RuntimeException("Extensión imagen no permitida.");
            if ($ext !== 'heic' && @getimagesize($tmp) === false) throw new RuntimeException("Imagen inválida o corrupta.");
        } else {
            throw new RuntimeException("El archivo debe ser PDF o imagen. Tipo: $mime");
        }
    } elseif ($kind === 'pdf') {
        if ($size > MAX_PDF_BYTES_MU) throw new RuntimeException("El PDF excede 20MB.");
        if (!in_array($ext, $GLOBALS['ALLOWED_PDF_EXT_MU'], true)) throw new RuntimeException("Extensión PDF no permitida.");
        if (stripos($mime, 'pdf') === false) throw new RuntimeException("El archivo no es un PDF válido.");
    } else {
        if ($size > MAX_IMG_BYTES_MU) throw new RuntimeException("La imagen excede 5MB.");
        if (!in_array($ext, $GLOBALS['ALLOWED_IMG_EXT_MU'], true)) throw new RuntimeException("Extensión imagen no permitida.");
        if ($ext !== 'heic' && @getimagesize($tmp) === false) throw new RuntimeException("Imagen inválida.");
    }

    if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
        throw new RuntimeException("No se pudo crear el directorio de destino.");
    }

    $safeBase = preg_replace('/[^A-Za-z0-9_\-]/', '_', pathinfo($name, PATHINFO_FILENAME));
    if ($safeBase === '') $safeBase = $kind;
    $final   = $safeBase . '_' . time() . '.' . $ext;
    $destAbs = rtrim($destDir, '/').'/'.$final;

    if (!move_uploaded_file($tmp, $destAbs)) {
        throw new RuntimeException("No se pudo mover el archivo subido.");
    }

    $docroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '/var/www/html', '/').'/';
    $rel     = ltrim(str_replace($docroot, '', $destAbs), '/');
    if (!str_starts_with($rel, 'calibraciones/')) {
        $rel = 'calibraciones/' . $rel;
    }
    return '/' . ltrim($rel, '/');
}

// 3) Procesar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || !csrf_validate($_POST['csrf'])) {
        $errors[] = 'Sesión expirada. Por favor, vuelve a intentar.';
    }

    $values['lastMaintDate'] = trim($_POST['lastMaintDate'] ?? '');
    $values['maintPeriod']   = trim($_POST['maintPeriod']   ?? '1Y');
    $values['location']      = trim($_POST['location']      ?? '');
    $values['comments']      = trim($_POST['comments']      ?? '');

    if ($values['lastMaintDate'] === '') {
        $errors[] = 'Debes ingresar la fecha del último mantenimiento.';
    }
    if (!array_key_exists($values['maintPeriod'], $PERIOD_ALLOWED_U)) {
        $errors[] = 'Período de mantenimiento inválido.';
    }

    // Calcular nextMaintDate
    $nextMaintDate = '';
    if (!$errors) {
        try {
            $nextMaintDate = calcNextMaintDateU($values['lastMaintDate'], $values['maintPeriod']);
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $destDirAbs = __DIR__ . '/uploads/mant_equipos/' . $id . '/';

            $newPdfUrl     = handleUploadMantU('pdf',     $destDirAbs, 'pdf_or_img');
            $newPictureUrl = handleUploadMantU('picture', $destDirAbs, 'img');

            if ($newPdfUrl !== null)     { $pdfPath     = $newPdfUrl; }
            if ($newPictureUrl !== null) { $picturePath = $newPictureUrl; }

            $statusAuto = calcStatusU($nextMaintDate);

            // UPDATE mant_equipos
            $upd = $pdo->prepare("
                UPDATE mant_equipos
                   SET LastMaintDate = :LastMaintDate,
                       NextMaintDate = :NextMaintDate,
                       MaintPeriod   = :MaintPeriod,
                       Status        = :Status,
                       Location      = :Location,
                       Comments      = :Comments,
                       PdfPath       = :PdfPath,
                       Picture       = :Picture
                 WHERE ID = :ID
            ");
            $upd->execute([
                ':LastMaintDate' => $values['lastMaintDate'],
                ':NextMaintDate' => $nextMaintDate,
                ':MaintPeriod'   => $values['maintPeriod'],
                ':Status'        => $statusAuto,
                ':Location'      => $values['location'],
                ':Comments'      => $values['comments'],
                ':PdfPath'       => $pdfPath    ?: null,
                ':Picture'       => $picturePath ?: null,
                ':ID'            => $id,
            ]);

            // INSERT mant_equipos_history
            $hst = $pdo->prepare("
                INSERT INTO mant_equipos_history
                  (EquipoID, Action, Description, Brand, Model, SerialNumber,
                   Location, LastMaintDate, NextMaintDate, MaintPeriod, Status,
                   Comments, PdfPath, Picture)
                VALUES
                  (:EquipoID, 'update', :Description, :Brand, :Model, :SerialNumber,
                   :Location, :LastMaintDate, :NextMaintDate, :MaintPeriod, :Status,
                   :Comments, :PdfPath, :Picture)
            ");
            $hst->execute([
                ':EquipoID'      => $id,
                ':Description'   => $description,
                ':Brand'         => $brand,
                ':Model'         => $model,
                ':SerialNumber'  => $serialNumber,
                ':Location'      => $values['location'],
                ':LastMaintDate' => $values['lastMaintDate'],
                ':NextMaintDate' => $nextMaintDate,
                ':MaintPeriod'   => $values['maintPeriod'],
                ':Status'        => $statusAuto,
                ':Comments'      => $values['comments'],
                ':PdfPath'       => $pdfPath    ?: null,
                ':Picture'       => $picturePath ?: null,
            ]);

            $pdo->commit();
            header('Location: mant_equipos_admin.php');
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
  <div class="col-12 col-lg-10 col-xl-8">
    <h1 class="h4 my-3 my-md-4">
      <i class="fa fa-gears me-2"></i>Registrar Mantenimiento — <?= h($id) ?>
    </h1>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <ul class="m-0 ps-3">
          <?php foreach ($errors as $err): ?>
            <li><?= h($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="POST" action="mant_equipos_update.php?id=<?= h($id) ?>" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="id"   value="<?= h($id) ?>">

      <div class="row g-3">
        <!-- Datos de solo lectura -->
        <div class="col-12">
          <label class="form-label">Equipo</label>
          <input type="text" class="form-control" value="<?= h($id) ?> — <?= h($description) ?>" disabled>
        </div>

        <div class="col-sm-4">
          <label class="form-label">Marca</label>
          <input type="text" class="form-control" value="<?= h($brand) ?>" disabled>
        </div>
        <div class="col-sm-4">
          <label class="form-label">Modelo</label>
          <input type="text" class="form-control" value="<?= h($model) ?>" disabled>
        </div>
        <div class="col-sm-4">
          <label class="form-label">Número de Serie</label>
          <input type="text" class="form-control" value="<?= h($serialNumber) ?>" disabled>
        </div>

        <!-- Período de mantenimiento (editable) -->
        <div class="col-sm-6">
          <label for="maintPeriod" class="form-label">
            <i class="fa fa-rotate me-1"></i>Período de Mantenimiento <span class="text-danger">*</span>
          </label>
          <select id="maintPeriod" name="maintPeriod" class="form-select" required>
            <?php foreach ($PERIOD_ALLOWED_U as $val => $label): ?>
              <option value="<?= h($val) ?>" <?= $values['maintPeriod'] === $val ? 'selected' : '' ?>>
                <?= h($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Fecha último mantenimiento (editable) -->
        <div class="col-sm-6">
          <label for="lastMaintDate" class="form-label">
            <i class="fa fa-calendar-check me-1 text-success"></i>Fecha del Mantenimiento <span class="text-danger">*</span>
          </label>
          <input type="date" class="form-control" id="lastMaintDate" name="lastMaintDate"
                 value="<?= h($values['lastMaintDate']) ?>" required>
          <div class="form-text">Ingresa la fecha en que se realizó este mantenimiento.</div>
        </div>

        <!-- Próximo (preview) -->
        <div class="col-sm-6">
          <label class="form-label">
            <i class="fa fa-calendar-days me-1 text-warning"></i>Próximo Mantenimiento (calculado)
          </label>
          <?php
            $nextPreview = '';
            if ($values['lastMaintDate'] && $values['maintPeriod']) {
                try { $nextPreview = calcNextMaintDateU($values['lastMaintDate'], $values['maintPeriod']); } catch (\Throwable $e) {}
            }
          ?>
          <input type="date" class="form-control" id="nextMaintPreview" value="<?= h($nextPreview) ?>" disabled>
          <div class="form-text">Se calcula automáticamente según el período.</div>
        </div>

        <!-- Estado preview -->
        <div class="col-sm-6">
          <label class="form-label">
            <i class="fa fa-circle-info me-1"></i>Estado (automático)
          </label>
          <input type="text" class="form-control" id="statusPreview"
                 value="<?= $nextPreview ? h(calcStatusU($nextPreview)) : '' ?>" disabled>
        </div>

        <!-- Ubicación (editable) -->
        <div class="col-12">
          <label for="location" class="form-label">
            <i class="fa fa-location-dot me-1"></i>Ubicación en Planta
          </label>
          <input type="text" id="location" name="location" class="form-control"
                 value="<?= h($values['location']) ?>"
                 placeholder="Ej: Nave 1, Celda A3">
        </div>

        <!-- Comentarios -->
        <div class="col-12">
          <label for="comments" class="form-label">
            <i class="fa fa-comment me-1"></i>Comentarios / Observaciones
          </label>
          <textarea id="comments" name="comments" class="form-control" rows="4"><?= h($values['comments']) ?></textarea>
        </div>

        <!-- PDF / documento -->
        <div class="col-12">
          <label class="form-label d-flex align-items-center justify-content-between">
            <span class="fw-semibold"><i class="fa fa-file-contract me-2"></i>Orden de Trabajo / Certificado</span>
            <?php if ($pdfPathView): ?>
              <a href="<?= h($pdfPathView) ?>" target="_blank" class="small text-decoration-none">
                <i class="fa fa-external-link me-1"></i>Ver actual
              </a>
            <?php endif; ?>
          </label>
          <input type="file" id="pdf" name="pdf"
                 accept="application/pdf,image/*"
                 capture="environment"
                 class="form-control form-control-lg">
          <div class="mt-3 d-none" id="pdfPreviewBox">
            <div class="preview-container">
              <div id="pdfImagePreview" class="d-none">
                <img id="pdfAsImage" src="" alt="preview" class="img-fluid rounded shadow-sm"
                     style="max-height:400px;width:100%;object-fit:contain;border:2px solid #444;">
              </div>
              <div id="pdfDocPreview" class="d-none">
                <iframe id="pdfPreview" title="PDF"
                        style="width:100%;height:400px;border:2px solid #444;border-radius:8px;"></iframe>
              </div>
            </div>
          </div>
        </div>

        <!-- Foto del equipo -->
        <div class="col-12 col-md-6">
          <label class="form-label d-flex align-items-center justify-content-between">
            <span class="fw-semibold"><i class="fa fa-camera me-2"></i>Foto del Equipo</span>
            <?php if ($picturePathView): ?>
              <a href="<?= h($picturePathView) ?>" target="_blank" class="small text-decoration-none">
                <i class="fa fa-external-link me-1"></i>Ver actual
              </a>
            <?php endif; ?>
          </label>
          <input type="file" id="picture" name="picture"
                 accept="image/*"
                 capture="environment"
                 class="form-control form-control-lg">
          <div class="mt-3 d-none" id="imgPreviewBox">
            <img id="imgPreview" src="" alt="preview" class="img-fluid rounded shadow-sm"
                 style="max-height:300px;width:100%;object-fit:cover;border:2px solid #444;">
          </div>
        </div>
      </div>

      <div class="mt-4 d-flex gap-2 flex-column flex-sm-row">
        <a href="mant_equipos_admin.php" class="btn btn-outline-secondary btn-lg">
          <i class="fa fa-times me-2"></i>Cancelar
        </a>
        <button type="submit" class="btn btn-primary btn-lg flex-grow-1">
          <i class="fa fa-save me-2"></i>Guardar Mantenimiento
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// Auto-cálculo de Próximo Mantenimiento y Estado
function toYMD(d) {
  const p = n => String(n).padStart(2,'0');
  return `${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())}`;
}
function addPeriod(ymd, period) {
  if (!ymd) return '';
  const [y, m, d] = ymd.split('-').map(Number);
  const dt = new Date(y, m - 1, d);
  if (period === '3M')      dt.setMonth(dt.getMonth() + 3);
  else if (period === '6M') dt.setMonth(dt.getMonth() + 6);
  else                      dt.setFullYear(dt.getFullYear() + 1);
  return toYMD(dt);
}
function calcStatusJS(nextDate) {
  if (!nextDate) return '';
  const today = toYMD(new Date());
  const limit = new Date(); limit.setDate(limit.getDate() + 30);
  const limitStr = toYMD(limit);
  if (nextDate < today)    return 'Vencido';
  if (nextDate <= limitStr) return 'Próximo mantenimiento';
  return 'Al corriente';
}
function updatePreview() {
  const lastDate = document.getElementById('lastMaintDate').value;
  const period   = document.getElementById('maintPeriod').value;
  const nextPrev = document.getElementById('nextMaintPreview');
  const statPrev = document.getElementById('statusPreview');
  if (lastDate && period) {
    const next = addPeriod(lastDate, period);
    nextPrev.value = next;
    statPrev.value = calcStatusJS(next);
  } else {
    nextPrev.value = '';
    statPrev.value = '';
  }
}
document.getElementById('lastMaintDate').addEventListener('change', updatePreview);
document.getElementById('maintPeriod').addEventListener('change', updatePreview);

// Vista previa imagen
const pictureInput = document.getElementById('picture');
if (pictureInput) {
  pictureInput.addEventListener('change', () => {
    const file = pictureInput.files?.[0];
    const box  = document.getElementById('imgPreviewBox');
    const img  = document.getElementById('imgPreview');
    if (file) { img.src = URL.createObjectURL(file); box.classList.remove('d-none'); }
    else { img.src = ''; box.classList.add('d-none'); }
  });
}

// Vista previa PDF/imagen de orden
const pdfInput = document.getElementById('pdf');
if (pdfInput) {
  pdfInput.addEventListener('change', () => {
    const file          = pdfInput.files?.[0];
    const box           = document.getElementById('pdfPreviewBox');
    const imgPreviewDiv = document.getElementById('pdfImagePreview');
    const pdfPreviewDiv = document.getElementById('pdfDocPreview');
    const img           = document.getElementById('pdfAsImage');
    const frame         = document.getElementById('pdfPreview');
    if (file) {
      const url   = URL.createObjectURL(file);
      const isPdf = file.type === 'application/pdf';
      if (isPdf) {
        frame.src = url;
        pdfPreviewDiv.classList.remove('d-none');
        imgPreviewDiv.classList.add('d-none');
      } else {
        img.src = url;
        imgPreviewDiv.classList.remove('d-none');
        pdfPreviewDiv.classList.add('d-none');
      }
      box.classList.remove('d-none');
    } else {
      frame.src = ''; img.src = '';
      box.classList.add('d-none');
    }
  });
}
</script>

<style>
@media (max-width: 767px) {
  .form-control, .form-control-lg { min-height: 48px; font-size: 16px; }
  .btn-lg { min-height: 52px; font-size: 1.1rem; }
}
.preview-container {
  background-color: #1a1a1a;
  padding: 1rem;
  border-radius: 0.5rem;
}
.form-control[type="file"] { cursor: pointer; padding: 0.75rem; }
.form-control[type="file"]::file-selector-button {
  padding: 0.5rem 1rem;
  margin-right: 1rem;
  background-color: #495057;
  border: 1px solid #6c757d;
  border-radius: 0.375rem;
  color: #fff;
  cursor: pointer;
  transition: background-color 0.15s ease-in-out;
}
.form-control[type="file"]::file-selector-button:hover { background-color: #5a6268; }
</style>

<?php include __DIR__ . '/partials/footer.php'; ?>
