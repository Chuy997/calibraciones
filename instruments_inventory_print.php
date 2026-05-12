<?php
// /var/www/html/calibraciones/instruments_inventory_print.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_auth('admin');

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function get_lightweight_image(string $url): string {
    if (!$url) return '';
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?: '/var/www/html';
    $path = rtrim($docRoot, '/') . $url;
    if (!file_exists($path)) return h($url);
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === 'jpg' || $ext === 'jpeg') {
        $img = @imagecreatefromjpeg($path);
    } elseif ($ext === 'png') {
        $img = @imagecreatefrompng($path);
    } else {
        return h($url);
    }
    if (!$img) return h($url);
    $w = imagesx($img);
    $h = imagesy($img);
    if ($w == 0 || $h == 0) return h($url);
    $max_dim = 40;
    if ($w > $h) {
        $new_w = $max_dim;
        $new_h = (int)max(1, $h * ($max_dim / $w));
    } else {
        $new_h = $max_dim;
        $new_w = (int)max(1, $w * ($max_dim / $h));
    }
    $thumb = imagecreatetruecolor($new_w, $new_h);
    if ($ext === 'png') {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
    } else {
        $bg = imagecolorallocate($thumb, 255, 255, 255);
        imagefill($thumb, 0, 0, $bg);
    }
    imagecopyresampled($thumb, $img, 0, 0, 0, 0, $new_w, $new_h, $w, $h);
    ob_start();
    if ($ext === 'png') {
        imagepng($thumb, null, 9);
    } else {
        imagejpeg($thumb, null, 25);
    }
    $data = ob_get_clean();
    imagedestroy($img);
    imagedestroy($thumb);
    $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';
    return 'data:' . $mime . ';base64,' . base64_encode($data);
}

$pdo = pdo();

$is_light = isset($_GET['no_img']) && $_GET['no_img'] == '1';

$sqlItems = "
    SELECT 
        ID, 
        Description, 
        Brand, 
        Model, 
        SerialNumber, 
        CalDate,
        DueDate,
        Comments,
        Picture,
        CASE
            WHEN CURRENT_DATE() > DueDate THEN 'Vencido'
            WHEN DueDate BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY) THEN 'Próxima calibración'
            ELSE 'Calibrado'
        END AS status_calculado
    FROM instruments
    ORDER BY ID ASC
";
$st = $pdo->query($sqlItems);
$items = $st->fetchAll();

$total = count($items);
$dateFormatted = date("d/m/Y H:i");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inventario Instrumentos - <?= date('Ymd') ?></title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #000; background: #fff; margin: 0; padding: 10px; }
        .header { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 15px; }
        .header h1 { margin: 0; font-size: 18px; text-transform: uppercase; }
        .meta p { margin: 2px 0; }
        .stats { display: flex; gap: 20px; margin-bottom: 15px; font-weight: bold; font-size: 12px; background: #f0f0f0; padding: 5px 10px; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; vertical-align: middle; }
        th { background: #eee; font-weight: bold; text-transform: uppercase; }
        .thumb { width: 40px; height: 40px; object-fit: cover; display: block; }
        .badge { padding: 2px 4px; border-radius: 3px; font-weight: bold; font-size: 9px; border: 1px solid #ccc; }
        .badge-ven { background: #ffebee; color: #c62828; }
        .badge-prox { background: #fff3e0; color: #ef6c00; }
        .badge-cal { background: #e8f5e9; color: #2e7d32; }
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
            <h1>Inventario Instrumentos de Medición</h1>
            <div class="meta" style="font-size: 12px; margin-top:5px; color:#555;">
                Generado el: <strong><?= $dateFormatted ?></strong><br>
                Departamento de Ingeniería
            </div>
        </div>
        <div><button class="no-print" onclick="window.print()" style="padding: 5px 15px; cursor: pointer; font-weight: bold;">🖨️ Imprimir / Guardar PDF</button></div>
    </div>
    <div class="stats"><span>Total Instrumentos: <?= $total ?></span></div>
    <table>
        <thead>
            <tr>
                <th style="width: 40px;">Img</th>
                <th style="width: 70px;">ID</th>
                <th>Descripción</th>
                <th>Marca / Modelo / Serie</th>
                <th>Calibración</th>
                <th>Vencimiento</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $itm): 
                $desc = h($itm['Description']);
                $brand = h($itm['Brand']);
                $model = h($itm['Model']);
                $sn    = h($itm['SerialNumber']);
                $cal   = h($itm['CalDate']);
                $due   = h($itm['DueDate']);
                $st    = $itm['status_calculado'];
                $fullSpec = "$brand $model<br><small class='color: #666;'>SN: $sn</small>";
                $pic = $itm['Picture'];
                
                if ($pic && $is_light) {
                    $pic_src = get_lightweight_image($pic);
                } else {
                    $pic_src = $pic ? h($pic) : '';
                }
                
                $imgTag = $pic_src ? '<img src="'.$pic_src.'" class="thumb">' : '<div style="width:40px;height:40px;background:#eee;color:#aaa;display:flex;align-items:center;justify-content:center;">N/A</div>';
                
                $badgeClass = match($st) {
                    'Vencido' => 'badge-ven',
                    'Próxima calibración' => 'badge-prox',
                    default => 'badge-cal'
                };
            ?>
            <tr>
                <td style="text-align: center; padding: 2px;"><?= $imgTag ?></td>
                <td><strong><?= h($itm['ID']) ?></strong></td>
                <td><?= $desc ?></td>
                <td><?= $fullSpec ?></td>
                <td><?= $cal ?></td>
                <td><strong><?= $due ?></strong></td>
                <td><span class="badge <?= $badgeClass ?>"><?= h($st) ?></span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="footer">Documento generado automáticamente por sistema.</div>
</body>
</html>
