<?php
// /var/www/html/calibraciones/update.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_auth('admin'); // solo admins

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// --- Config de subida ---
const MAX_IMG_BYTES = 5 * 1024 * 1024;     // 5MB
const MAX_PDF_BYTES = 20 * 1024 * 1024;    // 20MB
$ALLOWED_IMG_EXT = ['jpg','jpeg','png','webp'];
$ALLOWED_PDF_EXT = ['pdf'];

/** Estado en BD (enum) */
const STATE_CALIBRADO        = 'calibrado';
const STATE_FUERA            = 'fuera de calibracion';
const STATE_EN_PROCESO       = 'en proceso de calibracion';

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

// Datos actuales (solo lectura en el formulario)
$description  = (string)($instrument['Description']  ?? '');
$brand        = (string)($instrument['Brand']        ?? '');
$model        = (string)($instrument['Model']        ?? '');
$serialNumber = (string)($instrument['SerialNumber'] ?? '');

// Rutas actuales y CertificateNo
$pdfPath       = (string)($instrument['PdfPath'] ?? '');
$picturePath   = (string)($instrument['Picture'] ?? '');
$certificateNo = (string)($instrument['CertificateNo'] ?? '');

// Normaliza rutas existentes para la vista (por si quedaron sin / inicial)
$norm = fn(string $p)=> $p ? ('/'.ltrim($p,'/')) : '';
$pdfPathView     = $norm($pdfPath);
$picturePathView = $norm($picturePath);

// Valores que SÍ se pueden cambiar
$values = [
  'calDate'  => (string)($instrument['CalDate'] ?? ''),
  'dueDate'  => (string)($instrument['DueDate'] ?? ''), // será recalculado por servidor
  'comments' => (string)($instrument['Comments'] ?? ''),
];

$errors = [];

/** Sumar 1 año con DateTime (robusto a fin de mes) */
function plusOneYear(string $ymd): string {
    $dt = DateTime::createFromFormat('Y-m-d', $ymd);
    if (!$dt) { throw new RuntimeException('Fecha inválida (calDate).'); }
    $dt->modify('+1 year');
    return $dt->format('Y-m-d');
}

/** Estado automático a partir de dueDate */
function autoState(string $dueYmd): string {
    $today = (new DateTime('today'))->format('Y-m-d');
    return ($dueYmd < $today) ? STATE_FUERA : STATE_CALIBRADO;
}

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

/**
 * Manejar subida validando tamaño/mime/ext. Guarda en $destDir ABSOLUTO.
 * Retorna URL ABSOLUTA web (p.ej. /calibraciones/uploads/ID/archivo.ext) o null si no hay archivo.
 * $kind = 'pdf' | 'img' | 'pdf_or_img' (para campo que acepta ambos)
 */
function handleUpload(string $field, string $destDir, string $kind): ?string {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Error al subir $field: " . uploadErrMsg((int)$_FILES[$field]['error']));
    }

    $tmp  = $_FILES[$field]['tmp_name'];
    $name = $_FILES[$field]['name'] ?? $kind;
    $size = (int)($_FILES[$field]['size'] ?? 0);
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION) ?: '');

    // Detectar MIME type primero
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $tmp) ?: '';
    finfo_close($finfo);

    // Si no hay extensión, intentar asignarla basada en MIME type
    if ($ext === '' || $ext === $kind) {
        if (stripos($mime, 'pdf') !== false) {
            $ext = 'pdf';
        } elseif (stripos($mime, 'jpeg') !== false || stripos($mime, 'jpg') !== false) {
            $ext = 'jpg';
        } elseif (stripos($mime, 'png') !== false) {
            $ext = 'png';
        } elseif (stripos($mime, 'webp') !== false) {
            $ext = 'webp';
        } elseif (stripos($mime, 'heic') !== false || stripos($mime, 'heif') !== false) {
            $ext = 'heic';
        }
    }

    // Validación flexible para campo PDF que acepta también imágenes (para fotos desde móvil)
    if ($kind === 'pdf_or_img') {
        $isPdf = stripos($mime, 'pdf') !== false;
        $isImg = stripos($mime, 'image') !== false;
        
        if ($isPdf) {
            if ($size > MAX_PDF_BYTES) throw new RuntimeException("El PDF excede el tamaño permitido (20MB).");
            if (!in_array($ext, $GLOBALS['ALLOWED_PDF_EXT'], true)) {
                throw new RuntimeException("Extensión de PDF no permitida: .$ext");
            }
        } elseif ($isImg) {
            if ($size > MAX_IMG_BYTES) throw new RuntimeException("La imagen excede el tamaño permitido (5MB).");
            // Aceptar extensiones comunes de móviles, incluyendo jpg sin 'e'
            $allowedImgExt = ['jpg', 'jpeg', 'png', 'webp', 'heic'];
            if (!in_array($ext, $allowedImgExt, true)) {
                throw new RuntimeException("Extensión de imagen no permitida: .$ext (permitidas: " . implode(', ', $allowedImgExt) . ")");
            }
            // Intentar validar imagen solo si no es HEIC (que getimagesize no soporta bien)
            if ($ext !== 'heic' && @getimagesize($tmp) === false) {
                throw new RuntimeException("El archivo de imagen parece estar corrupto o no es válido.");
            }
        } else {
            throw new RuntimeException("El archivo debe ser un PDF o una imagen. Tipo detectado: $mime, extensión: .$ext");
        }
    } elseif ($kind === 'pdf') {
        if ($size > MAX_PDF_BYTES) throw new RuntimeException("El PDF excede el tamaño permitido (20MB).");
        if (!in_array($ext, $GLOBALS['ALLOWED_PDF_EXT'], true)) throw new RuntimeException("Extensión de PDF no permitida.");
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $tmp) ?: '';
        finfo_close($finfo);
        if (stripos($mime, 'pdf') === false) throw new RuntimeException("El archivo no es un PDF válido.");
    } else {
        if ($size > MAX_IMG_BYTES) throw new RuntimeException("La imagen excede el tamaño permitido (5MB).");
        if (!in_array($ext, $GLOBALS['ALLOWED_IMG_EXT'], true)) throw new RuntimeException("Extensión de imagen no permitida.");
        if (@getimagesize($tmp) === false) throw new RuntimeException("El archivo de imagen es inválido.");
    }

    // Asegurar directorio por instrumento (uploads/{ID}/)
    if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
        throw new RuntimeException("No se pudo crear el directorio de destino.");
    }

    $safeBase = preg_replace('/[^A-Za-z0-9_\-]/', '_', pathinfo($name, PATHINFO_FILENAME));
    if ($safeBase === '') $safeBase = $kind;
    $final    = $safeBase . '_' . time() . '.' . $ext;
    $destAbs  = rtrim($destDir, '/').'/'.$final;

    if (!move_uploaded_file($tmp, $destAbs)) {
        throw new RuntimeException("No se pudo mover el archivo subido.");
    }

    // Construir URL ABSOLUTA servible: /calibraciones/uploads/ID/archivo.ext
    // DocumentRoot típico: /var/www/html
    $docroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '/var/www/html', '/') . '/';
    $rel     = ltrim(str_replace($docroot, '', $destAbs), '/'); // p.ej. calibraciones/uploads/ID/archivo.ext
    // Asegurar prefijo calibraciones/ si hiciera falta (por despliegues alternos)
    if (!str_starts_with($rel, 'calibraciones/')) {
        $rel = 'calibraciones/' . $rel;
    }
    return '/' . ltrim($rel, '/'); // devolver ABSOLUTA
}

// 3) Si es POST, procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (!isset($_POST['csrf']) || !csrf_validate($_POST['csrf'])) {
        $errors[] = 'Sesión expirada. Por favor, vuelve a intentar.';
    }

    // Recoger SOLO campos permitidos
    $values['calDate']  = trim($_POST['calDate']  ?? '');
    $values['comments'] = trim($_POST['comments'] ?? '');

    // Validaciones
    if ($values['calDate'] === '') {
        $errors[] = 'Debes ingresar la fecha de calibración.';
    } else {
        // Recalcular dueDate en servidor
        try {
            $values['dueDate'] = plusOneYear($values['calDate']);
        } catch (Throwable $e) {
            $errors[] = 'Fecha de calibración inválida.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Carpeta de este instrumento: /var/www/html/calibraciones/uploads/{ID}/
            $projectRoot = __DIR__;
            $destDirAbs  = $projectRoot . '/uploads/' . $id . '/';

            // Subir archivos si se enviaron (devuelven URL absolutas)
            // PDF ahora acepta también imágenes (para fotos del documento desde móvil)
            $newPdfUrl     = handleUpload('pdf',     $destDirAbs, 'pdf_or_img'); // null si no hay nuevo
            $newPictureUrl = handleUpload('picture', $destDirAbs, 'img'); // null si no hay nuevo

            if ($newPdfUrl !== null)     { $pdfPath     = $newPdfUrl; }
            if ($newPictureUrl !== null) { $picturePath = $newPictureUrl; }

            // Estado automático
            $statusAuto = autoState($values['dueDate']); // 'calibrado' o 'fuera de calibracion'

            // Actualizar SOLO campos permitidos
            $upd = $pdo->prepare("
                UPDATE instruments
                   SET CalDate  = :CalDate,
                       DueDate  = :DueDate,
                       Status   = :Status,
                       Comments = :Comments,
                       PdfPath  = :PdfPath,
                       Picture  = :Picture
                 WHERE ID = :ID
            ");
            $upd->execute([
                ':CalDate'  => $values['calDate'],
                ':DueDate'  => $values['dueDate'],
                ':Status'   => $statusAuto,
                ':Comments' => $values['comments'],
                ':PdfPath'  => $pdfPath   ?: null,
                ':Picture'  => $picturePath ?: null,
                ':ID'       => $id,
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
                ':UpdatedColumn'=> 'calibration_update',
                ':OldValue'     => null,
                ':NewValue'     => null,
                ':Description'  => $description,
                ':Brand'        => $brand,
                ':Model'        => $model,
                ':SerialNumber' => $serialNumber,
                ':CalDate'      => $values['calDate'],
                ':DueDate'      => $values['dueDate'],
                ':CertificateNo'=> $certificateNo,
                ':Status'       => $statusAuto,
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
  <div class="col-12 col-lg-10 col-xl-8">
    <h1 class="h4 my-3 my-md-4">Actualizar instrumento</h1>

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
        <!-- Identidad del instrumento (solo lectura) -->
        <div class="col-12">
          <label class="form-label">ID</label>
          <input type="text" class="form-control" value="<?= h($id) ?>" disabled>
        </div>

        <div class="col-12">
          <label class="form-label">Descripción</label>
          <input type="text" class="form-control" value="<?= h($description) ?>" disabled>
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

        <!-- Calibración (editable) -->
        <div class="col-sm-6">
          <label for="calDate" class="form-label">Fecha de Calibración</label>
          <input type="date" class="form-control" id="calDate" name="calDate"
                 value="<?= h($values['calDate']) ?>" required>
          <div class="form-text">Al cambiar esta fecha, el vencimiento se fijará automáticamente a +1 año.</div>
        </div>

        <div class="col-sm-6">
          <label class="form-label">Fecha de Vencimiento</label>
          <?php
            $duePreview = $values['dueDate']
              ?: ($values['calDate'] ? plusOneYear($values['calDate']) : '');
          ?>
          <input type="date" class="form-control" id="dueDatePreview" value="<?= h($duePreview) ?>" disabled>
          <div class="form-text">Se calcula automáticamente en base a la fecha de calibración.</div>
        </div>

        <div class="col-sm-6">
          <label class="form-label">Estado (automático)</label>
          <?php
            $statusPreview = $duePreview ? autoState($duePreview) : '';
          ?>
          <input type="text" class="form-control" id="statusPreview"
                 value="<?= h($statusPreview) ?>" disabled>
          <div class="form-text">Se actualizará automáticamente según el vencimiento.</div>
        </div>

        <div class="col-12">
          <label for="comments" class="form-label">Comentarios</label>
          <textarea id="comments" name="comments" class="form-control" rows="3"
                    required><?= h($values['comments']) ?></textarea>
        </div>

        <!-- Documento del Proveedor (PDF o Foto) -->
        <div class="col-12">
          <label class="form-label d-flex align-items-center justify-content-between">
            <span class="fw-semibold"><i class="fa fa-file-contract me-2"></i>Certificado del Proveedor</span>
            <?php if ($pdfPathView): ?>
              <a href="<?= h($pdfPathView) ?>" target="_blank" class="small text-decoration-none">
                <i class="fa fa-external-link me-1"></i>Ver actual
              </a>
            <?php endif; ?>
          </label>
          <div class="small text-muted mb-2">
            <i class="fa fa-info-circle me-1"></i>Puedes tomar una foto del certificado o subir un PDF escaneado
          </div>
          
          <!-- Input optimizado para móvil: acepta PDF O foto directa -->
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

        <!-- Foto del Instrumento -->
        <div class="col-12 col-md-6">
          <label class="form-label d-flex align-items-center justify-content-between">
            <span class="fw-semibold"><i class="fa fa-camera me-2"></i>Foto del Instrumento</span>
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
        <a href="admin.php" class="btn btn-outline-secondary btn-lg">
          <i class="fa fa-times me-2"></i>Cancelar
        </a>
        <button type="submit" class="btn btn-primary btn-lg flex-grow-1">
          <i class="fa fa-save me-2"></i>Guardar cambios
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// Auto-cálculo de DueDate (+1 año) y estado (preview) al cambiar calDate
const calInput  = document.getElementById('calDate');
const duePrev   = document.getElementById('dueDatePreview');
const statusPrev= document.getElementById('statusPreview');

function toYMD(d){ const p=n=>String(n).padStart(2,'0'); return `${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())}`; }
function addOneYear(ymd){
  if(!ymd) return '';
  const [y,m,d] = ymd.split('-').map(Number);
  const dt = new Date(y, m-1, d);
  dt.setFullYear(dt.getFullYear()+1);
  if (dt.getMonth() !== (m-1)) { dt.setDate(0); }
  return toYMD(dt);
}
function autoStateJS(dueYmd){
  if(!dueYmd) return '';
  const today = toYMD(new Date());
  return (dueYmd < today) ? 'fuera de calibracion' : 'calibrado';
}

if (calInput) {
  calInput.addEventListener('change', ()=>{
    const cal = calInput.value;
    if (!cal) { duePrev.value=''; statusPrev.value=''; return; }
    const due = addOneYear(cal);
    duePrev.value = due;
    statusPrev.value = autoStateJS(due);
  });
}

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

// Vista previa de PDF nuevo (ahora acepta también imágenes)
const pdfInput = document.getElementById('pdf');
if (pdfInput) {
  pdfInput.addEventListener('change', () => {
    const file = pdfInput.files?.[0];
    const box  = document.getElementById('pdfPreviewBox');
    const imgPreviewDiv = document.getElementById('pdfImagePreview');
    const pdfPreviewDiv = document.getElementById('pdfDocPreview');
    const img  = document.getElementById('pdfAsImage');
    const frame= document.getElementById('pdfPreview');
    
    if (file) {
      const url = URL.createObjectURL(file);
      const isPdf = file.type === 'application/pdf';
      
      if (isPdf) {
        // Mostrar PDF en iframe
        frame.src = url;
        pdfPreviewDiv.classList.remove('d-none');
        imgPreviewDiv.classList.add('d-none');
      } else {
        // Mostrar imagen (foto del documento)
        img.src = url;
        imgPreviewDiv.classList.remove('d-none');
        pdfPreviewDiv.classList.add('d-none');
      }
      box.classList.remove('d-none');
    } else {
      frame.src = '';
      img.src = '';
      box.classList.add('d-none');
    }
  });
}
</script>

<!-- Estilos específicos para optimización móvil -->
<style>
/* Mejoras para dispositivos móviles */
@media (max-width: 767px) {
  /* Aumentar tamaño de inputs para mejor touch */
  .form-control,
  .form-control-lg {
    min-height: 48px;
    font-size: 16px; /* Evitar zoom en iOS */
  }
  
  .form-label {
    font-size: 0.95rem;
    margin-bottom: 0.5rem;
  }
  
  /* Botones más grandes para touch */
  .btn-lg {
    min-height: 52px;
    font-size: 1.1rem;
    padding: 0.75rem 1.5rem;
  }
  
  /* Espaciado mejorado */
  .row.g-3 > * {
    padding-bottom: 1rem;
  }
  
  /* Vista previa optimizada para móvil */
  #pdfPreviewBox,
  #imgPreviewBox {
    margin-top: 1rem;
  }
  
  #pdfAsImage,
  #imgPreview {
    max-height: 250px !important;
  }
  
  #pdfPreview {
    height: 300px !important;
  }
  
  /* Hacer texto de ayuda más visible */
  .text-muted,
  .form-text {
    font-size: 0.85rem;
  }
  
  /* Stack buttons verticalmente en móvil */
  .flex-column.flex-sm-row {
    gap: 0.75rem !important;
  }
}

/* Mejoras generales para inputs de archivo */
.form-control[type="file"] {
  cursor: pointer;
  padding: 0.75rem;
}

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

.form-control[type="file"]::file-selector-button:hover {
  background-color: #5a6268;
}

/* Preview container mejorado */
.preview-container {
  background-color: #1a1a1a;
  padding: 1rem;
  border-radius: 0.5rem;
}

/* Indicador visual para archivo seleccionado */
.form-control[type="file"]:not(:placeholder-shown) {
  border-color: #0d6efd;
}

/* Mejorar legibilidad de iconos */
.fa {
  vertical-align: middle;
}

/* Asegurar que labels sean tocables en toda su área */
.form-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

/* Sombras suaves para profundidad visual */
.shadow-sm {
  box-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.3) !important;
}
</style>

<?php include __DIR__ . '/partials/footer.php'; ?>
