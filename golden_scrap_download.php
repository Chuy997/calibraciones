<?php
// /var/www/html/calibraciones/golden_scrap_download.php
// Genera un archivo HTML con formato de impresión, con imágenes embebidas en base64
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
            $raw = @file_get_contents($absPath);
            if ($raw !== false) $src = @imagecreatefromstring($raw);
            break;
    }

    if ($src === false || $src === null) {
        $raw = @file_get_contents($absPath);
        if ($raw !== false) $src = @imagecreatefromstring($raw);
    }

    if (!$src) return '';

    $ratio = min(IMG_MAX_W / max(1, $srcW), IMG_MAX_H / max(1, $srcH), 1.0);
    $newW  = (int)round($srcW * $ratio);
    $newH  = (int)round($srcH * $ratio);

    $dst = imagecreatetruecolor($newW, $newH);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
    imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
    imagealphablending($dst, true);

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
    imagedestroy($src);

    ob_start();
    imagejpeg($dst, null, IMG_QUALITY);
    $data = ob_get_clean();
    imagedestroy($dst);

    if (empty($data)) return '';
    return 'data:image/jpeg;base64,' . base64_encode($data);
}

// Helpers
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$pdo = pdo();

$sqlItems = "
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
        Pedimento,
        Picture,
        Comments,
        UpdatedAt
    FROM golden_items
    WHERE Status = 'Scrap' 
    ORDER BY UpdatedAt DESC
";
$st = $pdo->prepare($sqlItems);
$st->execute();
$items = $st->fetchAll();

// Stats
$total    = count($items);
$conFoto  = 0;

$images = [];
foreach ($items as $r) {
    $id = (string)($r['ID'] ?? '');
    if (!empty($r['Picture'])) $conFoto++;
    $images[$id] = compressImageToBase64((string)($r['Picture'] ?? ''));
}

$dateFormatted = date("d/m/Y H:i");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Scrap - <?= date('Ymd') ?></title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #000; background: #fff; margin: 0; padding: 10px; }
        .header { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 15px; }
        .header h1 { margin: 0; font-size: 18px; text-transform: uppercase; }
        .meta p { margin: 2px 0; }
        .stats { display: flex; gap: 20px; margin-bottom: 15px; font-weight: bold; font-size: 12px; background: #f0f0f0; padding: 5px 10px; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; vertical-align: middle; }
        th { background: #eee; font-weight: bold; text-transform: uppercase; }
        .thumb { width: 40px; height: 40px; object-fit: contain; display: block; margin: 0 auto; }
        .footer { margin-top: 30px; border-top: 1px solid #000; padding-top: 10px; text-align: center; font-size: 10px; color: #555; }
        @media print {
            body { margin: 0; padding: 0; }
            .no-print { display: none; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            thead { display: table-header-group; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>Reporte Golden – Ítems en Scrap</h1>
            <div class="meta" style="font-size: 12px; margin-top:5px; color:#555;">
                Generado el: <strong><?= $dateFormatted ?></strong><br>
                Departamento de Ingeniería
            </div>
        </div>
        <div><button class="no-print" onclick="window.print()" style="padding: 5px 15px; cursor: pointer; font-weight: bold;">🖨️ Imprimir / Guardar PDF</button></div>
    </div>
    
    <div class="stats">
        <span>Total Scrap: <?= $total ?></span>
        <span>Con foto: <?= $conFoto ?></span>
    </div>
    
    <table>
        <thead>
            <tr>
                <th style="width: 40px;">Img</th>
                <th style="width: 70px;">ID</th>
                <th>Descripción</th>
                <th>Marca / Modelo / Serie</th>
                <th>Pedimento</th>
                <th>Ubicación</th>
                <th>Responsable</th>
                <th>Comentarios</th>
                <th style="width: 60px;">Fecha Scrap</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $itm): 
                $id = (string)($itm['ID'] ?? '');
                $desc = h($itm['Description']);
                $brand = h($itm['Brand']);
                $model = h($itm['Model']);
                $sn    = h($itm['SerialNumber']);
                $pedimento = h($itm['Pedimento']);
                
                $allComments = explode("\n", trim((string)$itm['Comments']));
                $lastComment = end($allComments);
                $comments = h($lastComment);
                
                $fullSpec = "$brand $model<br><small style='color: #666;'>SN: $sn</small>";
                
                $b64 = $images[$id] ?? '';
                $imgTag = $b64 !== '' ? '<img src="'.$b64.'" class="thumb">' : '<div style="width:40px;height:40px;background:#eee;color:#aaa;display:flex;align-items:center;justify-content:center;">N/A</div>';
            ?>
            <tr>
                <td style="text-align: center; padding: 2px;"><?= $imgTag ?></td>
                <td><strong><?= h($id) ?></strong></td>
                <td><?= $desc ?></td>
                <td><?= $fullSpec ?></td>
                <td><?= $pedimento ?></td>
                <td><strong><?= h($itm['Location']) ?></strong><br><small><?= h($itm['Department']) ?></small></td>
                <td><?= h($itm['Owner']) ?></td>
                <td style="white-space: pre-wrap;"><?= $comments ?></td>
                <td><?= date('d/m/y', strtotime($itm['UpdatedAt'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="footer">Documento generado automáticamente por sistema. Imágenes comprimidas en reporte.</div>
</body>
</html>
