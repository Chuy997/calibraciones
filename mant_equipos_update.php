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

// Ciclos habilitados para este equipo
$enabledCycles = [];
if (!empty($equipo['CycleMonthly']))   $enabledCycles['1M'] = 'Mensual';
if (!empty($equipo['CycleQuarterly'])) $enabledCycles['3M'] = 'Trimestral';
if (!empty($equipo['CycleYearly']))    $enabledCycles['1Y'] = 'Anual';
if (empty($enabledCycles))             $enabledCycles['1Y'] = 'Anual'; // fallback

// Fechas actuales por ciclo
$cycleDates = [
    '1M' => ['last' => (string)($equipo['LastMaintDate_1M'] ?? ''), 'next' => (string)($equipo['NextMaintDate_1M'] ?? '')],
    '3M' => ['last' => (string)($equipo['LastMaintDate_3M'] ?? ''), 'next' => (string)($equipo['NextMaintDate_3M'] ?? '')],
    '1Y' => ['last' => (string)($equipo['LastMaintDate']    ?? ''), 'next' => (string)($equipo['NextMaintDate']    ?? '')],
];

// Campos editables
$defaultCycle = array_key_first($enabledCycles);
$values = [
    'id'             => $id,
    'description'    => (string)($equipo['Description'] ?? ''),
    'lastMaintDate'  => $cycleDates[$defaultCycle]['last'],
    'selectedCycle'  => $defaultCycle,
    'location'       => (string)($equipo['Location'] ?? ''),
    'comments'       => (string)($equipo['Comments'] ?? ''),
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

// 3a) Acciones de Scrap y Eliminación (se evalúan antes del update normal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['action'] ?? ''), ['scrap_equipo','delete_equipo'], true)) {
    if (!isset($_POST['csrf']) || !csrf_validate($_POST['csrf'])) {
        $errors[] = 'Sesión expirada.';
    } else {
        $action = $_POST['action'];

        if ($action === 'scrap_equipo') {
            $reason = trim((string)($_POST['scrap_reason'] ?? ''));
            if ($reason === '') {
                $errors[] = 'Debes indicar el motivo para enviar a Scrap.';
            } else {
                try {
                    $pdo->beginTransaction();
                    $pdo->prepare("
                        UPDATE mant_equipos
                           SET Status = 'Scrap',
                               Comments = CONCAT(COALESCE(Comments,''),
                                          CASE WHEN COALESCE(Comments,'')='' THEN '' ELSE '\n' END,
                                          '[Scrap] ', :reason)
                         WHERE ID = :id
                    ")->execute([':reason' => $reason, ':id' => $id]);

                    $pdo->prepare("
                        INSERT INTO mant_equipos_history
                          (EquipoID, Action, Description, Brand, Model, SerialNumber,
                           Location, LastMaintDate, NextMaintDate, Status, Comments, PdfPath, Picture)
                        VALUES
                          (:eid,'scrap',:desc,:brand,:model,:serial,
                           :loc,:last,:next,'Scrap',:cmt,:pdf,:pic)
                    ")->execute([
                        ':eid'    => $id, ':desc'   => $description,
                        ':brand'  => $brand, ':model'  => $model,
                        ':serial' => $serialNumber,
                        ':loc'    => (string)($equipo['Location'] ?? ''),
                        ':last'   => (string)($equipo['LastMaintDate'] ?? ''),
                        ':next'   => (string)($equipo['NextMaintDate'] ?? ''),
                        ':cmt'    => '[Scrap] ' . $reason,
                        ':pdf'    => $pdfPath ?: null,
                        ':pic'    => $picturePath ?: null,
                    ]);
                    $pdo->commit();
                    header('Location: mant_equipos_admin.php');
                    exit;
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $errors[] = 'Error al enviar a Scrap: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'delete_equipo') {
            // Solo admin puede eliminar definitivamente
            if (($_SESSION['role'] ?? '') !== 'admin') {
                $errors[] = 'Solo el rol Administrador puede eliminar equipos.';
            } else {
                $confirm = trim((string)($_POST['delete_confirm'] ?? ''));
                if ($confirm !== $id) {
                    $errors[] = 'Confirmación incorrecta. Escribe el ID exacto del equipo.';
                } else {
                    try {
                        $pdo->beginTransaction();
                        // Primero registrar en historial (antes de borrar)
                        $pdo->prepare("
                            INSERT INTO mant_equipos_history
                              (EquipoID, Action, Description, Brand, Model, SerialNumber,
                               Location, LastMaintDate, NextMaintDate, Status, Comments, PdfPath, Picture)
                            VALUES
                              (:eid,'delete',:desc,:brand,:model,:serial,
                               :loc,:last,:next,:status,'[Eliminado definitivamente]',:pdf,:pic)
                        ")->execute([
                            ':eid'    => $id, ':desc'   => $description,
                            ':brand'  => $brand, ':model'  => $model,
                            ':serial' => $serialNumber,
                            ':loc'    => (string)($equipo['Location'] ?? ''),
                            ':last'   => (string)($equipo['LastMaintDate'] ?? ''),
                            ':next'   => (string)($equipo['NextMaintDate'] ?? ''),
                            ':status' => (string)($equipo['Status'] ?? ''),
                            ':pdf'    => $pdfPath ?: null,
                            ':pic'    => $picturePath ?: null,
                        ]);
                        // Eliminar historial asociado y luego el equipo
                        $pdo->prepare('DELETE FROM mant_equipos_history WHERE EquipoID = ?')->execute([$id]);
                        $pdo->prepare('DELETE FROM mant_equipos WHERE ID = ?')->execute([$id]);
                        $pdo->commit();
                        header('Location: mant_equipos_admin.php');
                        exit;
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        $errors[] = 'Error al eliminar el equipo: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

// 3b) Procesar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || !csrf_validate($_POST['csrf'])) {
        $errors[] = 'Sesión expirada. Por favor, vuelve a intentar.';
    }

    $newId                   = trim($_POST['new_id'] ?? $_POST['id'] ?? '');
    $values['id']            = $newId;
    $values['description']   = trim($_POST['description']   ?? '');
    $values['lastMaintDate'] = trim($_POST['lastMaintDate'] ?? '');
    $values['selectedCycle'] = trim($_POST['cycle'] ?? $defaultCycle);
    $values['location']      = trim($_POST['location']      ?? '');
    $values['comments']      = trim($_POST['comments']      ?? '');

    if ($newId === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $newId)) {
        $errors[] = 'ID inválido (solo letras, números, ".", "_" y "-").';
    } elseif ($newId !== $id) {
        $chk = $pdo->prepare('SELECT COUNT(*) FROM mant_equipos WHERE ID = ?');
        $chk->execute([$newId]);
        if ((int)$chk->fetchColumn() > 0) {
            $errors[] = 'Ya existe un equipo con el ID "' . $newId . '".';
        }
    }

    if ($values['description'] === '') {
        $errors[] = 'Debes ingresar el nombre o descripción del equipo.';
    }

    // Validar que el ciclo seleccionado está habilitado para este equipo
    if (!array_key_exists($values['selectedCycle'], $enabledCycles)) {
        $values['selectedCycle'] = $defaultCycle;
    }

    if ($values['lastMaintDate'] === '') {
        $errors[] = 'Debes ingresar la fecha del mantenimiento.';
    }

    // Calcular próxima fecha según ciclo
    $nextMaintDate = '';
    if (!$errors) {
        $dtBase = DateTime::createFromFormat('Y-m-d', $values['lastMaintDate']);
        if (!$dtBase) {
            $errors[] = 'Fecha de mantenimiento inválida.';
        } else {
            $interval = match($values['selectedCycle']) {
                '1M'    => '+1 month',
                '3M'    => '+3 months',
                default => '+1 year',
            };
            $nextMaintDate = (clone $dtBase)->modify($interval)->format('Y-m-d');
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Renombrar carpeta de uploads si cambió el ID
            $oldDirAbs = __DIR__ . '/uploads/mant_equipos/' . $id;
            $newDirAbs = __DIR__ . '/uploads/mant_equipos/' . $newId;
            if ($newId !== $id && is_dir($oldDirAbs) && !is_dir($newDirAbs)) {
                @rename($oldDirAbs, $newDirAbs);
            }

            if ($newId !== $id) {
                $oldRelPart = '/uploads/mant_equipos/' . $id . '/';
                $newRelPart = '/uploads/mant_equipos/' . $newId . '/';
                if ($pdfPath && str_contains($pdfPath, $oldRelPart)) {
                    $pdfPath = str_replace($oldRelPart, $newRelPart, $pdfPath);
                }
                if ($picturePath && str_contains($picturePath, $oldRelPart)) {
                    $picturePath = str_replace($oldRelPart, $newRelPart, $picturePath);
                }
            }

            $destDirAbs    = __DIR__ . '/uploads/mant_equipos/' . $newId . '/';
            $newPdfUrl     = handleUploadMantU('pdf',     $destDirAbs, 'pdf_or_img');
            $newPictureUrl = handleUploadMantU('picture', $destDirAbs, 'img');
            if ($newPdfUrl !== null)     { $pdfPath     = $newPdfUrl; }
            if ($newPictureUrl !== null) { $picturePath = $newPictureUrl; }

            // UPDATE solo las columnas del ciclo seleccionado
            if ($values['selectedCycle'] === '1Y') {
                $statusAuto = calcStatusU($nextMaintDate);
                $pdo->prepare("
                    UPDATE mant_equipos
                       SET ID = :new_id,
                           Description = :desc,
                           LastMaintDate = :last, NextMaintDate = :next,
                           MaintPeriod = '1Y', Status = :status,
                           Location = :loc, Comments = :cmt,
                           PdfPath = :pdf, Picture = :pic
                     WHERE ID = :old_id
                ")->execute([
                    ':new_id'=>$newId,
                    ':desc'=>$values['description'],
                    ':last'=>$values['lastMaintDate'],':next'=>$nextMaintDate,
                    ':status'=>$statusAuto,':loc'=>$values['location'],
                    ':cmt'=>$values['comments'],':pdf'=>$pdfPath?:null,
                    ':pic'=>$picturePath?:null,':old_id'=>$id]);
            } elseif ($values['selectedCycle'] === '3M') {
                $pdo->prepare("
                    UPDATE mant_equipos
                       SET ID = :new_id,
                           Description = :desc,
                           LastMaintDate_3M = :last, NextMaintDate_3M = :next,
                           Location = :loc, Comments = :cmt,
                           PdfPath = :pdf, Picture = :pic
                     WHERE ID = :old_id
                ")->execute([
                    ':new_id'=>$newId,
                    ':desc'=>$values['description'],
                    ':last'=>$values['lastMaintDate'],':next'=>$nextMaintDate,
                    ':loc'=>$values['location'],':cmt'=>$values['comments'],
                    ':pdf'=>$pdfPath?:null,':pic'=>$picturePath?:null,':old_id'=>$id]);
                $statusAuto = calcStatusU((string)($equipo['NextMaintDate'] ?? $nextMaintDate));
            } else { // 1M
                $pdo->prepare("
                    UPDATE mant_equipos
                       SET ID = :new_id,
                           Description = :desc,
                           LastMaintDate_1M = :last, NextMaintDate_1M = :next,
                           Location = :loc, Comments = :cmt,
                           PdfPath = :pdf, Picture = :pic
                     WHERE ID = :old_id
                ")->execute([
                    ':new_id'=>$newId,
                    ':desc'=>$values['description'],
                    ':last'=>$values['lastMaintDate'],':next'=>$nextMaintDate,
                    ':loc'=>$values['location'],':cmt'=>$values['comments'],
                    ':pdf'=>$pdfPath?:null,':pic'=>$picturePath?:null,':old_id'=>$id]);
                $statusAuto = calcStatusU((string)($equipo['NextMaintDate'] ?? $nextMaintDate));
            }

            // Actualizar historial previo si cambió el ID
            if ($newId !== $id) {
                $pdo->prepare("
                    UPDATE mant_equipos_history
                       SET EquipoID = :newId,
                           PdfPath = REPLACE(PdfPath, :oldRel1, :newRel1),
                           Picture = REPLACE(Picture, :oldRel2, :newRel2)
                     WHERE EquipoID = :oldId
                ")->execute([
                    ':newId'   => $newId,
                    ':oldRel1' => '/uploads/mant_equipos/' . $id . '/',
                    ':newRel1' => '/uploads/mant_equipos/' . $newId . '/',
                    ':oldRel2' => '/uploads/mant_equipos/' . $id . '/',
                    ':newRel2' => '/uploads/mant_equipos/' . $newId . '/',
                    ':oldId'   => $id,
                ]);
            }

            // INSERT historial con CycleType
            $histPeriod = in_array($values['selectedCycle'], ['3M','1Y'], true) ? $values['selectedCycle'] : null;
            $pdo->prepare("
                INSERT INTO mant_equipos_history
                  (EquipoID, Action, Description, Brand, Model, SerialNumber,
                   Location, LastMaintDate, NextMaintDate, MaintPeriod, CycleType, Status,
                   Comments, PdfPath, Picture)
                VALUES
                  (:EquipoID,'update',:Description,:Brand,:Model,:SerialNumber,
                   :Location,:LastMaintDate,:NextMaintDate,:MaintPeriod,:CycleType,:Status,
                   :Comments,:PdfPath,:Picture)
            ")->execute([
                ':EquipoID'      => $newId,
                ':Description'   => $values['description'],
                ':Brand'         => $brand,
                ':Model'         => $model,
                ':SerialNumber'  => $serialNumber,
                ':Location'      => $values['location'],
                ':LastMaintDate' => $values['lastMaintDate'],
                ':NextMaintDate' => $nextMaintDate,
                ':MaintPeriod'   => $histPeriod,
                ':CycleType'     => $values['selectedCycle'],
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
      <input type="hidden" name="original_id" value="<?= h($id) ?>">

      <div class="row g-3">
        <!-- Identificador del equipo (editable) -->
        <div class="col-sm-4">
          <label for="new_id" class="form-label">ID del Equipo <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="new_id" name="new_id"
                 value="<?= h($values['id']) ?>" required
                 placeholder="Ej: TORNO-01">
        </div>

        <!-- Nombre / Descripción del equipo (editable) -->
        <div class="col-sm-8">
          <label for="description" class="form-label">
            Nombre / Descripción del Equipo <span class="text-danger">*</span>
          </label>
          <input type="text" id="description" name="description" class="form-control"
                 value="<?= h($values['description']) ?>" required>
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

        <!-- Selector de ciclo -->
        <div class="col-12">
          <label class="form-label fw-semibold">
            <i class="fa fa-rotate me-1"></i>¿Qué ciclo estás registrando? <span class="text-danger">*</span>
          </label>
          <?php if (count($enabledCycles) === 1): ?>
            <input type="hidden" name="cycle" value="<?= h(array_key_first($enabledCycles)) ?>">
            <input type="text" class="form-control" value="<?= h(array_values($enabledCycles)[0]) ?>" disabled>
          <?php else: ?>
          <div class="cycle-btn-group" role="group">
            <?php
              $cycleColors = ['1M'=>'indigo','3M'=>'orange','1Y'=>'green'];
              $cycleIcons  = ['1M'=>'fa-calendar-day','3M'=>'fa-rotate','1Y'=>'fa-calendar-check'];
            ?>
            <?php foreach ($enabledCycles as $cKey => $cLabel): ?>
            <input type="radio" class="btn-check" name="cycle" id="cycle_<?= h($cKey) ?>"
                   value="<?= h($cKey) ?>" <?= $values['selectedCycle'] === $cKey ? 'checked' : '' ?>>
            <label class="btn cycle-opt cycle-opt-<?= h($cycleColors[$cKey] ?? 'secondary') ?>"
                   for="cycle_<?= h($cKey) ?>">
              <i class="fa <?= h($cycleIcons[$cKey] ?? 'fa-calendar') ?> me-1"></i><?= h($cLabel) ?>
              <small class="d-block mt-1" style="font-size:0.72rem;opacity:0.8;">
                Último: <?= h($cycleDates[$cKey]['last'] ?: '—') ?><br>
                Próx: <?= h($cycleDates[$cKey]['next'] ?: '—') ?>
              </small>
            </label>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

        <!-- Fecha del mantenimiento realizado -->
        <div class="col-sm-6">
          <label for="lastMaintDate" class="form-label">
            <i class="fa fa-calendar-check me-1 text-success"></i>Fecha del Mantenimiento <span class="text-danger">*</span>
          </label>
          <input type="date" class="form-control" id="lastMaintDate" name="lastMaintDate"
                 value="<?= h($values['lastMaintDate']) ?>" required>
          <div class="form-text">Fecha en que se realizó este mantenimiento.</div>
        </div>

        <!-- Próximo (preview) -->
        <div class="col-sm-6">
          <label class="form-label">
            <i class="fa fa-calendar-days me-1 text-warning"></i>Próximo (calculado)
          </label>
          <input type="date" class="form-control" id="nextMaintPreview" disabled>
          <div class="form-text">Se calcula automáticamente según el ciclo seleccionado.</div>
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

<!-- ===== ZONA DE PELIGRO ===== -->
<?php if (($equipo['Status'] ?? '') !== 'Scrap'): ?>
<div class="row justify-content-center mt-4 mb-5">
  <div class="col-12 col-lg-8 col-xl-7">
    <div class="card p-3" style="border:1px solid rgba(220,53,69,0.35);background:rgba(220,53,69,0.05);">
      <h6 class="text-danger mb-3"><i class="fa fa-triangle-exclamation me-2"></i>Zona de Peligro</h6>
      <div class="d-flex gap-2 flex-wrap">

        <!-- Scrap -->
        <button type="button" class="btn btn-warning"
                data-bs-toggle="modal" data-bs-target="#scrapModal">
          <i class="fa fa-dumpster me-2"></i>Enviar a Scrap
        </button>

        <!-- Eliminar (solo admin) -->
        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
        <button type="button" class="btn btn-danger"
                data-bs-toggle="modal" data-bs-target="#deleteModal">
          <i class="fa fa-trash me-2"></i>Eliminar Equipo
        </button>
        <?php endif; ?>
      </div>
      <small class="text-secondary mt-2 d-block">
        <strong>Scrap:</strong> desactiva el equipo y lo retira del panel sin borrar el historial.
        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
        &nbsp;&bull;&nbsp;<strong>Eliminar:</strong> borra permanentemente el equipo y todo su historial.
        <?php endif; ?>
      </small>
    </div>
  </div>
</div>
<?php else: ?>
<div class="row justify-content-center mt-4 mb-5">
  <div class="col-12 col-lg-8 col-xl-7">
    <div class="alert alert-warning">
      <i class="fa fa-dumpster me-2"></i>Este equipo ya se encuentra en estado <strong>Scrap</strong>.
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Modal Scrap -->
<div class="modal fade" id="scrapModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark">
      <div class="modal-header border-secondary">
        <h5 class="modal-title"><i class="fa fa-dumpster text-warning me-2"></i>Confirmar Scrap</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="mant_equipos_update.php?id=<?= h($id) ?>">
        <input type="hidden" name="csrf"   value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="id"     value="<?= h($id) ?>">
        <input type="hidden" name="action" value="scrap_equipo">
        <div class="modal-body">
          <p>El equipo <strong><?= h($id) ?> &ndash; <?= h($description) ?></strong> quedará marcado como <span class="text-warning fw-bold">Scrap</span> y desaparecerá del panel de mantenimientos.</p>
          <div class="mb-3">
            <label for="scrap_reason" class="form-label">Motivo de Scrap <span class="text-danger">*</span></label>
            <textarea id="scrap_reason" name="scrap_reason" class="form-control" rows="3"
                      placeholder="Describe la razón del retiro…" required></textarea>
          </div>
        </div>
        <div class="modal-footer border-secondary">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-warning">
            <i class="fa fa-dumpster me-2"></i>Confirmar Scrap
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Eliminar (solo admin) -->
<?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark">
      <div class="modal-header border-danger">
        <h5 class="modal-title text-danger"><i class="fa fa-trash me-2"></i>Eliminar Equipo Permanentemente</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="mant_equipos_update.php?id=<?= h($id) ?>">
        <input type="hidden" name="csrf"   value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="id"     value="<?= h($id) ?>">
        <input type="hidden" name="action" value="delete_equipo">
        <div class="modal-body">
          <div class="alert alert-danger">
            <i class="fa fa-triangle-exclamation me-2"></i>
            <strong>Esta acción es irreversible.</strong> Se borrará el equipo y todo su historial de mantenimientos.
          </div>
          <p>Para confirmar, escribe el ID del equipo:</p>
          <p class="font-monospace fw-bold text-danger"><?= h($id) ?></p>
          <input type="text" name="delete_confirm" class="form-control"
                 placeholder="Escribe: <?= h($id) ?>" autocomplete="off" required>
        </div>
        <div class="modal-footer border-danger">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-danger">
            <i class="fa fa-trash me-2"></i>Eliminar definitivamente
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
// Auto-cálculo de Próximo según ciclo
function toYMD(d) {
  const p = n => String(n).padStart(2,'0');
  return `${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())}`;
}
function addCycle(ymd, cycle) {
  if (!ymd) return '';
  const [y, m, d] = ymd.split('-').map(Number);
  const dt = new Date(y, m - 1, d);
  if      (cycle === '1M') dt.setMonth(dt.getMonth() + 1);
  else if (cycle === '3M') dt.setMonth(dt.getMonth() + 3);
  else                     dt.setFullYear(dt.getFullYear() + 1);
  return toYMD(dt);
}
function getSelectedCycle() {
  const r = document.querySelector('input[name="cycle"]:checked');
  return r ? r.value : '1Y';
}
function updatePreview() {
  const lastDate = document.getElementById('lastMaintDate').value;
  const cycle    = getSelectedCycle();
  const nextPrev = document.getElementById('nextMaintPreview');
  if (lastDate) {
    nextPrev.value = addCycle(lastDate, cycle);
  } else {
    nextPrev.value = '';
  }
}
document.getElementById('lastMaintDate').addEventListener('change', updatePreview);
document.querySelectorAll('input[name="cycle"]').forEach(r => r.addEventListener('change', updatePreview));
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
/* Cycle selector */
.cycle-btn-group { display: flex; gap: 0.75rem; flex-wrap: wrap; }
.cycle-opt {
  flex: 1; min-width: 130px; padding: 0.75rem 1rem;
  border-radius: 12px; font-weight: 600; font-size: 0.9rem;
  border: 2px solid transparent; text-align: center;
  transition: all 0.2s ease; cursor: pointer;
}
.cycle-opt-green  { background: rgba(32,201,151,0.1); border-color: rgba(32,201,151,0.3); color: #6ee7b7; }
.cycle-opt-orange { background: rgba(251,146,60,0.1);  border-color: rgba(251,146,60,0.3);  color: #fdba74; }
.cycle-opt-indigo { background: rgba(99,102,241,0.1);  border-color: rgba(99,102,241,0.3);  color: #a5b4fc; }
.btn-check:checked + .cycle-opt-green  { background: rgba(32,201,151,0.25); border-color: #20c997; box-shadow: 0 0 0 3px rgba(32,201,151,0.2); }
.btn-check:checked + .cycle-opt-orange { background: rgba(251,146,60,0.25);  border-color: #fb923c; box-shadow: 0 0 0 3px rgba(251,146,60,0.2); }
.btn-check:checked + .cycle-opt-indigo { background: rgba(99,102,241,0.25);  border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.2); }
@media (max-width: 576px) { .cycle-opt { min-width: 100%; } }

/* Existing styles */
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
