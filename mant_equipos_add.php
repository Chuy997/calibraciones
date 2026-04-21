<?php
// /var/www/html/calibraciones/mant_equipos_add.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_auth(['admin','ingenieria']);

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

const MAX_IMG_BYTES_M = 5 * 1024 * 1024;
const MAX_PDF_BYTES_M = 20 * 1024 * 1024;
$ALLOWED_IMG_EXT_M = ['jpg','jpeg','png','webp'];
$ALLOWED_PDF_EXT_M = ['pdf'];

$PERIOD_ALLOWED = [
    '3M' => 'Cada 3 meses',
    '6M' => 'Cada 6 meses',
    '1Y' => 'Anual (cada 12 meses)',
];

$values = [
    'id'           => '',
    'description'  => '',
    'brand'        => '',
    'model'        => '',
    'serialNumber' => '',
    'location'     => '',
    'lastMaintDate'=> '',
    'maintPeriod'  => '1Y',
    'comments'     => '',
];

$errors = [];

/**
 * Calcula NextMaintDate según el periodo elegido.
 */
function calcNextMaintDate(string $lastDate, string $period): string {
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
function calcStatus(string $nextDate): string {
    $today = (new DateTime('today'))->format('Y-m-d');
    $limit = (new DateTime('today'))->modify('+30 days')->format('Y-m-d');
    if ($nextDate < $today) return 'Vencido';
    if ($nextDate <= $limit) return 'Próximo mantenimiento';
    return 'Al corriente';
}

function handleUploadMant(string $field, string $destDir, string $kind): ?string {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Error en la subida de $field (código {$_FILES[$field]['error']}).");
    }

    $tmp  = $_FILES[$field]['tmp_name'];
    $name = $_FILES[$field]['name'];
    $size = (int)$_FILES[$field]['size'];
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if ($kind === 'pdf') {
        if ($size > MAX_PDF_BYTES_M) throw new RuntimeException("El PDF excede el tamaño permitido (20MB).");
        if (!in_array($ext, $GLOBALS['ALLOWED_PDF_EXT_M'], true)) throw new RuntimeException("Extensión de PDF no permitida.");
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $tmp) ?: '';
        finfo_close($finfo);
        if (stripos($mime, 'pdf') === false) throw new RuntimeException("El archivo no es un PDF válido.");
    } else {
        if ($size > MAX_IMG_BYTES_M) throw new RuntimeException("La imagen excede el tamaño permitido (5MB).");
        if (!in_array($ext, $GLOBALS['ALLOWED_IMG_EXT_M'], true)) throw new RuntimeException("Extensión de imagen no permitida.");
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

    $docroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '/var/www/html', '/').'/';
    $rel     = ltrim(str_replace($docroot, '', $destAbs), '/');
    if (!str_starts_with($rel, 'calibraciones/')) {
        $rel = 'calibraciones/' . $rel;
    }
    return '/' . ltrim($rel, '/');
}

// POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || !csrf_validate($_POST['csrf'])) {
        $errors[] = 'Sesión expirada. Por favor, vuelve a intentar.';
    }

    foreach ($values as $k => $_) {
        $values[$k] = trim($_POST[$k] ?? '');
    }

    // Validaciones
    if ($values['id'] === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $values['id'])) {
        $errors[] = 'ID inválido (solo letras, números, ".", "_" y "-").';
    }
    if ($values['description'] === '') {
        $errors[] = 'Debe ingresar una descripción del equipo.';
    }
    if ($values['lastMaintDate'] === '') {
        $errors[] = 'Debe ingresar la fecha del último mantenimiento.';
    }
    if (!array_key_exists($values['maintPeriod'], $PERIOD_ALLOWED)) {
        $errors[] = 'Período de mantenimiento inválido.';
    }

    // Calcular nextMaintDate
    $nextMaintDate = '';
    if (!$errors) {
        try {
            $nextMaintDate = calcNextMaintDate($values['lastMaintDate'], $values['maintPeriod']);
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (!$errors) {
        try {
            $pdo = pdo();

            // Duplicado de ID
            $dup = $pdo->prepare('SELECT COUNT(*) FROM mant_equipos WHERE ID = ?');
            $dup->execute([$values['id']]);
            if ((int)$dup->fetchColumn() > 0) {
                $errors[] = 'Ya existe un equipo con ese ID.';
            }

            if (!$errors) {
                $pdo->beginTransaction();

                $projectRoot = dirname(__FILE__);
                $destDirAbs  = $projectRoot . '/uploads/mant_equipos/' . $values['id'] . '/';

                $pdfPathRel     = handleUploadMant('pdf', $destDirAbs, 'pdf');
                $picturePathRel = handleUploadMant('picture', $destDirAbs, 'img');

                $statusAuto = calcStatus($nextMaintDate);

                // INSERT mant_equipos
                $ins = $pdo->prepare("
                    INSERT INTO mant_equipos
                      (ID, Description, Brand, Model, SerialNumber, Location,
                       LastMaintDate, NextMaintDate, MaintPeriod, Status,
                       Picture, PdfPath, Comments)
                    VALUES
                      (:ID, :Description, :Brand, :Model, :SerialNumber, :Location,
                       :LastMaintDate, :NextMaintDate, :MaintPeriod, :Status,
                       :Picture, :PdfPath, :Comments)
                ");
                $ins->execute([
                    ':ID'            => $values['id'],
                    ':Description'   => $values['description'],
                    ':Brand'         => $values['brand'],
                    ':Model'         => $values['model'],
                    ':SerialNumber'  => $values['serialNumber'],
                    ':Location'      => $values['location'],
                    ':LastMaintDate' => $values['lastMaintDate'],
                    ':NextMaintDate' => $nextMaintDate,
                    ':MaintPeriod'   => $values['maintPeriod'],
                    ':Status'        => $statusAuto,
                    ':Picture'       => $picturePathRel,
                    ':PdfPath'       => $pdfPathRel,
                    ':Comments'      => $values['comments'],
                ]);

                // INSERT mant_equipos_history (snapshot inicial)
                $hst = $pdo->prepare("
                    INSERT INTO mant_equipos_history
                      (EquipoID, Action, Description, Brand, Model, SerialNumber,
                       Location, LastMaintDate, NextMaintDate, MaintPeriod, Status,
                       Comments, PdfPath, Picture)
                    VALUES
                      (:EquipoID, 'create', :Description, :Brand, :Model, :SerialNumber,
                       :Location, :LastMaintDate, :NextMaintDate, :MaintPeriod, :Status,
                       :Comments, :PdfPath, :Picture)
                ");
                $hst->execute([
                    ':EquipoID'      => $values['id'],
                    ':Description'   => $values['description'],
                    ':Brand'         => $values['brand'],
                    ':Model'         => $values['model'],
                    ':SerialNumber'  => $values['serialNumber'],
                    ':Location'      => $values['location'],
                    ':LastMaintDate' => $values['lastMaintDate'],
                    ':NextMaintDate' => $nextMaintDate,
                    ':MaintPeriod'   => $values['maintPeriod'],
                    ':Status'        => $statusAuto,
                    ':Comments'      => $values['comments'],
                    ':PdfPath'       => $pdfPathRel,
                    ':Picture'       => $picturePathRel,
                ]);

                $pdo->commit();
                header('Location: mant_equipos_admin.php');
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
    <h1 class="h4 mb-3"><i class="fa fa-gears me-2"></i>Registrar nuevo equipo</h1>

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
          <label for="id" class="form-label">ID del Equipo <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="id" name="id"
                 value="<?= h($values['id']) ?>" required
                 placeholder="Ej: TORNO-01, PRENSA-A">
          <div class="form-text">Solo letras, números, ".", "_" y "-".</div>
        </div>

        <div class="col-12">
          <label for="description" class="form-label">Descripción del Equipo <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="description" name="description"
                 value="<?= h($values['description']) ?>" required
                 placeholder="Ej: Torno CNC Modelo X200">
        </div>

        <div class="col-sm-4">
          <label for="brand" class="form-label">Marca</label>
          <input type="text" class="form-control" id="brand" name="brand"
                 value="<?= h($values['brand']) ?>" placeholder="Ej: Fanuc, Mazak">
        </div>
        <div class="col-sm-4">
          <label for="model" class="form-label">Modelo</label>
          <input type="text" class="form-control" id="model" name="model"
                 value="<?= h($values['model']) ?>">
        </div>
        <div class="col-sm-4">
          <label for="serialNumber" class="form-label">Número de Serie</label>
          <input type="text" class="form-control" id="serialNumber" name="serialNumber"
                 value="<?= h($values['serialNumber']) ?>">
        </div>

        <div class="col-12">
          <label for="location" class="form-label">
            <i class="fa fa-location-dot me-1"></i>Ubicación en Planta
          </label>
          <input type="text" class="form-control" id="location" name="location"
                 value="<?= h($values['location']) ?>"
                 placeholder="Ej: Nave 1, Celda A3">
        </div>

        <!-- Período de mantenimiento -->
        <div class="col-sm-6">
          <label for="maintPeriod" class="form-label">
            <i class="fa fa-rotate me-1"></i>Período de Mantenimiento <span class="text-danger">*</span>
          </label>
          <select id="maintPeriod" name="maintPeriod" class="form-select" required>
            <?php foreach ($PERIOD_ALLOWED as $val => $label): ?>
              <option value="<?= h($val) ?>" <?= $values['maintPeriod'] === $val ? 'selected' : '' ?>>
                <?= h($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Frecuencia con la que se debe realizar el mantenimiento.</div>
        </div>

        <!-- Fecha último mantenimiento -->
        <div class="col-sm-6">
          <label for="lastMaintDate" class="form-label">
            <i class="fa fa-calendar-check me-1"></i>Fecha del Último Mantenimiento <span class="text-danger">*</span>
          </label>
          <input type="date" class="form-control" id="lastMaintDate" name="lastMaintDate"
                 value="<?= h($values['lastMaintDate']) ?>" required>
        </div>

        <!-- Próximo mantenimiento (preview calculado en JS) -->
        <div class="col-sm-6">
          <label for="nextMaintPreview" class="form-label">
            <i class="fa fa-calendar-days me-1"></i>Próximo Mantenimiento (calculado)
          </label>
          <input type="date" class="form-control" id="nextMaintPreview" disabled>
          <div class="form-text">Se calcula automáticamente según el período seleccionado.</div>
        </div>

        <!-- Estado preview -->
        <div class="col-sm-6">
          <label for="statusPreview" class="form-label">
            <i class="fa fa-circle-info me-1"></i>Estado (automático)
          </label>
          <input type="text" class="form-control" id="statusPreview" disabled>
        </div>

        <div class="col-12">
          <label for="comments" class="form-label">Comentarios / Notas</label>
          <textarea id="comments" name="comments" class="form-control" rows="3"><?= h($values['comments']) ?></textarea>
        </div>

        <div class="col-md-6">
          <label for="pdf" class="form-label">Subir PDF (orden de trabajo / certificado)</label>
          <input type="file" id="pdf" name="pdf" accept="application/pdf" class="form-control">
          <div class="mt-2 d-none" id="pdfPreviewBox">
            <iframe id="pdfPreview" title="PDF" style="width:100%;height:300px;border:1px solid #333;border-radius:8px;"></iframe>
          </div>
        </div>

        <div class="col-md-6">
          <label for="picture" class="form-label">Subir Imagen del Equipo</label>
          <input type="file" id="picture" name="picture" accept="image/*" class="form-control">
          <div class="mt-2 d-none" id="imgPreviewBox">
            <img id="imgPreview" src="" alt="preview" class="img-fluid rounded" style="max-height:300px;border:1px solid #333;">
          </div>
        </div>
      </div>

      <div class="mt-4 d-flex gap-2">
        <a href="mant_equipos_admin.php" class="btn btn-outline-secondary">
          <i class="fa fa-times me-1"></i>Cancelar
        </a>
        <button type="submit" class="btn btn-success">
          <i class="fa fa-save me-2"></i>Guardar Equipo
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
  if (period === '3M') {
    dt.setMonth(dt.getMonth() + 3);
  } else if (period === '6M') {
    dt.setMonth(dt.getMonth() + 6);
  } else {
    dt.setFullYear(dt.getFullYear() + 1);
  }
  return toYMD(dt);
}

function calcStatusJS(nextDate) {
  if (!nextDate) return '';
  const today = toYMD(new Date());
  const limit = new Date();
  limit.setDate(limit.getDate() + 30);
  const limitStr = toYMD(limit);
  if (nextDate < today) return 'Vencido';
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
updatePreview();

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

// Vista previa PDF
const pdfInput = document.getElementById('pdf');
if (pdfInput) {
  pdfInput.addEventListener('change', () => {
    const file  = pdfInput.files?.[0];
    const box   = document.getElementById('pdfPreviewBox');
    const frame = document.getElementById('pdfPreview');
    if (file) { frame.src = URL.createObjectURL(file); box.classList.remove('d-none'); }
    else { frame.src = ''; box.classList.add('d-none'); }
  });
}
</script>

<?php include __DIR__.'/partials/footer.php'; ?>
