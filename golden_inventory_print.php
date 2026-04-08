<?php
// /var/www/html/calibraciones/golden_inventory_print.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_auth(['admin','ingenieria','golden_consulta']);

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
        Picture,
        CreatedAt
    FROM golden_items
    WHERE Status != 'Scrap' 
    ORDER BY ID ASC
";
$st = $pdo->prepare($sqlItems);
$st->execute();
$items = $st->fetchAll();

// Stats
$total    = count($items);

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
        .thumb { width: 40px; height: 40px; object-fit: cover; display: block; }
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
            <h1>Inventario Golden (Activos Especiales)</h1>
            <div class="meta" style="font-size: 12px; margin-top:5px; color:#555;">
                Generado el: <strong><?= $dateFormatted ?></strong><br>
                Departamento de Ingeniería
            </div>
        </div>
        <div><button class="no-print" onclick="window.print()" style="padding: 5px 15px; cursor: pointer; font-weight: bold;">🖨️ Imprimir / Guardar PDF</button></div>
    </div>
    <div class="stats"><span>Total Activos: <?= $total ?></span></div>
    <table>
        <thead>
            <tr>
                <th style="width: 40px;">Img</th>
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
                $fullSpec = "$brand $model<br><small class='color: #666;'>SN: $sn</small>";
                $pic = $itm['Picture'];
                $imgTag = $pic ? '<img src="'.h($pic).'" class="thumb">' : '<div style="width:40px;height:40px;background:#eee;color:#aaa;display:flex;align-items:center;justify-content:center;">N/A</div>';
            ?>
            <tr>
                <td style="text-align: center; padding: 2px;"><?= $imgTag ?></td>
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
