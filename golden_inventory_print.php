<?php
// /var/www/html/calibraciones/golden_inventory_print.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_auth(['admin','ingenieria','golden_consulta']);

// Helpers
if (!function_exists('h')) {
    function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

/**
 * Genera o recupera miniatura optimizada y comprimida para PDF ligero.
 * Reduce el peso total de la página y del PDF de más de 50MB a menos de 1MB.
 */
if (!function_exists('get_lightweight_image')) {
function get_lightweight_image(?string $url): string {
    if (!$url) return '';
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?: '/var/www/html';
    $path = rtrim($docRoot, '/') . $url;
    if (!file_exists($path)) return h($url);

    $cacheDir = __DIR__ . '/uploads/cache/thumbs';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0777, true);
    }

    $mtime = filemtime($path);
    $hash = md5($path . '_' . $mtime);
    $cacheFile = $cacheDir . '/' . $hash . '.jpg';
    $cacheUrl = '/calibraciones/uploads/cache/thumbs/' . $hash . '.jpg';

    if (file_exists($cacheFile) && filesize($cacheFile) > 0) {
        return $cacheUrl;
    }

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === 'jpg' || $ext === 'jpeg') {
        $img = @imagecreatefromjpeg($path);
    } elseif ($ext === 'png') {
        $img = @imagecreatefrompng($path);
    } elseif ($ext === 'webp') {
        $img = @imagecreatefromwebp($path);
    } else {
        return h($url);
    }
    if (!$img) return h($url);

    if (function_exists('exif_read_data') && ($ext === 'jpg' || $ext === 'jpeg')) {
        $exif = @exif_read_data($path);
        if (!empty($exif['Orientation'])) {
            switch ($exif['Orientation']) {
                case 3: $img = imagerotate($img, 180, 0); break;
                case 6: $img = imagerotate($img, -90, 0); break;
                case 8: $img = imagerotate($img, 90, 0); break;
            }
        }
    }

    $w = imagesx($img);
    $h = imagesy($img);
    if ($w == 0 || $h == 0) {
        imagedestroy($img);
        return h($url);
    }

    $max_dim = 60;
    if ($w > $h) {
        $new_w = $max_dim;
        $new_h = (int)max(1, $h * ($max_dim / $w));
    } else {
        $new_h = $max_dim;
        $new_w = (int)max(1, $w * ($max_dim / $h));
    }

    $thumb = imagecreatetruecolor($new_w, $new_h);
    $bg = imagecolorallocate($thumb, 255, 255, 255);
    imagefill($thumb, 0, 0, $bg);
    imagecopyresampled($thumb, $img, 0, 0, 0, 0, $new_w, $new_h, $w, $h);

    $saved = false;
    if (is_writable($cacheDir)) {
        $saved = @imagejpeg($thumb, $cacheFile, 50);
    }

    if ($saved && file_exists($cacheFile)) {
        imagedestroy($img);
        imagedestroy($thumb);
        return $cacheUrl;
    }

    ob_start();
    imagejpeg($thumb, null, 40);
    $data = ob_get_clean();
    imagedestroy($img);
    imagedestroy($thumb);

    return 'data:image/jpeg;base64,' . base64_encode($data);
}
}

$pdo = pdo();

// Opciones de exportación
$isNoImg = isset($_GET['no_img']) && $_GET['no_img'] == '1';
$isFull  = isset($_GET['full']) && $_GET['full'] == '1';
$isLight = !$isNoImg && !$isFull; // Modo predeterminado ligero

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
        Picture,
        CreatedAt
    FROM golden_items
    WHERE Status NOT IN ('Scrap', 'Destruido')
    ORDER BY ID ASC
";
$st = $pdo->prepare($sqlItems);
$st->execute();
$items = $st->fetchAll();

// Stats
$total = count($items);

// Format Date
$dateFormatted = date("d/m/Y H:i");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inventario Golden - <?= date('Ymd') ?></title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #000; background: #fff; margin: 0; padding: 10px; }
        .header { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 15px; }
        .header h1 { margin: 0; font-size: 18px; text-transform: uppercase; }
        .meta p { margin: 2px 0; }
        .stats { display: flex; gap: 20px; margin-bottom: 15px; font-weight: bold; font-size: 12px; background: #f0f0f0; padding: 5px 10px; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; vertical-align: middle; }
        th { background: #eee; font-weight: bold; text-transform: uppercase; }
        .thumb { width: 40px; height: 40px; object-fit: cover; display: block; border-radius: 2px; }
        .footer { margin-top: 30px; border-top: 1px solid #000; padding-top: 10px; text-align: center; font-size: 10px; color: #555; }
        
        /* Barra de control en pantalla */
        .controls-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 6px 12px;
            margin-bottom: 12px;
        }
        .mode-btn {
            text-decoration: none;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            border: 1px solid #ccc;
            background: #fff;
            color: #333;
            transition: all 0.15s;
        }
        .mode-btn:hover { background: #e9ecef; }
        .mode-btn.active {
            background: #0d6efd;
            border-color: #0a58ca;
            color: #fff;
        }
        .btn-print {
            background: #198754;
            color: #fff;
            border: 1px solid #146c43;
            border-radius: 4px;
            padding: 5px 14px;
            font-weight: bold;
            font-size: 12px;
            cursor: pointer;
            margin-left: auto;
        }
        .btn-print:hover { background: #157347; }

        @media print {
            body { margin: 0; padding: 0; }
            .no-print { display: none !important; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            thead { display: table-header-group; }
        }
    </style>
</head>
<body>
    <div class="no-print controls-bar">
        <span style="font-weight: bold; color: #495057;">Modo PDF:</span>
        <a class="mode-btn <?= $isLight ? 'active' : '' ?>" href="golden_inventory_print.php" title="Ideal para enviar por correo (~800 KB)">
            📄 Ligero (Recomendado correo &lt; 1MB)
        </a>
        <a class="mode-btn <?= $isNoImg ? 'active' : '' ?>" href="golden_inventory_print.php?no_img=1" title="Texto únicamente sin imágenes (&lt; 500 KB)">
            📑 Sin fotos (&lt; 500 KB)
        </a>
        <a class="mode-btn <?= $isFull ? 'active' : '' ?>" href="golden_inventory_print.php?full=1" title="Fotos en resolución original (> 50 MB)">
            🖼️ Alta resolución (&gt; 50 MB)
        </a>
        <button class="btn-print" onclick="window.print()">
            🖨️ Imprimir / Guardar como PDF
        </button>
    </div>

    <div class="header">
        <div>
            <h1>Inventario Golden (Activos Especiales)</h1>
            <div class="meta" style="font-size: 12px; margin-top:5px; color:#555;">
                Generado el: <strong><?= $dateFormatted ?></strong><br>
                Departamento de Ingeniería
                <?php if ($isLight): ?>
                    <span style="color: #0d6efd; font-weight: bold;">(Versión optimizada para correo)</span>
                <?php elseif ($isNoImg): ?>
                    <span style="color: #6c757d; font-weight: bold;">(Versión sin fotos)</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="stats"><span>Total Activos: <?= $total ?></span></div>
    <table>
        <thead>
            <tr>
                <?php if (!$isNoImg): ?>
                <th style="width: 40px;">Img</th>
                <?php endif; ?>
                <th style="width: 70px;">ID</th>
                <th>Descripción</th>
                <th>Marca / Modelo / Serie</th>
                <th>Ubicación</th>
                <th>Responsable</th>
                <th style="width: 60px;">Alta</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $itm): 
                $desc = h($itm['Description']);
                $brand = h($itm['Brand']);
                $model = h($itm['Model']);
                $sn    = h($itm['SerialNumber']);
                $fullSpec = "$brand $model<br><small style='color: #666;'>SN: $sn</small>";
                
                if (!$isNoImg) {
                    $pic = $itm['Picture'];
                    if ($pic) {
                        $picSrc = $isFull ? h($pic) : get_lightweight_image($pic);
                        $imgTag = '<img src="'.$picSrc.'" class="thumb" alt="Foto">';
                    } else {
                        $imgTag = '<div style="width:40px;height:40px;background:#eee;color:#aaa;display:flex;align-items:center;justify-content:center;font-size:9px;">N/A</div>';
                    }
                }
            ?>
            <tr>
                <?php if (!$isNoImg): ?>
                <td style="text-align: center; padding: 2px;"><?= $imgTag ?></td>
                <?php endif; ?>
                <td><strong><?= h($itm['ID']) ?></strong></td>
                <td><?= $desc ?></td>
                <td><?= $fullSpec ?></td>
                <td><strong><?= h($itm['Location']) ?></strong><br><small><?= h($itm['Department']) ?></small></td>
                <td><?= h($itm['Owner']) ?></td>
                <td><?= date('d/m/y', strtotime($itm['CreatedAt'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="footer">Documento generado automáticamente por sistema.</div>
</body>
</html>
