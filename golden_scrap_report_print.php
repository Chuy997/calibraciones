<?php
// /var/www/html/calibraciones/golden_scrap_report_print.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_auth();

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = $_GET['id'] ?? '';
if (!$id) die("ID faltante.");

$pdo = pdo();

// 1. Batch Info
$st = $pdo->prepare("SELECT * FROM golden_scrap_batches WHERE BatchID = ?");
$st->execute([$id]);
$batch = $st->fetch();

if (!$batch) die("Lote de Scrap no encontrado.");

// 2. Items
$st = $pdo->prepare("SELECT * FROM golden_scrap_batch_items WHERE BatchID = ? ORDER BY DetailID ASC");
$st->execute([$id]);
$items = $st->fetchAll();

$total = count($items);
$dateFormatted = date("d/m/Y H:i", strtotime($batch['BatchDate']));

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Scrap #<?= h($id) ?></title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 11px; 
            color: #000;
            background: #fff;
            margin: 0;
            padding: 20px;
        }
        .header {
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            text-transform: uppercase;
            color: #d9534f; /* Red for Scrap */
        }
        .meta p { margin: 3px 0; font-size: 12px; }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 6px;
            vertical-align: top;
            text-align: left;
        }
        th {
            background: #eee;
            font-weight: bold;
        }
        .img-thumb {
            max-width: 80px;
            max-height: 80px;
            object-fit: contain;
            display: block;
            margin: auto;
        }
        
        .no-print {
            background: #f8f9fa;
            padding: 15px;
            text-align: center;
            margin-bottom: 20px;
            border: 1px solid #ddd;
        }
        
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button onclick="window.print()" style="padding: 10px 20px; font-weight: bold; cursor: pointer; border:2px solid #000; background:#fff;">
            🖨️ IMPRIMIR / GUARDAR PDF
        </button>
        <a href="golden_scrap_session.php" style="margin-left: 20px; color:#000;">&larr; Volver</a>
    </div>

    <div class="header">
        <div>
            <h1>Materiales para Scrap</h1>
            <div class="meta" style="margin-top:10px;">
                <p><strong>Lote ID:</strong> #<?= h($id) ?></p>
                <p><strong>Fecha:</strong> <?= h($dateFormatted) ?></p>
            </div>
        </div>
        <div class="meta" style="text-align: right;">
            <p><strong>Responsable:</strong> <?= h($batch['User']) ?></p>
            <p><strong>Total Items:</strong> <?= $total ?></p>
            <p><strong>Estado:</strong> <?= h($batch['Status']) ?></p>
        </div>
    </div>
    
    <div style="margin-bottom: 20px; font-style: italic; color: #555;">
        "Por medio de la presente se solicita que los siguientes materiales sean dados de baja del inventario y procesados como scrap debido a obsolescencia o daño irreparable."
    </div>

    <table>
        <thead>
            <tr>
                <th width="80">Foto</th>
                <th>Descripción / Material</th>
                <th>Marca / Modelo</th>
                <th>Serial Number / ID</th>
                <th>Notas / Motivo</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td style="text-align: center;">
                    <?php if ($item['Picture']): ?>
                        <img src="<?= h($item['Picture']) ?>" class="img-thumb">
                    <?php else: ?>
                        <span style="color:#ccc;">Sin foto</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?= h($item['Description']) ?>
                </td>
                <td>
                    <?= h($item['Brand']) ?><br>
                    <?= h($item['Model']) ?>
                </td>
                <td>
                    <strong style="font-family:monospace;"><?= h($item['SerialNumber']) ?></strong>
                    <?php if($item['GoldenID']): ?><br><span style="font-size:10px; color:#666;">ID: <?= h($item['GoldenID']) ?></span><?php endif; ?>
                </td>
                <td>
                    <?= nl2br(h($item['Notes'])) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- <div style="margin-top: 50px; display: flex; justify-content: space-between; page-break-inside: avoid;">
        <div style="text-align: center; width: 40%;">
            <div style="border-bottom: 1px solid #000; height: 50px;"></div>
            <p style="margin-top: 5px; font-weight: bold;"><?= h($batch['User']) ?></p>
            <p style="font-size: 10px;">Firma del Responsable</p>
        </div>
        <div style="text-align: center; width: 40%;">
            <div style="border-bottom: 1px solid #000; height: 50px;"></div>
            <p style="margin-top: 5px; font-weight: bold;">Gerencia / Autorización</p>
            <p style="font-size: 10px;">Firma de Aprobación</p>
        </div>
    </div> -->

    <div style="font-size: 9px; color: #999; margin-top: 50px; text-align: center;">
        Documento generado electrónicamente el <?= date('d/m/Y H:i:s') ?>.
    </div>

</body>
</html>
