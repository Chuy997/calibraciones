<?php
// /var/www/html/calibraciones/assets_hw_download.php
// Genera un archivo HTML autocontenido con imágenes embebidas en base64
// (reducidas de calidad con GD para aliviar el peso del archivo).
declare(strict_types=1);

require_once __DIR__.'/config.php';
require_auth(['admin','ingenieria']);

/* ---------------------------------------------------------------
 * Parámetros de compresión de imágenes
 * --------------------------------------------------------------- */
define('IMG_MAX_W',  320);   // px máximo de ancho
define('IMG_MAX_H',  240);   // px máximo de alto
define('IMG_QUALITY', 55);   // calidad JPEG 0-100

/* ---------------------------------------------------------------
 * Helper: convierte una ruta web /calibraciones/... a ruta absoluta
 * --------------------------------------------------------------- */
function webToAbsolute(string $webPath): string
{
    // /calibraciones/uploads/... → /var/www/html/calibraciones/uploads/...
    $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '/var/www/html', '/');
    return $docRoot . '/' . ltrim($webPath, '/');
}

/* ---------------------------------------------------------------
 * Helper: redimensiona y comprime una imagen con GD;
 * devuelve cadena base64 o '' si falla.
 * --------------------------------------------------------------- */
function compressImageToBase64(string $webPath): string
{
    if (empty($webPath)) return '';

    $absPath = webToAbsolute($webPath);
    if (!file_exists($absPath) || !is_readable($absPath)) return '';

    // Detectar tipo y crear recurso GD
    $info = @getimagesize($absPath);
    if ($info === false) return '';

    $mime = $info['mime'];
    $srcW = $info[0];
    $srcH = $info[1];

    $src = null;
    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            $src = @imagecreatefromjpeg($absPath);
            break;
        case 'image/png':
            $src = @imagecreatefrompng($absPath);
            break;
        case 'image/gif':
            $src = @imagecreatefromgif($absPath);
            break;
        case 'image/webp':
            $src = @imagecreatefromwebp($absPath);
            break;
        default:
            // Intentar con imagecreatefromstring como fallback
            $raw = @file_get_contents($absPath);
            if ($raw !== false) $src = @imagecreatefromstring($raw);
            break;
    }

    // Intentar con imagecreatefromstring si los anteriores fallan (p.e. .jfif)
    if ($src === false || $src === null) {
        $raw = @file_get_contents($absPath);
        if ($raw !== false) $src = @imagecreatefromstring($raw);
    }

    if (!$src) return '';

    // Calcular nuevo tamaño manteniendo proporción
    $ratio = min(IMG_MAX_W / max(1, $srcW), IMG_MAX_H / max(1, $srcH), 1.0);
    $newW  = (int)round($srcW * $ratio);
    $newH  = (int)round($srcH * $ratio);

    $dst = imagecreatetruecolor($newW, $newH);

    // Preservar transparencia para PNG/GIF
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
    imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
    imagealphablending($dst, true);

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
    imagedestroy($src);

    // Capturar salida JPEG comprimida
    ob_start();
    imagejpeg($dst, null, IMG_QUALITY);
    $data = ob_get_clean();
    imagedestroy($dst);

    if (empty($data)) return '';
    return 'data:image/jpeg;base64,' . base64_encode($data);
}

/* ---------------------------------------------------------------
 * Consultar inventario (misma query que admin)
 * --------------------------------------------------------------- */
$sql = <<<SQL
SELECT
  ID,
  Description,
  Brand,
  Model,
  SerialNumber,
  Location,
  Department,
  Owner,
  Status,
  Picture,
  Document,
  Pedimento,
  Comments,
  CreatedAt,
  UpdatedAt
FROM assets_hw_items
ORDER BY ID ASC
SQL;

$rows = pdo()->query($sql)->fetchAll();

/* ---------------------------------------------------------------
 * Construir el contenido HTML autocontenido
 * --------------------------------------------------------------- */
$now      = date('Y-m-d H:i');
$nowFile  = date('Ymd_His');
$total    = count($rows);

// Pre-procesar imágenes (comprimidas)
$images = [];
foreach ($rows as $r) {
    $id = (string)($r['ID'] ?? '');
    $images[$id] = compressImageToBase64((string)($r['Picture'] ?? ''));
}

ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Assets HW – Inventario <?= htmlspecialchars($now, ENT_QUOTES, 'UTF-8') ?></title>
<style>
  :root {
    --c-bg: #ffffff;
    --c-surface: #ffffff;
    --c-border: #e2e8f0;
    --c-header-bg: #0f172a;
    --c-header-txt: #ffffff;
    --c-accent: #3b82f6;
    --c-scrap: #ef4444;
    --c-active: #10b981;
    --c-row-alt: #f8fafc;
  }
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
    font-size: 10px;
    background: var(--c-bg);
    color: #334155;
    line-height: 1.4;
  }
  /* Contenedor principal para PDF */
  #pdf-content {
    background: #fff;
    padding: 0;
  }
  /* Encabezado */
  .report-header {
    background: var(--c-header-bg);
    color: var(--c-header-txt);
    padding: 20px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 4px solid var(--c-accent);
  }
  .report-header h1 { 
    font-size: 20px; 
    font-weight: 700; 
    letter-spacing: 0.5px;
    margin-bottom: 4px;
  }
  .report-meta { 
    font-size: 11px; 
    opacity: 0.85; 
    text-align: right; 
  }

  /* Stats row */
  .stats-row {
    display: flex;
    gap: 12px;
    padding: 12px 24px;
    background: #f1f5f9;
    border-bottom: 1px solid var(--c-border);
    flex-wrap: wrap;
  }
  .stat-box {
    background: #ffffff;
    border: 1px solid var(--c-border);
    border-radius: 6px;
    padding: 8px 16px;
    font-size: 11px;
    font-weight: 700;
    color: #0f172a;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
  }
  .stat-box span { font-weight: 500; color: #64748b; margin-left: 4px; }

  /* Tabla - Mejoras para evitar empalmes en el PDF */
  .wrap { padding: 16px 24px; }
  table {
    width: 100%;
    border-collapse: collapse;
    background: var(--c-surface);
    /* Prevención de cortes de página */
    page-break-inside: auto;
  }
  thead {
    display: table-header-group; /* Repite cabecera en nueva página */
  }
  tr {
    page-break-inside: avoid; /* Evita que la fila se parta a la mitad */
    page-break-after: auto;
  }
  thead tr {
    background: #f8fafc;
    border-bottom: 2px solid #cbd5e1;
    color: #475569;
  }
  thead th {
    padding: 10px 8px;
    text-align: left;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
  }
  tbody tr { border-bottom: 1px solid var(--c-border); }
  tbody tr:nth-child(even) { background: var(--c-row-alt); }
  tbody td {
    padding: 8px 8px;
    vertical-align: middle;
    color: #1e293b;
  }
  /* Imagen */
  .thumb-container {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 52px;
    height: 44px;
    background: #f1f5f9;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
  }
  .thumb {
    width: 100%;
    height: 100%;
    object-fit: contain; /* Mejor visualización sin recortar */
    background: #ffffff;
  }
  .no-img {
    font-size: 9px;
    color: #94a3b8;
    font-weight: 500;
  }
  /* Badge estado */
  .badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 99px;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
  }
  .badge-activo { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
  .badge-scrap  { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

  /* Lightbox */
  .lb-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.82);
    z-index: 9999;
    align-items: center;
    justify-content: center;
  }
  .lb-overlay.active { display: flex; }
  .lb-overlay img {
    max-width: 90vw;
    max-height: 88vh;
    border-radius: 6px;
    box-shadow: 0 8px 40px rgba(0,0,0,.6);
  }
  .lb-close {
    position: fixed;
    top: 16px;
    right: 20px;
    font-size: 28px;
    color: #fff;
    cursor: pointer;
    font-weight: 700;
    line-height: 1;
    z-index: 10000;
    background: none;
    border: none;
  }

  /* Print */
  @media print {
    .lb-overlay, .lb-close { display: none !important; }
    body { background: #fff; }
    .report-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    thead tr { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    table { box-shadow: none; }
  }
</style>
</head>
<body>

<div id="pdf-content">
<div class="report-header">
  <div>
    <h1>Assets HW – Inventario</h1>
    <div style="font-size:11px;opacity:.7;margin-top:4px;">Reporte generado con imágenes comprimidas</div>
  </div>
  <div class="report-meta">
    Generado: <?= htmlspecialchars($now, ENT_QUOTES, 'UTF-8') ?><br>
    Total registros: <?= $total ?>
  </div>
</div>

<?php
// Contar activos/scrap
$activos = 0; $scraps = 0; $conFoto = 0;
foreach ($rows as $r) {
    if (($r['Status'] ?? '') === 'Scrap') $scraps++; else $activos++;
    if (!empty($r['Picture'])) $conFoto++;
}
?>

<div class="stats-row">
  <div class="stat-box">Total <span><?= $total ?></span></div>
  <div class="stat-box">Activos <span style="color:var(--c-active)"><?= $activos ?></span></div>
  <div class="stat-box">Scrap <span style="color:var(--c-scrap)"><?= $scraps ?></span></div>
  <div class="stat-box">Con foto <span><?= $conFoto ?></span></div>
  <div class="stat-box">Calidad imagen <span>55 % JPEG / máx 320×240 px</span></div>
</div>

<div class="wrap">
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>ID</th>
        <th style="text-align:center">Foto</th>
        <th>Descripción</th>
        <th>Marca</th>
        <th>Modelo</th>
        <th>Serie</th>
        <th>Ubicación</th>
        <th>Depto</th>
        <th>Responsable</th>
        <th>Estado</th>
        <th>Asset No (Pedimento)</th>
        <th>Comentarios</th>
        <th>Actualizado</th>
      </tr>
    </thead>
    <tbody>
<?php
$n = 0;
foreach ($rows as $r):
    $n++;
    $id     = (string)($r['ID'] ?? '');
    $status = (string)($r['Status'] ?? '');
    $badge  = $status === 'Scrap' ? 'badge-scrap' : 'badge-activo';
    $b64    = $images[$id] ?? '';
    $esc    = fn(?string $s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
      <tr>
        <td><?= $n ?></td>
        <td><?= $esc($id) ?></td>
        <td style="text-align:center">
          <div class="thumb-container">
<?php if ($b64 !== ''): ?>
            <img class="thumb" src="<?= $b64 ?>"
                 alt="Foto <?= $esc($id) ?>"
                 onclick="openLB(this.src)"
                 title="Clic para ampliar">
<?php else: ?>
            <div class="no-img">Sin foto</div>
<?php endif; ?>
          </div>
        </td>
        <td><?= $esc($r['Description']) ?></td>
        <td><?= $esc($r['Brand']) ?></td>
        <td><?= $esc($r['Model']) ?></td>
        <td><?= $esc($r['SerialNumber']) ?></td>
        <td><?= $esc($r['Location']) ?></td>
        <td><?= $esc($r['Department']) ?></td>
        <td><?= $esc($r['Owner']) ?></td>
        <td><span class="badge <?= $badge ?>"><?= $esc($status) ?></span></td>
        <td><?= $esc($r['Pedimento']) ?></td>
        <td><?= $esc($r['Comments']) ?></td>
        <td style="white-space:nowrap"><?= $esc(substr((string)($r['UpdatedAt'] ?? ''), 0, 10)) ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
</div>
</div> <!-- /#pdf-content -->

<script>
function openLB(src) {
  document.getElementById('lbImg').src = src;
  document.getElementById('lb').classList.add('active');
}
function closeLB() {
  document.getElementById('lb').classList.remove('active');
  document.getElementById('lbImg').src = '';
}
document.addEventListener('keydown', function(e){
  if (e.key === 'Escape') closeLB();
});
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
// Cuando cargue la ventana, generamos el PDF automáticamente
window.onload = function() {
    const element = document.getElementById('pdf-content');
    const opt = {
        margin:       10,
        filename:     'assets_hw_inventario_<?= $nowFile ?>.pdf',
        image:        { type: 'jpeg', quality: 0.8 },
        html2canvas:  { scale: 2, useCORS: true },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
    };

    // Agregar un overlay de carga
    const loading = document.createElement('div');
    loading.innerHTML = '<div style="position:fixed;inset:0;background:rgba(255,255,255,0.9);z-index:99999;display:flex;flex-direction:column;align-items:center;justify-content:center;font-family:sans-serif;"><h2>Generando archivo PDF...</h2><p>Esto puede tardar unos segundos dependiendo de la cantidad de imágenes.</p></div>';
    document.body.appendChild(loading);

    html2pdf().set(opt).from(element).save().then(function() {
        // Al terminar, remover el mensaje y cerrar la pestaña
        document.body.removeChild(loading);
        setTimeout(() => { window.close(); }, 1000);
    });
};
</script>

</body>
</html>
<?php
// Ya no forzamos la descarga del HTML como archivo,
// dejamos que el navegador lo dibuje y el script JS genere el PDF.
$html = ob_get_clean();

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo $html;
exit;
